#!/bin/bash
# =================================================================
# deploy.sh — Ujian Terpadu Production Deployment
# Laravel Octane + FrankenPHP + Horizon
# Usage: ./deploy.sh [--fresh]
#   --fresh : Run fresh migration + seed (WARNING: destroys data)
# =================================================================

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()  { echo -e "${GREEN}[DEPLOY]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
err()  { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

FRESH=false
[[ "${1:-}" == "--fresh" ]] && FRESH=true

# --- Pre-flight checks ---
log "Checking requirements..."
command -v docker >/dev/null 2>&1 || err "Docker not installed"
command -v docker compose >/dev/null 2>&1 || err "Docker Compose not installed"

if [ ! -f .env ]; then
    if [ -f .env.production ]; then
        warn ".env not found, copying from .env.production"
        cp .env.production .env
        warn "IMPORTANT: Edit .env and set APP_KEY, APP_URL, DB passwords!"
        err "Please configure .env first, then re-run deploy.sh"
    else
        err ".env file not found. Copy .env.production to .env and configure it."
    fi
fi

# --- Pull latest code ---
if [ -d .git ]; then
    log "Pulling latest code..."
    git pull --ff-only || warn "Git pull failed, continuing with current code"
fi

# --- Install/update dependencies ---
log "Installing Composer dependencies..."
docker compose run --rm --no-deps app composer install --no-dev --optimize-autoloader --no-interaction

# --- Build frontend assets ---
log "Building frontend assets..."
if command -v npm >/dev/null 2>&1; then
    npm ci --production=false
    npm run build
else
    warn "npm not found locally. Make sure assets are pre-built."
fi

# --- Build and start containers ---
log "Building Docker images..."
docker compose build --no-cache app

log "Starting services..."
docker compose up -d

# --- Wait for MySQL ---
log "Waiting for MySQL to be ready..."
for i in $(seq 1 30); do
    if docker compose exec mysql mysqladmin ping -h localhost --silent 2>/dev/null; then
        break
    fi
    sleep 2
done

# --- Run migrations ---
if [ "$FRESH" = true ]; then
    warn "Running FRESH migration (all data will be lost)..."
    docker compose exec app php artisan migrate:fresh --seed --force
else
    log "Running migrations..."
    docker compose exec app php artisan migrate --force
fi

# --- Laravel optimizations ---
log "Optimizing Laravel for production..."
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
docker compose exec app php artisan event:cache

# --- Storage link ---
log "Creating storage symlink..."
docker compose exec app php artisan storage:link 2>/dev/null || true

# --- Restart Octane workers (pick up new code) ---
log "Reloading Octane workers..."
docker compose exec app php artisan octane:reload 2>/dev/null || warn "Octane reload failed, restarting container..."

# --- Restart Horizon (pick up new code) ---
log "Terminating Horizon for restart..."
docker compose exec app php artisan horizon:terminate 2>/dev/null || true

# --- Health check ---
log "Running health check..."
sleep 5
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:${APP_PORT:-80}/up || echo "000")
if [ "$HTTP_CODE" = "200" ]; then
    log "Health check passed (HTTP $HTTP_CODE)"
else
    warn "Health check returned HTTP $HTTP_CODE — check logs with: docker compose logs"
fi

# --- Summary ---
echo ""
log "======================================"
log "  Deployment complete!"
log "======================================"
log "  App:      http://localhost:${APP_PORT:-80}"
log "  Horizon:  http://localhost:${APP_PORT:-80}/horizon"
log "  Logs:     docker compose logs -f"
log "  Octane:   docker compose exec app php artisan octane:status"
log "  MySQL:    docker compose exec mysql mysql -u root -p"
echo ""

# --- Show container status ---
docker compose ps

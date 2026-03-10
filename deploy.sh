#!/bin/bash
# =================================================================
# deploy.sh — Ujian Terpadu Production Deployment
# Laravel Octane + FrankenPHP + Horizon
# Usage: ./deploy.sh [--fresh] [--build]
#   --fresh : Run fresh migration + seed (WARNING: destroys data)
#   --build : Force rebuild Docker image (no cache)
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
BUILD=false
for arg in "$@"; do
    case "$arg" in
        --fresh) FRESH=true ;;
        --build) BUILD=true ;;
    esac
done

# --- Pre-flight checks ---
log "Checking requirements..."
command -v docker >/dev/null 2>&1 || err "Docker not installed"
command -v docker compose >/dev/null 2>&1 || err "Docker Compose not installed"

if [ ! -f .env ]; then
    if [ -f .env.production ]; then
        warn ".env not found, copying from .env.production"
        cp .env.production .env
        warn "Please edit .env and set APP_URL, DB passwords, ADMIN_EMAIL/ADMIN_PASSWORD"
        warn "Then re-run: ./deploy.sh --build"
        exit 1
    else
        err ".env file not found. Copy .env.production to .env and configure it."
    fi
fi

# --- Pull latest code ---
if [ -d .git ]; then
    log "Pulling latest code..."
    git pull --ff-only || warn "Git pull failed, continuing with current code"
fi

# --- Build and start containers ---
if [ "$BUILD" = true ]; then
    log "Building Docker images (no cache)..."
    docker compose build --no-cache app
else
    log "Building Docker images..."
    docker compose build app
fi

log "Starting services..."
docker compose up -d

# --- Wait for app to be ready (entrypoint handles migrations, caching, etc.) ---
log "Waiting for app to be ready (entrypoint running migrations, caching)..."
for i in $(seq 1 60); do
    if curl -sf http://localhost:${APP_PORT:-80}/up > /dev/null 2>&1; then
        break
    fi
    sleep 3
    if [ $((i % 10)) -eq 0 ]; then
        log "Still waiting... ($i/60) — check logs: docker compose logs -f app"
    fi
done

# --- Fresh migration if requested ---
if [ "$FRESH" = true ]; then
    warn "Running FRESH migration (all data will be lost)..."
    docker compose exec app php artisan migrate:fresh --seed --force
    docker compose exec app php artisan db:seed --class=AdminSeeder --force
    docker compose exec app php artisan config:cache
fi

# --- Reload Octane workers (pick up new code) ---
log "Reloading Octane workers..."
docker compose exec app php artisan octane:reload 2>/dev/null || warn "Octane reload skipped"

# --- Restart Horizon (pick up new code) ---
log "Terminating Horizon for restart..."
docker compose exec app php artisan horizon:terminate 2>/dev/null || true

# --- Health check ---
log "Running health check..."
sleep 3
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:${APP_PORT:-80}/up || echo "000")
if [ "$HTTP_CODE" = "200" ]; then
    log "Health check passed (HTTP $HTTP_CODE)"
else
    warn "Health check returned HTTP $HTTP_CODE — check logs: docker compose logs -f app"
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
echo ""

# --- Show container status ---
docker compose ps

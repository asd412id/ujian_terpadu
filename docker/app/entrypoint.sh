#!/bin/bash
set -e

echo "[entrypoint] Starting Ujian Terpadu setup..."

# --- Initialize storage structure (named volume may be empty on first run) ---
mkdir -p /app/storage/{app/public,framework/{cache,sessions,testing,views},logs}
touch /app/storage/logs/laravel.log

# --- Generate APP_KEY if empty ---
if ! grep -q '^APP_KEY=base64:' /app/.env 2>/dev/null; then
    echo "[entrypoint] Generating APP_KEY..."
    NEW_KEY=$(php artisan key:generate --show --no-interaction 2>/dev/null)
    if [ -n "$NEW_KEY" ]; then
        sed "s|^APP_KEY=.*|APP_KEY=${NEW_KEY}|" /app/.env > /tmp/.env.tmp && cp /tmp/.env.tmp /app/.env && rm /tmp/.env.tmp
        export APP_KEY="$NEW_KEY"
        echo "[entrypoint] APP_KEY set: ${NEW_KEY:0:20}..."
    fi
fi

# --- Run migrations (DB already healthy from depends_on, minimal retries) ---
echo "[entrypoint] Running database migrations..."
for i in 1 2 3 4 5; do
    if php artisan migrate --force --no-interaction 2>&1; then
        echo "[entrypoint] Migrations complete."
        break
    fi
    echo "[entrypoint] Waiting for database... (attempt $i/5)"
    sleep 2
done

# --- First-run tasks (seed admin, create storage symlink) ---
FIRST_RUN_FLAG="/app/storage/.setup_done"
if [ ! -f "$FIRST_RUN_FLAG" ]; then
    if [ -n "${ADMIN_EMAIL:-}" ]; then
        echo "[entrypoint] Seeding admin user..."
        php artisan db:seed --class=AdminSeeder --force --no-interaction 2>&1 || true
    fi
    php artisan storage:link --no-interaction 2>/dev/null || true
    touch "$FIRST_RUN_FLAG"
fi

# --- Cache config & routes (depend on runtime .env) ---
echo "[entrypoint] Caching config & routes..."
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction

# --- Fix permissions ---
chown -R www-data:www-data /app/storage /app/bootstrap/cache 2>/dev/null || true
chmod -R 775 /app/storage /app/bootstrap/cache 2>/dev/null || true

echo "[entrypoint] Setup complete. Starting services..."
exec "$@"

#!/bin/bash
set -e

echo "[entrypoint] Starting Ujian Terpadu setup..."

# --- Initialize storage structure (named volume may be empty on first run) ---
for dir in app/public framework/cache framework/sessions framework/testing framework/views logs; do
    mkdir -p /app/storage/$dir
done
touch /app/storage/logs/laravel.log

# --- Generate APP_KEY if empty ---
if [ -z "$(grep '^APP_KEY=base64:' /app/.env 2>/dev/null)" ]; then
    echo "[entrypoint] Generating APP_KEY..."
    php artisan key:generate --force --no-interaction
fi

# --- Run migrations (retry until MySQL is ready) ---
echo "[entrypoint] Running database migrations..."
for i in $(seq 1 30); do
    if php artisan migrate --force --no-interaction 2>&1; then
        echo "[entrypoint] Migrations complete."
        break
    fi
    echo "[entrypoint] Waiting for database... (attempt $i/30)"
    sleep 2
done

# --- Seed admin user if ADMIN_EMAIL is set ---
if [ -n "${ADMIN_EMAIL:-}" ]; then
    echo "[entrypoint] Seeding admin user..."
    php artisan db:seed --class=AdminSeeder --force --no-interaction 2>&1 || true
fi

# --- Publish Horizon assets ---
php artisan horizon:publish --no-interaction 2>/dev/null || true

# --- Create storage symlink ---
php artisan storage:link --no-interaction 2>/dev/null || true

# --- Cache config/routes/views for performance ---
echo "[entrypoint] Caching config, routes, views..."
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
php artisan event:cache --no-interaction

# --- Fix permissions ---
chown -R www-data:www-data /app/storage /app/bootstrap/cache 2>/dev/null || true
chmod -R 775 /app/storage /app/bootstrap/cache 2>/dev/null || true

echo "[entrypoint] Setup complete. Starting services..."
exec "$@"

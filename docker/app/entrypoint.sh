#!/bin/bash
set -e

echo "[entrypoint] Starting Ujian Terpadu setup..."

# --- Initialize storage structure (named volume may be empty on first run) ---
mkdir -p /app/storage/{app/public,framework/{cache,sessions,testing,views},logs}
touch /app/storage/logs/laravel.log

# --- Run migrations (only if RUN_MIGRATIONS=true, prevents race condition in replicas) ---
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    # --- Generate APP_KEY if empty (only migrator writes .env to avoid race) ---
    if ! grep -q '^APP_KEY=base64:' /app/.env 2>/dev/null; then
        echo "[entrypoint] Generating APP_KEY..."
        NEW_KEY=$(php artisan key:generate --show --no-interaction 2>/dev/null)
        if [ -n "$NEW_KEY" ]; then
            sed "s|^APP_KEY=.*|APP_KEY=${NEW_KEY}|" /app/.env > /tmp/.env.tmp && cp /tmp/.env.tmp /app/.env && rm /tmp/.env.tmp
            export APP_KEY="$NEW_KEY"
            echo "[entrypoint] APP_KEY set: ${NEW_KEY:0:20}..."
        fi
    fi

    echo "[entrypoint] Running database migrations..."
    MIGRATION_SUCCESS=false
    for i in 1 2 3 4 5; do
        if php artisan migrate --force --no-interaction 2>&1; then
            MIGRATION_SUCCESS=true
            echo "[entrypoint] Migrations complete."
            break
        fi
        echo "[entrypoint] Waiting for database... (attempt $i/5)"
        sleep 3
    done

    if [ "$MIGRATION_SUCCESS" = "false" ]; then
        echo "[entrypoint] FATAL: Migrations failed after 5 attempts"
        exit 1
    fi

    # --- First-run tasks (seed essential data) ---
    FIRST_RUN_FLAG="/app/storage/.setup_done"
    if [ ! -f "$FIRST_RUN_FLAG" ]; then
        echo "[entrypoint] Seeding essential data..."
        php artisan db:seed --class=DinasPendidikanSeeder --force --no-interaction 2>&1 || true
        php artisan db:seed --class=KategoriSoalSeeder --force --no-interaction 2>&1 || true
        if [ -n "${ADMIN_EMAIL:-}" ]; then
            echo "[entrypoint] Seeding admin user..."
            php artisan db:seed --class=AdminSeeder --force --no-interaction 2>&1 || true
        fi
        touch "$FIRST_RUN_FLAG"
    fi

    # --- Cache config & routes (only once, shared via app_storage volume) ---
    echo "[entrypoint] Caching config & routes..."
    php artisan config:cache --no-interaction
    php artisan route:cache --no-interaction
else
    echo "[entrypoint] Skipping migrations (RUN_MIGRATIONS=${RUN_MIGRATIONS:-false})"
    # Wait for database to be reachable (migrations running in app-migrator container)
    echo "[entrypoint] Waiting for database readiness..."
    for i in 1 2 3 4 5 6 7 8 9 10; do
        if php artisan tinker --execute="DB::select('SELECT 1')" 2>/dev/null; then
            echo "[entrypoint] Database is ready."
            break
        fi
        echo "[entrypoint] Database not ready yet... (attempt $i/10)"
        sleep 3
    done

    # --- Cache config & routes per-replica (bootstrap/cache is NOT a shared volume) ---
    echo "[entrypoint] Caching config & routes..."
    php artisan config:cache --no-interaction 2>&1 || true
    php artisan route:cache --no-interaction 2>&1 || true
fi

# --- Create storage symlink ---
php artisan storage:link --no-interaction 2>/dev/null || true

# --- Fix permissions (run as root before supervisor drops to www-data) ---
chown -R www-data:www-data /app/storage /app/bootstrap/cache 2>/dev/null || true
chmod -R 775 /app/storage /app/bootstrap/cache 2>/dev/null || true

# Ensure FrankenPHP/Caddy data dirs are writable by www-data
mkdir -p /data/caddy /config/caddy
chown -R www-data:www-data /data /config 2>/dev/null || true

echo "[entrypoint] Setup complete. Starting services..."
exec "$@"

# =================================================================
# Dockerfile — Ujian Terpadu (Production)
# Laravel Octane + FrankenPHP + Horizon
# Multi-stage build for fast rebuilds and small final image
# =================================================================

# ==== Stage 1: Composer dependencies ====
FROM composer:2 AS composer-deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --no-dev --prefer-dist --ignore-platform-reqs

# ==== Stage 2: Frontend build ====
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY vite.config.js ./
COPY resources ./resources
# Tailwind v4 @source scans blade views + vendor pagination views
COPY --from=composer-deps /app/vendor/laravel/framework/src/Illuminate/Pagination/resources ./vendor/laravel/framework/src/Illuminate/Pagination/resources
RUN npm run build

# ==== Stage 3: Final production image ====
FROM dunglas/frankenphp:1.12-php8.3

# Install system deps (no Node.js needed at runtime!)
RUN apt-get update && apt-get install -y --no-install-recommends \
    git curl libpng-dev libjpeg62-turbo-dev libwebp-dev \
    libxml2-dev zip unzip libonig-dev \
    libfreetype6-dev libzip-dev libicu-dev \
    supervisor ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql mbstring exif pcntl bcmath \
        gd zip intl opcache

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

WORKDIR /app

# Copy composer binary + vendor from stage 1
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=composer-deps /app/vendor ./vendor

# Copy application code
COPY . .

# Dump optimized autoloader
RUN composer dump-autoload --optimize --no-dev

# Copy built frontend assets from stage 2
COPY --from=frontend /app/public/build ./public/build

# Pre-cache views, events at build time
RUN cp .env.example .env \
    && php artisan view:cache --no-interaction \
    && php artisan event:cache --no-interaction \
    && rm .env

# Copy configs
COPY docker/app/php.ini      /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/app/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Set permissions
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

EXPOSE 8000

HEALTHCHECK --interval=10s --timeout=5s --retries=5 --start-period=20s \
    CMD curl -f http://localhost:8000/up || exit 1

ENTRYPOINT ["entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

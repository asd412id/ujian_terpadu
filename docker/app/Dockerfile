# =================================================================
# Dockerfile — Ujian Terpadu (Production)
# Laravel Octane + FrankenPHP + Horizon
# Optimized for 4 core / 4GB RAM / 2000 concurrent users
# =================================================================

FROM dunglas/frankenphp:latest-php8.3 AS base

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git curl libpng-dev libjpeg62-turbo-dev libwebp-dev \
    libxml2-dev zip unzip libonig-dev \
    libfreetype6-dev libzip-dev libicu-dev \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql mbstring exif pcntl bcmath \
        gd zip intl opcache

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy composer files first for layer caching
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --no-dev --prefer-dist

# Copy application
COPY . .
RUN composer dump-autoload --optimize --no-dev

# Copy configs
COPY docker/app/php.ini      /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/app/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Set permissions
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

# FrankenPHP serves HTTP directly on port 8000 (Octane)
# Port 443 for HTTPS/HTTP3 via Caddy (auto TLS)
EXPOSE 8000 443

HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD curl -f http://localhost:8000/up || exit 1

CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

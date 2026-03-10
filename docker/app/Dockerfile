# =================================================================
# Dockerfile — Ujian Terpadu (Production)
# Laravel Octane + FrankenPHP + Horizon
# Optimized for 4 core / 4GB RAM / 2000 concurrent users
#
# Ready-to-use: composer install, npm build, migrations, and
# admin seeder all run automatically via entrypoint.
# =================================================================

FROM dunglas/frankenphp:latest-php8.3 AS base

# Install system dependencies + Node.js 20
RUN apt-get update && apt-get install -y --no-install-recommends \
    git curl libpng-dev libjpeg62-turbo-dev libwebp-dev \
    libxml2-dev zip unzip libonig-dev \
    libfreetype6-dev libzip-dev libicu-dev \
    supervisor ca-certificates gnupg \
    && mkdir -p /etc/apt/keyrings \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
       | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_20.x nodistro main" \
       > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update && apt-get install -y --no-install-recommends nodejs \
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

# Restore the base image FrankenPHP binary (Octane/Composer downloads an
# incompatible newer version to /app/frankenphp — overwrite it)
RUN cp /usr/local/bin/frankenphp /app/frankenphp \
    && chmod +x /app/frankenphp

# Install npm dependencies and build frontend assets
RUN npm ci --ignore-scripts && npm run build && rm -rf node_modules

# Copy configs
COPY docker/app/php.ini      /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/app/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Set permissions
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

# FrankenPHP serves HTTP directly on port 8000 (Octane)
EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --retries=3 --start-period=60s \
    CMD curl -f http://localhost:8000/up || exit 1

ENTRYPOINT ["entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

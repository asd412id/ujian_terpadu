FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git curl libpng-dev libjpeg-turbo-dev libwebp-dev \
    libxml2-dev zip unzip oniguruma-dev \
    freetype-dev libzip-dev icu-dev \
    supervisor fcgi

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql mbstring exif pcntl bcmath \
        gd zip intl opcache

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install php-fpm-healthcheck
RUN curl -o /usr/local/bin/php-fpm-healthcheck \
    https://raw.githubusercontent.com/renatomefi/php-fpm-healthcheck/master/php-fpm-healthcheck \
    && chmod +x /usr/local/bin/php-fpm-healthcheck

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files first for layer caching
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --no-dev --prefer-dist

# Copy application
COPY . .
RUN composer dump-autoload --optimize --no-dev

# Copy configs
COPY docker/app/php.ini      /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/app/www.conf     /usr/local/etc/php-fpm.d/www.conf
COPY docker/app/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Remove default pool config (we use www.conf)
RUN rm -f /usr/local/etc/php-fpm.d/zz-docker.conf

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD php-fpm-healthcheck || exit 1

CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

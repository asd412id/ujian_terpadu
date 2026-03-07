FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git curl libpng-dev libjpeg-turbo-dev libwebp-dev \
    libxml2-dev zip unzip oniguruma-dev \
    freetype-dev libzip-dev icu-dev \
    ghostscript imagemagick imagemagick-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install \
        pdo_mysql mbstring exif pcre bcmath \
        gd zip intl opcache

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files first for layer caching
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --no-dev --prefer-dist

# Copy application
COPY . .
RUN composer dump-autoload --optimize --no-dev

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]

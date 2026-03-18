FROM php:8.4-fpm AS app

# System dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    opcache

# Redis extension via PECL
RUN pecl install redis \
    && docker-php-ext-enable redis

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install PHP dependencies (production: no dev packages)
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]

# ─────────────────────────────────────────
# Stage 2: Nginx web server image
# ─────────────────────────────────────────
FROM nginx:alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

COPY --from=app /var/www/html/public /var/www/html/public

# Symlink so Nginx can serve uploaded files from the storage volume.
# The storage_data volume is mounted at /var/www/html/storage at runtime.
RUN ln -s /var/www/html/storage/app/public /var/www/html/public/storage

EXPOSE 80

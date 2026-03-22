# ─────────────────────────────────────────
# Stage 1: Node — compile frontend assets
# ─────────────────────────────────────────
FROM node:22-alpine AS node-builder

WORKDIR /app

COPY package.json package-lock.json vite.config.js postcss.config.js ./
RUN npm ci

COPY resources/scss ./resources/scss
COPY resources/js  ./resources/js

RUN npm run build

# ─────────────────────────────────────────
# Stage 2: PHP-FPM Application
# ─────────────────────────────────────────
FROM php:8.4-fpm AS app

# public-dev includes dev dependencies (Faker etc.) so the debug generator
# widget and factories work. production strips them.
ARG BUILD_ENV=production

# System dependencies + Node.js 22 (required for npm run build in SCSS editor)
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
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
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

# Copy only the dependency manifests first so composer install is cached
# independently of application code changes.
COPY composer.json composer.lock ./

# Install PHP dependencies — dev packages included for public-dev builds
RUN if [ "$BUILD_ENV" = "public-dev" ]; then \
        composer install --no-interaction --prefer-dist --no-scripts --no-autoloader; \
    else \
        composer install --no-interaction --prefer-dist --no-scripts --no-autoloader --no-dev; \
    fi

# Copy application files
COPY . .

# Copy compiled frontend assets from node-builder stage
COPY --from=node-builder /app/public/build ./public/build

# Generate the optimised autoloader now that all files are present
RUN if [ "$BUILD_ENV" = "public-dev" ]; then \
        composer dump-autoload --optimize; \
    else \
        composer dump-autoload --optimize --no-dev; \
    fi

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]

# ─────────────────────────────────────────
# Stage 3: Nginx web server image
# ─────────────────────────────────────────
FROM nginx:alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

COPY --from=app /var/www/html/public /var/www/html/public

# Symlink so Nginx can serve uploaded files from the storage volume.
# The storage_data volume is mounted at /var/www/html/storage at runtime.
RUN ln -sf /var/www/html/storage/app/public /var/www/html/public/storage

EXPOSE 80

# ─────────────────────────────────────────
# Stage 1: Node — compile frontend assets
# ─────────────────────────────────────────
FROM node:22-alpine AS node-builder

# Install PHP and Composer so Vite can scan vendor/filament blade files
# for the admin theme Tailwind build.
RUN apk add --no-cache php83 php83-phar php83-mbstring php83-openssl php83-curl php83-tokenizer \
    && ln -sf /usr/bin/php83 /usr/bin/php

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP dependencies (only needed for Tailwind content scanning)
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader --no-dev --ignore-platform-reqs

# Install Node dependencies
COPY package.json package-lock.json vite.config.js vite.config.public.js postcss.config.js tailwind.config.js tailwind.config.filament.js ./
RUN npm ci

# Copy source files needed for the build
COPY resources/scss ./resources/scss
COPY resources/js  ./resources/js
COPY resources/css ./resources/css
COPY resources/views ./resources/views
COPY app/Filament ./app/Filament
COPY app/Livewire ./app/Livewire

# Full Vite build (public assets + Filament admin theme)
RUN npm run build

# ─────────────────────────────────────────
# Stage 2: PHP-FPM Application
# ─────────────────────────────────────────
FROM php:8.4-fpm AS app

# public-dev includes dev dependencies (Faker etc.) so the debug generator
# widget and factories work. production strips them.
ARG BUILD_ENV=production

# System dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    postgresql-client \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-configure gd \
    --with-jpeg \
    --with-webp \
    --with-freetype \
    && docker-php-ext-install \
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

# Copy Vite-built assets from the node-builder stage.
# Only copy the Vite manifest and assets dir — leave public/build/widgets/
# intact if it was placed in the build context by a CI build:public step.
COPY --from=node-builder /app/public/build/manifest.json ./public/build/manifest.json
COPY --from=node-builder /app/public/build/assets ./public/build/assets

# Copy node_modules from the node-builder stage so that build:public can
# read library source files (Swiper CSS, Chart.js UMD, jCalendar CSS) when
# producing per-library bundles for the admin page builder preview.
COPY --from=node-builder /app/node_modules ./node_modules

# Generate the optimised autoloader now that all files are present
RUN if [ "$BUILD_ENV" = "public-dev" ]; then \
        composer dump-autoload --optimize; \
    else \
        composer dump-autoload --optimize --no-dev; \
    fi

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/resources/scss /var/www/html/public/build

# Entrypoint script: fix ownership on mounted volumes before dropping to www-data.
# Named volumes are mounted after build, so the Dockerfile chown doesn't persist.
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
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

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

ARG APP_VERSION=dev

# System dependencies.
#
# postgresql-client-17 is pinned to the major version explicitly. The pg_dump
# binary used by spatie/laravel-backup MUST match the postgres server's major
# version (mismatched majors produce dumps containing directives the older
# server cannot ingest, e.g. PG17's `transaction_timeout` against a PG16
# server). When bumping this pin, also bump the `image: postgres:N-alpine`
# in docker-compose.yml and docker-compose.prod.yml to the same major.
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
    postgresql-client-17 \
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

# PCOV extension via PECL — installed but not auto-enabled. Loaded
# opt-in for Infection mutation-testing runs via `php -d extension=pcov
# -d pcov.enabled=1`. Default Pest runs are unaffected.
RUN pecl install pcov

# Large-upload PHP limits. Baked here (not bind-mounted) so prod + worker
# — which mount no php ini — get 768M instead of stock 2M/8M, which is
# why off-local zip/theme/media imports were failing. `zz-` sorts last in
# conf.d so it wins even where local.ini is mounted (dev/e2e).
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini

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

RUN mkdir -p /var/cache/app && echo "$APP_VERSION" > /var/cache/app/VERSION

# Entrypoint script: fix ownership on mounted volumes before dropping to www-data.
# Named volumes are mounted after build, so the Dockerfile chown doesn't persist.
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

# Deep liveness probe: boot the full framework via artisan so a bootstrap-level
# fatal (e.g. a stale manifest naming a removed provider) trips the healthcheck.
# Without this the app container reported healthy the instant php-fpm's process
# started — `compose up --wait` false-greened past a non-booting image into the
# migrate step. Image-baked so it propagates through the normal upgrade path and
# covers the worker too (shared image). Targets the failure directly: the
# Debugbar brick killed artisan as well as the web tier.
HEALTHCHECK --interval=30s --timeout=15s --start-period=40s --retries=3 \
  CMD php /var/www/html/artisan app:health-check || exit 1

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

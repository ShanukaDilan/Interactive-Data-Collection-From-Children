# syntax=docker/dockerfile:1
#
# Learning Fun! — production image (Railway or any Docker host).
# PHP 8.3 + FrankenPHP serving /app/public on $PORT.

# ── 1. PHP dependencies ─────────────────────────────────────────────
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# ── 2. Front-end assets (Vite) ──────────────────────────────────────
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
# Tailwind scans Laravel's pagination views inside vendor/.
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

# ── 3. Runtime ──────────────────────────────────────────────────────
FROM dunglas/frankenphp:1-php8.3 AS app

RUN install-php-extensions pdo_mysql pdo_pgsql intl zip gd bcmath opcache pcntl \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf "upload_max_filesize=12M\npost_max_size=14M\nmemory_limit=512M\nexpose_php=Off\nopcache.validate_timestamps=0\n" > "$PHP_INI_DIR/conf.d/zz-learning-fun.ini"

WORKDIR /app

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN rm -rf legacy tests node_modules .env \
    && mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x docker/start.sh

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    PORT=8080

EXPOSE 8080

CMD ["/app/docker/start.sh"]

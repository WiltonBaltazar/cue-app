# Cue App production image: Nginx + PHP-FPM + queue worker + scheduler, all kept
# running by Supervisor in one container. The frontend build (public/build,
# public/sw.js) is committed, so no Node is needed here.

FROM php:8.4-fpm-bookworm

ARG DEBIAN_FRONTEND=noninteractive

# Nginx and Supervisor; the MariaDB client for the nightly backup's dump.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx supervisor mariadb-client curl unzip ca-certificates \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

# PHP extensions: Redis, MariaDB/MySQL, Web Push signing (gmp, bcmath), intl, zip (backups),
# gd (PDF), pcntl (graceful queue stops), opcache.
COPY --from=mlocati/php-extension-installer:2.7 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions redis pdo_mysql gmp bcmath intl zip gd exif pcntl opcache

COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Dependencies first, so code changes don't reinstall them.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-cue.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-cue.conf
COPY docker/nginx.conf /etc/nginx/conf.d/cue.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/cue.conf
COPY docker/entrypoint.sh /usr/local/bin/cue-entrypoint
RUN chmod +x /usr/local/bin/cue-entrypoint

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS http://127.0.0.1/up || exit 1

ENTRYPOINT ["cue-entrypoint"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]

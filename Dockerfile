# syntax=docker/dockerfile:1
FROM --platform=$BUILDPLATFORM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.2-fpm-alpine AS runtime

RUN apk add --no-cache \
    bash curl git icu-libs libzip nginx oniguruma supervisor unzip mysql-client \
  && apk add --no-cache --virtual .build-deps \
    icu-dev libzip-dev oniguruma-dev $PHPIZE_DEPS \
  && docker-php-ext-install -j$(nproc) bcmath intl mbstring pcntl pdo_mysql opcache zip \
  && pecl install redis \
  && docker-php-ext-enable redis \
  && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts
COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN composer dump-autoload --no-dev --optimize --no-scripts \
  && php artisan package:discover --ansi \
  && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache storage/app/public \
  && chown -R www-data:www-data storage bootstrap/cache \
  && chmod -R ug+rw storage bootstrap/cache

COPY docker/php/conf.d/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/start-worker.sh /usr/local/bin/start-worker.sh
COPY docker/start-scheduler.sh /usr/local/bin/start-scheduler.sh
RUN mkdir -p /run/nginx /var/log/supervisor \
  && ln -sf /dev/stdout /var/log/nginx/access.log \
  && ln -sf /dev/stderr /var/log/nginx/error.log \
  && chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/start-worker.sh /usr/local/bin/start-scheduler.sh

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=3 \
  CMD curl --fail --silent http://127.0.0.1/login > /dev/null || exit 1
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]

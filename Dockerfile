FROM php:8.2-fpm-alpine AS base

RUN apk add --no-cache \
    bash \
    curl \
    git \
    icu-dev \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    unzip \
    zip \
    mysql-client \
    $PHPIZE_DEPS \
  && docker-php-ext-install \
    bcmath \
    intl \
    pdo_mysql \
    opcache \
    zip \
  && pecl install redis \
  && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY package.json package-lock.json ./
RUN apk add --no-cache nodejs npm \
  && npm ci

COPY . .

RUN npm run build \
  && npm cache clean --force \
  && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
  && php artisan package:discover --ansi \
  && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
  && chown -R www-data:www-data /var/www/html \
  && chmod -R ug+rw storage bootstrap/cache

COPY docker/php/conf.d/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/start-worker.sh /usr/local/bin/start-worker.sh
COPY docker/start-scheduler.sh /usr/local/bin/start-scheduler.sh

RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/start-worker.sh /usr/local/bin/start-scheduler.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

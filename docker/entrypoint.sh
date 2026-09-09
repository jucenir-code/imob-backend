#!/bin/sh
set -e

cd /var/www/html

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache storage/app/public
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rw storage bootstrap/cache

if [ -z "${APP_KEY:-}" ]; then
  if [ "${APP_ENV:-production}" = "production" ]; then
    echo "APP_KEY is required in production. Generate one with: php -r 'echo \"base64:\".base64_encode(random_bytes(32)).PHP_EOL;'"
    exit 1
  fi

  export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
fi

if [ -n "${DB_HOST:-}" ] && [ "${DB_CONNECTION:-mysql}" = "mysql" ]; then
  export DB_PORT="${DB_PORT:-3306}"
  echo "Waiting for database at ${DB_HOST}:${DB_PORT}..."
  tries=0
  until php -r "\$socket = @fsockopen(getenv('DB_HOST'), (int) getenv('DB_PORT'), \$errno, \$errstr, 3); if (! \$socket) { exit(1); } fclose(\$socket);" >/dev/null 2>&1
  do
    tries=$((tries + 1))
    if [ "$tries" -ge 60 ]; then
      echo "Database is not reachable after 60 attempts."
      exit 1
    fi
    sleep 2
  done
fi

php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  php artisan migrate --force
fi
php artisan storage:link >/dev/null 2>&1 || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Artisan runs as root here; PHP-FPM and workers must be able to update caches.
chown -R www-data:www-data storage bootstrap/cache

exec "$@"

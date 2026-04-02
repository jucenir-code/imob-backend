#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
  echo ".env not found"
  exit 1
fi

php artisan key:generate --force --no-interaction >/dev/null 2>&1 || true
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan migrate --force
php artisan storage:link >/dev/null 2>&1 || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"

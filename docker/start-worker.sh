#!/bin/sh
set -e

cd /var/www/html

php artisan queue:work --verbose --tries=3 --timeout=120

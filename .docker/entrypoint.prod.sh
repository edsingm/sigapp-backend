#!/bin/bash
set -e

php artisan optimize:clear
php artisan storage:link || true

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

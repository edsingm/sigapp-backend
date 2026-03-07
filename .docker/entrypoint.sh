#!/bin/bash
set -e

php artisan optimize:clear
php artisan optimize
php artisan storage:link

exec supervisord -n -c /etc/supervisor/conf.d/supervisord.conf

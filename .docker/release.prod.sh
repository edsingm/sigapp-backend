#!/bin/bash
set -euo pipefail

php artisan migrate --force
php artisan tenants:migrate

php artisan config:cache
php artisan route:cache
php artisan view:cache

#!/bin/bash
set -e

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

php artisan optimize:clear
php artisan storage:link

exec php artisan serve --host=0.0.0.0 --port=8000

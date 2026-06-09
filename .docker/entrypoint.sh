#!/bin/bash
set -e

COMPOSER_ARGS="--no-interaction --prefer-dist --optimize-autoloader"

if [ ! -f vendor/autoload.php ]; then
    if [ "${APP_ENV}" = "production" ]; then
        composer install ${COMPOSER_ARGS} --no-dev
    else
        composer install ${COMPOSER_ARGS}
    fi
fi

php artisan optimize:clear
php artisan storage:link

if [ "${APP_ENV}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
else
    exec php artisan serve --host=0.0.0.0 --port=8000
fi

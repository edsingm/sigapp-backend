#!/bin/bash
set -e

# Instala dependências caso o vendor ainda não exista (clone novo via bind mount)
if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

# Garante .env e APP_KEY no ambiente local
[ -f .env ] || cp .env.example .env
if ! grep -q "^APP_KEY=base64:" .env; then
    php artisan key:generate --no-interaction
fi

php artisan optimize:clear
php artisan storage:link || true

exec php artisan serve --host=0.0.0.0 --port=8000

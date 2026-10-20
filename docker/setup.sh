#!/bin/sh
set -e
composer install --no-interaction
[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force
# primeira subida (sem tabela de migrations): migra e já semeia a demo. Depois disso só migra
if php artisan migrate:status >/dev/null 2>&1; then
    php artisan migrate --force
else
    php artisan migrate --force --seed
fi

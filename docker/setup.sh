#!/bin/sh
set -e
composer install --no-interaction
[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force
php artisan migrate --force

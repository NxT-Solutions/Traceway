#!/usr/bin/env bash
# First-time and every-start setup for the Laravel app. Safe to run again.
set -euo pipefail

cd /var/www/html/apps/app

if [ ! -f .env ]; then
    cp .env.example .env
fi

if grep -q '^APP_URL=' .env; then
    sed -i "s|^APP_URL=.*|APP_URL=${DDEV_PRIMARY_URL}|" .env
else
    printf '\nAPP_URL=%s\n' "${DDEV_PRIMARY_URL}" >> .env
fi

composer install --no-interaction --prefer-dist

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

mkdir -p database
touch database/database.sqlite
php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction

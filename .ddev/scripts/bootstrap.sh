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

# SQLite everywhere. Drop DB_* lines an older DDEV wrote for its Postgres container.
sed -i -E '/^DB_(HOST|PORT|DATABASE|USERNAME|PASSWORD)=/d' .env
if grep -q '^DB_CONNECTION=' .env; then
    sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=sqlite|" .env
else
    printf '\nDB_CONNECTION=sqlite\n' >> .env
fi

composer install --no-interaction --prefer-dist

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

# AI assistants sign in through Passport, which signs its tokens with these.
if [ ! -f storage/oauth-private.key ]; then
    php artisan passport:keys --no-interaction
fi

mkdir -p database
touch database/database.sqlite
php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction

cd /var/www/html/apps/mail
npm ci
npm run build

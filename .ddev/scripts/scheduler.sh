#!/usr/bin/env bash
set -euo pipefail
cd /var/www/html/apps/app
until [ -f vendor/autoload.php ]; do
    sleep 2
done
while true; do
    php artisan schedule:run --verbose --no-interaction
    sleep 60
done

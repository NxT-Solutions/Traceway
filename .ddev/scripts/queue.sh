#!/usr/bin/env bash
set -euo pipefail
cd /var/www/html/apps/app
until [ -f vendor/autoload.php ]; do
    sleep 2
done
exec php artisan queue:work --queue=default --tries=3 --timeout=60

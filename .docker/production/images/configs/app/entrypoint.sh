#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

app_user="${APP_RUNTIME_USER:-www-data}"
app_group="${APP_RUNTIME_GROUP:-www-data}"

run_as_app() {
  if [ "$(id -u)" -eq 0 ]; then
    exec su-exec "${app_user}:${app_group}" "$@"
  fi

  exec "$@"
}

if [ -f .env.production ] && [ "$(id -u)" -eq 0 ]; then
  cp .env.production .env
  chown "${app_user}:${app_group}" .env .env.production
  chmod 0640 .env .env.production
fi

mkdir -p \
  bootstrap/cache \
  storage/app/public \
  storage/database \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/testing \
  storage/framework/views \
  storage/logs

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
  db_file="${DB_DATABASE:-/var/www/html/storage/database/traceway.sqlite}"

  case "$db_file" in
    /*) ;;
    *) db_file="/var/www/html/${db_file#./}" ;;
  esac

  mkdir -p "$(dirname "$db_file")"
  touch "$db_file"
fi

if [ "$(id -u)" -eq 0 ]; then
  ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage
  chown -R "${app_user}:${app_group}" bootstrap/cache storage
  chmod -R ug+rwX bootstrap/cache storage
fi

role="${APP_RUNTIME_ROLE:-web}"

case "$role" in
  web)
    if [ "${RUN_POST_DEPLOY_ON_BOOT:-false}" = "true" ]; then
      su-exec "${app_user}:${app_group}" /usr/local/bin/traceway-post-deploy
    fi

    # The server-side renderer draws the planner's pages before the browser
    # takes over. If it stops, Laravel sends the pages for the browser to
    # draw instead, so it restarts here and never takes the site down.
    (
      while true; do
        su-exec "${app_user}:${app_group}" bun bootstrap/ssr/app.js || true
        sleep 1
      done
    ) &
    ssr_pid=$!

    php-fpm -F &
    php_pid=$!

    nginx -g 'daemon off;' &
    nginx_pid=$!

    trap 'kill "$ssr_pid" "$php_pid" "$nginx_pid" 2>/dev/null || true' EXIT
    wait -n "$php_pid" "$nginx_pid"
    ;;
  queue)
    while true; do
      su-exec "${app_user}:${app_group}" php artisan queue:work --verbose --tries=3 --timeout=60 --sleep=1 --max-jobs=250 --max-time=1800
      sleep 1
    done
    ;;
  scheduler)
    while true; do
      su-exec "${app_user}:${app_group}" php artisan schedule:run --verbose --no-interaction
      sleep 60
    done
    ;;
  post-deploy)
    run_as_app /usr/local/bin/traceway-post-deploy
    ;;
  *)
    printf 'Unknown APP_RUNTIME_ROLE=%s\n' "$role" >&2
    exit 1
    ;;
esac

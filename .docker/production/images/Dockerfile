# syntax=docker/dockerfile:1.7

FROM php:8.5-fpm-alpine AS base

ENV BUN_INSTALL=/usr/local/bun
ENV PATH="${BUN_INSTALL}/bin:${PATH}"

RUN apk add --no-cache \
    bash \
    curl \
    git \
    icu-dev \
    libzip-dev \
    nginx \
    oniguruma-dev \
    sqlite \
    su-exec \
    unzip

RUN curl -fsSL https://bun.sh/install | bash -s bun-v1.3.6 \
    && ln -sf "${BUN_INSTALL}/bin/bun" /usr/local/bin/bun

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions pdo_sqlite sqlite3 zip exif pcntl intl opcache bcmath @composer

WORKDIR /var/www/html

FROM base AS php-build

ENV APP_ENV=production
ENV APP_DEBUG=false
ENV APP_URL=https://traceway.online
ENV CACHE_STORE=file
ENV DB_CONNECTION=sqlite
ENV DB_DATABASE=/tmp/traceway-build.sqlite
ENV QUEUE_CONNECTION=sync
ENV SESSION_DRIVER=file

COPY apps/app/ /var/www/html/

RUN APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    cp .env.production .env \
    && touch /tmp/traceway-build.sqlite \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && bun install --frozen-lockfile \
    && bun run build \
    && rm -f .env public/hot

FROM node:22-alpine AS web-build

WORKDIR /src

COPY apps/web/package.json apps/web/package-lock.json ./
RUN npm ci

COPY apps/web/ ./

ENV PUBLIC_SITE_URL=https://traceway.online

RUN npm run build

FROM base AS runtime

COPY apps/app/ /var/www/html/
COPY --from=php-build /var/www/html/vendor /var/www/html/vendor
COPY --from=php-build /var/www/html/public/build /var/www/html/public/build
COPY --from=web-build /src/dist /var/www/web

RUN rm -f public/hot

RUN --mount=type=secret,id=runtime_env,target=/run/secrets/runtime_env \
    install -m 0644 /run/secrets/runtime_env /var/www/html/.env.production \
    && cp /var/www/html/.env.production /var/www/html/.env \
    && chmod 0640 /var/www/html/.env /var/www/html/.env.production

COPY .docker/production/images/configs/nginx/nginx.conf /etc/nginx/nginx.conf
COPY .docker/production/images/configs/php/app.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY .docker/production/images/configs/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY .docker/production/images/configs/app/entrypoint.sh /usr/local/bin/traceway-entrypoint
COPY .docker/production/images/configs/app/post-deploy.sh /usr/local/bin/traceway-post-deploy

RUN chmod +x /usr/local/bin/traceway-entrypoint /usr/local/bin/traceway-post-deploy \
    && install -d -m 0775 \
      /run/nginx \
      /var/lib/nginx/tmp/client_body \
      /var/www/html/bootstrap/cache \
      /var/www/html/storage/app/public \
      /var/www/html/storage/database \
      /var/www/html/storage/framework/cache/data \
      /var/www/html/storage/framework/sessions \
      /var/www/html/storage/framework/testing \
      /var/www/html/storage/framework/views \
      /var/www/html/storage/logs \
    && ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/traceway-entrypoint"]

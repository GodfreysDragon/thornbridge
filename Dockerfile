# Production image for the public demo (Render free tier, scripted brain).
# The database is SQLite inside the container, so games reset whenever the
# container restarts. That suits a demo: nothing in a game is worth keeping.

FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json .npmrc ./
RUN npm ci --ignore-scripts --audit false

COPY vite.config.ts tsconfig.json ./
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM serversideup/php:8.4-fpm-nginx-v5.0.5

ENV AUTORUN_ENABLED=true \
    PHP_OPCACHE_ENABLE=1

WORKDIR /var/www/html

COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

RUN composer dump-autoload --no-dev --optimize \
    && php artisan package:discover --ansi \
    && touch database/database.sqlite

# Without an APP_KEY, generate one at start-up. It lives as long as the
# container, which is exactly as long as the SQLite games it signs.
USER root
RUN printf '%s\n' \
        '#!/bin/sh' \
        'if [ -z "$APP_KEY" ] && [ ! -f "$APP_BASE_DIR/.env" ]; then' \
        '    echo "APP_KEY=base64:$(head -c 32 /dev/urandom | base64)" > "$APP_BASE_DIR/.env"' \
        'fi' \
        > /etc/entrypoint.d/45-app-key.sh \
    && chmod 755 /etc/entrypoint.d/45-app-key.sh
USER www-data

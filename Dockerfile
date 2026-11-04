# syntax=docker/dockerfile:1

FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js tsconfig.json ./
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM php:8.4.27-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libwebp-dev libicu-dev libonig-dev nginx \
    chromium fonts-liberation fonts-noto-color-emoji \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-rubiarocha.ini
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/entrypoint.sh /entrypoint.sh

RUN ln -sfn /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --optimize-autoloader

COPY . /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build
COPY --from=assets /usr/local/bin/node /usr/local/bin/node
COPY --from=assets /app/node_modules /var/www/html/node_modules

RUN composer dump-autoload -o --no-dev \
    && chmod +x /entrypoint.sh \
    && mkdir -p storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8000

ENTRYPOINT ["/entrypoint.sh"]

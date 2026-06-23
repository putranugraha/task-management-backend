FROM node:22-alpine AS assets

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.4-fpm AS app

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    unzip \
    libonig-dev \
    libpq-dev \
    libzip-dev \
    zip \
    && docker-php-ext-install bcmath mbstring opcache pdo pdo_pgsql zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]

FROM nginx:1.27-alpine AS nginx

WORKDIR /var/www/html

COPY --from=app /var/www/html/public ./public
RUN ln -s /var/www/html/storage/app/public /var/www/html/public/storage
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

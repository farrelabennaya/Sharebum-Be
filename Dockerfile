# Laravel API on Render (HTTP via PHP built-in)
FROM php:8.2-cli

# 1) system deps
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libonig-dev libpq-dev \
 && docker-php-ext-install pdo pdo_mysql mbstring zip

# 2) composer (boleh jalan sebagai root di container)
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# 3) install deps PRODUKSI tanpa scripts/plugins (biar gak manggil artisan)
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-plugins --no-scripts

# 4) copy source laravel
COPY . .

# 5) optimize autoload (tanpa trigger artisan)
RUN composer dump-autoload -o

# (opsional) set permission, kalau butuh
RUN mkdir -p storage bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# 6) Render inject $PORT; listen di situ
ENV PORT=10000
EXPOSE 10000

# 7) start HTTP server (pakai sh -c agar $PORT diexpand)
CMD ["sh", "-c", "php -d variables_order=EGPCS -S 0.0.0.0:${PORT:-10000} -t public"]

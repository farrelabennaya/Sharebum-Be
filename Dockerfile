# Dockerfile (API only)
FROM php:8.2-cli

# deps
RUN apt-get update && apt-get install -y git unzip libzip-dev libonig-dev libpq-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring zip

# composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# install deps produksi
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --classmap-authoritative --no-interaction

# copy source laravel
COPY . .

# optim autoload
RUN composer dump-autoload -o

# optional (boleh aktifkan kalau env sudah tersedia saat build)
# RUN php artisan config:cache && php artisan route:cache

# Render inject PORT
ENV PORT=10000
EXPOSE 10000

# jalankan php built-in server
CMD php -d variables_order=EGPCS -S 0.0.0.0:$PORT -t public

FROM php:8.2-cli

# deps yang umum untuk Laravel + MySQL
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libonig-dev libpq-dev \
 && docker-php-ext-install pdo pdo_mysql mbstring zip

# composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# install dependency produksi dulu (pakai lockfile)
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --classmap-authoritative --no-interaction

# copy source
COPY . .

# optimize autoload
RUN composer dump-autoload -o

# (opsional) cache kalau env sudah tersedia saat build
# RUN php artisan config:cache && php artisan route:cache

# penting: Render inject PORT; pastikan listen di situ
ENV PORT=10000
EXPOSE 10000

# pakai sh -c supaya $PORT diexpand
CMD ["sh", "-c", "php -d variables_order=EGPCS -S 0.0.0.0:${PORT:-10000} -t public"]

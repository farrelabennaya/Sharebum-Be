# Laravel API on Render (HTTP via PHP built-in server)
FROM php:8.2-cli

# 1) System deps + ekstensi PHP
# - libpq-dev + pdo_pgsql: WAJIB untuk konek ke PostgreSQL/Supabase
# - (opsional) pdo_mysql kalau masih butuh MySQL di tempat lain
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libonig-dev libpq-dev \
 && docker-php-ext-install pdo_pgsql \
 && docker-php-ext-install pdo pdo_mysql mbstring zip

# 2) Composer (boleh jalan sebagai root di container)
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# 3) Install deps PRODUKSI TANPA scripts/plugins (hindari artisan kepanggil saat build)
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-plugins --no-scripts

# 4) Copy seluruh source (baru sekarang file artisan ikut masuk)
COPY . .

# 5) Optimize autoload TANPA scripts juga (supaya post-autoload-dump tidak jalan)
RUN composer dump-autoload -o --no-scripts

# 6) Permission (kalau perlu)
RUN mkdir -p storage bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# 7) Render inject $PORT, pastikan listen di situ
ENV PORT=10000
EXPOSE 10000

# 8) Jalankan HTTP server (pakai sh -c agar $PORT diexpand)
CMD ["sh", "-c", "php -d variables_order=EGPCS -S 0.0.0.0:${PORT:-10000} -t public"]

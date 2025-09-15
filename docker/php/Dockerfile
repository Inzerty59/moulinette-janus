# docker/php/Dockerfile
FROM php:8.3-fpm-alpine

# Outils & dépendances
RUN apk add --no-cache \
    bash git curl icu-dev oniguruma-dev libzip-dev zlib-dev \
    libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev \
    libxml2-dev openssl-dev autoconf g++ make

# Extensions PHP utiles pour Symfony
RUN docker-php-ext-configure intl \
 && docker-php-ext-install -j$(nproc) intl pdo_mysql opcache zip

# APCu (cache)
RUN pecl install apcu && docker-php-ext-enable apcu

# GD (si besoin d’images)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j$(nproc) gd

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Recommandations de prod (opcache)
ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=0 \
    PHP_OPCACHE_MAX_ACCELERATED_FILES=20000 \
    PHP_OPCACHE_MEMORY_CONSUMPTION=256 \
    PHP_OPCACHE_INTERNED_STRINGS_BUFFER=16

WORKDIR /var/www/html
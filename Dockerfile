FROM php:8.2-apache

COPY php-upload.ini /usr/local/etc/php/conf.d/99-sema-uploads.ini

RUN apt-get update && apt-get install -y \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libicu-dev \
        libonig-dev \
        unzip \
        git \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_mysql mysqli zip gd intl mbstring \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# O código (e o vendor/) vem do volume montado pelo docker-compose. Se faltar o vendor/,
# o scripts/start.sh roda o composer install dentro do container.
WORKDIR /var/www/html

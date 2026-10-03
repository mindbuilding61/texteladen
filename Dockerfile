# syntax=docker/dockerfile:1.7
FROM php:8.3-apache-bookworm

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip ca-certificates libxml2 libpng16-16 libzip4 libonig5 libicu72 libjpeg62-turbo \
        libxml2-dev libzip-dev libicu-dev libpng-dev libjpeg-dev libonig-dev \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath intl zip gd pdo_mysql pdo_sqlite \
    && pecl install apcu \
    && docker-php-ext-enable apcu opcache \
    && apt-get purge -y libxml2-dev libzip-dev libicu-dev libpng-dev libjpeg-dev libonig-dev \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/php/php.ini     /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/apache/erechnung.conf /etc/apache2/sites-available/000-default.conf

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/app

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader

COPY . /var/www/app

RUN composer dump-autoload --no-dev --classmap-authoritative \
    && mkdir -p var/invoice-storage var/cache var/log public/assets \
    && chown -R www-data:www-data /var/www/app

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

VOLUME ["/var/www/app/var"]

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]

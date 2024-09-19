FROM composer:2.4 as vendor

WORKDIR /app

COPY composer.json composer.json
COPY composer.lock composer.lock 
COPY deps deps

RUN composer install \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --no-dev \
    --prefer-dist

COPY . .
RUN composer dump-autoload


####################################################

FROM php:8.2-apache

COPY *.php /var/www/html/
COPY .htaccess /var/www/html/
COPY --from=vendor app/vendor/ /var/www/html/vendor/

EXPOSE 80

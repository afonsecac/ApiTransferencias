# Stage 1: Composer dependencies
FROM composer:2 AS composer-deps

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

# Stage 2: PHP base (used for development)
FROM php:8.2-fpm-alpine AS php-base

RUN apk add --no-cache \
    icu-dev \
    postgresql-dev \
    rabbitmq-c-dev \
    linux-headers \
    libzip-dev \
    $PHPIZE_DEPS

RUN docker-php-ext-install \
    pdo_pgsql \
    intl \
    opcache \
    zip \
    sockets

RUN pecl install amqp apcu \
    && docker-php-ext-enable amqp apcu

# Cleanup build dependencies
RUN apk del $PHPIZE_DEPS

COPY docker/php-fpm/php.ini $PHP_INI_DIR/conf.d/app.ini
COPY docker/php-fpm/www.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www

EXPOSE 9000

CMD ["php-fpm"]

# Stage 3: Production (code baked in)
FROM php-base AS app

COPY --from=composer-deps /app/vendor ./vendor
COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative \
    && php bin/console cache:warmup --env=prod \
    && chown -R www-data:www-data var/

USER www-data

EXPOSE 9000

CMD ["php-fpm"]

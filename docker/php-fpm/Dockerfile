# Stage 1: Composer dependencies
FROM composer:2 AS composer-deps

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install --no-scripts --no-autoloader --prefer-dist --no-progress --ignore-platform-reqs

# Stage 2: PHP base (used for development)
FROM php:8.4-fpm-alpine AS php-base

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

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=composer-deps /app/vendor ./vendor
COPY . .

# Create .env stub (Symfony Dotenv requires this file to exist;
# real values are injected at runtime via docker-compose environment)
RUN printf '%s\n' \
       'APP_ENV=prod' \
       'APP_SECRET=change-me' \
       'DATABASE_URL="postgresql://localhost:5432/app?serverVersion=15"' \
       'CORS_ALLOW_ORIGIN="^https?://localhost$"' \
       'MESSENGER_TRANSPORT_DSN=doctrine://default' \
       'MESSENGER_TRANSPORT_VHOST=/' \
       'MESSENGER_TRANSPORT_FAILURE=doctrine://default?queue_name=failed' \
       'MAILER_DSN=null://null' \
       'MICROSOFT_URL=https://localhost' \
       'JWT_MEMORY_KEY=change-me' \
       'JWT_PHRASE_KEY=change-me' \
       'JWT_TIMEOUT=30' \
       'APP_TEST_PHONE=0000000000' \
       'APP_MAILER_FROM=noreply@localhost' \
       > .env

RUN composer dump-autoload --optimize \
    && php bin/console cache:warmup --env=prod \
    && chown -R www-data:www-data var/

USER www-data

EXPOSE 9000

CMD ["php-fpm"]

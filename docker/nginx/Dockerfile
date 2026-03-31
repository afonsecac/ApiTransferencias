# Build PHP app first to get public/ with bundle assets
FROM php:8.4-fpm-alpine AS php-build

WORKDIR /var/www

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache icu-dev postgresql-dev rabbitmq-c-dev libzip-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql intl opcache zip sockets \
    && pecl install amqp apcu && docker-php-ext-enable amqp apcu \
    && apk del $PHPIZE_DEPS

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-progress --ignore-platform-reqs

COPY . .

RUN printf '%s\n' \
       'APP_ENV=prod' \
       'APP_SECRET=build' \
       'DATABASE_URL="postgresql://localhost:5432/app?serverVersion=15"' \
       'MESSENGER_TRANSPORT_DSN=doctrine://default' \
       'MESSENGER_TRANSPORT_VHOST=/' \
       'MESSENGER_TRANSPORT_FAILURE=doctrine://default?queue_name=failed' \
       'MAILER_DSN=null://null' \
       'MICROSOFT_URL=https://localhost' \
       'JWT_MEMORY_KEY=build' \
       'JWT_PHRASE_KEY=build' \
       'JWT_TIMEOUT=30' \
       'APP_TEST_PHONE=0000000000' \
       'APP_MAILER_FROM=noreply@localhost' \
       'CORS_ALLOW_ORIGIN="^https?://localhost$"' \
       > .env \
    && composer dump-autoload --optimize \
    && php bin/console assets:install public --env=prod

# Nginx image with public/ including bundle assets
FROM nginx:1.25-alpine

RUN rm /etc/nginx/conf.d/default.conf

COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/conf.d/ /etc/nginx/conf.d/
COPY --from=php-build /var/www/public/ /var/www/public/

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]

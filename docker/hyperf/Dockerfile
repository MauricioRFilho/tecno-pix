FROM php:8.3-cli-alpine

RUN apk add --no-cache \
    $PHPIZE_DEPS \
    bash \
    brotli-dev \
    curl \
    git \
    linux-headers \
    mysql-client \
    openssl-dev \
    oniguruma-dev \
    supervisor \
    unzip

RUN docker-php-ext-install \
    bcmath \
    pcntl \
    pdo_mysql
RUN docker-php-ext-install sockets
RUN pecl install redis swoole \
    && docker-php-ext-enable redis swoole

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/hyperf/supervisord.conf /etc/supervisord.conf
COPY docker/hyperf/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh

EXPOSE 9501

ENTRYPOINT ["/entrypoint.sh"]

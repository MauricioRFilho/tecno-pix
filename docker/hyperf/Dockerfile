FROM hyperf/hyperf:8.4-alpine-v3.21-swoole

RUN set -ex \
    && apk add --no-cache \
        bash \
        mysql-client \
        supervisor \
    && php -v \
    && php -m \
    && php --ri swoole

WORKDIR /var/www/html

COPY docker/hyperf/supervisord.conf /etc/supervisord.conf
COPY docker/hyperf/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh

EXPOSE 9501

ENTRYPOINT ["/entrypoint.sh"]

#!/bin/sh
set -e

cd /var/www/html

if [ ! -f composer.json ] || [ ! -f bin/hyperf.php ]; then
  echo "Projeto Hyperf ainda nao foi inicializado."
  echo "Container pronto para scaffold e proximas etapas."
  tail -f /dev/null
fi

if [ ! -d vendor ]; then
  composer install --no-interaction
fi

exec /usr/bin/supervisord -c /etc/supervisord.conf


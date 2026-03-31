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

SUPERVISOR_CONFIG=/tmp/supervisord.generated.conf

cat /etc/supervisord.conf > "$SUPERVISOR_CONFIG"

cat <<'EOF' >> "$SUPERVISOR_CONFIG"

[program:hyperf-http]
command=php /var/www/html/bin/hyperf.php start
directory=/var/www/html
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
EOF

if php /var/www/html/bin/hyperf.php list | grep -q "queue:work"; then
  cat <<'EOF' >> "$SUPERVISOR_CONFIG"

[program:hyperf-queue]
command=php /var/www/html/bin/hyperf.php queue:work
directory=/var/www/html
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
EOF
fi

if php /var/www/html/bin/hyperf.php list | grep -q "crontab:run"; then
  cat <<'EOF' >> "$SUPERVISOR_CONFIG"

[program:hyperf-cron]
command=php /var/www/html/bin/hyperf.php crontab:run
directory=/var/www/html
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
EOF
fi

exec /usr/bin/supervisord -c "$SUPERVISOR_CONFIG"

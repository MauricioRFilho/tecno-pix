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

wait_for_db() {
  if [ -z "${DB_HOST:-}" ] || [ -z "${DB_PORT:-}" ] || [ -z "${DB_DATABASE:-}" ] || [ -z "${DB_USERNAME:-}" ]; then
    return 0
  fi

  max_seconds="${DB_WAIT_MAX_SECONDS:-90}"
  elapsed=0
  sleep_step=2

  echo "Aguardando banco de dados em ${DB_HOST}:${DB_PORT}..."
  while [ "$elapsed" -lt "$max_seconds" ]; do
    if php -r '
      $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT"), getenv("DB_DATABASE"));
      try {
          new PDO($dsn, (string) getenv("DB_USERNAME"), (string) getenv("DB_PASSWORD"), [PDO::ATTR_TIMEOUT => 2]);
          exit(0);
      } catch (Throwable $throwable) {
          exit(1);
      }
    '; then
      echo "Banco de dados pronto."
      return 0
    fi

    sleep "$sleep_step"
    elapsed=$((elapsed + sleep_step))
  done

  echo "Timeout aguardando banco de dados."
  return 1
}

if [ "${AUTO_DB_BOOTSTRAP:-true}" = "true" ]; then
  wait_for_db

  echo "Executando migrations..."
  php /var/www/html/bin/hyperf.php migrate

  if php /var/www/html/bin/hyperf.php list | grep -q "db:seed"; then
    echo "Executando seed inicial (apenas se banco estiver vazio)..."
    php /var/www/html/bin/hyperf.php db:seed
  fi
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

if php /var/www/html/bin/hyperf.php list | grep -q "withdraw:process-scheduled"; then
  SCHEDULED_WITHDRAW_POLL_SECONDS="${SCHEDULED_WITHDRAW_POLL_SECONDS:-15}"
  cat <<EOF >> "$SUPERVISOR_CONFIG"

[program:hyperf-withdraw-scheduler]
command=/bin/sh -c "while true; do php /var/www/html/bin/hyperf.php withdraw:process-scheduled; sleep ${SCHEDULED_WITHDRAW_POLL_SECONDS}; done"
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

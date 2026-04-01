# Tecno Pix

API REST para saque PIX imediato e agendado com Hyperf 3, MySQL, Redis e Mailhog.

## Requisitos

- Docker + Docker Compose
- Porta `9501` livre para API
- Porta `9500` livre para Swagger UI

## Subir projeto

```bash
cp .env.example .env
docker compose up --build -d
docker compose exec app composer install
docker compose exec app php bin/hyperf.php migrate
```

## Serviços

- API: `http://localhost:9501`
- Swagger UI: `http://localhost:9500/swagger`
- OpenAPI JSON: `http://localhost:9500/http.json`
- Mailhog: `http://localhost:8025`
- MySQL: `localhost:3306`
- Redis: `localhost:6379`

## Endpoint principal

`POST /account/{accountId}/balance/withdraw`

Payload exemplo:

```json
{
  "method": "pix",
  "amount": 150.75,
  "pix": {
    "type": "email",
    "key": "cliente@example.com"
  },
  "schedule": null
}
```

## Testes

Executar suíte completa:

```bash
docker compose exec app composer test
```

Executar apenas partes implementadas:

```bash
docker compose exec app composer run test:implemented
```

Executar validação final do plano (Parte 10):

```bash
docker compose exec app composer run test:part10
```

Checklist manual complementar da Parte 10:

1. Validar emails no Mailhog em `http://localhost:8025`.
2. Conferir registros no MySQL:

```bash
docker compose exec mysql mysql -u root -proot tecno_pix -e "SELECT id, account_id, amount, scheduled, done, error, processed_at FROM account_withdraw ORDER BY created_at DESC LIMIT 10;"
docker compose exec mysql mysql -u root -proot tecno_pix -e "SELECT account_withdraw_id, type, `key` FROM account_withdraw_pix ORDER BY account_withdraw_id DESC LIMIT 10;"
```

## Logs operacionais

Eventos principais emitidos:

- `withdraw.created`
- `withdraw.processed`
- `withdraw.failed`
- `withdraw.email_sent`
- `withdraw.email_failed`
- `cron.scheduled.start`
- `cron.scheduled.summary`

Arquivo de log padrão: `runtime/logs/hyperf.log`.

## Rate limit no saque

Configuração via `.env`:

- `WITHDRAW_RATE_LIMIT_MAX`
- `WITHDRAW_RATE_LIMIT_WINDOW_SECONDS`

Quando excedido, a API retorna `429` com código `rate_limit_exceeded`.

## Planejamento técnico

Detalhamento completo em [PLANEJAMENTO.md](./PLANEJAMENTO.md).

## Guia de testes

Cenários automatizados, adversariais e de stress em [TESTES.md](./TESTES.md).

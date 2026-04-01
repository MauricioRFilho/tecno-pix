# Tecno Pix

![Tecno Pix Logo](docs/assets/tecnopix-logo.svg)

API REST para saque PIX imediato e agendado com Hyperf 3, MySQL, Redis e Mailhog.

## Visão Geral Técnica

O projeto entrega um fluxo completo de saque PIX com:
- API HTTP para solicitação de saque.
- Processamento assíncrono e processamento agendado.
- Persistência relacional para trilha de auditoria.
- Notificação de email após conclusão.
- Dashboard operacional para validação visual.
- Swagger para validação de contrato de API.

Para visão executiva (não técnica), consulte: [Documentação para RH](docs/README_RH.md).

## Requisitos

- Docker + Docker Compose
- Porta `9501` livre para API e Dashboard
- Porta `9500` livre para Swagger UI

## Subir Projeto

```bash
cp .env.example .env
docker compose up --build -d
```

No boot do container `app`:
- `migrate` é executado automaticamente.
- `db:seed` é executado automaticamente.

Parâmetros relevantes:
- `AUTO_DB_BOOTSTRAP=true|false`: habilita/desabilita bootstrap de banco.
- `DB_WAIT_MAX_SECONDS=90`: tempo máximo de espera do MySQL no startup.

## Serviços

- API: `http://localhost:9501`
- Dashboard visual: `http://localhost:9501/dashboard`
- Swagger UI: `http://localhost:9500/swagger`
- OpenAPI JSON: `http://localhost:9500/http.json`
- Mailhog: `http://localhost:8025`
- MySQL: `localhost:3306`
- Redis: `localhost:6379`

## Endpoints Principais

- `POST /account/{accountId}/balance/withdraw`
- `GET /accounts`
- `POST /accounts`
- `GET /operations`

Payload exemplo de saque:

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

## Exemplo de Email de Sucesso

Exemplo de conteúdo HTML enviado após conclusão do saque:

```html
<h1>Saque PIX concluido</h1>
<p>withdraw_id: 8a5bf2a0-1f35-4d4f-98f2-16cbf8d8c0a1</p>
<p>account_id: 9de9f4da-2de0-4aaf-9f79-30d34b590312</p>
<p>processed_at: 2026-04-01 14:35:22</p>
<p>amount: 150.75</p>
<p>pix_type: email</p>
<p>pix_key: cliente@example.com</p>
```

## Decisões e Porquês

1. Bootstrap automático de banco (`migrate` + `db:seed` no startup do app)
   - Por que: reduzir atrito no primeiro `docker compose up`.
   - Impacto: ambiente sobe pronto para uso sem comandos extras.
   - Risco controlado: seed é idempotente por regra de existência (`Account::exists()`).

2. Seed inicial com 4 contas demo
   - Por que: acelerar onboarding e teste manual do fluxo no dashboard.
   - Impacto: qualquer clone já tem dados úteis para validar saque/consulta.
   - Risco controlado: população ocorre apenas com base vazia.

3. SMTP local com Mailhog por padrão
   - Por que: evitar dependência de provedor externo e credenciais.
   - Impacto: validação de template e envio possível em qualquer máquina.
   - Risco controlado: não envia email real em ambiente de desenvolvimento.

4. Separação de portas (`9501` app e `9500` Swagger)
   - Por que: separar claramente fluxo de negócio e documentação de contrato.
   - Impacto: troubleshooting mais simples e menor confusão de rota.

5. Dashboard + Swagger como dupla de validação
   - Por que: público técnico valida contrato no Swagger; público operacional valida fluxo no dashboard.
   - Impacto: reduz tempo de verificação e melhora comunicação entre perfis.

6. Wait-for-DB no entrypoint
   - Por que: evitar condição de corrida no startup entre app e MySQL.
   - Impacto: reduz falhas intermitentes de conexão no boot.

## Testes

Suíte completa:

```bash
docker compose exec app composer test
```

Somente etapas implementadas:

```bash
docker compose exec app composer run test:implemented
```

Validação final (Parte 10):

```bash
docker compose exec app composer run test:part10
```

Checklist manual:
1. Validar emails no Mailhog (`http://localhost:8025`).
2. Conferir registros no MySQL:

```bash
docker compose exec mysql mysql -u root -psecret tecno_pix -e "SELECT id, account_id, amount, scheduled, done, error, processed_at FROM account_withdraw ORDER BY created_at DESC LIMIT 10;"
docker compose exec mysql mysql -u root -psecret tecno_pix -e "SELECT account_withdraw_id, type, `key` FROM account_withdraw_pix ORDER BY account_withdraw_id DESC LIMIT 10;"
```

Mais detalhes de testes em [TESTES.md](docs/TESTES.md).

## Operação e Observabilidade

Eventos de log principais:
- `withdraw.created`
- `withdraw.processed`
- `withdraw.failed`
- `withdraw.email_sent`
- `withdraw.email_failed`
- `cron.scheduled.start`
- `cron.scheduled.summary`

Log padrão: `runtime/logs/hyperf.log`.

Rate limit de saque:
- `WITHDRAW_RATE_LIMIT_MAX`
- `WITHDRAW_RATE_LIMIT_WINDOW_SECONDS`

Resposta quando excedido:
- HTTP `429`
- código `rate_limit_exceeded`

## Referências

- Planejamento técnico completo: [PLANEJAMENTO.md](docs/PLANEJAMENTO.md)
- Guia de testes: [TESTES.md](docs/TESTES.md)
- Documento não técnico (RH): [docs/README_RH.md](docs/README_RH.md)

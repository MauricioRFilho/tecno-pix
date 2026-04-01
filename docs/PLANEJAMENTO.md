# 📋 Planejamento — Saque PIX (Hyperf 3)

> Case técnico Full Stack — Plataforma de Conta Digital com Saque PIX

---

## Sumário

1. [Visão Geral](#1-visão-geral)
2. [Stack e Tecnologias](#2-stack-e-tecnologias)
3. [Arquitetura dos Serviços](#3-arquitetura-dos-serviços)
4. [Estrutura de Pastas](#4-estrutura-de-pastas)
5. [Banco de Dados](#5-banco-de-dados)
6. [Rotas da API](#6-rotas-da-api)
7. [Fluxos de Negócio](#7-fluxos-de-negócio)
8. [Regras de Negócio](#8-regras-de-negócio)
9. [Design de Código — Extensibilidade](#9-design-de-código--extensibilidade)
10. [Proteção contra Race Condition](#10-proteção-contra-race-condition)
11. [Email de Notificação](#11-email-de-notificação)
12. [Cron — Saques Agendados](#12-cron--saques-agendados)
13. [Observabilidade e Logging](#13-observabilidade-e-logging)
14. [Segurança](#14-segurança)
15. [Docker e Infraestrutura](#15-docker-e-infraestrutura)
16. [Ordem de Implementação](#16-ordem-de-implementação)
17. [README do Projeto](#17-readme-do-projeto)
18. [Decisões Arquiteturais e Trade-offs](#18-decisões-arquiteturais-e-trade-offs)

---

## 1. Visão Geral

O projeto consiste em uma API REST para uma plataforma de conta digital que permite ao usuário realizar **saques via PIX** — tanto imediatos quanto agendados — com notificação por email após a execução, utilizando PHP Hyperf 3 como framework principal, totalmente containerizado com Docker.

---

## 2. Stack e Tecnologias

| Tecnologia | Versão | Papel |
|---|---|---|
| PHP | 8.3 | Linguagem base |
| Hyperf | 3.x | Framework HTTP + DI + ORM + Queue + Cron |
| MySQL | 8.0 | Banco de dados relacional |
| Mailhog | latest | Serviço de email para testes |
| Docker | — | Containerização |
| Docker Compose | — | Orquestração dos serviços |
| Swoole | 5.x | Servidor assíncrono (base do Hyperf) |

> **Por que Hyperf?**  
> Hyperf é construído sobre Swoole, o que oferece um servidor HTTP persistente em memória, corrotinas nativas e processamento assíncrono sem necessidade de extensões como FPM. Isso resulta em alta performance e suporte natural a filas, cron jobs e escalabilidade horizontal.

---

## 3. Arquitetura dos Serviços

```
┌────────────────────────────────────────────────────────────────┐
│                        Docker Compose                          │
│                                                                │
│  ┌──────────────────────┐   ┌──────────┐   ┌───────────────┐  │
│  │       app            │   │  mysql   │   │   mailhog     │  │
│  │  Hyperf 3 (Swoole)   │   │  :3306   │   │  :1025 SMTP   │  │
│  │  HTTP  → :9501       │◄──►          │   │  :8025 WebUI  │  │
│  │  Queue Worker        │   │          │   │               │  │
│  │  Cron Scheduler      │   └──────────┘   └───────────────┘  │
│  └──────────────────────┘                                      │
│                                                                │
│  └──────────────── Rede interna: pix-network ─────────────────┘│
└────────────────────────────────────────────────────────────────┘
```

O container `app` executa três responsabilidades via **Supervisor**:

- Servidor HTTP Hyperf (porta 9501)
- Worker de filas (para processamento assíncrono dos saques imediatos)
- Scheduler de cron (para processar saques agendados)

---

## 4. Estrutura de Pastas

```
pix-withdraw/
├── app/
│   ├── Command/
│   │   └── ProcessScheduledWithdrawsCommand.php   # Cron de saques agendados
│   ├── Contract/
│   │   └── WithdrawMethodInterface.php            # Contrato p/ expansão futura
│   ├── Controller/
│   │   └── WithdrawController.php                 # Endpoint POST withdraw
│   ├── Exception/
│   │   ├── Handler/
│   │   │   └── AppExceptionHandler.php            # Handler global de erros
│   │   ├── InsufficientBalanceException.php
│   │   ├── AccountNotFoundException.php
│   │   └── InvalidScheduleException.php
│   ├── Job/
│   │   └── ProcessWithdrawJob.php                 # Job assíncrono p/ saque imediato
│   ├── Listener/
│   │   └── WithdrawCompletedListener.php          # Dispara email após saque
│   ├── Mail/
│   │   └── WithdrawNotificationMail.php           # Template do email
│   ├── Method/
│   │   └── Pix/
│   │       ├── PixWithdrawMethod.php              # Implementação do método PIX
│   │       └── PixEmailValidator.php              # Valida chave do tipo email
│   ├── Model/
│   │   ├── Account.php
│   │   ├── AccountWithdraw.php
│   │   └── AccountWithdrawPix.php
│   ├── Request/
│   │   └── WithdrawRequest.php                    # Validação do payload
│   └── Service/
│       ├── WithdrawService.php                    # Orquestrador do fluxo de saque
│       └── WithdrawMethodFactory.php              # Resolve método pelo nome
├── config/
│   ├── autoload/
│   │   ├── databases.php
│   │   ├── logger.php
│   │   ├── queue.php
│   │   └── mail.php
│   └── routes.php
├── migrations/
│   ├── 2026_01_01_000001_create_account_table.php
│   ├── 2026_01_01_000002_create_account_withdraw_table.php
│   └── 2026_01_01_000003_create_account_withdraw_pix_table.php
├── docker/
│   ├── hyperf/
│   │   ├── Dockerfile
│   │   └── supervisord.conf
│   └── mysql/
│       └── init.sql
├── .env
├── .env.example
├── docker-compose.yml
└── README.md
```

---

## 5. Banco de Dados

### 5.1 Tabela `account`

| Coluna | Tipo | Restrições | Observação |
|---|---|---|---|
| id | CHAR(36) | PK | UUID v4 |
| name | VARCHAR(255) | NOT NULL | Nome do titular |
| balance | DECIMAL(15,2) | NOT NULL, DEFAULT 0.00 | Saldo disponível |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### 5.2 Tabela `account_withdraw`

| Coluna | Tipo | Restrições | Observação |
|---|---|---|---|
| id | CHAR(36) | PK | UUID v4 |
| account_id | CHAR(36) | FK → account.id | |
| method | VARCHAR(50) | NOT NULL | Ex: `PIX` |
| amount | DECIMAL(15,2) | NOT NULL | Valor solicitado |
| scheduled | TINYINT(1) | NOT NULL, DEFAULT 0 | Flag de agendamento |
| scheduled_for | DATETIME | NULL | Data/hora do agendamento |
| done | TINYINT(1) | NOT NULL, DEFAULT 0 | Saque concluído |
| error | TINYINT(1) | NOT NULL, DEFAULT 0 | Saque com falha |
| error_reason | TEXT | NULL | Mensagem de falha |
| processed_at | DATETIME | NULL | ✅ Adicionado — timestamp exato da execução |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

> **`processed_at` (coluna adicional):** registra o momento exato em que o saque foi processado. Usado no email de notificação e em auditorias.

### 5.3 Tabela `account_withdraw_pix`

| Coluna | Tipo | Restrições | Observação |
|---|---|---|---|
| account_withdraw_id | CHAR(36) | PK, FK → account_withdraw.id | |
| type | VARCHAR(50) | NOT NULL | Ex: `email` |
| key | VARCHAR(255) | NOT NULL | Chave PIX |

### 5.4 Índices recomendados

```sql
-- Cron query: busca saques agendados pendentes
CREATE INDEX idx_withdraw_scheduled
  ON account_withdraw (scheduled, done, error, scheduled_for);

-- Queries por conta
CREATE INDEX idx_withdraw_account
  ON account_withdraw (account_id);
```

### 5.5 Diagrama de Relacionamento

```
account (1) ──────────────── (N) account_withdraw (1) ──── (1) account_withdraw_pix
  id                                id                              account_withdraw_id
  name                              account_id (FK)                 type
  balance                           method                          key
                                    amount
                                    scheduled
                                    scheduled_for
                                    done
                                    error
                                    error_reason
                                    processed_at
```

---

## 6. Rotas da API

```
POST /account/{accountId}/balance/withdraw
```

### Request Body

```json
{
  "method": "PIX",
  "pix": {
    "type": "email",
    "key": "fulano@email.com"
  },
  "amount": 150.75,
  "schedule": null
}
```

| Campo | Tipo | Obrigatório | Validação |
|---|---|---|---|
| method | string | ✅ | Deve ser `PIX` (por ora) |
| pix.type | string | ✅ | Deve ser `email` |
| pix.key | string | ✅ | Email válido (filter_var) |
| amount | decimal | ✅ | > 0, máx 2 casas decimais |
| schedule | string\|null | ✅ | Formato `Y-m-d H:i` ou `null` |

### Responses

| Cenário | Status | Body |
|---|---|---|
| Saque imediato enfileirado | `202 Accepted` | `{ "withdraw_id": "uuid" }` |
| Saque agendado registrado | `202 Accepted` | `{ "withdraw_id": "uuid", "scheduled_for": "..." }` |
| Conta não encontrada | `404 Not Found` | `{ "error": "Account not found" }` |
| Saldo insuficiente | `422 Unprocessable Entity` | `{ "error": "Insufficient balance" }` |
| Agendamento no passado | `422 Unprocessable Entity` | `{ "error": "Schedule date must be in the future" }` |
| Payload inválido | `422 Unprocessable Entity` | `{ "errors": { ... } }` |

---

## 7. Fluxos de Negócio

### 7.1 Saque Imediato (`schedule: null`)

```
POST /account/{accountId}/balance/withdraw
             │
             ▼
    WithdrawController
             │
             ▼
    WithdrawRequest::validate()
      ├── method, pix.type, pix.key, amount, schedule
      └── ERRO 422 se inválido
             │
             ▼
    WithdrawService::create()
      ├── Busca Account por {accountId}
      │     └── ERRO 404 se não existir
      ├── Verifica amount <= account.balance
      │     └── ERRO 422 se insuficiente
      ├── Cria account_withdraw (done=false, scheduled=false)
      ├── WithdrawMethodFactory::make("PIX")
      ├── PixWithdrawMethod::persistDetails()
      │     └── Cria account_withdraw_pix
      └── Despacha ProcessWithdrawJob (async)
             │
             ▼
         202 Accepted
             │
    (assíncrono — Swoole coroutine / queue)
             │
             ▼
    ProcessWithdrawJob::handle()
      ├── Inicia transação DB
      ├── SELECT account FOR UPDATE  ← lock de linha
      ├── Verifica saldo novamente   ← proteção race condition
      ├── account.balance -= amount
      ├── account_withdraw.done = true
      ├── account_withdraw.processed_at = NOW()
      ├── Commit
      └── Dispara WithdrawCompletedListener
             │
             ▼
    WithdrawCompletedListener::process()
      └── WithdrawNotificationMail::send()
            └── Email → pix.key (endereço do destinatário)
```

### 7.2 Saque Agendado (`schedule: "2026-06-01 15:00"`)

```
POST /account/{accountId}/balance/withdraw
             │
             ▼
    WithdrawService::create()
      ├── Valida schedule > NOW()
      │     └── ERRO 422 se no passado
      ├── Verifica saldo suficiente (pré-validação informativa)
      │     └── ERRO 422 se insuficiente
      ├── Cria account_withdraw (scheduled=true, scheduled_for=date)
      ├── Cria account_withdraw_pix
      └── NÃO despacha job (processado somente pelo cron)
             │
             ▼
         202 Accepted
```

### 7.3 Processamento pelo Cron

```
ProcessScheduledWithdrawsCommand (a cada minuto)
             │
             ▼
    SELECT * FROM account_withdraw
      WHERE scheduled = 1
        AND done = 0
        AND error = 0
        AND scheduled_for <= NOW()
             │
             ▼
    Para cada saque pendente:
      ├── Inicia transação DB
      ├── SELECT account FOR UPDATE
      ├── Saldo suficiente?
      │     ├── SIM:
      │     │     ├── account.balance -= amount
      │     │     ├── done = true
      │     │     ├── processed_at = NOW()
      │     │     ├── Commit
      │     │     └── Envia email de notificação
      │     └── NÃO:
      │           ├── error = true
      │           ├── error_reason = "Insufficient balance at scheduled time"
      │           └── Commit (registra falha)
      └── Log estruturado do resultado
```

---

## 8. Regras de Negócio

| # | Regra | Onde implementada |
|---|---|---|
| 1 | Saque deve ser registrado nas tabelas `account_withdraw` e `account_withdraw_pix` | `WithdrawService` |
| 2 | Saque imediato (`schedule: null`) deve ocorrer de imediato via job assíncrono | `ProcessWithdrawJob` |
| 3 | Saque agendado processado **somente** via cron | `ProcessScheduledWithdrawsCommand` |
| 4 | O saque deduz o saldo da tabela `account` | `ProcessWithdrawJob` / Cron |
| 5 | Apenas PIX do tipo `email` é aceito atualmente | `PixEmailValidator` |
| 6 | Implementação deve facilitar expansão de novos métodos de saque | `WithdrawMethodInterface` + Factory |
| 7 | Não é permitido sacar mais do que o saldo disponível | `WithdrawService` + revalidação no job |
| 8 | Saldo não pode ficar negativo | `SELECT FOR UPDATE` + verificação antes do débito |
| 9 | Agendamento não pode ser no passado | `WithdrawRequest` + `WithdrawService` |
| 10 | Falha por saldo insuficiente no agendado deve ser registrada no banco | Cron — `error=true, error_reason=...` |
| 11 | Email enviado após execução do saque com: data/hora, valor e dados do PIX | `WithdrawNotificationMail` |

---

## 9. Design de Código — Extensibilidade

### 9.1 Contrato de Métodos de Saque

```php
// app/Contract/WithdrawMethodInterface.php
interface WithdrawMethodInterface
{
    public function getMethodName(): string;
    public function validate(array $data): void;
    public function persistDetails(AccountWithdraw $withdraw, array $data): void;
}
```

### 9.2 Factory de Métodos

```php
// app/Service/WithdrawMethodFactory.php
class WithdrawMethodFactory
{
    public function make(string $method): WithdrawMethodInterface
    {
        return match (strtoupper($method)) {
            'PIX' => $this->container->get(PixWithdrawMethod::class),
            // Futuros métodos:
            // 'TED' => $this->container->get(TedWithdrawMethod::class),
            // 'DOC' => $this->container->get(DocWithdrawMethod::class),
            default => throw new UnsupportedWithdrawMethodException($method),
        };
    }
}
```

### 9.3 Implementação PIX

```php
// app/Method/Pix/PixWithdrawMethod.php
class PixWithdrawMethod implements WithdrawMethodInterface
{
    public function getMethodName(): string { return 'PIX'; }

    public function validate(array $data): void
    {
        // Valida que type = email e que key é email válido
    }

    public function persistDetails(AccountWithdraw $withdraw, array $data): void
    {
        AccountWithdrawPix::create([
            'account_withdraw_id' => $withdraw->id,
            'type'                => $data['pix']['type'],
            'key'                 => $data['pix']['key'],
        ]);
    }
}
```

> Para adicionar um novo método de saque no futuro (ex: TED), basta criar uma nova classe implementando `WithdrawMethodInterface` e registrá-la no `match` da factory. Zero alterações no `WithdrawService` ou na controller.

---

## 10. Proteção contra Race Condition

Em um ambiente com escalabilidade horizontal (múltiplos containers `app`), dois workers poderiam processar o mesmo saque simultaneamente, resultando em saldo negativo. A proteção é feita em **duas camadas**:

### Camada 1 — Pré-validação (rápida, não bloqueante)
No `WithdrawService::create()`, verificamos se o saldo é suficiente **antes** de criar o registro. Isso evita criar saques que claramente não podem ser executados.

### Camada 2 — Lock otimista no processamento (crítica)
No `ProcessWithdrawJob` e no Cron, toda dedução de saldo ocorre dentro de uma transação com `SELECT ... FOR UPDATE`:

```php
DB::transaction(function () use ($withdraw) {
    // Bloqueia a linha da conta durante a transação
    $account = Account::lockForUpdate()->findOrFail($withdraw->account_id);

    if (bccomp((string) $account->balance, (string) $withdraw->amount, 2) < 0) {
        // Registra falha — outro worker já deduziu antes
        $withdraw->update(['error' => true, 'error_reason' => 'Insufficient balance']);
        return;
    }

    $account->decrement('balance', $withdraw->amount);
    $withdraw->update(['done' => true, 'processed_at' => now()]);
});
```

> O uso de `bcmath` em vez de aritmética de ponto flutuante nativo garante precisão exata em operações monetárias.

---

## 11. Email de Notificação

### Configuração
O Mailhog é configurado como servidor SMTP de testes na porta 1025. A WebUI de inspeção de emails fica disponível em `http://localhost:8025`.

### Template mínimo exigido
O email deve conter obrigatoriamente:
- **Data e hora** do saque (`processed_at`)
- **Valor** sacado (`amount`)
- **Dados do PIX**: tipo e chave

### Fluxo de disparo
O email é enviado pelo `WithdrawCompletedListener`, que é acionado após a conclusão bem-sucedida do saque (tanto no job imediato quanto no cron). Isso desacopla o envio do email da lógica de dedução de saldo.

```
ProcessWithdrawJob::handle()
    └── event(new WithdrawCompleted($withdraw))
            └── WithdrawCompletedListener::process()
                    └── Mail::to($pix->key)->send(new WithdrawNotificationMail($withdraw))
```

---

## 12. Cron — Saques Agendados

### Registro no Hyperf

```php
// app/Command/ProcessScheduledWithdrawsCommand.php
#[Crontab(rule: '* * * * *', name: 'ProcessScheduledWithdraws', singleton: true)]
class ProcessScheduledWithdrawsCommand
{
    public function execute(): void
    {
        // Busca e processa saques agendados pendentes
    }
}
```

A opção `singleton: true` garante que, mesmo em múltiplos containers, apenas uma instância do cron processe de cada vez (usa mecanismo de lock do Hyperf).

### Query de busca

```sql
SELECT aw.*, awp.*
FROM account_withdraw aw
JOIN account_withdraw_pix awp ON awp.account_withdraw_id = aw.id
WHERE aw.scheduled = 1
  AND aw.done = 0
  AND aw.error = 0
  AND aw.scheduled_for <= NOW()
ORDER BY aw.scheduled_for ASC
LIMIT 100;  -- processa em lotes para evitar timeout
```

### Processamento em lote
Para evitar que um cron de 1 minuto acumule muitos saques e estoure o tempo de execução, o processamento é feito em **chunks de 100 registros**, utilizando corrotinas do Swoole para paralelizar:

```php
AccountWithdraw::query()->where(...)->chunkById(100, function ($withdraws) {
    foreach ($withdraws as $withdraw) {
        go(fn() => $this->processWithdraw($withdraw));
    }
});
```

---

## 13. Observabilidade e Logging

### Formato
Todos os logs são emitidos em **JSON estruturado**, facilitando ingestão por ferramentas como Datadog, CloudWatch ou ELK.

### Campos padrão em cada log de saque

```json
{
  "timestamp": "2026-03-31T12:00:00Z",
  "level": "info",
  "event": "withdraw.processed",
  "account_id": "uuid",
  "withdraw_id": "uuid",
  "amount": "150.75",
  "method": "PIX",
  "scheduled": false,
  "duration_ms": 42,
  "status": "success"
}
```

### Eventos logados

| Evento | Nível | Descrição |
|---|---|---|
| `withdraw.created` | INFO | Saque registrado no banco |
| `withdraw.processed` | INFO | Saldo deduzido com sucesso |
| `withdraw.failed` | WARNING | Falha (ex: saldo insuficiente no agendado) |
| `withdraw.email_sent` | INFO | Email enviado com sucesso |
| `withdraw.email_failed` | ERROR | Falha no envio do email |
| `cron.scheduled.start` | INFO | Cron iniciou a varredura |
| `cron.scheduled.summary` | INFO | Cron finalizou — N processados, M falhas |

---

## 14. Segurança

| Ponto | Medida |
|---|---|
| UUID na rota | Validação com regex antes de consultar o banco — evita enumeração e injeção |
| Chave PIX | Validada com `filter_var($key, FILTER_VALIDATE_EMAIL)` |
| Valores monetários | Calculados com `bcmath` — sem imprecisão de ponto flutuante |
| Rate limiting | Middleware Hyperf limita requisições por IP/conta no endpoint de saque |
| Saldo negativo | Dupla verificação (pré-criação + `FOR UPDATE` no processamento) |
| Variáveis sensíveis | Gerenciadas via `.env`, nunca hardcoded; `.env` no `.gitignore` |
| Headers de segurança | Middleware adiciona `X-Content-Type-Options`, `X-Frame-Options` |

---

## 15. Docker e Infraestrutura

### docker-compose.yml (estrutura)

```yaml
services:

  app:
    build: ./docker/hyperf
    ports:
      - "9501:9501"
    depends_on:
      mysql:
        condition: service_healthy
    environment:
      - DB_HOST=mysql
      - MAIL_HOST=mailhog
    volumes:
      - .:/var/www/html
    networks:
      - pix-network

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: pix_withdraw
      MYSQL_ROOT_PASSWORD: secret
    ports:
      - "3306:3306"
    volumes:
      - mysql_data:/var/lib/mysql
      - ./docker/mysql/init.sql:/docker-entrypoint-initdb.d/init.sql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 5s
      retries: 10
    networks:
      - pix-network

  mailhog:
    image: mailhog/mailhog
    ports:
      - "1025:1025"   # SMTP
      - "8025:8025"   # WebUI
    networks:
      - pix-network

networks:
  pix-network:
    driver: bridge

volumes:
  mysql_data:
```

### Dockerfile do Hyperf (resumo)

```dockerfile
FROM php:8.3-cli

# Instala Swoole e extensões necessárias
RUN pecl install swoole && docker-php-ext-enable swoole
RUN docker-php-ext-install pdo_mysql bcmath

# Instala Composer e dependências
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --optimize-autoloader

# Supervisor controla HTTP + Queue + Cron
COPY docker/hyperf/supervisord.conf /etc/supervisor/conf.d/

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
```

### supervisord.conf

```ini
[program:hyperf-http]
command=php bin/hyperf.php start
autostart=true
autorestart=true

[program:hyperf-queue]
command=php bin/hyperf.php queue:work
autostart=true
autorestart=true

[program:hyperf-cron]
command=php bin/hyperf.php crontab:run
autostart=true
autorestart=true
```

---

## 16. Ordem de Implementação

Para facilitar a execução, o plano pode ser quebrado em **partes menores, independentes e validáveis**. A ideia é terminar cada parte com algo funcional e verificável antes de avançar.

### Parte 1 — Base do ambiente

**Objetivo:** subir o projeto localmente com todos os serviços essenciais.

**Entregáveis**
- `docker-compose.yml`
- `docker/hyperf/Dockerfile`
- `docker/hyperf/supervisord.conf`
- `.env.example`

**Checklist**
- Configurar serviço `app` com Hyperf
- Configurar serviço `mysql`
- Configurar serviço `mailhog`
- Definir variáveis de ambiente mínimas
- Garantir que o container da aplicação sobe após o banco estar saudável

**Critério de pronto**
- `docker compose up --build` sobe sem erro
- API responde na porta `9501`
- Mailhog responde na porta `8025`

### Parte 2 — Modelo de dados

**Objetivo:** estruturar o banco para suportar conta, saque e detalhes PIX.

**Entregáveis**
- Migrations de `account`
- Migration de `account_withdraw`
- Migration de `account_withdraw_pix`
- Índices principais
- Models com relacionamentos

**Checklist**
- Criar tabela de contas com saldo
- Criar tabela de saques com status, agendamento e rastreabilidade
- Criar tabela específica para dados PIX
- Adicionar `processed_at`
- Mapear relacionamentos ORM
- Criar seed inicial para testes

**Critério de pronto**
- Migrations executam do zero
- Relacionamentos funcionam no ORM
- Existe ao menos uma conta com saldo para teste

### Parte 3 — Entrada HTTP do saque

**Objetivo:** receber a requisição de saque e validar o payload.

**Entregáveis**
- `config/routes.php`
- `app/Request/WithdrawRequest.php`
- `app/Controller/WithdrawController.php`

**Checklist**
- Criar rota `POST /account/{accountId}/balance/withdraw`
- Validar `method`, `pix.type`, `pix.key`, `amount` e `schedule`
- Validar formato do `accountId`
- Retornar erros HTTP coerentes para payload inválido

**Critério de pronto**
- Endpoint aceita payload válido
- Payload inválido retorna `422`
- Conta inexistente ainda pode ficar pendente para a próxima parte, se a controller estiver apenas delegando

### Parte 4 — Núcleo do caso de uso

**Objetivo:** registrar corretamente a intenção de saque e aplicar as regras principais.

**Entregáveis**
- `app/Service/WithdrawService.php`
- Exceções de domínio
- Persistência em `account_withdraw` e `account_withdraw_pix`

**Checklist**
- Buscar conta pelo `accountId`
- Retornar `404` se não existir
- Validar saldo disponível antes do registro
- Validar agendamento futuro
- Criar registro principal do saque
- Criar registro de detalhes PIX
- Diferenciar fluxo imediato e agendado

**Critério de pronto**
- Saque imediato é registrado como pendente
- Saque agendado é registrado com `scheduled=true`
- Regras de saldo e agendamento já estão protegidas na criação

### Parte 5 — Extensibilidade do método de saque

**Objetivo:** evitar acoplamento da regra de saque ao PIX.

**Entregáveis**
- `app/Contract/WithdrawMethodInterface.php`
- `app/Service/WithdrawMethodFactory.php`
- `app/Method/Pix/PixWithdrawMethod.php`
- `app/Method/Pix/PixEmailValidator.php`

**Checklist**
- Definir contrato comum para métodos de saque
- Implementar factory por nome do método
- Isolar validação específica do PIX
- Isolar persistência dos detalhes PIX

**Critério de pronto**
- `WithdrawService` não conhece detalhes internos do PIX
- Trocar ou adicionar método futuro exige alteração mínima

### Parte 6 — Processamento assíncrono do saque imediato

**Objetivo:** concluir saques imediatos em background com segurança transacional.

**Entregáveis**
- `app/Job/ProcessWithdrawJob.php`
- Evento/listener de conclusão

**Checklist**
- Despachar job para saque imediato
- Recarregar conta com `FOR UPDATE`
- Revalidar saldo dentro da transação
- Debitar saldo com precisão monetária
- Marcar saque como concluído
- Preencher `processed_at`

**Critério de pronto**
- Endpoint retorna `202`
- Job conclui o débito com consistência
- Dois processamentos concorrentes não deixam saldo negativo

### Parte 7 — Email de notificação

**Objetivo:** avisar o usuário após a execução bem-sucedida do saque.

**Entregáveis**
- `config/autoload/mail.php`
- `app/Mail/WithdrawNotificationMail.php`
- `app/Listener/WithdrawCompletedListener.php`

**Checklist**
- Configurar SMTP apontando para Mailhog
- Montar template mínimo com data, valor e dados do PIX
- Disparar email apenas após sucesso
- Tratar falha de envio com log

**Critério de pronto**
- Email aparece no Mailhog após saque concluído
- Conteúdo obrigatório está presente no template

### Parte 8 — Processamento de saques agendados

**Objetivo:** executar automaticamente os saques agendados quando a data chegar.

**Entregáveis**
- `app/Command/ProcessScheduledWithdrawsCommand.php`

**Checklist**
- Registrar comando com `#[Crontab]`
- Buscar saques pendentes elegíveis
- Processar em lote
- Aplicar o mesmo padrão transacional do saque imediato
- Marcar erro em caso de saldo insuficiente na hora da execução
- Enviar email somente quando concluir com sucesso

**Critério de pronto**
- Um saque agendado válido é processado pelo cron
- Um saque sem saldo suficiente é marcado com erro
- O cron não processa o mesmo saque duas vezes

### Parte 9 — Qualidade operacional

**Objetivo:** deixar a API pronta para demonstração, suporte e manutenção.

**Entregáveis**
- Logging estruturado
- `AppExceptionHandler`
- Rate limiting
- `README.md`

**Checklist**
- Padronizar logs de criação, sucesso e falha
- Padronizar respostas de erro em JSON
- Adicionar proteção básica contra abuso no endpoint
- Documentar setup, execução e testes manuais

**Critério de pronto**
- Logs ajudam a rastrear um saque de ponta a ponta
- Erros têm formato previsível
- Outra pessoa consegue subir e testar o projeto só pelo README

### Parte 10 — Validação final

**Objetivo:** validar o fluxo completo do case de ponta a ponta.

**Checklist final**
- Subir tudo do zero com rebuild
- Testar saque imediato com sucesso
- Testar saque agendado com sucesso
- Testar conta inexistente
- Testar saldo insuficiente na criação
- Testar saldo insuficiente no processamento agendado
- Validar email no Mailhog
- Conferir registros no banco

**Critério de pronto**
- Todos os cenários principais do case funcionam sem ajustes manuais inesperados
- O projeto está apresentável para entrega técnica

### Sequência recomendada

Se quisermos atacar por blocos objetivos, a ordem mais segura é:

1. Parte 1 — Base do ambiente
2. Parte 2 — Modelo de dados
3. Parte 3 — Entrada HTTP do saque
4. Parte 4 — Núcleo do caso de uso
5. Parte 5 — Extensibilidade do método de saque
6. Parte 6 — Processamento assíncrono do saque imediato
7. Parte 7 — Email de notificação
8. Parte 8 — Processamento de saques agendados
9. Parte 9 — Qualidade operacional
10. Parte 10 — Validação final

---

## 17. README do Projeto

O `README.md` do projeto entregue deve conter no mínimo:

```
# PIX Withdraw — Conta Digital

## Requisitos
- Docker 24+
- Docker Compose v2+

## Como subir o projeto
docker compose up --build

## Como popular o saldo
UPDATE account SET balance = 1000.00 WHERE id = '<uuid>';

## Endpoints
POST http://localhost:9501/account/{accountId}/balance/withdraw

## Como testar os emails
Acesse http://localhost:8025 (Mailhog WebUI)

## Decisões arquiteturais
[...]

## Trade-offs conhecidos
[...]
```

---

## 18. Decisões Arquiteturais e Trade-offs

### Saque imediato via Job assíncrono
**Decisão:** O endpoint retorna `202 Accepted` imediatamente após registrar o saque e despachar o job, sem bloquear a resposta HTTP para executar a dedução.  
**Motivo:** Performance e experiência do cliente — a dedução (que envolve lock de linha no banco) ocorre em background.  
**Trade-off:** O cliente recebe `202` antes de o saldo ser efetivamente deduzido. Uma requisição de consulta de saldo imediatamente após pode mostrar o saldo ainda não deduzido.

### `SELECT FOR UPDATE` vs. otimistic locking
**Decisão:** Usamos `SELECT FOR UPDATE` (lock pessimista).  
**Motivo:** Operações financeiras têm baixa tolerância a conflitos. Lock pessimista garante consistência sem precisar de lógica de retry.  
**Trade-off:** Pode gerar contenção sob altíssima carga no mesmo `account_id`. Aceitável dado o contexto do case.

### bcmath para operações monetárias
**Decisão:** Toda aritmética de valores usa `bcmath` (`bccomp`, `bcsub`).  
**Motivo:** Evita imprecisão clássica do ponto flutuante: `0.1 + 0.2 !== 0.3` em PHP nativo.

### Cron via Hyperf Scheduler (não cron do SO)
**Decisão:** A tarefa agendada é registrada no próprio Hyperf com `#[Crontab]`.  
**Motivo:** Mantém tudo dentro do container, sem dependência de `crontab` do sistema operacional host.  
**Trade-off:** Requer que o container `app` esteja sempre ativo. Em ambientes com múltiplas réplicas, o `singleton: true` garante execução única.

### Mailhog como serviço de email
**Decisão:** Mailhog intercepta todos os emails SMTP enviados pelo Hyperf.  
**Motivo:** Permite testar o fluxo completo de envio de email sem configurar um servidor de email real. Nenhum email real é enviado.

### Sem autenticação no endpoint
**Decisão:** O endpoint não possui autenticação JWT/API Key.  
**Motivo:** O case não solicitou autenticação. O `{accountId}` na rota já funciona como identificador da operação.  
**Observação:** Em produção, autenticação seria obrigatória.

---

*Documento gerado como planejamento técnico do case Full Stack — Tecnofit.*

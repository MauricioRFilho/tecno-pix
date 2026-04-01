# Tecno Pix — Resumo para RH

![Tecno Pix Logo](assets/tecnopix-logo.svg)

Este documento explica, em linguagem simples, o que foi construído no case técnico.

![Favicon Tecno Pix](assets/tecnopix-favicon.svg)

## O que foi entregue

Foi desenvolvida uma solução de saque PIX com:
- API backend para receber pedidos de saque.
- Processamento de saques imediatos e agendados.
- Registro em banco para auditoria e rastreabilidade.
- Notificação por email após conclusão do saque.
- Dashboard visual para acompanhamento das operações.
- Swagger para documentação e teste técnico da API.

## Benefício para o negócio

- Agilidade: o ambiente sobe rapidamente com Docker.
- Confiabilidade: validações e logs operacionais em cada etapa.
- Rastreabilidade: operações ficam registradas com status e histórico.
- Comunicação entre áreas: equipe técnica usa Swagger e operação usa Dashboard.

## Como validar rapidamente (sem perfil técnico profundo)

1. Suba o projeto:

```bash
cp .env.example .env
docker compose up --build -d
```

2. Abra os links:
- Dashboard: `http://localhost:9501/dashboard`
- Swagger: `http://localhost:9500/swagger`
- Mailhog (emails de teste): `http://localhost:8025`

3. No Dashboard:
- Escolha uma conta.
- Solicite um saque PIX.
- Veja a operação aparecer no histórico.

## Decisões importantes (em termos simples)

- O sistema cria dados iniciais automaticamente na primeira execução para facilitar demonstração.
- O envio de email usa Mailhog em desenvolvimento para não depender de serviços externos.
- Há uma espera de inicialização do banco para evitar falha de subida da aplicação.

## Navegação

- Voltar para documentação técnica: [README.md](../README.md)

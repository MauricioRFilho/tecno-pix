# Tecno Pix

API REST para saque PIX imediato e agendado, construída com PHP 8.3 e Hyperf 3.

## Status

Base inicial do projeto criada com infraestrutura Docker e skeleton do Hyperf integrado ao repositório.

## Subindo o ambiente

```bash
cp .env.example .env
docker compose up --build
```

## Serviços

- API Hyperf: `http://localhost:9501`
- Swagger UI: `http://localhost:9500/swagger`
- MySQL: `localhost:3306`
- Mailhog: `http://localhost:8025`

## Planejamento

O detalhamento técnico e a decomposição das entregas estão em [PLANEJAMENTO.md](./PLANEJAMENTO.md).

## Próximo passo

Implementar a Parte 2 do planejamento: banco de dados, models e seed inicial.

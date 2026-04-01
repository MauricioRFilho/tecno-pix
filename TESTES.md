# Testes do Projeto

Este documento concentra os testes executáveis e os testes de estresse usados para validar o projeto.

## 1) Suíte de qualidade (PHPUnit)

### Rodar tudo

```bash
docker compose exec app composer test
```

### Rodar apenas etapas implementadas

```bash
docker compose exec app composer run test:implemented
```

### Rodar validação final (Parte 10)

```bash
docker compose exec app composer run test:part10
```

### Rodar testes adversariais (quebra/fuzz)

```bash
docker compose exec app composer run test:break
```

Cobertura da suíte `test:break`:
- Overflow numérico malicioso (`amount: "1e309"`)
- Fuzz de payload inválido (tipos inesperados)
- Burst de requisições para detectar 5xx

## 2) Testes fortes de carga

Os testes de stress foram executados de dentro do container `app` para evitar limitação de rede local do sandbox.

### Exemplo de abuso no mesmo account (foco em rate limit)

```bash
docker compose exec app php -r '
$url="http://127.0.0.1:9501/account/<ACCOUNT_ID>/balance/withdraw";
$payload=json_encode(["method"=>"pix","amount"=>1,"pix"=>["type"=>"email","key"=>"perf@example.com"]]);
$mh=curl_multi_init(); $chs=[]; $total=300;
for($i=0;$i<$total;$i++){ $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_POST=>1,CURLOPT_RETURNTRANSFER=>1,CURLOPT_HTTPHEADER=>["Content-Type: application/json"],CURLOPT_POSTFIELDS=>$payload,CURLOPT_TIMEOUT=>10]); curl_multi_add_handle($mh,$ch); $chs[]=$ch; }
do { $status=curl_multi_exec($mh,$active); if($active){curl_multi_select($mh,1.0);} } while($active && $status==CURLM_OK);
$codes=[]; foreach($chs as $ch){ $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $codes[$code]=($codes[$code]??0)+1; curl_multi_remove_handle($mh,$ch); curl_close($ch);} curl_multi_close($mh); ksort($codes); foreach($codes as $c=>$n){ echo $c,":",$n,PHP_EOL; }'
```

### Exemplo distribuído em múltiplas contas (foco em pool/concurrency)

Use o mesmo padrão de script acima, alternando `account_id` em lote para simular tráfego real.

## 3) Email e template

O template do email de saque fica em:
- `app/Mail/WithdrawNotificationMail.php`

Fluxo de validação:
1. Criar saque imediato (`POST /account/{id}/balance/withdraw`) com `pix.key` do tipo email.
2. Consultar Mailhog (`http://localhost:8025`) para verificar entrega.
3. Em caso de troubleshooting, confirmar em logs:
   - `withdraw.email_sent`
   - `withdraw.email_failed`

## 4) Tuning de estabilidade sob carga

Parâmetros relevantes no `.env`:
- `DB_POOL_MIN_CONNECTIONS`
- `DB_POOL_MAX_CONNECTIONS`
- `DB_POOL_WAIT_TIMEOUT`
- `WITHDRAW_PROCESS_MAX_CONCURRENCY`
- `WITHDRAW_RATE_LIMIT_MAX`
- `WITHDRAW_RATE_LIMIT_WINDOW_SECONDS`

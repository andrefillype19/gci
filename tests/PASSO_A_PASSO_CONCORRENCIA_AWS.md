# Teste de concorrencia no mesmo recurso na AWS (cenario 1: NFS + sticky)

Complementa `PASSO_A_PASSO.md` (nao o substitui). O `loadtest.js` usa sessao e arquivos proprios por usuario virtual; o
`k6/loadtest-concorrencia.js` faz os usuarios **disputarem o mesmo recurso**: o mesmo arquivo de sessao, o mesmo PDF e a
mesma linha do banco. Nada da aplicacao, dos overlays ou dos scripts existentes e alterado; os arquivos novos sao:

- `k6/loadtest-concorrencia.js`: o teste (5 min por execucao por padrao).
- `observability/grafana/dashboards/gci-concorrencia-aws.json`: dashboard novo, provisionado sozinho pelo Grafana.

Comandos a partir da pasta `tests` (`cd C:\Users\andre\Documents\gci\tests`). Variaveis: `$KEY` = caminho da
`key-gci.pem`, `$IP` = IP publico da EC2 da aplicacao.

```powershell
$KEY = "C:\Users\andre\Documents\AWS\key-gci.pem"
$IP  = "<IP_PUBLICO_DA_EC2>"
```

## 1. O que o script exercita

Tres blocos rodam ao mesmo tempo, por `DURATION` (5 min):

| Bloco | VUs | O que fazem | Recurso disputado |
|---|---|---|---|
| `sessao_compartilhada` | `SESSION_VUS` (10) | login uma vez; a cada ciclo, `SESSION_PARALLEL` (6) requisicoes **simultaneas** a `user/profile` com o **mesmo cookie** | arquivo de sessao no NFS (`/sessions`) |
| `arquivo_leitores` | `READERS` (20) | emitem o certificado (`atividade/certpal`) do **mesmo** subevento em laco | PDF `certificado-test-k6-compartilhado.pdf` |
| `arquivo_escritores` | `WRITERS` (4) | regravam o **mesmo** subevento (`subevento/update`) com upload do mesmo nome | o mesmo PDF e a mesma linha de `subevento` |

Contadores: `conc_session_ok` / `conc_session_fail{reason}`, `conc_file_read_ok` / `conc_file_read_fail{reason}`,
`conc_file_write_ok` / `conc_file_write_fail{reason}`; tempos `conc_session_batch_ms`, `conc_file_read_ms`,
`conc_file_write_ms`. Variaveis: `BASE_URL`, `DURATION`, `SESSION_VUS`, `SESSION_PARALLEL`, `SESSION_AFFINITY`
(`keep` | `drop`), `READERS`, `WRITERS`, `WRITE_PAUSE_MS` (100), `SMOKE=1`.

`SESSION_AFFINITY`:

- `keep` (padrao): o jar de cookies envia tambem o cookie de afinidade do Traefik (`GCI_STICKY`), como um navegador
  real; as requisicoes da mesma sessao vao ao **mesmo pod**.
- `drop`: so o `PHPSESSID` e enviado, sem `GCI_STICKY`. O Traefik distribui as requisicoes da mesma sessao entre os
  pods, e a disputa passa a ser entre **pods diferentes** sobre o mesmo arquivo no NFS (pior caso, artificial).

## 2. Pre-requisitos

Os mesmos da secao 1 do `PASSO_A_PASSO.md` (Docker, k6, OpenSSH, chave). Com a EC2 da aplicacao **running** e
o NFS (`gci-nfs-server`) e o banco ligados:

```powershell
.\scripts\sync-aws.ps1 -KeyPath $KEY -AppIp $IP
.\scripts\stack.ps1 up
.\scripts\stack.ps1 status
```

Grafana em <http://localhost:3000>. O dashboard novo **"GCI - Concorrencia no Mesmo Recurso (AWS)"** aparece em ate
30 s depois do `up` (o Grafana relê a pasta de dashboards sozinho; se nao aparecer, `.\scripts\stack.ps1 down` e `up`).

## 3. Preparar o cenario 1 com 3 replicas fixas

Para haver disputa **entre pods**, fixe 3 replicas (o HPA nao pode reduzi-las). O piso volta a 1 no final (secao 8).

Se outro overlay estiver aplicado, remova-o antes (veja a secao 3 do `PASSO_A_PASSO.md`). Depois:

```powershell
ssh -i $KEY ubuntu@$IP "cd /home/ubuntu/gci/deploy/overlays/nfs-sticky && sudo k3s kubectl kustomize . --load-restrictor LoadRestrictionsNone | sudo k3s kubectl apply -f - -n app && sudo k3s kubectl rollout status deployment/gci-app -n app --timeout=180s"
'{"spec":{"minReplicas":3}}' | ssh -i $KEY ubuntu@$IP "sudo k3s kubectl patch hpa gci-app -n app --type merge --patch-file /dev/stdin"
ssh -i $KEY ubuntu@$IP "sudo k3s kubectl scale deploy/gci-app -n app --replicas=3 && sudo k3s kubectl rollout status deployment/gci-app -n app --timeout=180s"
ssh -i $KEY ubuntu@$IP "sudo k3s kubectl get deploy,hpa,pods,ingress,pvc -n app -o wide"
```

O `patch` recebe o JSON pela entrada padrao (`--patch-file /dev/stdin`) para evitar problemas de aspas no PowerShell.

Confira: 3 pods `Running` e `Ready`, PVCs `Bound`, `HPA` com `MINPODS 3`, e o Service `gci-app` com a annotation
`GCI_STICKY` (`ssh -i $KEY ubuntu@$IP "sudo k3s kubectl get svc gci-app -n app -o yaml"`).

Zere os dados de teste:

```powershell
Get-Content -Raw scripts\reset-test-data.sh | ssh -i $KEY -o LogLevel=ERROR ubuntu@$IP "tr -d '\r\357\273\277' | bash -s"
```

O reset remove os registros `cert-k6-*` / `k6-loadtest-*`, os PDFs `certificado-test-k6-*` (inclusive o
`certificado-test-k6-compartilhado.pdf` deste teste) e as sessoes `sess_*`. Rode-o **antes e depois** de cada execucao.

## 4. Variaveis e funcao de execucao

```powershell
$env:BASE_URL = "http://$IP"
$env:K6_PROMETHEUS_RW_SERVER_URL = "http://127.0.0.1:9090/api/v1/write"
$env:K6_PROMETHEUS_RW_TREND_AS_NATIVE_HISTOGRAM = "true"
$env:K6_FEATURES = "native-histograms"
New-Item -ItemType Directory -Force results | Out-Null

function Invoke-K6Conc {
    param([string]$Cenario, [string]$RunId)
    k6 run -o experimental-prometheus-rw --tag scenario_name=$Cenario --tag run_id=$RunId `
        --summary-export "results\$RunId.json" k6/loadtest-concorrencia.js
}

function Reset-Gci {
    Get-Content -Raw scripts\reset-test-data.sh | ssh -i $KEY -o LogLevel=ERROR ubuntu@$IP "tr -d '\r\357\273\277' | bash -s"
}
```

## 5. Smoke (20 s)

```powershell
$env:SMOKE = "1"
Invoke-K6Conc -Cenario aws-conc-smoke -RunId aws-conc-smoke-01
Remove-Item Env:SMOKE
Reset-Gci
```

O `setup` imprime `recurso compartilhado: evento=... subevento=... atividade=...`. Pode haver 1 ou 2 falhas de leitura
(e a corrida procurada); `conc_file_write_fail` e `conc_session_fail` devem ser 0 ou quase. Se o smoke falhar no
`setup` (login ou `certpal`), nao siga: veja a secao 9.

## 6. As tres execucoes (5 min cada)

Rode **uma por vez**, nesta ordem, com reset entre elas. Anote o horario de inicio e fim (`Get-Date`) de cada uma.
Os `run_id` terminam em `-01`; para repetir, use `-02`, `-03` (recomendado: pelo menos 3 repeticoes de A e de B).

### A. Principal: sticky real (`keep`), leitores e escritores

```powershell
Remove-Item Env:SESSION_AFFINITY, Env:SESSION_PARALLEL, Env:WRITERS -ErrorAction SilentlyContinue
Get-Date; Invoke-K6Conc -Cenario aws-conc-nfs-sticky -RunId aws-conc-nfs-sticky-keep-01; Get-Date
Reset-Gci
```

### B. Pior caso: mesma sessao em pods diferentes (`drop`)

```powershell
$env:SESSION_AFFINITY = "drop"
Get-Date; Invoke-K6Conc -Cenario aws-conc-nfs-sticky -RunId aws-conc-nfs-sticky-drop-01; Get-Date
Remove-Item Env:SESSION_AFFINITY
Reset-Gci
```

### C. Controle: sem disputa (1 requisicao por sessao, sem escritores)

```powershell
$env:SESSION_PARALLEL = "1"
$env:WRITERS = "0"
Get-Date; Invoke-K6Conc -Cenario aws-conc-nfs-sticky -RunId aws-conc-nfs-sticky-controle-01; Get-Date
Remove-Item Env:SESSION_PARALLEL, Env:WRITERS
Reset-Gci
```

A execucao C e a referencia: sem disputa, as falhas devem ser 0 e a latencia, a base para comparar com A e B.
Antes de cada execucao, confira que continuam 3 pods `Ready` (`kubectl get pods`).

## 7. Ver no Grafana e ler os resultados

Abra o dashboard **"GCI - Concorrencia no Mesmo Recurso (AWS)"** e ajuste o periodo para cobrir as execucoes
(ou use `?from=<epoch_ms>&to=<epoch_ms>` na URL). Paineis: leitura OK x falhas por motivo, taxa de falha de leitura,
sessao OK x falhas, regravacao OK x falhas, latencia p95 e a tabela **"Resumo por execucao"**, que soma os contadores
por `run_id` dentro do periodo selecionado. As metricas de CPU dos pods, do NFS e do banco estao no dashboard
principal **"GCI - Validacao de Escalabilidade"**, no mesmo intervalo.

Consultas uteis (Prometheus em <http://localhost:9090>):

```promql
sum by (run_id) (max_over_time(k6_conc_file_read_ok_total[7d]))
sum by (run_id, reason) (max_over_time(k6_conc_file_read_fail_total[7d]))
sum by (run_id, reason) (max_over_time(k6_conc_session_fail_total[7d]))
```

| Pergunta | Onde olhar | Leitura |
|---|---|---|
| A sessao se perde sob requisicoes simultaneas? | `conc_session_fail` | 0 em A e B indica que o NFS sustentou a disputa |
| O bloqueio da sessao serializa as requisicoes? | p95 do lote de sessao em A e B contra C | lote bem mais lento que uma requisicao e esperado, nao e erro |
| Leitura concorrente com regravacao falha? | `conc_file_read_fail` (C deve ser 0) | `http_500` indica a janela entre apagar e gravar |
| Escritas concorrentes no mesmo arquivo falham? | `conc_file_write_fail` | 0 indica que o servidor sustenta as regravacoes |

Opcional: o resumo do k6 de cada execucao fica em `results\<run_id>.json`.

Para confirmar a causa das leituras com erro, procure os HTTP 500 de `atividade/certpal` no Loki (Explore do
Grafana) no intervalo da execucao e correlacione com os eventos da secao 9 do dashboard principal.

## 8. Restaurar o ambiente

```powershell
'{"spec":{"minReplicas":1}}' | ssh -i $KEY ubuntu@$IP "sudo k3s kubectl patch hpa gci-app -n app --type merge --patch-file /dev/stdin"
ssh -i $KEY ubuntu@$IP "sudo k3s kubectl scale deploy/gci-app -n app --replicas=1"
Reset-Gci
```

## 9. Diagnostico rapido

| Sintoma | O que verificar |
|---|---|
| `setup` falha (`login_failed` ou `certpal`) | App no ar? `BASE_URL` com o IP atual, sem barra final; o dataset de teste (usuario do `loadtest.js`) existe? |
| `session_lost` alto em A | Cookie `GCI_STICKY` presente? Service com a annotation? `session.save_path=/sessions` ativo e PVC montado? |
| `session_lost` ou `http_500` alto em B | Esperado ser maior que A; se for muito alto, veja o NFS (dashboard principal, secao 6) |
| Leituras sempre falhando em C | PDF base no NFS? Permissao `82:82`? Pod removido pelo HPA durante a execucao? |
| Menos de 3 pods | O piso do HPA nao foi alterado (secao 3); repita o `patch` e o `scale` |
| Metricas `k6_conc_*` ausentes | k6 escreve direto em `127.0.0.1:9090`; confirme `-o experimental-prometheus-rw` e o Prometheus `Ready` |
| Dashboard novo nao aparece | Aguarde 30 s; confira `observability/grafana/dashboards/gci-concorrencia-aws.json` e `stack.ps1 logs grafana` |

## 10. Observacoes e limitacoes

- O k6 roda no seu PC, fora da AWS, entao a latencia inclui a internet; compare A, B e C entre si, nao com o local.
- Os 3 pods rodam no **mesmo no** (k3s em uma `t3.small`), com um unico cliente NFS e seu cache. A coerencia de cache
  entre clientes de nos distintos nao e exercitada. Registre como limitacao.
- A `t3.small` e burstavel: 3 execucoes de 5 min seguidas podem consumir creditos de CPU (`CPUCreditBalance` no
  CloudWatch); espere a recuperacao entre execucoes se o saldo cair muito.
- Diferenca para o ambiente local: aqui o servidor NFS e o do *kernel* (NFSv4.1), na EC2 `gci-nfs-server`, e a afinidade
  usa o cookie `GCI_STICKY` do Traefik, em vez do `GCI_ROUTE` do ingress-nginx.
- O arquivo regravado tem sempre o mesmo conteudo; uma falha de leitura vem de a leitura coincidir com a regravacao,
  nao de conteudos diferentes.

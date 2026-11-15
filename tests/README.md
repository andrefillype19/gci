# Suite de testes do GCI na AWS

Valida se a aplicacao (Yii 1, PHP) mantem **sessao** e **arquivos enviados** quando roda em varios pods,
comparando quatro cenarios, e observa o comportamento do **HPA** (1 -> 3 -> 1 replicas):

| # | Cenario | Overlay (`deploy/overlays/`) |
|---|---|---|
| 1 | NFS + Sticky Session | `nfs-sticky` |
| 2 | NFS + Round Robin | `nfs-round-robin` |
| 3 | Sem NFS + Sticky Session | `no-nfs-sticky` |
| 4 | Sem NFS + Round Robin | `no-nfs-round-robin` |

O k6 e a observabilidade (Prometheus, Loki, Grafana, coletores) rodam **na sua maquina**. A AWS recebe o trafego do
k6 e uma conexao SSH (tunel) para leitura de metricas e logs. A troca de ambiente e o reset dos dados de teste
sao feitos por **comandos SSH** na EC2. Guia: **[PASSO_A_PASSO.md](PASSO_A_PASSO.md)**.

```
 k6 (host) ------------------ HTTP :80 ------------------> EC2 app (k3s)
   | remote write
   v
 Prometheus <-- scrape --+
 Loki <-- Alloy ---------+-- tunel SSH (via EC2 app) --> API do k3s, node_exporter, postgres_exporter
 Grafana (Prometheus + Loki)
```

## Estrutura

| Caminho | Funcao |
|---|---|
| `k6/loadtest.js` | Script k6 (fluxos de sessao e de upload/verificacao). Perfis `steady`, `hpa`, `smoke`. |
| `k6/certificado-test-k6.pdf` | PDF usado nos uploads. |
| `scripts/reset-test-data.sh` | **Unico script de teste**: reseta os dados gerados (banco, PDFs, sessoes). Roda na EC2 via SSH. |
| `scripts/sync-aws.ps1`, `scripts/stack.ps1`, `scripts/_common.ps1` | Apoio da stack local: gerar `.env`/kubeconfig e subir/parar o Docker Compose. |
| `observability/` | Docker Compose: tunel SSH, Prometheus, kube-state-metrics, Loki, Alloy, Grafana (dashboard provisionado). |
| `.local/` | Kubeconfig e certificados do cluster (**segredos**, nao versionado). |
| `results/` | Resumos exportados pelo k6 (nao versionado). |

## O que o teste avalia

O `k6/loadtest.js` roda dois fluxos em paralelo (por padrao 10 VUs de sessao e 5 de upload). Cada requisicao usa
`Connection: close`, entao cada uma abre uma conexao nova e, em round robin, pode cair em outro pod.

### Fluxo `session`: a sessao sobrevive a troca de pod?

1. Zera o cookie jar e acessa `user/profile` sem login: deve redirecionar para `user/login`.
2. Faz login (`GCI` / `admin`) e confirma que um cookie de sessao foi emitido.
3. Acessa `user/profile` de novo com o mesmo cookie.

Sucesso (`session_success`) = resposta `200` sem redirecionamento para o login. Falha (`session_loss`, com tag `reason`):
`login_failed`, `session_lost` (302 para o login: o pod que atendeu nao conhece a sessao), `http_500` ou `unexpected_status`.

### Fluxo `upload`: o arquivo enviado e visivel de qualquer pod?

1. Login e criacao de um **evento**, de um **subevento com upload do PDF** e de uma **atividade** ligada a ele.
   Falhas viram `upload_failure` (`login_failed`, `event_failed`, `upload_failed`) ou `file_verify_failure` (`activity_failed`).
   Se o upload completa, conta `upload_success`.
2. Abre uma **nova sessao** (novo login) e acessa `atividade/certpal&id=<atividade>&mode=1`, rota que le o PDF do disco.
   Sucesso (`file_verify_success`) = `200` com `Content-Type: application/pdf`. Falha (`file_verify_failure`):
   `session_lost`, `login_failed` ou `http_<status>` (o 500 indica arquivo ausente no pod que atendeu).

A verificacao em nova sessao e proposital: com sticky, o novo cookie pode ser atribuido a outro pod, que nao tem
o arquivo se o disco nao for compartilhado.

### O que se espera em cada cenario

| Cenario | Sessao | Arquivo |
|---|---|---|
| NFS + sticky / round robin | preservada | visivel em qualquer pod |
| Sem NFS + sticky | preservada enquanto o pod existir | pode faltar (verificacao em nova sessao cai em outro pod) |
| Sem NFS + round robin | perdas frequentes | falhas frequentes |

Perfil `hpa`: o mesmo trafego com subida, pico, queda e baixa carga prolongada, para acompanhar 1 -> 3 -> 1 replicas.
Sem NFS, remover um pod apaga as sessoes e os arquivos dele, mesmo com sticky.

### O que o teste NAO avalia (limites)

- **Nao ha thresholds**: o k6 termina com sucesso mesmo com falhas. O veredito vem dos contadores
  (`session_*`, `upload_*`, `file_verify_*`) e dos paineis do Grafana.
- Nao mede capacidade maxima: a carga e baixa (15 VUs) e serve para exercitar a troca de pod, nao para saturar.
- Latencia do NFS e do banco e **indireta** (aparece no tempo das requisicoes e nos paineis dos hosts).
- Nao testa corridas de escrita concorrente na mesma sessao nem upload de arquivos grandes (so um PDF de teste).
- O k6 roda no seu PC: a latencia inclui sua conexao de internet. Compare os cenarios entre si.

## Pendencias fora desta pasta (necessarias para os dados completos)

1. **Exporters nas EC2:** `node_exporter` (app, banco, NFS; com `--collector.nfs` e `--collector.nfsd`) e
   `postgres_exporter` + role `pg_monitor` no banco. Sem eles os alvos `node-*` e `postgres` ficam *down*.
2. **Overlays NFS em `deploy/`:** PV/PVC (hoje `nfs-pv.yaml` esta vazio), PVC de sessoes montado em `/tmp`,
   `initContainer` com `fsGroup: 82`, servidor NFS (ou EC2 dedicada) e `nfs-common` no no do k3s.
3. **Sticky no Traefik:** a anotacao de cookie costuma valer no *Service*, nao na Ingress (confirmar).
4. **HPA 1-3:** `maxReplicas: 3` e remover `replicas: 2` do Deployment. O dashboard assume requests/limit de
   CPU de `250m` por pod (alvo do HPA em 175m).
5. Restringir a porta 22 do `app_sg` ao seu IP (o tunel e o reset usam SSH).

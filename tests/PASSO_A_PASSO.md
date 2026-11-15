# Guia de execucao dos testes na AWS

Comandos a partir da pasta `tests` (`cd C:\Users\andre\Documents\gci\tests`).
Variaveis usadas abaixo: `$KEY` = caminho da `key-gci.pem`, `$IP` = IP publico da EC2 da aplicacao.

## 1. Pre-requisitos (uma vez)

| Item | Para que | Como verificar |
|---|---|---|
| Docker Desktop | stack de observabilidade | `docker version` |
| k6 | geracao de carga | `k6 version` |
| kubectl | acesso ao k3s pelo tunel (opcional) | `kubectl version --client` |
| OpenSSH (`ssh`, `scp`) | tunel, reset e troca de ambiente | `ssh -V` |
| Chave `key-gci.pem` | login na EC2 da aplicacao | arquivo no seu PC |

## 2. Preparar a observabilidade local

Com a EC2 da aplicacao **running** e o k3s instalado:

```powershell
.\scripts\sync-aws.ps1 -KeyPath C:\Users\andre\.ssh\key-gci.pem -AppIp <IP_PUBLICO_DA_EC2>
.\scripts\stack.ps1 up
.\scripts\stack.ps1 status
```

- `sync-aws.ps1` cria `observability/.env` e `.local/k3s/` (kubeconfig e certificados; **segredos**, ja no `.gitignore`).
- Rode `sync-aws.ps1` de novo sempre que o IP publico da EC2 mudar; depois `stack.ps1 up`.
- Grafana: <http://localhost:3000> (`admin` / `admin`, ou a senha do `.env`). Dashboard
  **"GCI - Validacao de Escalabilidade"** ja provisionado.
- Alvos `node-*` e `postgres` ficam *down* ate os exporters serem instalados nas EC2 (esperado).

## 3. Trocar de ambiente (via SSH)

O workflow de deploy copia `deploy/` para `/home/ubuntu/gci/deploy` na EC2. Os overlays existentes sao
`nfs-sticky`, `nfs-round-robin`, `no-nfs-sticky` e `no-nfs-round-robin`.

Aplicar um overlay:

```bash
ssh -i $KEY ubuntu@$IP "cd /home/ubuntu/gci/deploy/overlays/nfs-sticky && sudo k3s kubectl kustomize . --load-restrictor LoadRestrictionsNone | sudo k3s kubectl apply -f - -n app && sudo k3s kubectl rollout status deployment/gci-app -n app --timeout=180s"
```

Antes de trocar, remova o overlay atual para nao sobrar Ingress/PVC do cenario anterior:

```bash
ssh -i $KEY ubuntu@$IP "cd /home/ubuntu/gci/deploy/overlays/nfs-sticky && sudo k3s kubectl kustomize . --load-restrictor LoadRestrictionsNone | sudo k3s kubectl delete -f - -n app --ignore-not-found"
```

Conferir o estado:

```bash
ssh -i $KEY ubuntu@$IP "sudo k3s kubectl get deploy,hpa,pods,ingress,pvc -n app -o wide"
```

Piso do HPA durante o teste (2 replicas para exercitar a troca de pod; 1 para a dinamica do HPA):

```bash
ssh -i $KEY ubuntu@$IP "sudo k3s kubectl patch hpa gci-app -n app --type merge -p '{\"spec\":{\"minReplicas\":2}}'"
```

| Cenario | Overlay | Storage | Balanceamento |
|---|---|---|---|
| 1 | `nfs-sticky` | NFS (uploads + sessoes) | cookie de afinidade |
| 2 | `nfs-round-robin` | NFS (uploads + sessoes) | round robin |
| 3 | `no-nfs-sticky` | local ao pod | cookie de afinidade |
| 4 | `no-nfs-round-robin` | local ao pod | round robin |

## 4. Resetar os dados de teste (via SSH)

Um unico script, em bash, executado na EC2 ([scripts/reset-test-data.sh](scripts/reset-test-data.sh)):

```bash
ssh -i $KEY ubuntu@$IP 'bash -s' < scripts/reset-test-data.sh
ssh -i $KEY ubuntu@$IP 'bash -s -- --keep-sessions' < scripts/reset-test-data.sh
```

No PowerShell (nao aceita `<`):

```powershell
Get-Content -Raw scripts\reset-test-data.sh | ssh -i $KEY -o LogLevel=ERROR ubuntu@$IP "tr -d '\r\357\273\277' | bash -s"
```

Remove eventos, subeventos e atividades `k6-loadtest-*` / `cert-k6-*`, os PDFs `certificado-test-k6-*` e as sessoes
`sess_*` de cada pod. Nao remove o PDF base. O SQL roda em um pod temporario `postgres:11-alpine` no namespace `app`.
Faca o reset **antes e depois** de cada execucao.

## 5. Executar o k6

No seu PC, no mesmo terminal:

```powershell
$env:BASE_URL = "http://<IP_PUBLICO_DA_EC2>"
$env:K6_PROMETHEUS_RW_SERVER_URL = "http://127.0.0.1:9090/api/v1/write"
$env:K6_PROMETHEUS_RW_TREND_AS_NATIVE_HISTOGRAM = "true"
$env:K6_FEATURES = "native-histograms"

# smoke (uma iteracao de cada fluxo; todos os checks devem fechar em 100%)
$env:SMOKE = "1"; k6 run -o experimental-prometheus-rw --tag scenario_name=smoke k6/loadtest.js; Remove-Item Env:SMOKE

# bateria de comparacao (14 min)
k6 run -o experimental-prometheus-rw --tag scenario_name=nfs-sticky k6/loadtest.js

# dinamica do HPA
$env:PROFILE = "hpa"; $env:VUS_SESSION = "30"; $env:VUS_UPLOAD = "10"
k6 run -o experimental-prometheus-rw --tag scenario_name=nfs-round-robin-hpa k6/loadtest.js
```

| Variavel | Padrao | Efeito |
|---|---|---|
| `BASE_URL` | obrigatoria | IP publico da EC2 (sem barra final) |
| `PROFILE` | `steady` | `steady` (2m + 10m + 2m), `hpa` (subida, pico, queda, cooldown) ou `smoke` |
| `VUS_SESSION` / `VUS_UPLOAD` | 10 / 5 | VUs de cada fluxo |
| `HPA_RAMP_UP`, `HPA_PEAK`, `HPA_RAMP_DOWN`, `HPA_COOLDOWN`, `HPA_LOW_FRACTION` | 3m, 6m, 2m, 6m, 0.1 | duracoes do perfil `hpa` |

Opcional: `--summary-export results\<nome>.json` guarda o resumo do k6 (a pasta `results/` nao e versionada).

**Calibrar o perfil `hpa`:** os VUs padrao podem nao levar a CPU a 70% do request. Rode o perfil com mais VUs, olhe a
secao 5 do dashboard e ajuste ate chegar a 3 replicas no pico e voltar a 1 no cooldown. Use **os mesmos VUs nos 4
cenarios**. A `t3.small` e burstavel: carga alta e longa esgota creditos de CPU (veja `CPUCreditBalance` no CloudWatch).

Execute **um cenario por vez** e repita pelo menos 3 vezes cada um.

## 6. Onde ficam os dados

- **Prometheus** (90 dias), **Loki** e **Grafana**: volumes do Docker, preservados por `stack.ps1 down` e apagados so por `stack.ps1 wipe`.
- Metricas do k6 levam as tags `scenario_name` (e as que voce adicionar com `--tag`). Metricas de infraestrutura nao
  sao marcadas por cenario: filtre pelo intervalo de tempo do Grafana (anote horario de inicio e fim de cada execucao).

Backup do volume do Prometheus (com a stack parada):

```powershell
docker run --rm -v gci-observability_prometheus-data:/data -v ${PWD}\results:/backup alpine tar czf /backup/prometheus-data.tgz -C /data .
```

## 7. Como ler os resultados

Veja a secao "O que o teste avalia" no [README](README.md). No Grafana, ajuste o intervalo para a janela da execucao:

- **Sessoes:** `session_success` x `session_loss` (secao 2).
- **Arquivos:** `file_verify_success` x `file_verify_failure` (secao 2).
- **Latencia e erros HTTP:** secoes 1 e 3.
- **Equilibrio entre pods:** secao 4. **HPA:** secao 5. **NFS / PostgreSQL / hosts:** secoes 6 a 8.
- **Logs e eventos (escala do HPA, mounts):** secao 9.

## 8. Diagnostico rapido

| Sintoma | O que verificar |
|---|---|
| `stack.ps1 up` falha em `APP_IP` | Rode `sync-aws.ps1` para gerar o `.env`. |
| Tunel nao conecta | `stack.ps1 logs tunnel`: instancia desligada, IP mudou, porta 22 bloqueada ou chave errada. |
| `cadvisor` / `kube-state-metrics` *down* | Certificados desatualizados: `sync-aws.ps1` e `stack.ps1 up`. |
| Alvos `node-*` ou `postgres` *down* | Exporters ainda nao instalados nas EC2. |
| Grafana sem logs | `http://localhost:12345` (Alloy) mostra erros; confira o tunel e o kubeconfig. |
| k6: "Defina BASE_URL" | `$env:BASE_URL = 'http://<ip>'`. |
| Metricas do k6 ausentes no Grafana | k6 escreve direto em `127.0.0.1:9090` (sem tunel); confirme o Prometheus `Ready` e o `-o experimental-prometheus-rw`. |
| `session_loss` alto com NFS | `session.save_path=/sessions` ativo (ConfigMap do overlay NFS) e PVC de sessoes montado? Pod removido pelo HPA durante o teste? |
| `file_verify_failure` alto com NFS | PDF base no NFS? Permissao `82:82` nos diretorios exportados? |
| Reset falha ao ler o Secret | `sudo k3s kubectl get secret app-database-credentials -n app` na EC2. |

## Notas: NFS em EC2 propria e HPA

- O servidor NFS e a EC2 `gci-nfs-server` (`terraform/nfs.tf`, IP fixo `10.0.2.60`, NFSv4.1, exports `/uploads` e `/sessions`). Depois de criada, preencha `NFS_IP=10.0.2.60` em `observability/.env` e rode `scripts/stack.ps1 up` para o tunel abrir a porta 19102 (`node-nfs`).
- Nos overlays NFS o PHP grava sessoes em `/sessions` (ConfigMap `gci-php-overrides` sobrescrito) e os uploads em `protected/uploads`, ambos no NFS. Um initContainer copia os PDFs que vem na imagem para o volume NFS.
- O no do k3s precisa de `nfs-common` (o workflow 2 instala).
- Afinidade de sessao (sticky) usa annotations no Service (`GCI_STICKY`), nao no Ingress.
- O HPA (1 a 3 replicas, CPU 70% sobre request de 250m) esta na base e vale nos 4 cenarios. O Deployment nao fixa `replicas`; antes de cada execucao deixe em 1 replica (`kubectl scale deploy/gci-app -n app --replicas=1`) e aguarde o rollout.
- Padroes do k6 apos calibracao por extrapolacao: `VUS_SESSION=30`, `VUS_UPLOAD=15`, `HPA_PEAK=6m` (perfil `hpa`, cerca de 17 min).

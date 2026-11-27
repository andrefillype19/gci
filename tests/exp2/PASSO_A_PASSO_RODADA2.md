# Experimento 2, rodada 2: oito execuções, HPA livre, F1 sem congelar

Roteiro completo para reexecutar os testes. Complementa o [PASSO_A_PASSO_TESTES.md](PASSO_A_PASSO_TESTES.md) e o
[PASSO_A_PASSO_AWS.md](PASSO_A_PASSO_AWS.md) (provisionamento); aqui ficam todos os passos desta rodada.
Os scripts novos e os overlays `soft` **ainda não foram testados na AWS**: o que é estimativa está marcado.

## 1. Cenários, perfil de carga e quem faz o quê

| ID | Armazenamento | Roteamento | Overlay (`OV`) | Observação |
|---|---|---|---|---|
| C1 | NFS | Sticky | `nfs-sticky` | |
| C2 | NFS | Round Robin | `nfs-round-robin` | |
| C3 | sem NFS | Sticky | `no-nfs-sticky` | |
| C4 | sem NFS | Round Robin | `no-nfs-round-robin` | |
| C1-E | EFS | Sticky | `efs-sticky` | exige a preparação das pastas do EFS (seção 3) |
| C2-E | EFS | Round Robin | `efs-round-robin` | idem |
| F1-S | NFS | Sticky | `nfs-sticky-f1` | queda do NFS; montagem `soft` |
| F1-RR | NFS | Round Robin | `nfs-round-robin-f1` | queda do NFS; montagem `soft` |

- **Carga**: 10 VUs de sessão + 5 VUs de upload, em paralelo; 5 min de subida, 12 min de platô e 3 min de descida (20 min).
- **HPA livre**: 1 a 3 réplicas, alvo 70% de CPU; a execução começa com 1 réplica.
- **Depois da carga**: 5 min de coleta para ver o HPA reduzir as réplicas.
- **Uma execução por cenário.** Ordem sugerida (menos trocas de armazenamento): C1, C2, C3, C4, C1-E, C2-E, F1-S, F1-RR.
- **Tempo**: cerca de 35 a 40 min por execução (preparo, 20 min de carga, 5 min de coleta final, cópia); 8 execuções ≈ 5 h. O dead-man switch do gerador (12 h) cobre; renove-o a cada sessão.
- Os IDs **não** equivalem aos do experimento anterior com 8+3 VUs; as análises novas valem para estas VUs.

**Onde cada comando roda** (os blocos indicam o local):

| Local | O que roda lá |
|---|---|
| **Console AWS** (navegador) | ligar e desligar as instâncias; ver créditos no CloudWatch |
| **Seu PC** (PowerShell) | `ssh` ao gerador; `commit`/`push` dos arquivos novos; `scp` dos resultados |
| **Gerador** (`ubuntu@ip-10-0-1-245`, dentro do `tmux`) | **todos os demais comandos**: `kubectl`, `k6`, `aws`, coletor, falhas, resets |
| Instâncias `gci-app-server`, `gci-agent-1/2`, NFS e banco | nada manual; o gerador as comanda por `kubectl` e por SSM. Exceção: preparar o EFS (seção 3, em um nó) |

## 2. Preparação (uma vez)

**2.1. Seu PC**: os arquivos novos desta rodada precisam estar no repositório que o gerador baixa. Faça `commit` e `push` na branch
que o gerador usa (`deploy/cluster/traefik-accesslog.yaml`, `deploy/common/nfs-pv-soft.yaml`, `deploy/overlays/nfs-*-f1/`,
`tests/k6/loadtest.js`, `tests/exp2/collect.sh`, `fault.sh`, `fetch-results.ps1`, `scripts/check-time.sh` e este roteiro).
**Não** altere `app/` nem `deploy/` depois de começar: a imagem e os manifestos não podem mudar entre as execuções.

**2.2. Console AWS**: ligue as 6 instâncias (`gci-app-server`, `gci-agent-1`, `gci-agent-2`, NFS, banco, gerador). Os IPs privados persistem;
o IP público do gerador muda: copie-o do console. Aguarde ~3 min.

**2.3. Seu PC**: entre no gerador e abra o `tmux` (se cair a conexão, a carga continua):

```powershell
ssh -i "C:\caminho\para\key-gci.pem" ubuntu@<IP_PUBLICO_DO_GERADOR>
```

**2.4. Gerador**: ambiente e atualização do repositório:

```bash
tmux new -s exp2                    # voltar: tmux attach -t exp2   (sair sem fechar: Ctrl+b, depois d)
cat > ~/exp2.env <<'EOF'
export BASE_URL="http://10.0.1.254"
export K6_NO_USAGE_REPORT=true
export RES="$HOME/results"
export NFS_ID="$(aws ec2 describe-instances --region us-east-2 --filters Name=tag:Name,Values=gci-nfs-server --query 'Reservations[].Instances[].InstanceId' --output text)"
EOF
source ~/exp2.env
cd ~/gci && git pull && git branch --show-current && cd ~     # confirme a branch que contém os arquivos novos
mkdir -p $RES
echo "$(date -u +%FT%TZ) sessao iniciada" >> $RES/runs.log
sudo systemctl restart gci-deadman  # renova o dead-man switch (12 h)
kubectl get nodes                   # 3 nós Ready
echo $NFS_ID                        # deve imprimir i-...
```

**2.5. Gerador**: access log do Traefik. **Reinicia o Traefik** (alguns segundos fora do ar), então faça agora, sem carga:

```bash
kubectl apply -f ~/gci/deploy/cluster/traefik-accesslog.yaml
kubectl -n kube-system rollout status deploy/traefik --timeout=120s
curl -s -o /dev/null http://10.0.1.254/; kubectl -n kube-system logs deploy/traefik --tail=3
```

Esperado: linhas JSON com `ServiceAddr` (IP:porta do pod), `DownstreamStatus` e `Duration`. Se não aparecerem, o chart do k3s usa
outra chave de valores: mande a saída. O log é o que permite atribuir cada erro a um pod.

**2.6. Gerador**: relógios. Os horários de pods, Traefik, NFS e banco vêm de relógios diferentes do gerador; sem sincronia, o alinhamento das falhas com o k6 perde precisão.

```bash
bash ~/gci/tests/exp2/scripts/check-time.sh          # relatório (--fix configura o chrony com 169.254.169.123)
```

Script **não testado** e depende de SSH do gerador aos hosts; no banco e no NFS (sem chave) use o Session Manager do console e `chronyc tracking`.
Desvios acima de ~1 s: corrija antes de começar.

**2.7. Gerador**: smoke com o k6 e coletor (conferência rápida, sem contar como execução). Com um overlay qualquer já aplicado (seção 4, passos 1 e 2):

```bash
SMOKE=1 k6 run ~/gci/tests/k6/loadtest.js | tail -n 20
mkdir -p $RES/teste-coletor && (bash ~/gci/tests/exp2/collect.sh $RES/teste-coletor 5 &) ; sleep 30
touch $RES/teste-coletor/stop; sleep 8
head -n 15 $RES/teste-coletor/series.tsv; cut -f2,4 $RES/teste-coletor/series.tsv | sort | uniq -c; tail $RES/teste-coletor/collect.err
```

Esperado no `series.tsv`: fontes `kubelet`, `nodeexp`, `hpa`, `deploy` e `pods`. Se faltar alguma, mande `collect.err`.

## 3. EFS: preparar as pastas (uma vez, antes de C1-E)

O EFS novo é vazio e a raiz é do root. **Em um nó do cluster** (por exemplo `gci-agent-1`, por SSH ou SSM a partir do gerador):

```bash
sudo mkdir -p /mnt/efs && sudo mount -t nfs4 -o nfsvers=4.1,rsize=1048576,wsize=1048576,hard,timeo=600,retrans=2,noresvport 10.0.2.70:/ /mnt/efs
sudo mkdir -p /mnt/efs/uploads /mnt/efs/sessions && sudo chown 82:82 /mnt/efs/uploads /mnt/efs/sessions && sudo chmod 0777 /mnt/efs/uploads /mnt/efs/sessions
sudo umount /mnt/efs
```

Pré-requisito: o `terraform apply` do EFS já aplicado (`terraform output efs_id`). Nos testes C1-E e C2-E o `nfs-mount.txt` deve mostrar o IP `10.0.2.70`.

## 4. Procedimento de uma execução (tudo no gerador)

Repita os passos 1 a 6 para cada linha da tabela da seção 1. Antes da primeira execução da sessão, rode **uma execução descartável**
(qualquer cenário, `STEADY_PLATEAU=2m`, sem entrar nos resultados) para condicionar os `t3` e a rede.

**Variáveis da execução** (exemplo C2; troque `ID` e `OV` conforme a tabela):

```bash
source ~/exp2.env
export ID=C2 OV=nfs-round-robin REP=1 VUS_SESSION=10 VUS_UPLOAD=5
export STEADY_RAMP_UP=5m STEADY_PLATEAU=12m STEADY_RAMP_DOWN=3m
unset HTTP_TIMEOUT                     # só o F1 usa timeout (seção 5)
R=$RES/$ID-r$REP; mkdir -p $R
```

**Passo 1. Trocar de cenário** (remove qualquer overlay anterior, inclusive PV, PVC e Ingress, e aplica o novo):

```bash
cd ~/gci/deploy/overlays
for o in nfs-sticky nfs-round-robin no-nfs-sticky no-nfs-round-robin efs-sticky efs-round-robin nfs-sticky-f1 nfs-round-robin-f1; do (cd $o && kubectl kustomize . --load-restrictor LoadRestrictionsNone | kubectl delete -f - -n app --ignore-not-found); done
kubectl -n app wait --for=delete pod -l app.kubernetes.io/name=gci-app --timeout=180s
cd $OV && kubectl kustomize . --load-restrictor LoadRestrictionsNone | kubectl apply -f - -n app; cd ~
```

Avisos de `patchesStrategicMerge` deprecado são normais.

**Passo 2. HPA livre e espalhamento** (HPA com os valores do manifesto: 1 a 3, alvo 70%; começa com 1 réplica):

```bash
kubectl -n app patch deploy gci-app --type merge -p '{"spec":{"template":{"spec":{"topologySpreadConstraints":[{"maxSkew":1,"topologyKey":"kubernetes.io/hostname","whenUnsatisfiable":"DoNotSchedule","labelSelector":{"matchLabels":{"app.kubernetes.io/name":"gci-app"}}}]}}}}'
kubectl -n app scale deploy/gci-app --replicas=1
kubectl -n app rollout status deploy/gci-app --timeout=300s
kubectl -n app get pods -o wide; kubectl -n app get hpa gci-app
```

Confira: 1 pod `Running` e HPA com `MINPODS`=1, `MAXPODS`=3. Com o HPA livre, o número de pods muda durante a execução (esperado).

**Passo 3. Reset e registros de antes**:

```bash
bash ~/gci/tests/scripts/reset-test-data.sh
sleep 90                               # CPU em repouso antes da carga
kubectl -n app get pods -o wide > $R/pods-before.txt
kubectl -n app get hpa gci-app > $R/hpa-before.txt
kubectl -n app get pods -o jsonpath='{range .items[*]}{.metadata.name}{"  "}{.status.containerStatuses[0].imageID}{"\n"}{end}' > $R/image.txt
```

Em C1, C2, C1-E, C2-E, F1-S e F1-RR (com NFS ou EFS), registre também as opções de montagem:

```bash
kubectl -n app exec deploy/gci-app -c gci-app -- sh -c 'grep -E " /sessions | /app/trunk/protected/uploads " /proc/mounts' > $R/nfs-mount.txt
cat $R/nfs-mount.txt
```

**Passo 4. Coletor em segundo plano** (inicia **antes** da carga e só termina no passo 6):

```bash
nohup bash ~/gci/tests/exp2/collect.sh $R 5 >/dev/null 2>&1 &
sleep 20; tail -n 4 $R/series.tsv; tail -n 3 $R/collect.err
```

**Passo 5. Carga (20 min)**. `t0` marca a partida; os snapshots caem no início do platô (300 s) e no fim (1020 s):

```bash
date +%s > $R/t0
echo "$(date -u +%FT%TZ) inicio $ID r$REP" | tee -a $RES/runs.log
( sleep 300; bash ~/gci/tests/exp2/snapshot.sh $R inicio; sleep 720; bash ~/gci/tests/exp2/snapshot.sh $R fim ) &
k6 run --tag scenario_name=$ID --tag run=$REP --summary-export $R/summary.json --out json=$R/points.json.gz ~/gci/tests/k6/loadtest.js 2>&1 | tee $R/k6.txt
wait
echo "$(date -u +%FT%TZ) fim carga $ID r$REP" | tee -a $RES/runs.log
```

**Passo 6. Pós-carga, encerramento e reset**: aguarde ~5 min coletando (o HPA reduz réplicas só depois da estabilização), então:

```bash
sleep 300
touch $R/stop; sleep 8                 # encerra o coletor
kubectl -n app get pods -o wide > $R/pods-after.txt
kubectl -n app get hpa gci-app > $R/hpa-after.txt
kubectl -n app get events --sort-by=.lastTimestamp > $R/events.txt
bash ~/gci/tests/scripts/reset-test-data.sh
ls -lh $R
```

Esperado em `$R`: `t0`, `k6.txt`, `summary.json`, `points.json.gz`, `snap-inicio.txt`, `snap-fim.txt`, `series.tsv`, `traefik.log`, `podlogs/`, `applog/`,
`collect.err`, `pods-*.txt`, `hpa-*.txt`, `events.txt`, `image.txt` (e `nfs-mount.txt` com NFS/EFS). Confira no `k6.txt` os contadores
`session_success`, `upload_success` e `file_verify_success` (com NFS e EFS as falhas devem ser quase nulas; sem NFS, esperadas).

**Cópia para o PC** (ao fim de **cada** cenário, pois o disco do gerador é a única cópia). No **seu PC**, PowerShell, na pasta do repositório:

```powershell
.\tests\exp2\fetch-results.ps1 -Ip <IP_PUBLICO_DO_GERADOR> -Key "C:\caminho\para\key-gci.pem" -Run C2-r1
```

## 5. F1-S e F1-RR (queda do NFS)

Mesmo procedimento da seção 4, com estas diferenças:

| | F1-S | F1-RR |
|---|---|---|
| `ID` / `OV` | `F1-S` / `nfs-sticky-f1` | `F1-RR` / `nfs-round-robin-f1` |
| Montagem | `soft,timeo=10,retrans=3` | idem |
| k6 | `export HTTP_TIMEOUT=20s` antes do passo 5 | idem |

**5.1. Calibrar o `soft` (uma vez, antes do F1-S; ~2 min, sem carga)**: aplique o overlay F1 (passos 1 e 2) e confirme que a E/S falha em poucos
segundos em vez de congelar:

```bash
kubectl -n app exec deploy/gci-app -c gci-app -- grep -E " /sessions | /app/trunk/protected/uploads " /proc/mounts   # deve mostrar soft,timeo=10,retrans=3
aws ssm send-command --region us-east-2 --instance-ids $NFS_ID --document-name AWS-RunShellScript --parameters 'commands=["systemctl stop nfs-kernel-server"]' --query Command.CommandId --output text
sleep 5
time kubectl -n app exec deploy/gci-app -c gci-app -- sh -c 'echo x > /app/trunk/protected/uploads/.probe'
aws ssm send-command --region us-east-2 --instance-ids $NFS_ID --document-name AWS-RunShellScript --parameters 'commands=["systemctl start nfs-kernel-server","exportfs -ra"]' --query Command.CommandId --output text
```

O erro deve vir em ~10 s (estimativa pela `nfs(5)`: espera linear de `timeo`×(retrans+1)(retrans+2)/2 décimos de segundo; confirme na medição). Se passar de ~30 s, reduza `timeo` em
`deploy/common/nfs-pv-soft.yaml`, publique e refaça `git pull` no gerador. Depois confirme que o pod voltou a gravar (`kubectl -n app get pods`, sem reinício) e rode o reset.
Aviso da `nfs(5)`: `soft` pode perder dados escritos sem erro visível; isso é parte do que se observa.

**5.2. Execução**: passos 1 a 4 normais (com o overlay F1). No passo 5, defina `HTTP_TIMEOUT=20s` e, **em outra janela do tmux** (`Ctrl+b`, depois `c`), logo depois de iniciar o k6, rode a falha:

```bash
source ~/exp2.env; export ID=F1-S REP=1; R=$RES/$ID-r$REP     # F1-RR no outro cenário
bash ~/gci/tests/exp2/fault.sh $R       # NFS para em T0+540 s (4 min de platô) e volta 180 s depois
```

`fault.log` registra o `CommandId` e os horários de início e fim de cada comando do SSM. Depois do passo 6, confira `kubectl -n app get pods`
(pods sem reinício por causa da montagem) antes do reset. Para mudar os instantes: `FAULT_AT=... FAULT_FOR=... bash fault.sh $R`.

## 6. Fim de sessão

**Créditos das `t3`** (gerador; ajuste `-4 hours` à duração da sessão; o banco `m7i-flex` não é burstável):

```bash
for i in $(aws ec2 describe-instances --region us-east-2 --filters Name=tag:Project,Values=GCI Name=instance-state-name,Values=running --query 'Reservations[].Instances[].InstanceId' --output text); do echo "== $i"; for m in CPUCreditBalance CPUSurplusCreditBalance CPUSurplusCreditsCharged; do aws cloudwatch get-metric-statistics --region us-east-2 --namespace AWS/EC2 --metric-name $m --dimensions Name=InstanceId,Value=$i --start-time $(date -u -d '-6 hours' +%FT%TZ) --end-time $(date -u +%FT%TZ) --period 300 --statistics Maximum --query "sort_by(Datapoints,&Timestamp)[-1].[Timestamp,Maximum]" --output text; done; done
```

Os dados no CloudWatch têm resolução de 5 min. Depois copie tudo (`fetch-results.ps1` sem `-Run`) e **desligue as instâncias pelo console** (o dead-man switch para a pilha 12 h depois do boot do gerador).

## 7. Dados para a análise e limites

- **Falhas** (horário, fluxo, erro, pod): `points.json.gz` (status e erro por requisição, tag `flow`), `traefik.log` (`StartUTC`, `RequestPath`, `DownstreamStatus`, `Duration`, `ServiceAddr` = pod), `podlogs/` e `applog/` (causa no PHP; o `applog` de um pod que saiu vale até a última cópia, feita a cada ~30 s).
- **Séries** (`series.tsv`, relógio do gerador): CPU de pod = Δ`pod_cpu_s`/Δt; CPU de host = 1 − Δ`cpu_idle_s`/Δ`cpu_total_s`; HPA, réplicas e pods por tempo. Alinhe tudo pelo `t0`.
- Uma execução por cenário: sem variação entre repetições. Com o HPA livre, compare por séries, não por um valor único por pod.
- O NFS (`t3.micro`, base de 20% de CPU) pode ser gargalo com 15 VUs; vigie `cpu_idle_s` do host `nfs` no `series.tsv`.
- Não validado na AWS: `collect.sh` (filtros `jq`/`awk` testados só com texto de exemplo; `jq` não foi executado), `fault.sh`, `check-time.sh`, o HelmChartConfig do Traefik e o tempo real do `soft`.

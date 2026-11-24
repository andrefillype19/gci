# Experimento 2: execução manual dos testes

Tudo roda **no gerador** (`ubuntu@ip-10-0-1-245`), dentro do `tmux`, então o seu computador pode dormir ou
desligar. Só os resultados voltam para o seu computador no fim (`scp`). Pré-requisito: o provisionamento
([PASSO_A_PASSO_AWS.md](PASSO_A_PASSO_AWS.md)) validado: 3 nós `Ready`, gerador alcançando o cluster.

Cada execução leva cerca de **15 min** (8 min de carga + troca de cenário, resets e 90 s de repouso).
Não faça `push` em `app/` nem em `deploy/` durante o experimento: a imagem `andrefillype98/gci:latest` não pode mudar
entre repetições (o `imageID` de cada execução é registrado).

## 0. Uma vez, no gerador

Variáveis (cole como está; o `NFS_ID` é descoberto na hora):

```bash
cat > ~/exp2.env <<'EOF'
export BASE_URL="http://10.0.1.254"
export K6_NO_USAGE_REPORT=true
export RES="$HOME/results"
export NFS_ID="$(aws ec2 describe-instances --region us-east-2 --filters Name=tag:Name,Values=gci-nfs-server --query 'Reservations[].Instances[].InstanceId' --output text)"
EOF
```

Início de **cada sessão** de trabalho:

```bash
tmux new -s exp2                  # voltar depois: tmux attach -t exp2   (sair sem fechar: Ctrl+b, depois d)
source ~/exp2.env
cd ~/gci && git pull && cd ~
mkdir -p $RES
echo "$(date -u +%FT%TZ) sessao iniciada" >> $RES/runs.log
sudo systemctl restart gci-deadman   # renova o dead-man switch (12 h)
```

Pendências do provisionamento, se ainda não feitas: `sudo k3s token rotate` no server e o alerta de orçamento (guia da AWS, seção 6).

## 1. Procedimento padrão de uma execução

Defina as variáveis da execução (exemplo: C2, repetição 1, com 30+15 VUs):

```bash
export ID=C2 REP=1 OV=nfs-round-robin N=3 VUS_SESSION=30 VUS_UPLOAD=15
R=$RES/$ID-r$REP; mkdir -p $R
```

| ID | Overlay (`OV`) | Réplicas (`N`) | Posicionamento |
|---|---|---|---|
| C1 | `nfs-sticky` | 3 | 1 pod por nó |
| C2 | `nfs-round-robin` | 3 | 1 pod por nó |
| C3 | `no-nfs-sticky` | 3 | 1 pod por nó |
| C4 | `no-nfs-round-robin` | 3 | 1 pod por nó |
| E0 | `no-nfs-round-robin` | 1 | fixo em `gci-agent-1` |
| E1 | `nfs-round-robin` | 1 | fixo em `gci-agent-1` |

### P1. Trocar de cenário (pule se o overlay e o `N` forem os mesmos da execução anterior)

Remove qualquer overlay anterior (para não sobrar Ingress, PVC ou PV) e aplica o novo:

```bash
cd ~/gci/deploy/overlays
for o in nfs-sticky nfs-round-robin no-nfs-sticky no-nfs-round-robin; do (cd $o && kubectl kustomize . --load-restrictor LoadRestrictionsNone | kubectl delete -f - -n app --ignore-not-found); done
kubectl -n app wait --for=delete pod -l app.kubernetes.io/name=gci-app --timeout=180s
cd $OV && kubectl kustomize . --load-restrictor LoadRestrictionsNone | kubectl apply -f - -n app; cd ~
```

Avisos de `patchesStrategicMerge` deprecado são normais. Se o `kustomize` falhar, mande a saída.

### P2. Réplicas fixas e posicionamento

**Para N=3** (C1 a C4): primeiro o espalhamento, depois o HPA, depois a escala (um único rollout):

```bash
kubectl -n app patch deploy gci-app --type merge -p '{"spec":{"template":{"spec":{"topologySpreadConstraints":[{"maxSkew":1,"topologyKey":"kubernetes.io/hostname","whenUnsatisfiable":"DoNotSchedule","labelSelector":{"matchLabels":{"app.kubernetes.io/name":"gci-app"}}}]}}}}'
kubectl -n app patch hpa gci-app --type merge -p '{"spec":{"minReplicas":3,"maxReplicas":3}}'
kubectl -n app scale deploy/gci-app --replicas=3
```

**Para N=1** (E0 e E1): pod fixo em `gci-agent-1`, longe do Traefik e do plano de controle:

```bash
kubectl -n app patch deploy gci-app --type merge -p '{"spec":{"template":{"spec":{"nodeSelector":{"kubernetes.io/hostname":"gci-agent-1"}}}}}'
kubectl -n app patch hpa gci-app --type merge -p '{"spec":{"minReplicas":1,"maxReplicas":1}}'
kubectl -n app scale deploy/gci-app --replicas=1
```

Depois, nos dois casos:

```bash
kubectl -n app rollout status deploy/gci-app --timeout=300s
kubectl -n app get pods -o wide; kubectl -n app get hpa gci-app
```

Confira: N pods `Running`, **1 por nó** (N=3) ou só em `gci-agent-1` (N=1), e o HPA com `MINPODS` = `MAXPODS` = N.
Se o espalhamento falhar (2 pods no mesmo nó), pare e me avise.

### P3. Reset e registros de antes

```bash
bash ~/gci/tests/scripts/reset-test-data.sh
sleep 90       # CPU em repouso antes da carga
kubectl -n app get pods -o wide > $R/pods-before.txt
kubectl -n app get hpa gci-app > $R/hpa-before.txt
kubectl -n app get pods -o jsonpath='{range .items[*]}{.metadata.name}{"  "}{.status.containerStatuses[0].imageID}{"\n"}{end}' > $R/image.txt
```

Só nos cenários com NFS (C1, C2, E1), registre as opções de montagem:

```bash
kubectl -n app exec deploy/gci-app -c gci-app -- sh -c 'grep -E " /sessions | /app/trunk/protected/uploads " /proc/mounts' > $R/nfs-mount.txt
```

### P4. Carga (8 min)

```bash
export STEADY_RAMP_UP=2m STEADY_PLATEAU=5m STEADY_RAMP_DOWN=1m
echo "$(date -u +%FT%TZ) inicio $ID r$REP" | tee -a $RES/runs.log
( sleep 120; bash ~/gci/tests/exp2/snapshot.sh $R inicio; sleep 295; bash ~/gci/tests/exp2/snapshot.sh $R fim ) &
k6 run --tag scenario_name=$ID --tag run=$REP --summary-export $R/summary.json --out json=$R/points.json.gz ~/gci/tests/k6/loadtest.js 2>&1 | tee $R/k6.txt
wait
echo "$(date -u +%FT%TZ) fim $ID r$REP" | tee -a $RES/runs.log
```

O `snapshot.sh` roda em t=2 min (início do platô) e t=7 min (fim do platô) e grava os contadores de CPU, rede, ENA e NFS
dos 6 hosts e dos pods; a diferença entre os dois dá o consumo **só do platô**. O k6 ainda leva cerca de 30 s depois dos
8 min para encerrar os VUs.

### P5. Depois da execução

```bash
kubectl -n app get pods -o wide > $R/pods-after.txt
kubectl -n app get hpa gci-app > $R/hpa-after.txt
bash ~/gci/tests/scripts/reset-test-data.sh
ls -lh $R
```

Esperado em `$R`: `points.json.gz`, `summary.json`, `k6.txt`, `snap-inicio.txt`, `snap-fim.txt`, `pods-*.txt`, `hpa-*.txt`, `image.txt`
(e `nfs-mount.txt` com NFS). Confira rapidamente no `k6.txt` os contadores `session_success`, `upload_success` e
`file_verify_success` (com NFS, as falhas devem ser quase nulas; sem NFS, esperadas).

## 2. Antes do primeiro teste: smoke com o k6 v2.4.0

O Experimento 1 pode ter usado outra versão do k6. Valide as opções de saída antes de contar execuções:

```bash
export ID=smoke REP=1 OV=nfs-round-robin N=3
# P1, P2 e P3 acima para esse cenario; depois:
SMOKE=1 k6 run --tag scenario_name=smoke --summary-export $RES/smoke-summary.json --out json=$RES/smoke.json.gz ~/gci/tests/k6/loadtest.js
ls -lh $RES/smoke*; zcat $RES/smoke.json.gz | head -n 3
bash ~/gci/tests/scripts/reset-test-data.sh
```

Esperado: todos os checks em 100%, os dois arquivos criados e linhas JSON de métricas (`"type":"Metric"` e `"type":"Point"`).
Se qualquer flag falhar no k6 v2.4.0, mande a saída: ajusto o roteiro.

## 3. Calibração dos VUs (no C2, 3 execuções curtas)

O k6 dentro da VPC elimina cerca de 170 ms de latência por requisição e muda a taxa de requisições: não herde os 45 VUs
do Experimento 1. O alvo é **CPU média por pod perto de 60 a 70% do request (150 a 175 m)**.

Para cada nível, faça P1+P2 (uma vez, C2 com N=3) e depois P3 e esta carga curta (2 min de subida + 3 min de platô):

| Execução | `VUS_SESSION` | `VUS_UPLOAD` |
|---|---|---|
| cal-1 | 30 | 15 |
| cal-2 | 60 | 30 |
| cal-3 | 90 | 45 |

```bash
export ID=cal-1 REP=1 VUS_SESSION=30 VUS_UPLOAD=15 STEADY_RAMP_UP=2m STEADY_PLATEAU=3m STEADY_RAMP_DOWN=30s
R=$RES/$ID-r$REP; mkdir -p $R
k6 run --tag scenario_name=$ID --summary-export $R/summary.json ~/gci/tests/k6/loadtest.js 2>&1 | tee $R/k6.txt
```

Durante o platô (entre 2:30 e 5:00), em **outra janela do tmux** (`Ctrl+b`, depois `c`), rode duas vezes, com ~30 s de intervalo:

```bash
source ~/exp2.env; kubectl top pods -n app; top -bn1 | head -n 5
```

Escolha o **menor** nível cuja média de CPU por pod fique em 150 a 175 m e com o gerador abaixo de ~70% de CPU. Se nenhum chegar
a 150 m, use o maior e registre. Depois:

- **C1 a C4** usam esses VUs (`VUS_SESSION` e `VUS_UPLOAD`).
- **E0 e E1** usam **um terço** (arredonde: por exemplo 30+15 vira 10+5), para manter a carga por pod equivalente.
- Anote os VUs escolhidos e as leituras do `kubectl top` na planilha.

## 4. Início de cada sessão: execução descartável

Condiciona os `t3` e a rede (créditos, ENA). Rode **uma** execução com o cenário da primeira execução real da sessão e
`ID=descarte-<n>`, com `STEADY_PLATEAU=2m`. Não entra nos resultados.

## 5. Os blocos de testes

Ordem: **repetição por fora, teste por dentro** (intercalado), para que uma deriva do ambiente não se concentre em um cenário.

**Bloco 1: C1 a C4, 3 repetições** (12 execuções, cerca de 3 h):

`C1 C2 C3 C4 · C1 C2 C3 C4 · C1 C2 C3 C4` (REP=1, depois REP=2, depois REP=3).

Cada execução é P1 (a cada troca de overlay), P2, P3, P4 e P5.

**Bloco 2: E0 e E1, 3 repetições** (6 execuções, cerca de 1,5 h):

`E0 E1 · E0 E1 · E0 E1`, com N=1 e os VUs reduzidos (um terço).

**Bloco 3: F1, queda do NFS** (1 execução, cerca de 20 min). Cenário **C1** (`nfs-sticky`, N=3), mesmos VUs do C, `ID=F1 REP=1`.
Fazer P1, P2 e P3. Depois, **duas janelas do tmux**.

Janela 1 (a carga; o `t0` marca o instante de partida):

```bash
export STEADY_RAMP_UP=2m STEADY_PLATEAU=5m STEADY_RAMP_DOWN=1m
R=$RES/F1-r1; mkdir -p $R; date +%s > $R/t0
echo "$(date -u +%FT%TZ) inicio F1 r1" | tee -a $RES/runs.log
k6 run --tag scenario_name=F1 --tag run=1 --summary-export $R/summary.json --out json=$R/points.json.gz ~/gci/tests/k6/loadtest.js 2>&1 | tee $R/k6.txt
```

Janela 2 (a falha), rodada **logo após** iniciar a janela 1:

```bash
source ~/exp2.env; R=$RES/F1-r1
until [ -f $R/t0 ]; do sleep 1; done; T0=$(cat $R/t0)
wait_until() { local t=$(( T0 + $1 - $(date +%s) )); [ $t -gt 0 ] && sleep $t; }
( while true; do echo "$(date -u +%T) ready=$(kubectl -n app get deploy gci-app -o jsonpath='{.status.readyReplicas}')"; sleep 5; done ) > $R/ready.log &
READY_PID=$!
wait_until 180
echo "$(date -u +%FT%TZ) NFS parado" | tee -a $R/fault.log
aws ssm send-command --region us-east-2 --instance-ids $NFS_ID --document-name AWS-RunShellScript --parameters 'commands=["systemctl stop nfs-kernel-server"]' --query Command.CommandId --output text | tee -a $R/fault.log
wait_until 300
echo "$(date -u +%FT%TZ) NFS restaurado" | tee -a $R/fault.log
aws ssm send-command --region us-east-2 --instance-ids $NFS_ID --document-name AWS-RunShellScript --parameters 'commands=["systemctl start nfs-kernel-server","exportfs -ra"]' --query Command.CommandId --output text | tee -a $R/fault.log
wait_until 600
kill $READY_PID
```

A queda ocorre no minuto 3 (1 min de platô) e o NFS volta no minuto 5; o `ready.log` acompanha as réplicas prontas a cada 5 s por
10 min. Montagem `hard` (a atual): os pods travam em vez de falhar. Depois confirme que o NFS voltou
(`aws ssm get-command-invocation` com o `CommandId` do `fault.log`, `Status` = `Success`) e que os pods recuperaram
(`kubectl -n app get pods`). Só então rode o `reset-test-data.sh`. Registre erros por janela, pods travados e o tempo de recuperação
(a partir do `k6.txt`, do `points.json.gz` e do `ready.log`).

**Bloco 4: K-D, concorrência no mesmo recurso** (cerca de 1 h). Script `loadtest-concorrencia.js`, 5 min por configuração, **1 execução cada**.

Com NFS: C1 (`nfs-sticky`, N=3, P1 a P3). Sem NFS: E0 (`no-nfs-round-robin`, N=1, fixo em `gci-agent-1`; P1 a P3).
Para cada configuração: reset antes e depois, e use estas variáveis:

```bash
export DURATION=5m SESSION_VUS=10 READERS=20 WRITERS=4 SESSION_PARALLEL=6 WRITE_PAUSE_MS=100
kd() {   # kd <ID>   (use as variaveis de ambiente de cada configuracao na mesma linha)
  R=$RES/$1-r1; mkdir -p $R
  k6 run --tag scenario_name=$1 --tag run=1 --summary-export $R/summary.json --out json=$R/points.json.gz ~/gci/tests/k6/loadtest-concorrencia.js 2>&1 | tee $R/k6.txt
}
```

| Configuração | Cenário | Comando |
|---|---|---|
| **A**: sticky real | com NFS | `bash ~/gci/tests/scripts/reset-test-data.sh; kd KD-NFS-A; bash ~/gci/tests/scripts/reset-test-data.sh` |
| **B**: sem cookie de afinidade | com NFS | `SESSION_AFFINITY=drop kd KD-NFS-B` (com os resets antes e depois) |
| **C**: controle (sem disputa) | com NFS | `SESSION_PARALLEL=1 WRITERS=0 kd KD-NFS-C` (com os resets) |
| **A** | sem NFS | `kd KD-LOCAL-A` (com os resets) |
| **C** | sem NFS | `SESSION_PARALLEL=1 WRITERS=0 kd KD-LOCAL-C` (com os resets) |

Observação: a variável na frente de `kd` vale só para aquela chamada. Sem NFS há 1 pod: a afinidade é irrelevante, e o objetivo é ver se a falha
de leitura do PDF (regravação não atômica da aplicação) também ocorre sem NFS.

## 6. Fim de sessão

Créditos de CPU das `t3` (conferir se houve excedente; o banco `m7i-flex` não é burstável e não retorna dados):

```bash
for i in $(aws ec2 describe-instances --region us-east-2 --filters Name=tag:Project,Values=GCI Name=instance-state-name,Values=running --query 'Reservations[].Instances[].InstanceId' --output text); do echo "== $i"; for m in CPUSurplusCreditBalance CPUSurplusCreditsCharged; do aws cloudwatch get-metric-statistics --region us-east-2 --namespace AWS/EC2 --metric-name $m --dimensions Name=InstanceId,Value=$i --start-time $(date -u -d '-4 hours' +%FT%TZ) --end-time $(date -u +%FT%TZ) --period 300 --statistics Maximum --query "sort_by(Datapoints,&Timestamp)[-1].[Timestamp,Maximum]" --output text; done; done
```

Restaurar o HPA e baixar os resultados (no **seu computador**; pode ser PowerShell):

```bash
kubectl -n app patch hpa gci-app --type merge -p '{"spec":{"minReplicas":1,"maxReplicas":3}}'
```

```powershell
scp -i "C:\caminho\para\key-gci.pem" -r ubuntu@<IP_PUBLICO_DO_GERADOR>:~/results .
```

Depois, parar tudo pelo console (EC2 → selecionar as 6 instâncias → Estado da instância → **Parar**). O IP público do gerador muda
no próximo início (veja no console); o IP privado persiste. O dead-man switch para a pilha 12 h depois do boot do gerador.

## 7. Planilha de registro (preencher à mão)

Para cada execução: ID, repetição, VUs, hora de início e fim UTC (também no `~/results/runs.log`), observações
(erro do k6, pod reiniciado, HPA fora do esperado), `imageID`. Para a calibração, os VUs escolhidos e as leituras do `kubectl top`.

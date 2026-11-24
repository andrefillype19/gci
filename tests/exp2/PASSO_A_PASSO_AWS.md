# Experimento 2: montagem da AWS (3 nós k3s + gerador de carga na VPC)

Comandos **manuais**, em bash (Git Bash, WSL, Linux ou macOS), a partir da pasta `tests/exp2`.
Os passos marcados com **[CRIA]** ou **[ALTERA]** mudam a AWS; os demais são só leitura.
Região `us-east-2`. Não rode `terraform apply`: o estado é remoto e os recursos abaixo são manuais
(ver a seção 11 para a conversão futura).

Limite de tempo da montagem: **90 min**. Anote `date` agora. Se passar disso sem 3 nós `Ready`,
seguimos no nó único e registramos a limitação.

```bash
cd tests/exp2
date
export KEY="/caminho/para/key-gci.pem"
source scripts/lib.sh
```

## 0. Pré-requisitos e permissões

```bash
aws --version                       # AWS CLI v2
aws sts get-caller-identity         # confira a conta e o usuário (campo Arn)
aws iam list-attached-user-policies --user-name <USUARIO>   # esperado: AdministratorAccess
k6 version                          # use a MESMA versão do Experimento 1 abaixo
export K6_VERSION="v1.0.0"          # troque pela versão que você usou (formato vX.Y.Z)
```

Se o usuário **não** for administrador, pule a seção 4 e o F1 fica com um passo manual
(parar e religar o NFS), avisado na hora.

## 1. Descobrir IDs e IPs (somente leitura)

```bash
bash scripts/discover.sh
source .local/env.sh
```

Esperado agora: `SRV`, `DB` e `NFS` com ID e IPs; `AG1`, `AG2` e `GEN` vazios (ainda não existem).
O IP privado do server (`SRV_PRIV`) é descoberto a cada sessão.

## 2. Inspecionar o server (somente leitura)

```bash
sshx "$SRV_PUB" "k3s --version | head -n1; sudo k3s kubectl get nodes -o wide; df -h / | tail -n1; systemctl is-active prometheus-node-exporter"
aws ec2 describe-instance-credit-specifications --instance-ids "$SRV_ID" "$DB_ID" "$NFS_ID" \
  --query 'InstanceCreditSpecifications[].[InstanceId,CpuCredits]' --output text
K3S_VERSION="$(sshx "$SRV_PUB" "k3s --version | awk 'NR==1{print \$3}'")"
export KUBECTL_VERSION="${K3S_VERSION%%+*}"
echo "k3s=$K3S_VERSION  kubectl=$KUBECTL_VERSION"
```

`CpuCredits` = `unlimited` (padrão dos `t3`) significa que não há limitação por créditos, mas há
**cobrança** de créditos excedentes (US$ 0,05 por vCPU-hora). Registramos `CPUSurplusCreditBalance`.

## 3. [ALTERA] Regra do Security Group entre os membros

O `gci-app-sg` não permite tráfego entre seus próprios membros, o que bloqueia k3s (6443), kubelet
(10250), flannel (8472/UDP) e `node_exporter` (9100). Uma regra auto-referenciada resolve todos:

```bash
aws ec2 authorize-security-group-ingress --group-id "$SG_APP" --protocol -1 --source-group "$SG_APP"
```

(`InvalidPermission.Duplicate` significa que a regra já existe.) O `aws_security_group` do Terraform
usa blocos `ingress` inline, que são autoritativos: um `apply` futuro apagaria esta regra, então ela
deve entrar no `.tf` na conversão (seção 11).

## 4. [CRIA] IAM do gerador (permissões mínimas)

O gerador precisa de `ssm:SendCommand` somente no servidor NFS (F1), leitura de EC2 e CloudWatch
(`CPUSurplusCreditBalance`) e `ec2:StopInstances` por tag (para desligar a pilha ao fim).
O ID do NFS entra na política: se a EC2 do NFS for recriada, refaça `put-role-policy`.

```bash
sed -e "s/__ACCOUNT_ID__/$ACCOUNT_ID/g" -e "s/__NFS_ID__/$NFS_ID/g" -e "s/__REGION__/$AWS_DEFAULT_REGION/g" \
  iam/loadgen-policy.json > .local/loadgen-policy.json

aws iam create-role --role-name gci-loadgen-role --assume-role-policy-document file://iam/trust-policy.json
aws iam attach-role-policy --role-name gci-loadgen-role --policy-arn arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore
aws iam put-role-policy --role-name gci-loadgen-role --policy-name gci-loadgen-inline --policy-document file://.local/loadgen-policy.json
aws iam create-instance-profile --instance-profile-name gci-loadgen-profile
aws iam add-role-to-instance-profile --instance-profile-name gci-loadgen-profile --role-name gci-loadgen-role
sleep 15   # propagacao do instance profile
```

## 5. [CRIA] Agents e gerador

Todos `t3.small`, na mesma AZ e sub-rede pública do server, com o `gci-app-sg`. Agents usam o
profile SSM existente (`gci-ssm-instance-profile`); o gerador, o novo. Disco gp3: 20 GB nos agents,
30 GB no gerador (resultados brutos: reserve 2 a 3 GB livres).

```bash
sed -e "s/__K6_VERSION__/$K6_VERSION/" -e "s/__KUBECTL_VERSION__/$KUBECTL_VERSION/" \
  userdata/loadgen.sh > .local/loadgen-userdata.sh

launch() {   # launch <nome> <instance-profile> <user-data> <disco-GB> <servico>
  aws ec2 run-instances --image-id "$AMI_ID" --instance-type t3.small --count 1 \
    --subnet-id "$SUBNET_PUB" --security-group-ids "$SG_APP" --key-name key-gci \
    --iam-instance-profile "Name=$2" --user-data "file://$3" \
    --block-device-mappings "DeviceName=/dev/sda1,Ebs={VolumeSize=$4,VolumeType=gp3,Encrypted=true}" \
    --tag-specifications "ResourceType=instance,Tags=[{Key=Name,Value=$1},{Key=Project,Value=GCI},{Key=Service,Value=$5}]" \
    --query 'Instances[0].InstanceId' --output text
}

AG1_NEW="$(launch gci-agent-1 gci-ssm-instance-profile userdata/agent.sh 20 Application)"
AG2_NEW="$(launch gci-agent-2 gci-ssm-instance-profile userdata/agent.sh 20 Application)"
GEN_NEW="$(launch gci-loadgen gci-loadgen-profile .local/loadgen-userdata.sh 30 LoadGenerator)"
aws ec2 wait instance-status-ok --instance-ids "$AG1_NEW" "$AG2_NEW" "$GEN_NEW"
bash scripts/discover.sh && source .local/env.sh
for ip in "$AG1_PUB" "$AG2_PUB" "$GEN_PUB"; do sshx "$ip" "cloud-init status --wait"; done
```

## 6. Juntar os agents ao cluster

O token do k3s é lido do server e passado por pipe (não aparece na tela nem fica em arquivo local).
O IP usado em `K3S_URL` é o **privado** do server.

```bash
join() {   # join <ip-publico-do-agent> <nome-do-no>
  sshx "$SRV_PUB" "sudo cat /var/lib/rancher/k3s/server/node-token" | tr -d '\r' |
    sshx "$1" "read -r T; curl -sfL https://get.k3s.io | sudo INSTALL_K3S_VERSION='$K3S_VERSION' K3S_URL='https://$SRV_PRIV:6443' K3S_TOKEN=\"\$T\" K3S_NODE_NAME='$2' sh -"
}
join "$AG1_PUB" gci-agent-1
join "$AG2_PUB" gci-agent-2
sshx "$SRV_PUB" "sudo k3s kubectl get nodes -o wide -L kubernetes.io/hostname"
```

Esperado: 3 nós `Ready` (`gci-app-server`, `gci-agent-1`, `gci-agent-2`) e a coluna `HOSTNAME` igual ao nome
do nó (o E0 e o E1 fixam o pod com `kubernetes.io/hostname=gci-agent-1`).

## 7. Gerador: acesso ao cluster e ao Traefik

O kubeconfig vai do server para o gerador por pipe (a chave PEM **não** vai para o gerador).

```bash
sshx "$SRV_PUB" "sudo cat /etc/rancher/k3s/k3s.yaml" | tr -d '\r' |
  sed "s#https://127.0.0.1:6443#https://$SRV_PRIV:6443#" |
  sshx "$GEN_PUB" "mkdir -p ~/.kube && cat > ~/.kube/config && chmod 600 ~/.kube/config"

sshx "$GEN_PUB" "curl -sI --max-time 5 http://$SRV_PRIV | head -n1; kubectl get nodes -o wide; k6 version; aws --version; df -h / | tail -n1"
sshx "$GEN_PUB" "git clone https://github.com/andrefillype19/gci.git ~/gci"
```

Esperado: o `curl` devolve uma linha HTTP (404 do Traefik é normal sem aplicação); `kubectl` lista os
3 nós; `k6 version` é a versão fixada; disco com mais de 20 GB livres. Se o `kubectl` falhar com erro de
certificado (`x509`), rode no gerador: `kubectl config set-cluster default --tls-server-name=kubernetes`.

## 8. [ALTERA] `node_exporter` com ethtool (métricas da ENA) em todas as instâncias

Server, agents e gerador por SSH; banco e NFS (sem chave) por SSM.

```bash
for ip in "$SRV_PUB" "$AG1_PUB" "$AG2_PUB" "$GEN_PUB"; do sshx "$ip" "sudo bash -s" < scripts/ne-args.sh; done
ssm_wait_online "$DB_ID";  ssm_run "$DB_ID"  scripts/ne-args.sh
ssm_wait_online "$NFS_ID"; ssm_run "$NFS_ID" scripts/ne-args.sh
```

Validação, do gerador (alcança todos pelo IP privado):

```bash
sshx "$GEN_PUB" "for h in $SRV_PRIV $AG1_PRIV $AG2_PRIV $DB_PRIV $NFS_PRIV; do printf '%s ' \$h; curl -fsS --max-time 5 http://\$h:9100/metrics | grep -c '^node_ethtool_bw_in_allowance_exceeded'; done"
```

Cada linha deve terminar em `1` ou mais. Se aparecer `0` ou erro, a instância não expõe o coletor
(veja `ne-args.sh`) ou o SG bloqueia a 9100.

## 9. Validar rede, NFS, banco e espalhamento de pods

```bash
# NFS montável a partir de cada agent, e banco alcançável
for ip in "$AG1_PUB" "$AG2_PUB"; do
  sshx "$ip" "sudo mount -t nfs4 -o nfsvers=4.1 $NFS_PRIV:/uploads /mnt && ls /mnt | head -n3; sudo umount /mnt; timeout 3 bash -c '</dev/tcp/$DB_PRIV/5432' && echo db-ok"
done

# 3 pods de teste, 1 por nó (mesmo topologySpreadConstraints que o experimento usará)
sshx "$GEN_PUB" 'kubectl apply -f - <<EOF
apiVersion: apps/v1
kind: Deployment
metadata: {name: spread-test, namespace: default}
spec:
  replicas: 3
  selector: {matchLabels: {app: spread-test}}
  template:
    metadata: {labels: {app: spread-test}}
    spec:
      topologySpreadConstraints:
        - maxSkew: 1
          topologyKey: kubernetes.io/hostname
          whenUnsatisfiable: DoNotSchedule
          labelSelector: {matchLabels: {app: spread-test}}
      containers:
        - {name: pause, image: registry.k8s.io/pause:3.9}
EOF
kubectl rollout status deploy/spread-test --timeout=120s && kubectl get pods -l app=spread-test -o wide
kubectl delete deploy spread-test'
```

Esperado: 3 pods em 3 nós diferentes. A mesma validação com o NFS será refeita pelo `kubectl get pods -o wide`
no início de cada execução do experimento.

## 10. Dead-man switch, start e stop

```bash
DEADMAN_MIN=720 bash scripts/start.sh   # idempotente: se tudo ja esta ligado, so renova o desligamento
```

- `scripts/start.sh`: liga o que estiver parado (tag `Project=GCI`), espera os status checks, reexecuta o
  `discover.sh` e agenda `shutdown -h +720` em todas as instâncias (SSH nas quatro com IP público; SSM no banco e no NFS).
- `scripts/stop.sh`: para tudo com a tag `Project=GCI` (pede confirmação; `--yes` pula). **Baixe os resultados
  do gerador antes** (`scp -i $KEY -r ubuntu@$GEN_PUB:~/results .`).
- Os IPs privados persistem em stop/start; o **IP público muda** (use o resumo do `discover.sh`).
- Ao iniciar uma sessão, o gerador deve rodar dentro de `tmux`: `sshx "$GEN_PUB"` e depois `tmux new -s exp2`.

Alerta de orçamento recomendado (US$ 20), com o seu e-mail no lugar do marcador:

```bash
cat > .local/budget.json <<'EOF'
{"BudgetName":"gci-exp2","BudgetLimit":{"Amount":"20","Unit":"USD"},"TimeUnit":"MONTHLY","BudgetType":"COST"}
EOF
cat > .local/budget-notif.json <<'EOF'
[{"Notification":{"NotificationType":"ACTUAL","ComparisonOperator":"GREATER_THAN","Threshold":80,"ThresholdType":"PERCENTAGE"},
  "Subscribers":[{"SubscriptionType":"EMAIL","Address":"<SEU_EMAIL>"}]}]
EOF
aws budgets create-budget --account-id "$ACCOUNT_ID" --budget file://.local/budget.json --notifications-with-subscribers file://.local/budget-notif.json
```

## 11. O que transformar em Terraform depois (por pull request)

1. `aws_vpc_security_group_ingress_rule` (ou bloco inline) auto-referenciado no `gci-app-sg` (seção 3).
2. `aws_iam_role` + `aws_iam_role_policy` + `aws_iam_instance_profile` do gerador (seção 4).
3. Dois `aws_instance` `gci-agent-{1,2}` com `user_data` de `userdata/agent.sh` e a entrada no cluster
   (`INSTALL_K3S_VERSION`, `K3S_URL` com o IP privado do server e `K3S_TOKEN` via Secrets Manager ou SSM Parameter, **nunca** no `user_data`).
4. Um `aws_instance` `gci-loadgen` com `userdata/loadgen.sh`.
5. IP privado fixo para o server (`private_ip`), como já existe para o banco e o NFS.
6. `node_exporter` com `--collector.ethtool` no `user_data` do banco, do NFS e do server.
7. Tags `Project=GCI` em todos (usadas por `start.sh` e `stop.sh`).

## Se desistir ou precisar refazer

Os comandos abaixo **apagam** recursos; revise os IDs antes.

```bash
aws ec2 terminate-instances --instance-ids "$AG1_ID" "$AG2_ID" "$GEN_ID"
aws ec2 revoke-security-group-ingress --group-id "$SG_APP" --protocol -1 --source-group "$SG_APP"
aws iam remove-role-from-instance-profile --instance-profile-name gci-loadgen-profile --role-name gci-loadgen-role
aws iam delete-instance-profile --instance-profile-name gci-loadgen-profile
aws iam delete-role-policy --role-name gci-loadgen-role --policy-name gci-loadgen-inline
aws iam detach-role-policy --role-name gci-loadgen-role --policy-arn arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore
aws iam delete-role --role-name gci-loadgen-role
```

Para tirar os agents do cluster sem apagar a instância: `kubectl delete node gci-agent-1 gci-agent-2` no server
(e `sudo /usr/local/bin/k3s-agent-uninstall.sh` em cada agent).

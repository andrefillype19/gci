# Experimento 2: provisionamento da AWS (3 nós k3s + gerador de carga na VPC)

Os recursos novos (2 agents, o gerador, o IAM do gerador e uma regra do Security Group) são criados pelo
**Terraform**, por pull request: o PR mostra o `plan`, e o merge no `main` aplica sozinho (`-auto-approve`).
Nada de AWS CLI no seu computador. O que sobra de manual é pequeno: juntar os agents ao cluster, configurar o
`node_exporter` nas instâncias antigas e preparar o acesso do gerador ao cluster.

Os comandos abaixo rodam **dentro das instâncias Linux** (por SSH ou Session Manager), então o terminal do seu
computador (PowerShell, Git Bash ou outro) tanto faz.

Limite de tempo: **90 min** a partir do merge. Se passar disso sem 3 nós `Ready`, seguimos no nó único e
registramos a limitação.

Variáveis usadas abaixo: `$KEY` = caminho da `key-gci.pem`; `SRV_PUB` e `SRV_PRIV` = IPs do server;
`AG1_PUB`, `AG2_PUB`, `GEN_PUB` = IPs públicos dos agents e do gerador; `NFS_PRIV`, `DB_PRIV` = IPs privados do NFS e do banco.

## 1. Revisar o plan e dar o merge

No PR, abra a execução **Infrastructure CI/CD (Terraform)** → passo **Terraform Plan**. O esperado é:

```
Plan: 7 to add, 1 to change, 0 to destroy.
```

- 7 a criar: `aws_instance.k3s_agent` ×2, `aws_instance.loadgen`, `aws_iam_role.loadgen_role`,
  `aws_iam_role_policy_attachment.loadgen_ssm`, `aws_iam_role_policy.loadgen_inline` e `aws_iam_instance_profile.loadgen_profile`.
- 1 a alterar (in-place): `aws_security_group.app_sg`, com a regra entre membros do próprio SG
  (libera k3s 6443, kubelet 10250, flannel 8472/UDP e `node_exporter` 9100).
- **0 a destruir.** Se aparecer `must be replaced` ou `destroy` em `postgres_db`, `nfs_server` ou `app_server`,
  **não dê o merge** (perda do banco, do NFS ou do cluster) e me avise.

Depois do merge, o passo **Terraform Apply** termina com os `Outputs`: copie `agents_public_ips`, `loadgen_public_ip`,
`app_private_ip` e `agents_private_ips`. Os IPs também aparecem no console (EC2 → Instâncias). Aguarde ~3 min
para o `cloud-init` terminar nas 3 instâncias.

Confirme a versão do k6 (a do Experimento 1): `terraform/experiment2.tf`, variável `k6_version` (padrão `v1.0.0`).
Se a sua for outra, ajuste **antes** do merge: depois o `user_data` é ignorado de propósito, e a troca seria manual no gerador.

## 2. Juntar os agents ao cluster

No **server** (`ssh -i $KEY ubuntu@SRV_PUB`):

```bash
k3s --version | head -n1                                  # anote a versao, ex.: v1.31.4+k3s1
hostname -I                                               # IP privado (SRV_PRIV)
sudo cat /var/lib/rancher/k3s/server/node-token           # copie o token
```

Em **cada agent** (`ssh -i $KEY ubuntu@AG1_PUB`, depois `AG2_PUB`), trocando `<VERSAO>`, `<SRV_PRIV>`, `<TOKEN>` e o nome do nó
(`gci-agent-1` e `gci-agent-2`). O token não deve ficar no histórico do shell:

```bash
set +o history
curl -sfL https://get.k3s.io | sudo INSTALL_K3S_VERSION='<VERSAO>' K3S_URL='https://<SRV_PRIV>:6443' K3S_TOKEN='<TOKEN>' K3S_NODE_NAME='gci-agent-1' sh -
set -o history
```

Validação, no server:

```bash
sudo k3s kubectl get nodes -o wide -L kubernetes.io/hostname
```

Esperado: 3 nós `Ready` (`gci-app-server`, `gci-agent-1`, `gci-agent-2`) e a coluna `HOSTNAME` igual ao nome do nó
(o E0 e o E1 fixam o pod com `kubernetes.io/hostname=gci-agent-1`).

## 3. `node_exporter` com ethtool nas instâncias antigas

Os agents e o gerador já nascem configurados. No **server**, no **banco** e no **NFS** (estes dois sem chave: console AWS →
EC2 → instância → Conectar → **Session Manager**), rode como root o conteúdo de [scripts/ne-args.sh](scripts/ne-args.sh).
No server, por SSH: `sudo bash` e cole o script. Resumo do que ele faz:

```bash
sudo tee /etc/default/prometheus-node-exporter >/dev/null <<'EOF'
ARGS='--web.listen-address=:9100 --collector.ethtool --collector.nfs --collector.nfsd'
EOF
sudo systemctl restart prometheus-node-exporter
curl -s localhost:9100/metrics | grep -E '^node_ethtool_(bw_in|bw_out|pps)_allowance_exceeded'
```

Esperado: 3 linhas com as métricas da ENA. Sem elas o controle de validade (limite de banda e de pacotes das `t3`) fica sem dado.

## 4. Gerador: acesso ao cluster, rede e IAM

`ssh -i $KEY ubuntu@GEN_PUB`, depois:

```bash
cloud-init status --wait
k6 version; kubectl version --client; aws --version; df -h / | tail -n1
systemctl is-active gci-deadman           # esperado: active (dead-man switch em contagem)
aws sts get-caller-identity               # esperado: assumed-role/gci-loadgen-role
```

**Kubeconfig.** No server, `sudo cat /etc/rancher/k3s/k3s.yaml` e copie. No gerador, crie `~/.kube/config` com esse conteúdo
(`mkdir -p ~/.kube && nano ~/.kube/config`), troque `https://127.0.0.1:6443` por `https://<SRV_PRIV>:6443` e proteja:

```bash
chmod 600 ~/.kube/config
kubectl get nodes -o wide                 # esperado: 3 nos Ready
```

Se der erro de certificado (`x509`): `kubectl config set-cluster default --tls-server-name=kubernetes`.
O kubeconfig é credencial de administrador do cluster: fica só no gerador, nunca no repositório.

**Rede e código:**

```bash
curl -sI --max-time 5 http://<SRV_PRIV> | head -n1       # qualquer linha HTTP (404 do Traefik e normal sem a aplicacao)
git clone https://github.com/andrefillype19/gci.git ~/gci
for h in <SRV_PRIV> <AG1_PRIV> <AG2_PRIV> <DB_PRIV> <NFS_PRIV>; do
  printf '%s ' $h; curl -fsS --max-time 5 http://$h:9100/metrics | grep -c '^node_ethtool_bw_in_allowance_exceeded'
done                                                       # cada linha deve terminar em 1
```

**Permissão do F1** (executa um comando inofensivo no NFS por SSM):

```bash
aws ssm send-command --region us-east-2 --instance-ids <NFS_ID> --document-name AWS-RunShellScript \
  --parameters 'commands=["echo ok"]' --query Command.CommandId --output text
```

Se responder um `CommandId`, o IAM do gerador funciona. Se der `AccessDenied` por SCP da organização, o F1 fica com o passo
manual (você me avisa na hora, e eu paro e religo o NFS).

## 5. Validar rede, NFS, banco e espalhamento de pods

Em **cada agent**:

```bash
sudo mount -t nfs4 -o nfsvers=4.1 <NFS_PRIV>:/uploads /mnt && ls /mnt | head -n3; sudo umount /mnt
timeout 3 bash -c '</dev/tcp/<DB_PRIV>/5432' && echo db-ok
```

No **gerador**, 3 pods de teste, 1 por nó (o mesmo `topologySpreadConstraints` que o experimento usará):

```bash
kubectl apply -f - <<'EOF'
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
kubectl delete deploy spread-test
```

Esperado: 3 pods em 3 nós diferentes.

## 6. Ligar, desligar e custo

- **Parar** (stop, não terminate): console EC2 → selecione as 6 instâncias (`gci-app-server`, `gci-postgres-db`,
  `gci-nfs-server`, `gci-agent-1`, `gci-agent-2`, `gci-loadgen`) → Estado da instância → **Parar**. Antes, baixe os resultados
  do gerador: `scp -i $KEY -r ubuntu@GEN_PUB:~/results .`. O disco persiste no stop.
- **Ligar:** Estado da instância → **Iniciar**. O IP privado persiste; o **IP público muda** (veja no console). Os agents
  reentram no cluster sozinhos.
- **Dead-man switch:** o gerador para **todas** as instâncias com a tag `Project=GCI` 720 min depois de cada boot dele.
  Renovar: `sudo systemctl restart gci-deadman`. Cancelar: `sudo systemctl stop gci-deadman`.
  Se o gerador estiver parado, não há dead-man: lembre de parar a pilha ao fim.
- **Custo:** cerca de US$ 0,21/h das instâncias, mais cerca de US$ 0,02/h de IPv4 público (4 IPs) e eventuais créditos
  excedentes de CPU das `t3` (US$ 0,05 por vCPU-hora).
- **Alerta de orçamento (recomendado):** console → Billing → Budgets → Criar orçamento → "Orçamento de custo", US$ 20/mês,
  alerta em 80% para o seu e-mail.

## 7. Desfazer

Reverta o PR (`git revert` do merge e novo PR): o Terraform destrói os 7 recursos novos e remove a regra do SG. Para tirar
só os agents do cluster, sem apagar a instância: `sudo k3s kubectl delete node gci-agent-1 gci-agent-2` no server e
`sudo /usr/local/bin/k3s-agent-uninstall.sh` em cada agent.

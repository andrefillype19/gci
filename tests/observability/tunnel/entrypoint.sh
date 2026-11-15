#!/bin/sh
# Tunel SSH unico via EC2 da aplicacao (bastion) para:
#   6443  -> API do k3s (na propria EC2 da aplicacao)
#   19100 -> node_exporter da EC2 da aplicacao
#   19101 -> node_exporter do banco        (DB_IP:9100)
#   19187 -> postgres_exporter do banco    (DB_IP:9187)
#   19102 -> node_exporter do NFS          (NFS_IP:9100, opcional)
# Reconecta sozinho se a conexao cair.
set -u

# O ssh exige permissao 600 na chave; o arquivo montado do Windows nao a tem.
mkdir -p /root/.ssh
cp /ssh/key /root/.ssh/id
chmod 600 /root/.ssh/id

FORWARDS="-L 0.0.0.0:6443:127.0.0.1:6443 -L 0.0.0.0:19100:127.0.0.1:9100"
FORWARDS="$FORWARDS -L 0.0.0.0:19101:${DB_IP}:9100 -L 0.0.0.0:19187:${DB_IP}:9187"
if [ -n "${NFS_IP:-}" ]; then
  FORWARDS="$FORWARDS -L 0.0.0.0:19102:${NFS_IP}:9100"
fi

while true; do
  echo "[tunnel] conectando em ${SSH_USER}@${APP_IP} ..."
  # shellcheck disable=SC2086
  ssh -N -i /root/.ssh/id \
    -o StrictHostKeyChecking=no \
    -o UserKnownHostsFile=/dev/null \
    -o ServerAliveInterval=15 \
    -o ServerAliveCountMax=3 \
    -o ExitOnForwardFailure=yes \
    $FORWARDS \
    "${SSH_USER}@${APP_IP}"
  echo "[tunnel] conexao encerrada (codigo $?). Nova tentativa em 5s."
  sleep 5
done

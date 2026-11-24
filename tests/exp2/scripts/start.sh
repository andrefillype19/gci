#!/usr/bin/env bash
# Liga a pilha do Experimento 2 (instancias com tag Project=GCI), reexecuta o discover e agenda o
# dead-man switch (desligamento automatico do sistema operacional em DEADMAN_MIN minutos, padrao 720).
# Idempotente: se tudo ja estiver ligado, so renova o dead-man switch.
#
# Uso:  KEY=/caminho/key-gci.pem bash scripts/start.sh
# Depois: se o IP publico do server mudou, os IPs novos aparecem no resumo do discover.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
: "${KEY:?Defina KEY=caminho da key-gci.pem}"
DEADMAN_MIN="${DEADMAN_MIN:-720}"

stopped="$(aws ec2 describe-instances --filters Name=tag:Project,Values=GCI Name=instance-state-name,Values=stopped \
  --query 'Reservations[].Instances[].InstanceId' --output text)"
if [ -n "$stopped" ]; then
  echo "==> Ligando: $stopped"
  # shellcheck disable=SC2086
  aws ec2 start-instances --instance-ids $stopped >/dev/null
fi

all="$(aws ec2 describe-instances --filters Name=tag:Project,Values=GCI Name=instance-state-name,Values=pending,running \
  --query 'Reservations[].Instances[].InstanceId' --output text)"
echo "==> Aguardando running e status checks"
# shellcheck disable=SC2086
aws ec2 wait instance-running --instance-ids $all
# shellcheck disable=SC2086
aws ec2 wait instance-status-ok --instance-ids $all

bash "$(dirname "${BASH_SOURCE[0]}")/discover.sh"
load_env

echo "==> Dead-man switch: desligamento em $DEADMAN_MIN min"
for ip in "$SRV_PUB" "$AG1_PUB" "$AG2_PUB" "$GEN_PUB"; do
  [ -n "$ip" ] || continue
  sshx "$ip" "sudo shutdown -c 2>/dev/null || true; sudo shutdown -h +$DEADMAN_MIN" 2>&1 | tail -n1 || true
done
tmp="$(mktemp)"
printf 'shutdown -c 2>/dev/null || true\nshutdown -h +%s\n' "$DEADMAN_MIN" >"$tmp"
for id in "$DB_ID" "$NFS_ID"; do
  [ -n "$id" ] || continue
  ssm_wait_online "$id"
  ssm_run "$id" "$tmp" >/dev/null && echo "dead-man agendado em $id"
done
rm -f "$tmp"

echo
echo "Pilha pronta. Lembre: o IP publico do server pode ter mudado (veja a tabela acima)."
echo "Parar tudo ao final: bash scripts/stop.sh   (baixe os resultados do gerador antes)"

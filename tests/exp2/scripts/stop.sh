#!/usr/bin/env bash
# Para (stop, nao terminate) todas as instancias com tag Project=GCI. Os discos EBS e os IPs privados
# persistem; o IP publico muda no proximo start.
#
# Uso:  bash scripts/stop.sh          (pede confirmacao)
#       bash scripts/stop.sh --yes    (sem perguntar)
# Baixe os resultados do gerador antes (scp) para nao depender do disco.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

ids="$(aws ec2 describe-instances --filters Name=tag:Project,Values=GCI Name=instance-state-name,Values=running,pending \
  --query 'Reservations[].Instances[].InstanceId' --output text)"
if [ -z "$ids" ]; then
  echo "Nenhuma instancia em execucao."
  exit 0
fi
echo "Serao paradas: $ids"
if [ "${1:-}" != "--yes" ]; then
  read -r -p "Parar estas instancias? (sim/nao) " ans
  [ "$ans" = "sim" ] || {
    echo "Cancelado."
    exit 1
  }
fi
# shellcheck disable=SC2086
aws ec2 stop-instances --instance-ids $ids >/dev/null
# shellcheck disable=SC2086
aws ec2 wait instance-stopped --instance-ids $ids
echo "Todas paradas."

#!/usr/bin/env bash
# F1 (queda do NFS) da rodada 2. Roda NO GERADOR, em outra janela do tmux, logo apos iniciar o k6.
# Le o instante de partida em <pasta>/t0 (epoch, gravado antes do k6) e, a partir dele:
#   para o nfs-kernel-server em T0+FAULT_AT e o religa em T0+FAULT_AT+FAULT_FOR (via SSM).
#
# Uso:  source ~/exp2.env; bash ~/gci/tests/exp2/fault.sh <pasta-da-execucao>
#   FAULT_AT  (padrao 540 s = 5 min de subida + 4 min de platao)
#   FAULT_FOR (padrao 180 s)
# Saida: <pasta>/fault.log  com  epoch<TAB>evento<TAB>detalhe  (inicio e fim de cada comando,
# CommandId e o status/horarios que o SSM informa). Os instantes sao do relogio do gerador.
set -u
R="${1:?pasta da execucao}"
FAULT_AT="${FAULT_AT:-540}"
FAULT_FOR="${FAULT_FOR:-180}"
REGION="${AWS_REGION:-us-east-2}"
: "${NFS_ID:?defina NFS_ID (source ~/exp2.env)}"
L="$R/fault.log"
ev() { printf '%s\t%s\t%s\n' "$(date +%s.%N)" "$1" "$2" | tee -a "$L"; }

until [ -f "$R/t0" ]; do sleep 1; done
T0=$(cat "$R/t0")
wait_until() { local t=$(( T0 + $1 - $(date +%s) )); [ "$t" -gt 0 ] && sleep "$t"; return 0; }

run_ssm() {  # $1 = rotulo, $2 = comandos (JSON)
  ev "${1}_pedido" "T0+$(( $(date +%s) - T0 ))s"
  local id
  id=$(aws ssm send-command --region "$REGION" --instance-ids "$NFS_ID" --document-name AWS-RunShellScript \
        --parameters "commands=$2" --query Command.CommandId --output text) || { ev "${1}_ERRO_ssm" "send-command falhou"; return 1; }
  ev "${1}_commandid" "$id"
  local i st
  for i in $(seq 1 30); do
    st=$(aws ssm get-command-invocation --region "$REGION" --command-id "$id" --instance-id "$NFS_ID" \
          --query '[Status,ExecutionStartDateTime,ExecutionEndDateTime]' --output text 2>/dev/null) || st=""
    case "$st" in Success*|Failed*|Cancelled*|TimedOut*) break ;; esac
    sleep 1
  done
  ev "${1}_status" "$st"
}

wait_until "$FAULT_AT"
run_ssm nfs_parado '["systemctl stop nfs-kernel-server"]'
wait_until $(( FAULT_AT + FAULT_FOR ))
run_ssm nfs_restaurado '["systemctl start nfs-kernel-server","exportfs -ra"]'
ev fim "T0+$(( $(date +%s) - T0 ))s"

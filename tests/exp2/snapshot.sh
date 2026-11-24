#!/usr/bin/env bash
# Instantaneo (snapshot) das metricas de infraestrutura, para calcular os deltas do platao
# (o Experimento 2 nao tem Prometheus: os contadores sao cumulativos, entao a diferenca entre o
# snapshot do inicio e o do fim do platao da CPU, rede e ENA no intervalo).
#
# Uso, NO GERADOR:   bash ~/gci/tests/exp2/snapshot.sh <pasta-da-execucao> <rotulo>
#   ex.:  bash ~/gci/tests/exp2/snapshot.sh ~/results/C2-r1 inicio
#
# Cobre: node_exporter de 6 hosts (CPU, memoria, rede, ENA, NFS), cAdvisor dos pods da aplicacao
# nos 3 nos e `kubectl top`. Os IPs privados persistem em stop/start; ajuste SNAP_HOSTS se mudarem.
set -u
OUT="${1:?pasta de saida}"
LABEL="${2:?rotulo}"
mkdir -p "$OUT"
F="$OUT/snap-$LABEL.txt"

HOSTS="${SNAP_HOSTS:-app=10.0.1.254 agent1=10.0.1.164 agent2=10.0.1.51 db=10.0.2.153 nfs=10.0.2.60 loadgen=127.0.0.1}"
NODES="${SNAP_NODES:-gci-app-server gci-agent-1 gci-agent-2}"

{
  echo "# snapshot $LABEL epoch=$(date +%s.%N) utc=$(date -u +%FT%TZ)"
  for h in $HOSTS; do
    name="${h%%=*}"
    ip="${h#*=}"
    echo "## node_exporter $name $ip"
    curl -fsS --max-time 5 "http://$ip:9100/metrics" |
      grep -E '^(node_cpu_seconds_total|node_memory_(MemTotal|MemAvailable|Cached|Buffers)_bytes|node_network_(receive|transmit)_bytes_total\{device="e(ns|np)[^"]*"|node_ethtool_[a-z_]*allowance|node_nfs|node_nfsd)' ||
      echo "# FALHA $name"
  done
  for n in $NODES; do
    echo "## cadvisor $n"
    kubectl get --raw "/api/v1/nodes/$n/proxy/metrics/cadvisor" --request-timeout=10s |
      grep -E '^container_(cpu_usage_seconds_total|memory_working_set_bytes)\{.*namespace="app"' ||
      echo "# FALHA $n"
  done
  echo "## kubectl top"
  kubectl top pods -n app --no-headers 2>&1
} >"$F"

echo "snapshot salvo em $F ($(wc -l <"$F") linhas)"

#!/usr/bin/env bash
# Coletor de series temporais e logs do Experimento 2 (rodada 2). Roda NO GERADOR, em segundo plano,
# durante toda a execucao (da subida ate alguns minutos apos a carga, para ver o HPA reduzir).
#
# Uso:  bash ~/gci/tests/exp2/collect.sh <pasta-da-execucao> [intervalo-em-s]      (padrao 5 s)
#   parar:  touch <pasta>/stop      (ou kill -TERM no PID gravado em <pasta>/collect.pid)
#
# Saidas em <pasta>:
#   series.tsv        epoch<TAB>fonte<TAB>entidade<TAB>metrica<TAB>valor   (relogio do gerador)
#       fonte=kubelet: pod_cpu_s (cumulativo, s), pod_mem_bytes, node_cpu_s, node_mem_bytes
#       fonte=nodeexp: cpu_idle_s, cpu_steal_s, cpu_total_s (cumulativos, todos os nucleos), mem_total/avail_bytes
#       fonte=hpa:     current/desired/min/max replicas, cpu_util_pct, cpu_target_pct
#       fonte=deploy:  ready/replicas
#       fonte=pods:    pod (valor 1) com a entidade "nome|ip|no|fase|pronto|terminando"
#   traefik.log       access log JSON do Traefik (requer deploy/cluster/traefik-accesslog.yaml)
#   podlogs/<pod>.log stdout dos pods da aplicacao (com --timestamps), mantido apos o pod sair
#   applog/<pod>.log  copia periodica de protected/runtime/application.log de cada pod (ultima copia boa)
#   collect.err       falhas de coleta (nao interrompem o laco)
# CPU em milinucleos por pod = delta(pod_cpu_s) / delta(t) * 1000 (ver plot/cpu_summary).
set -u
OUT="${1:?pasta de saida}"
INT="${2:-5}"
NS="${NS:-app}"
APPLOG="${APPLOG:-/app/trunk/protected/runtime/application.log}"
HOSTS="${SNAP_HOSTS:-app=10.0.1.254 agent1=10.0.1.164 agent2=10.0.1.51 db=10.0.2.153 nfs=10.0.2.60 loadgen=127.0.0.1}"
NODES="${SNAP_NODES:-gci-app-server gci-agent-1 gci-agent-2}"

mkdir -p "$OUT/podlogs" "$OUT/applog"
S="$OUT/series.tsv"
E="$OUT/collect.err"
rm -f "$OUT/stop"
echo $$ >"$OUT/collect.pid"
[ -s "$S" ] || printf 'epoch\tsource\tentity\tmetric\tvalue\n' >"$S"

BG=""
cleanup() { for p in $BG; do pkill -P "$p" 2>/dev/null; kill "$p" 2>/dev/null; done; pkill -f "kubectl.* logs -f.*(traefik|gci-app)" 2>/dev/null; wait 2>/dev/null; echo "$(date +%s.%N) coletor encerrado" >>"$E"; exit 0; }
trap cleanup INT TERM

# --- kubelet: CPU/memoria por pod e por no (barato; sem cAdvisor) ---
kubelet_sample() {
  local n="$1" now
  now=$(date +%s.%N)
  kubectl get --raw "/api/v1/nodes/$n/proxy/metrics/resource" --request-timeout=8s 2>>"$E" |
    awk -v t="$now" -v ns="$NS" -v node="$n" '
      function lab(s, k,   m) { if (match(s, k "=\"[^\"]*\"")) { m = substr(s, RSTART + length(k) + 2, RLENGTH - length(k) - 3); return m } return "" }
      /^pod_cpu_usage_seconds_total\{/    { if (lab($0,"namespace")==ns) printf "%s\tkubelet\t%s\tpod_cpu_s\t%s\n", t, lab($0,"pod"), $2 }
      /^pod_memory_working_set_bytes\{/   { if (lab($0,"namespace")==ns) printf "%s\tkubelet\t%s\tpod_mem_bytes\t%s\n", t, lab($0,"pod"), $2 }
      /^node_cpu_usage_seconds_total /    { printf "%s\tkubelet\t%s\tnode_cpu_s\t%s\n", t, node, $2 }
      /^node_memory_working_set_bytes /   { printf "%s\tkubelet\t%s\tnode_mem_bytes\t%s\n", t, node, $2 }' >>"$S"
}

# --- node_exporter: CPU total/idle/steal e memoria de cada host ---
nodeexp_sample() {
  local name="$1" ip="$2" now
  now=$(date +%s.%N)
  curl -fsS --max-time 4 "http://$ip:9100/metrics" 2>>"$E" |
    awk -v t="$now" -v h="$name" '
      /^node_cpu_seconds_total\{/ { v=$NF; tot+=v; if ($0 ~ /mode="idle"/) idle+=v; if ($0 ~ /mode="steal"/) steal+=v }
      /^node_memory_MemTotal_bytes /     { mt=$2 }
      /^node_memory_MemAvailable_bytes / { ma=$2 }
      END { if (tot>0) {
        printf "%s\tnodeexp\t%s\tcpu_total_s\t%s\n%s\tnodeexp\t%s\tcpu_idle_s\t%s\n%s\tnodeexp\t%s\tcpu_steal_s\t%s\n", t,h,tot, t,h,idle, t,h,steal
        printf "%s\tnodeexp\t%s\tmem_total_bytes\t%s\n%s\tnodeexp\t%s\tmem_avail_bytes\t%s\n", t,h,mt, t,h,ma } }' >>"$S"
}

# --- HPA, Deployment e pods (estado) ---
state_sample() {
  local now
  now=$(date +%s.%N)
  kubectl -n "$NS" get hpa gci-app -o json --request-timeout=8s 2>>"$E" | jq -r --arg t "$now" '
    . as $h | [
      ["current_replicas", ($h.status.currentReplicas // 0)],
      ["desired_replicas", ($h.status.desiredReplicas // 0)],
      ["min_replicas", ($h.spec.minReplicas // 0)],
      ["max_replicas", ($h.spec.maxReplicas // 0)],
      ["cpu_util_pct", ([$h.status.currentMetrics[]? | select(.type=="Resource" and .resource.name=="cpu") | .resource.current.averageUtilization] | first // "")],
      ["cpu_target_pct", ([$h.spec.metrics[]? | select(.type=="Resource" and .resource.name=="cpu") | .resource.target.averageUtilization] | first // "")]
    ][] | select(.[1] != "") | "\($t)\thpa\tgci-app\t\(.[0])\t\(.[1])"' >>"$S"
  kubectl -n "$NS" get deploy gci-app -o json --request-timeout=8s 2>>"$E" | jq -r --arg t "$now" '
    "\($t)\tdeploy\tgci-app\tready_replicas\t\(.status.readyReplicas // 0)",
    "\($t)\tdeploy\tgci-app\treplicas\t\(.status.replicas // 0)"' >>"$S"
  kubectl -n "$NS" get pods -l app.kubernetes.io/name=gci-app -o json --request-timeout=8s 2>>"$E" | jq -r --arg t "$now" '
    .items[] | "\($t)\tpods\t\(.metadata.name)|\(.status.podIP // "")|\(.spec.nodeName // "")|\(.status.phase)|\([.status.conditions[]? | select(.type=="Ready") | .status] | first // "")|\(if .metadata.deletionTimestamp then "terminating" else "" end)\tpod\t1"' >>"$S"
}

# --- seguidores de log ---
start_traefik_follow() {
  ( while [ ! -f "$OUT/stop" ]; do
      kubectl -n kube-system logs -f --since=5s --timestamps deploy/traefik >>"$OUT/traefik.log" 2>>"$E"
      sleep 2
    done ) &
  BG="$BG $!"
}
follow_pods() {
  local p
  for p in $(kubectl -n "$NS" get pods -l app.kubernetes.io/name=gci-app -o name --request-timeout=8s 2>>"$E" | sed 's#pod/##'); do
    if [ ! -e "$OUT/podlogs/.$p.pid" ] || ! kill -0 "$(cat "$OUT/podlogs/.$p.pid")" 2>/dev/null; then
      if [ ! -e "$OUT/podlogs/$p.log" ]; then
        kubectl -n "$NS" logs -f --timestamps "$p" >"$OUT/podlogs/$p.log" 2>>"$E" &
        echo $! >"$OUT/podlogs/.$p.pid"
        BG="$BG $!"
      fi
    fi
  done
}
copy_applogs() {
  local p
  for p in $(kubectl -n "$NS" get pods -l app.kubernetes.io/name=gci-app -o name --request-timeout=8s 2>>"$E" | sed 's#pod/##'); do
    if kubectl -n "$NS" exec "$p" --request-timeout=10s -- cat "$APPLOG" >"$OUT/applog/$p.tmp" 2>/dev/null; then
      mv -f "$OUT/applog/$p.tmp" "$OUT/applog/$p.log"
    else
      rm -f "$OUT/applog/$p.tmp"
    fi
  done
}

echo "$(date +%s.%N) coletor iniciado intervalo=${INT}s" >>"$E"
start_traefik_follow
i=0
while [ ! -f "$OUT/stop" ]; do
  t_ini=$(date +%s)
  (
    for n in $NODES; do kubelet_sample "$n" & done
    for h in $HOSTS; do nodeexp_sample "${h%%=*}" "${h#*=}" & done
    state_sample &
    wait
  )
  follow_pods
  if [ $((i % 6)) -eq 0 ]; then copy_applogs & fi
  i=$((i + 1))
  d=$((t_ini + INT - $(date +%s)))
  [ "$d" -gt 0 ] && sleep "$d"
done
cleanup

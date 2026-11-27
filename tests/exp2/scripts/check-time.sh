#!/usr/bin/env bash
# Mostra o estado da sincronizacao de relogio (chrony) e o desvio em relacao ao gerador.
# Os horarios de pods, Traefik, NFS e banco vem de relogios diferentes; sem sincronia o
# alinhamento das falhas com o k6 (relogio do gerador) fica impreciso.
# Uso, NO GERADOR:  bash ~/gci/tests/exp2/scripts/check-time.sh [--fix]
#   --fix: configura o chrony de cada host com o servico de tempo da AWS (169.254.169.123)
# Hosts por SSH: depende de ~/exp2.env (KEY, IPs). Nao testado em AWS; confira a saida.
set -u
[ -f ~/exp2.env ] && . ~/exp2.env
HOSTS="${TIME_HOSTS:-10.0.1.254 10.0.1.164 10.0.1.51 10.0.2.153 10.0.2.60}"
KEYOPT=""; [ -n "${KEY:-}" ] && KEYOPT="-i $KEY"
FIX=0; [ "${1:-}" = "--fix" ] && FIX=1
echo "gerador: $(date -u +%T.%N) $(chronyc tracking 2>/dev/null | grep -E 'System time|Leap status' | tr '\n' ' ')"
for h in $HOSTS; do
  t0=$(date +%s.%N)
  r=$(ssh $KEYOPT -o StrictHostKeyChecking=no -o ConnectTimeout=5 ubuntu@"$h" 'date +%s.%N; chronyc tracking 2>/dev/null | grep -E "System time|Leap status"' 2>&1) || { echo "$h: SSH falhou (use o Session Manager no banco/NFS)"; continue; }
  t1=$(date +%s.%N)
  rem=$(echo "$r" | head -1)
  off=$(awk -v a="$t0" -v b="$t1" -v r="$rem" 'BEGIN{printf "%.3f", r-(a+b)/2}')
  echo "$h: desvio ~${off}s (inclui latencia do SSH) | $(echo "$r" | tail -n +2 | tr '\n' ' ')"
  if [ "$FIX" = 1 ]; then
    ssh $KEYOPT -o StrictHostKeyChecking=no ubuntu@"$h" "grep -q 169.254.169.123 /etc/chrony/chrony.conf 2>/dev/null || (echo 'server 169.254.169.123 prefer iburst minpoll 4 maxpoll 4' | sudo tee -a /etc/chrony/chrony.conf >/dev/null; sudo systemctl restart chrony)" && echo "  $h: chrony configurado"
  fi
done

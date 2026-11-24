#!/bin/bash
# Configura o node_exporter (pacote prometheus-node-exporter do Ubuntu) com o coletor ethtool,
# que expoe as metricas da ENA (bw_in_allowance_exceeded, bw_out_allowance_exceeded,
# pps_allowance_exceeded), alem dos coletores nfs e nfsd. Idempotente. Rodar como root:
#   sudo bash, depois colar o conteudo (server, por SSH)
#   Session Manager do console (banco e NFS, sem chave): colar o conteudo
set -e
cat >/etc/default/prometheus-node-exporter <<'EOF'
ARGS='--web.listen-address=:9100 --collector.ethtool --collector.nfs --collector.nfsd'
EOF
systemctl enable prometheus-node-exporter >/dev/null 2>&1 || true
systemctl restart prometheus-node-exporter
sleep 2
echo "node_exporter: $(systemctl is-active prometheus-node-exporter)"
if curl -fsS http://127.0.0.1:9100/metrics | grep -E '^node_ethtool_(bw_in_allowance_exceeded|bw_out_allowance_exceeded|pps_allowance_exceeded)'; then
  echo "metricas ENA: ok"
else
  echo "AVISO: metricas ENA ausentes (confira 'curl localhost:9100/metrics | grep ethtool')"
fi

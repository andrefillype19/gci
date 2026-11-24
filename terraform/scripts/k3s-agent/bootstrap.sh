#!/bin/bash
# user_data dos agents k3s (gci-agent-1 e gci-agent-2): so prepara o sistema operacional.
# A entrada no cluster e feita depois, por SSH, com o token do server (nao vai para o user_data).
exec > /var/log/user-data.log 2>&1
set -ex
export DEBIAN_FRONTEND=noninteractive

apt-get update -y
# nfs-common: o kubelet monta os PVs NFS (deploy/common/nfs-pv.yaml) com mount.nfs4.
apt-get install -y nfs-common prometheus-node-exporter curl ca-certificates

# Coletor ethtool: metricas da ENA (bw_in_allowance_exceeded, bw_out_allowance_exceeded, pps_allowance_exceeded).
cat >/etc/default/prometheus-node-exporter <<'EOF'
ARGS='--web.listen-address=:9100 --collector.ethtool --collector.nfs --collector.nfsd'
EOF
systemctl enable prometheus-node-exporter
systemctl restart prometheus-node-exporter

touch /var/lib/cloud/gci-agent-ready

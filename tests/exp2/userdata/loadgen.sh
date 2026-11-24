#!/bin/bash
# user_data do gerador de carga (gci-loadgen): k6, kubectl, AWS CLI, tmux, git, jq e node_exporter.
# Os marcadores __K6_VERSION__ e __KUBECTL_VERSION__ sao trocados por sed antes do run-instances
# (veja o PASSO_A_PASSO_AWS.md).
exec > /var/log/user-data.log 2>&1
set -ex
export DEBIAN_FRONTEND=noninteractive

K6_VERSION=__K6_VERSION__
KUBECTL_VERSION=__KUBECTL_VERSION__

apt-get update -y
apt-get install -y curl ca-certificates tmux git jq unzip gzip python3 prometheus-node-exporter

# k6 (binario oficial, versao fixa)
curl -fsSL "https://github.com/grafana/k6/releases/download/${K6_VERSION}/k6-${K6_VERSION}-linux-amd64.tar.gz" | tar -xz -C /tmp
install -m 0755 "/tmp/k6-${K6_VERSION}-linux-amd64/k6" /usr/local/bin/k6

# kubectl na mesma versao do k3s
curl -fsSLo /usr/local/bin/kubectl "https://dl.k8s.io/release/${KUBECTL_VERSION}/bin/linux/amd64/kubectl"
chmod 0755 /usr/local/bin/kubectl

# AWS CLI v2 (credenciais vem do instance profile)
curl -fsSLo /tmp/awscliv2.zip "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip"
unzip -q /tmp/awscliv2.zip -d /tmp
/tmp/aws/install

cat >/etc/default/prometheus-node-exporter <<'EOF'
ARGS='--web.listen-address=:9100 --collector.ethtool'
EOF
systemctl enable prometheus-node-exporter
systemctl restart prometheus-node-exporter

install -d -o ubuntu -g ubuntu /home/ubuntu/results
touch /var/lib/cloud/gci-loadgen-ready

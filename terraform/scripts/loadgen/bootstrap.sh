#!/bin/bash
# user_data do gerador de carga (gci-loadgen): k6, kubectl, AWS CLI, tmux, git, jq, node_exporter
# e o dead-man switch. Este arquivo e um template do Terraform (templatefile): evite usar
# chaves precedidas de cifrao no shell; use $VAR sem chaves.
exec > /var/log/user-data.log 2>&1
set -ex
export DEBIAN_FRONTEND=noninteractive

K6_VERSION=${k6_version}

apt-get update -y
apt-get install -y curl ca-certificates tmux git jq unzip gzip python3 prometheus-node-exporter

# k6 (binario oficial, versao fixa)
curl -fsSL "https://github.com/grafana/k6/releases/download/$K6_VERSION/k6-$K6_VERSION-linux-amd64.tar.gz" | tar -xz -C /tmp
install -m 0755 "/tmp/k6-$K6_VERSION-linux-amd64/k6" /usr/local/bin/k6

# kubectl (release estavel atual; o cliente tolera diferenca de 1 versao menor para o k3s)
KUBECTL_VERSION="$(curl -fsSL https://dl.k8s.io/release/stable.txt)"
curl -fsSLo /usr/local/bin/kubectl "https://dl.k8s.io/release/$KUBECTL_VERSION/bin/linux/amd64/kubectl"
chmod 0755 /usr/local/bin/kubectl

# AWS CLI v2 (credenciais vem do instance profile gci-loadgen-profile)
curl -fsSLo /tmp/awscliv2.zip "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip"
unzip -q /tmp/awscliv2.zip -d /tmp
/tmp/aws/install

cat >/etc/default/prometheus-node-exporter <<'EOF'
ARGS='--web.listen-address=:9100 --collector.ethtool'
EOF
systemctl enable prometheus-node-exporter
systemctl restart prometheus-node-exporter

install -d -o ubuntu -g ubuntu /home/ubuntu/results

# ---------------------------------------------------------------------------
# Dead-man switch: ${deadman_minutes} min apos CADA boot do gerador, para todas as
# instancias com tag Project=GCI (inclusive o proprio gerador).
# Renovar:  sudo systemctl restart gci-deadman     Cancelar:  sudo systemctl stop gci-deadman
# ---------------------------------------------------------------------------
cat >/usr/local/sbin/gci-deadman.sh <<'SCRIPT'
#!/bin/bash
set -u
sleep "$((DEADMAN_MIN * 60))"
ids="$(aws ec2 describe-instances \
  --filters Name=tag:Project,Values=GCI Name=instance-state-name,Values=running,pending \
  --query 'Reservations[].Instances[].InstanceId' --output text)"
if [ -n "$ids" ]; then
  aws ec2 stop-instances --instance-ids $ids
fi
SCRIPT
chmod 0755 /usr/local/sbin/gci-deadman.sh

cat >/etc/systemd/system/gci-deadman.service <<'UNIT'
[Unit]
Description=GCI - dead-man switch (para a pilha apos o prazo)
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
Environment=DEADMAN_MIN=${deadman_minutes}
Environment=AWS_DEFAULT_REGION=${aws_region}
ExecStart=/usr/local/sbin/gci-deadman.sh

[Install]
WantedBy=multi-user.target
UNIT

systemctl daemon-reload
systemctl enable --now gci-deadman.service

touch /var/lib/cloud/gci-loadgen-ready

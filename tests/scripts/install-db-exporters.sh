#!/usr/bin/env bash
# Instala node_exporter (9100) e postgres_exporter (9187) na EC2 do banco.
# Executar UMA vez, como root, num shell do AWS Session Manager:
#   sudo -i
#   (colar este script)  ou  bash install-db-exporters.sh
# Idempotente: pode ser repetido sem efeito colateral.
set -euo pipefail

DB_NAME="${DB_NAME:-gci-test-db}"
EXPORTER_ROLE="gci_exporter"
EXPORTER_PASS="${EXPORTER_PASS:-gci_exporter_pw}"

export DEBIAN_FRONTEND=noninteractive

echo "==> Instalando pacotes (via NAT instance)"
apt-get update -qq
apt-get install -y -qq prometheus-node-exporter prometheus-postgres-exporter

echo "==> Role de monitoramento no PostgreSQL (somente leitura de estatisticas)"
sudo -u postgres psql -v ON_ERROR_STOP=1 -v pass="${EXPORTER_PASS}" -d postgres <<'SQL'
SELECT 'CREATE ROLE gci_exporter LOGIN' WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'gci_exporter')\gexec
ALTER ROLE gci_exporter PASSWORD :'pass';
GRANT pg_monitor TO gci_exporter;
SQL

echo "==> Configurando postgres_exporter"
cat > /etc/default/prometheus-postgres-exporter <<EOF
DATA_SOURCE_NAME='postgresql://${EXPORTER_ROLE}:${EXPORTER_PASS}@127.0.0.1:5432/${DB_NAME}?sslmode=disable'
ARGS='--web.listen-address=:9187'
EOF
chmod 640 /etc/default/prometheus-postgres-exporter

echo "==> Configurando node_exporter (escuta em todas as interfaces, porta 9100)"
cat > /etc/default/prometheus-node-exporter <<'EOF'
ARGS='--web.listen-address=:9100'
EOF

systemctl enable prometheus-node-exporter prometheus-postgres-exporter >/dev/null 2>&1
systemctl restart prometheus-node-exporter prometheus-postgres-exporter
sleep 3

echo "==> Verificando"
systemctl is-active prometheus-node-exporter prometheus-postgres-exporter
curl -fsS http://127.0.0.1:9100/metrics | grep -m1 '^node_cpu_seconds_total' || echo "FALHA: node_exporter"
curl -fsS http://127.0.0.1:9187/metrics | grep -m1 '^pg_up' || echo "FALHA: postgres_exporter"
echo "Concluido."

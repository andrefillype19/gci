#!/bin/bash
# -----------------------------------------------------------------------------
# GCI - Bootstrap do PostgreSQL 11 (EC2 privada, instalação nativa via PGDG)
#
# Este arquivo é renderizado pelo Terraform com templatefile():
#   - variáveis do Terraform usam a sintaxe de interpolação com chaves;
#   - variáveis do bash devem usar $VAR (sem chaves) ou ser escapadas com $$.
# -----------------------------------------------------------------------------

exec > >(tee -a /var/log/gci-postgres-bootstrap.log) 2>&1

set -Eeuo pipefail

echo "========================================"
echo "GCI - PostgreSQL 11 Bootstrap"
echo "========================================"

# =====================================================
# 1. VARIÁVEIS (injetadas pelo Terraform)
# =====================================================

VPC_CIDR="${vpc_cidr}"
AWS_REGION="${aws_region}"
ARTIFACTS_BUCKET="${artifacts_bucket}"
DB_NAME="${db_name}"
DB_PASSWORD='${db_password}'

PG_VERSION="11"
PG_HBA="/etc/postgresql/$PG_VERSION/main/pg_hba.conf"
INIT_DIR="/opt/gci-db"
RESTORE_MARKER="/var/lib/postgresql/.gci-restore-done"

export DEBIAN_FRONTEND=noninteractive

# =====================================================
# 2. AGUARDAR SAÍDA PARA INTERNET (NAT)
# =====================================================

for i in $(seq 1 30); do
    if curl -fsS --max-time 5 -o /dev/null https://apt.postgresql.org; then
        echo "Saída para internet OK."
        break
    fi
    echo "Aguardando NAT Gateway... ($i/30)"
    sleep 10
done

# =====================================================
# 3. DEPENDÊNCIAS
#    Obs.: o pacote apt "awscli" não existe no Ubuntu 24.04,
#    por isso o AWS CLI é instalado via snap.
# =====================================================

apt-get update -y

apt-get install -y \
    curl \
    ca-certificates \
    gnupg \
    lsb-release

snap wait system seed.loaded

if ! snap list aws-cli >/dev/null 2>&1; then
    snap install aws-cli --classic
fi

AWS_BIN="/snap/bin/aws"

# =====================================================
# 4. REPOSITÓRIO PGDG
# =====================================================

install -d /usr/share/postgresql-common/pgdg

curl -fsSL \
    https://www.postgresql.org/media/keys/ACCC4CF8.asc \
    -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc

. /etc/os-release

cat > /etc/apt/sources.list.d/pgdg.sources <<EOF
Types: deb
URIs: https://apt.postgresql.org/pub/repos/apt
Suites: $VERSION_CODENAME-pgdg
Components: main
Signed-By: /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc
EOF

# =====================================================
# 5. INSTALAR POSTGRESQL 11
# =====================================================

apt-get update -y

if apt-cache policy "postgresql-$PG_VERSION" | grep -q 'Candidate: (none)' ||
   ! apt-cache policy "postgresql-$PG_VERSION" | grep -q 'Candidate:'; then
    echo "Erro: pacote postgresql-$PG_VERSION não encontrado no PGDG."
    exit 1
fi

apt-get install -y \
    "postgresql-$PG_VERSION" \
    "postgresql-client-$PG_VERSION"

if ! pg_lsclusters | grep -q "^$PG_VERSION *main"; then
    echo "Erro: cluster PostgreSQL $PG_VERSION/main não encontrado."
    exit 1
fi

systemctl enable postgresql
systemctl start postgresql

# =====================================================
# 6. CONFIGURAÇÃO DE REDE (listen + pg_hba)
# =====================================================

runuser -u postgres -- \
    psql -v ON_ERROR_STOP=1 -c "ALTER SYSTEM SET listen_addresses = '*';"

RULE="host all all $VPC_CIDR md5"

if ! grep -Fqx "$RULE" "$PG_HBA"; then
    echo "$RULE" >> "$PG_HBA"
fi

systemctl restart postgresql

for i in $(seq 1 30); do
    pg_isready -h 127.0.0.1 -p 5432 && break
    sleep 2
done

# =====================================================
# 7. SENHA DO USUÁRIO postgres E BANCO DA APLICAÇÃO
# =====================================================

runuser -u postgres -- psql -v ON_ERROR_STOP=1 \
    -v pwd="$DB_PASSWORD" <<'EOSQL'
ALTER USER postgres WITH PASSWORD :'pwd';
EOSQL

DB_EXISTS=$(runuser -u postgres -- psql -tAc \
    "SELECT 1 FROM pg_database WHERE datname = '$DB_NAME'")

if [ "$DB_EXISTS" != "1" ]; then
    runuser -u postgres -- createdb "$DB_NAME"
fi

# =====================================================
# 8. BAIXAR ARTEFATOS DO S3 (gci.sql + scripts de init)
#    O tráfego sai pelo VPC Endpoint de S3 (gateway).
# =====================================================

install -d -o postgres -g postgres -m 0750 "$INIT_DIR"

for f in 01-create-role.sh 02-restore.sh gci.sql; do
    "$AWS_BIN" s3 cp \
        "s3://$ARTIFACTS_BUCKET/postgres/$f" \
        "$INIT_DIR/$f" \
        --region "$AWS_REGION" \
        --no-progress
done

# Remove eventuais CRLF dos scripts (repositório editado no Windows)
sed -i 's/\r$//' "$INIT_DIR"/*.sh

chown -R postgres:postgres "$INIT_DIR"
chmod 0750 "$INIT_DIR"/*.sh

# =====================================================
# 9. CRIAR ROLE E RESTAURAR O DUMP
#    Os scripts originais usam POSTGRES_USER / POSTGRES_DB
#    (padrão da imagem Docker), então exportamos as mesmas
#    variáveis para reaproveitá-los sem alteração de lógica.
# =====================================================

if [ ! -f "$RESTORE_MARKER" ]; then
    runuser -u postgres -- env \
        POSTGRES_USER=postgres \
        POSTGRES_DB="$DB_NAME" \
        GCI_SQL_FILE="$INIT_DIR/gci.sql" \
        bash "$INIT_DIR/01-create-role.sh"

    runuser -u postgres -- env \
        POSTGRES_USER=postgres \
        POSTGRES_DB="$DB_NAME" \
        GCI_SQL_FILE="$INIT_DIR/gci.sql" \
        bash "$INIT_DIR/02-restore.sh"

    touch "$RESTORE_MARKER"
    chown postgres:postgres "$RESTORE_MARKER"
    echo "Restore do gci.sql concluído."
else
    echo "Restore já executado anteriormente ($RESTORE_MARKER). Pulando."
fi

# =====================================================
# 10. SSM AGENT (já vem no AMI Ubuntu; apenas garante)
# =====================================================

systemctl enable --now snap.amazon-ssm-agent.amazon-ssm-agent.service || \
    echo "Aviso: não foi possível habilitar o SSM Agent."

# =====================================================
# 11. VALIDAÇÃO
# =====================================================

if ! pg_isready -h 127.0.0.1 -p 5432; then
    echo "Erro: PostgreSQL não está respondendo."
    exit 1
fi

runuser -u postgres -- psql -d "$DB_NAME" -tAc \
    "SELECT 'Tabelas restauradas: ' || count(*) FROM information_schema.tables WHERE table_schema = 'public';"

psql --version
pg_lsclusters

echo "========================================"
echo "Bootstrap finalizado com sucesso."
echo "========================================"

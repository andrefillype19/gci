#!/bin/bash
set -e

# No container o dump fica em /docker-entrypoint-initdb.d;
# na EC2 o bootstrap informa o caminho via GCI_SQL_FILE.
GCI_SQL_FILE="${GCI_SQL_FILE:-/docker-entrypoint-initdb.d/gci.sql}"

psql -v ON_ERROR_STOP=1 \
  --username "$POSTGRES_USER" \
  --dbname "$POSTGRES_DB" \
  -f "$GCI_SQL_FILE"

# A aplicacao envia datas no formato brasileiro (dd/mm/aaaa). Em um servidor com locale
# C/en_US o padrao do PostgreSQL e MDY e "15/10/2026" falha com
# "date/time field value out of range". Fixa DMY para este banco.
psql -v ON_ERROR_STOP=1 \
  --username "$POSTGRES_USER" \
  --dbname "$POSTGRES_DB" \
  -v dbname="$POSTGRES_DB" <<-'EOSQL'
    ALTER DATABASE :"dbname" SET datestyle TO 'iso, dmy';
EOSQL

#!/bin/bash
set -e

# No container o dump fica em /docker-entrypoint-initdb.d;
# na EC2 o bootstrap informa o caminho via GCI_SQL_FILE.
GCI_SQL_FILE="${GCI_SQL_FILE:-/docker-entrypoint-initdb.d/gci.sql}"

psql -v ON_ERROR_STOP=1 \
  --username "$POSTGRES_USER" \
  --dbname "$POSTGRES_DB" \
  -f "$GCI_SQL_FILE"

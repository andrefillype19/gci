#!/bin/bash

set -e

# Funciona tanto no container (docker-entrypoint-initdb.d) quanto na EC2,
# onde o bootstrap exporta POSTGRES_USER / POSTGRES_DB.
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    DO \$\$
    BEGIN
        IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'computacaoifg') THEN
            CREATE ROLE computacaoifg;
        END IF;
    END
    \$\$;
EOSQL

FROM postgres:11-alpine

COPY init/01-create-role.sh /docker-entrypoint-initdb.d/01-create-role.sh
COPY init/02-restore.sh /docker-entrypoint-initdb.d/02-restore.sh

COPY gci.sql /docker-entrypoint-initdb.d/gci.sql

RUN chmod +x /docker-entrypoint-initdb.d/01-create-role.sh /docker-entrypoint-initdb.d/02-restore.sh
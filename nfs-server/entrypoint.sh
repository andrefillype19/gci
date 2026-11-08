#!/bin/sh
set -eu

mkdir -p /export/uploads /export/sessions

chown 82:82 /export/uploads /export/sessions
chmod 0775 /export/uploads /export/sessions

cat > /etc/exports <<'EOF'
/export/uploads (rw,no_root_squash)
/export/sessions (rw,no_root_squash)
EOF

mkdir -p /run/rpcbind
chmod 0111 /run/rpcbind
rpcbind

exec unfsd -d -e /etc/exports -n 2049 -m 20048 -t

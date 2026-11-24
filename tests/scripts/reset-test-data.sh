#!/usr/bin/env bash
# Reset dos dados gerados pelos testes k6. Roda NA EC2 da aplicacao (Linux, k3s).
#
# Remove:
#   - banco: eventos "k6-loadtest-*", subeventos "cert-k6-*" / PDFs "certificado-test-k6-*"
#            e atividades "k6-loadtest-*" (com dependencias);
#   - arquivos: PDFs de teste em /app/trunk/protected/uploads de cada pod;
#   - sessoes: /tmp/sess_* de cada pod (use --keep-sessions para preservar).
# Nao remove o PDF base nem dados de usuarios reais.
#
# Uso (a partir do seu PC):
#   Git Bash:    ssh -i KEY ubuntu@IP 'bash -s' < tests/scripts/reset-test-data.sh
#                ssh -i KEY ubuntu@IP 'bash -s -- --keep-sessions' < tests/scripts/reset-test-data.sh
#   PowerShell:  Get-Content -Raw tests\scripts\reset-test-data.sh | ssh -i KEY ubuntu@IP "tr -d '\r' | bash -s"
# Ou copie para a EC2 e rode:  bash reset-test-data.sh [--keep-sessions]
#
# O SQL roda em um pod temporario postgres:11-alpine (a EC2 do banco e privada e a
# aplicacao nao tem psql), com as credenciais do Secret app-database-credentials.

set -euo pipefail

NS="app"
KEEP_SESSIONS=0

for arg in "$@"; do
  case "$arg" in
    --keep-sessions) KEEP_SESSIONS=1 ;;
    -h|--help) sed -n '2,19p' "$0" 2>/dev/null || true; exit 0 ;;
    *) echo "Argumento desconhecido: $arg" >&2; exit 1 ;;
  esac
done

if kubectl get ns >/dev/null 2>&1; then
  K="kubectl"
elif sudo -n k3s kubectl get ns >/dev/null 2>&1; then
  K="sudo k3s kubectl"
else
  echo "kubectl nao acessivel (tente: sudo k3s kubectl get ns)." >&2
  exit 1
fi

secret() {
  $K get secret app-database-credentials -n "$NS" -o "jsonpath={.data.$1}" </dev/null | base64 -d
}

echo "==> Lendo credenciais do banco"
DB_HOST="$(secret DB_HOST)"
DB_PORT="$(secret DB_PORT)"
DB_NAME="$(secret DB_NAME)"
DB_USER="$(secret DB_USER)"
DB_PASSWORD="$(secret DB_PASSWORD)"

echo "==> Limpando registros de teste no PostgreSQL ($DB_HOST:$DB_PORT/$DB_NAME)"
POD="gci-reset-psql-$RANDOM"

RESULT="$($K run "$POD" -n "$NS" --rm -i --restart=Never --quiet \
  --image=postgres:11-alpine --env="PGPASSWORD=$DB_PASSWORD" \
  --command -- psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" \
  -At -q -v ON_ERROR_STOP=1 <<'SQL'
BEGIN;
CREATE TEMP TABLE _k6_eventos ON COMMIT DROP AS
SELECT id FROM "Evento" WHERE tituloevento LIKE 'k6-loadtest-%';
CREATE TEMP TABLE _k6_subeventos ON COMMIT DROP AS
SELECT id FROM subevento
WHERE nome LIKE 'cert-k6-%'
   OR COALESCE(filepath, '') LIKE '%certificado-test-k6-%'
   OR id_evento IN (SELECT id FROM _k6_eventos);
CREATE TEMP TABLE _k6_atividades ON COMMIT DROP AS
SELECT id FROM atividade
WHERE tema LIKE 'k6-loadtest-%'
   OR id_evento IN (SELECT id FROM _k6_eventos)
   OR id_subevento IN (SELECT id FROM _k6_subeventos);
DELETE FROM inscricaoatividade WHERE id_atividade IN (SELECT id FROM _k6_atividades);
DELETE FROM equipe WHERE id_subevento IN (SELECT id FROM _k6_subeventos);
DELETE FROM "Inscricao" WHERE id_evento IN (SELECT id FROM _k6_eventos);
DELETE FROM atividade WHERE id IN (SELECT id FROM _k6_atividades);
DELETE FROM subevento WHERE id IN (SELECT id FROM _k6_subeventos);
DELETE FROM "Evento" WHERE id IN (SELECT id FROM _k6_eventos);
COMMIT;
SELECT
  (SELECT count(*) FROM "Evento" WHERE tituloevento LIKE 'k6-loadtest-%'),
  (SELECT count(*) FROM subevento WHERE nome LIKE 'cert-k6-%' OR COALESCE(filepath, '') LIKE '%certificado-test-k6-%'),
  (SELECT count(*) FROM atividade WHERE tema LIKE 'k6-loadtest-%');
SQL
)"

LAST="$(printf '%s\n' "$RESULT" | tail -n 1)"
IFS='|' read -r C_EVENTOS C_SUBEVENTOS C_ATIVIDADES <<<"$LAST"
echo "Eventos de teste restantes:    ${C_EVENTOS:-?}"
echo "Subeventos de teste restantes: ${C_SUBEVENTOS:-?}"
echo "Atividades de teste restantes: ${C_ATIVIDADES:-?}"

echo "==> Removendo PDFs de teste nos pods da aplicacao"
PODS="$($K get pods -n "$NS" -l app.kubernetes.io/name=gci-app -o 'jsonpath={.items[*].metadata.name}' </dev/null)"
if [ -z "$PODS" ]; then
  echo "Aviso: nenhum pod gci-app encontrado." >&2
fi

for pod in $PODS; do
  # timeout: com o NFS fora do ar (F1) um exec em pod travado nunca retornaria.
  timeout 60 $K exec -n "$NS" "$pod" -c gci-app </dev/null -- sh -c \
    'rm -f /app/trunk/protected/uploads/certificado-test-k6-*.pdf /app/trunk/protected/uploads/upload-vu*.pdf' \
    || echo "Aviso: nao foi possivel limpar uploads no pod $pod" >&2

  if [ "$KEEP_SESSIONS" -eq 0 ]; then
    timeout 60 $K exec -n "$NS" "$pod" -c gci-app </dev/null -- sh -c 'rm -f /tmp/sess_*' \
      || echo "Aviso: nao foi possivel limpar sessoes no pod $pod" >&2
  fi
done

# Com NFS os arquivos sao compartilhados (limpar por um pod basta); sem NFS cada pod
# tem o proprio emptyDir e o laco acima limpou todos.
echo "Limpeza concluida."

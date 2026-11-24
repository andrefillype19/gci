#!/usr/bin/env bash
# Funcoes comuns do Experimento 2. Uso: source scripts/lib.sh
#
# Rodar em Git Bash, WSL ou Linux/macOS (precisa de aws CLI v2 e ssh).

export MSYS_NO_PATHCONV=1   # Git Bash: nao converter caminhos como /dev/sda1
export AWS_DEFAULT_REGION="${AWS_DEFAULT_REGION:-us-east-2}"

EXP2_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$EXP2_DIR/.local/env.sh"

# Carrega as variaveis gravadas por scripts/discover.sh (IDs e IPs).
load_env() {
  if [ ! -f "$ENV_FILE" ]; then
    echo "Rode scripts/discover.sh antes." >&2
    return 1
  fi
  # shellcheck disable=SC1090
  . "$ENV_FILE"
}

# ssh nas instancias com IP publico (KEY = caminho da key-gci.pem).
#   sshx <ip> [comando...]
sshx() {
  : "${KEY:?Defina KEY=caminho da key-gci.pem}"
  local host="$1"
  shift
  ssh -i "$KEY" -o StrictHostKeyChecking=accept-new -o LogLevel=ERROR -o ConnectTimeout=15 \
    -o ServerAliveInterval=30 "ubuntu@$host" "$@"
}

# Executa um script local como root em uma instancia por SSM (para as EC2 sem chave: banco e NFS).
#   ssm_run <instance-id> <arquivo.sh>
ssm_run() {
  local id="$1" file="$2" b64 cmd st
  b64="$(base64 "$file" | tr -d '\n')"
  cmd="$(aws ssm send-command --instance-ids "$id" --document-name AWS-RunShellScript \
    --parameters "commands=[\"echo $b64 | base64 -d | bash\"]" \
    --query Command.CommandId --output text)"
  for _ in $(seq 1 60); do
    st="$(aws ssm get-command-invocation --command-id "$cmd" --instance-id "$id" \
      --query Status --output text 2>/dev/null || echo Pending)"
    case "$st" in Success | Failed | Cancelled | TimedOut) break ;; esac
    sleep 3
  done
  aws ssm get-command-invocation --command-id "$cmd" --instance-id "$id" \
    --query '[Status,StandardOutputContent,StandardErrorContent]' --output text
  [ "$st" = "Success" ]
}

# Espera uma instancia aparecer como Online no SSM (o agente demora ~1 min depois do boot).
#   ssm_wait_online <instance-id>
ssm_wait_online() {
  local id="$1" p
  for _ in $(seq 1 60); do
    p="$(aws ssm describe-instance-information --filters "Key=InstanceIds,Values=$id" \
      --query 'InstanceInformationList[0].PingStatus' --output text 2>/dev/null || true)"
    [ "$p" = "Online" ] && return 0
    sleep 5
  done
  echo "SSM nao ficou Online em $id" >&2
  return 1
}

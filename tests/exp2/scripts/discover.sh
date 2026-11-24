#!/usr/bin/env bash
# Descobre IDs e IPs da AWS e grava .local/env.sh (nao versionado).
# SOMENTE LEITURA: describe-*, sts get-caller-identity e ssm get-parameter (AMI publica).
#
# Rode a cada sessao: o IP publico muda em stop/start e o IP privado do server nao e fixo no
# Terraform (persiste em stop/start, mas muda se a instancia for recriada).
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
mkdir -p "$EXP2_DIR/.local"

# inst <tag Name> <campo da instancia>   ex.: inst gci-app-server InstanceId
inst() {
  local v
  v="$(aws ec2 describe-instances \
    --filters "Name=tag:Name,Values=$1" "Name=instance-state-name,Values=pending,running,stopping,stopped" \
    --query "Reservations[].Instances[].$2" --output text)"
  v="${v%%$'\t'*}"
  v="${v%%$'\n'*}"
  [ "$v" = "None" ] && v=""
  printf '%s' "$v"
}

ACCOUNT_ID="$(aws sts get-caller-identity --query Account --output text)"
VPC_ID="$(aws ec2 describe-vpcs --filters Name=tag:Name,Values=gci-vpc --query 'Vpcs[0].VpcId' --output text)"
SUBNET_PUB="$(aws ec2 describe-subnets --filters Name=tag:Name,Values=gci-public-subnet "Name=vpc-id,Values=$VPC_ID" \
  --query 'Subnets[0].SubnetId' --output text)"
SG_APP="$(aws ec2 describe-security-groups --filters Name=group-name,Values=gci-app-sg "Name=vpc-id,Values=$VPC_ID" \
  --query 'SecurityGroups[0].GroupId' --output text)"
AMI_ID="$(aws ssm get-parameter \
  --name /aws/service/canonical/ubuntu/server/24.04/stable/current/amd64/hvm/ebs-gp3/ami-id \
  --query Parameter.Value --output text)"

out="$EXP2_DIR/.local/env.sh"
{
  echo "# Gerado por scripts/discover.sh em $(date -Is). Nao versionar."
  echo "export ACCOUNT_ID='$ACCOUNT_ID'"
  echo "export VPC_ID='$VPC_ID'"
  echo "export SUBNET_PUB='$SUBNET_PUB'"
  echo "export SG_APP='$SG_APP'"
  echo "export AMI_ID='$AMI_ID'"
  for item in SRV:gci-app-server DB:gci-postgres-db NFS:gci-nfs-server AG1:gci-agent-1 AG2:gci-agent-2 GEN:gci-loadgen; do
    p="${item%%:*}"
    n="${item#*:}"
    echo "export ${p}_ID='$(inst "$n" InstanceId)'"
    echo "export ${p}_PRIV='$(inst "$n" PrivateIpAddress)'"
    echo "export ${p}_PUB='$(inst "$n" PublicIpAddress)'"
    echo "export ${p}_STATE='$(inst "$n" State.Name)'"
  done
} >"$out"

# shellcheck disable=SC1090
. "$out"
printf '%-6s %-21s %-15s %-15s %s\n' NO ID IP_PRIVADO IP_PUBLICO ESTADO
for p in SRV DB NFS AG1 AG2 GEN; do
  eval "printf '%-6s %-21s %-15s %-15s %s\n' $p \"\${${p}_ID:--}\" \"\${${p}_PRIV:--}\" \"\${${p}_PUB:--}\" \"\${${p}_STATE:--}\""
done
echo
echo "VPC=$VPC_ID  subnet-publica=$SUBNET_PUB  SG-app=$SG_APP  conta=$ACCOUNT_ID"
echo "AMI Ubuntu 24.04: $AMI_ID"
echo "Gravado em $out"

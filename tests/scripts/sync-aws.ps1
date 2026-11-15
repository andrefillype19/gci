# Sincroniza esta maquina com a infraestrutura atual da AWS:
#   1. descobre/recebe o IP publico da EC2 da aplicacao;
#   2. grava observability/.env (usado pelo docker compose);
#   3. baixa o kubeconfig do k3s por SCP e gera .local/k3s/ (kubeconfig-host,
#      kubeconfig-container e os certificados usados pelo Prometheus).
#
# Rode de novo sempre que a EC2 da aplicacao for recriada ou receber outro IP.
#
# Exemplos:
#   .\scripts\sync-aws.ps1 -KeyPath C:\Users\andre\.ssh\key-gci.pem -AppIp 3.140.10.20
#   .\scripts\sync-aws.ps1            # reaproveita a chave do .env; descobre o IP com o AWS CLI

[CmdletBinding()]
param(
    [string]$AppIp,
    [string]$KeyPath,
    [string]$User = "ubuntu",
    [string]$DbIp = "",
    [string]$NfsIp = "",
    [string]$Region = "us-east-2"
)

$ErrorActionPreference = "Stop"
. "$PSScriptRoot\_common.ps1"

Assert-Command ssh "Instale o cliente OpenSSH do Windows."
Assert-Command scp "Instale o cliente OpenSSH do Windows."

$current = Read-DotEnv

# ---- chave SSH ----
if (-not $KeyPath) { $KeyPath = $current["SSH_KEY_PATH"] }
if (-not $KeyPath -or $KeyPath -like "*SEU_USUARIO*") {
    throw "Informe a chave SSH da EC2: -KeyPath C:\caminho\key-gci.pem"
}
if (-not (Test-Path $KeyPath)) { throw "Chave SSH nao encontrada: $KeyPath" }
$KeyPath = (Resolve-Path $KeyPath).Path

# ---- IP da aplicacao ----
if (-not $AppIp) {
    if (Get-Command aws -ErrorAction SilentlyContinue) {
        Write-Step "Descobrindo o IP publico da gci-app-server com o AWS CLI"
        $AppIp = (& aws ec2 describe-instances --region $Region `
            --filters "Name=tag:Name,Values=gci-app-server" "Name=instance-state-name,Values=running" `
            --query "Reservations[].Instances[].PublicIpAddress" --output text).Trim()
    }
}
if (-not $AppIp -or $AppIp -eq "None") {
    throw "Nao foi possivel descobrir o IP. Informe -AppIp (console AWS > EC2 > gci-app-server)."
}

if (-not $DbIp)  { $DbIp  = if ($current["DB_IP"])  { $current["DB_IP"] }  else { "10.0.2.153" } }
if (-not $NfsIp) { $NfsIp = if ($current.ContainsKey("NFS_IP")) { $current["NFS_IP"] } else { "" } }
$grafanaPassword = if ($current["GRAFANA_PASSWORD"]) { $current["GRAFANA_PASSWORD"] } else { "admin" }

# ---- .env ----
Write-Step "Gravando observability/.env"
$envText = @"
# Gerado por scripts/sync-aws.ps1 - nao versionar.
APP_IP=$AppIp
SSH_USER=$User
SSH_KEY_PATH=$($KeyPath -replace '\\','/')
DB_IP=$DbIp
NFS_IP=$NfsIp
GRAFANA_PASSWORD=$grafanaPassword
"@
Write-Utf8NoBom $script:EnvFile $envText
Write-Host "APP_IP=$AppIp  DB_IP=$DbIp  NFS_IP=$NfsIp"

# ---- kubeconfig ----
Write-Step "Baixando o kubeconfig do k3s ($User@$AppIp)"
New-Item -ItemType Directory -Force -Path $script:K3sDir | Out-Null
$knownHosts = Join-Path $env:TEMP "gci_known_hosts"

# Le o kubeconfig com "sudo cat": o k3s recria o arquivo com permissao 600 a cada reinicio,
# entao o scp direto falha. Avisos do ssh vao para stderr; no PowerShell 5.1 isso viraria
# erro com "Stop", por isso o ErrorActionPreference temporario.
$ErrorActionPreference = "Continue"
$yamlLines = & ssh -i $KeyPath -o StrictHostKeyChecking=no -o UserKnownHostsFile=$knownHosts -o LogLevel=ERROR `
    -o BatchMode=yes -o ConnectTimeout=15 "${User}@${AppIp}" "sudo cat /etc/rancher/k3s/k3s.yaml"
$sshExit = $LASTEXITCODE
$ErrorActionPreference = "Stop"
if ($sshExit -ne 0 -or -not $yamlLines) {
    throw "Falha ao ler o kubeconfig por SSH. Verifique: instancia running, porta 22 liberada para o seu IP, chave correta e se o k3s ja terminou de instalar (/var/log/user-data.log)."
}

$yaml = ($yamlLines | ForEach-Object { "$_" }) -join "`n"
function Get-YamlValue([string]$Text, [string]$Key) {
    $m = [regex]::Match($Text, "(?m)^\s*$([regex]::Escape($Key)):\s*(\S+)\s*$")
    if (-not $m.Success) { throw "Campo '$Key' nao encontrado no kubeconfig do k3s." }
    return $m.Groups[1].Value
}
$caB64   = Get-YamlValue $yaml "certificate-authority-data"
$certB64 = Get-YamlValue $yaml "client-certificate-data"
$keyB64  = Get-YamlValue $yaml "client-key-data"

[System.IO.File]::WriteAllBytes((Join-Path $script:K3sDir "ca.crt"),     [Convert]::FromBase64String($caB64))
[System.IO.File]::WriteAllBytes((Join-Path $script:K3sDir "client.crt"), [Convert]::FromBase64String($certB64))
[System.IO.File]::WriteAllBytes((Join-Path $script:K3sDir "client.key"), [Convert]::FromBase64String($keyB64))

function New-Kubeconfig([string]$Server, [string]$ExtraClusterLine) {
@"
apiVersion: v1
kind: Config
clusters:
- name: gci-k3s
  cluster:
    server: $Server
$ExtraClusterLine    certificate-authority-data: $caB64
users:
- name: gci-admin
  user:
    client-certificate-data: $certB64
    client-key-data: $keyB64
contexts:
- name: gci
  context:
    cluster: gci-k3s
    user: gci-admin
current-context: gci
"@
}

# O certificado do k3s cobre 127.0.0.1 e "localhost"; dentro do compose o host e "tunnel",
# por isso o kubeconfig dos containers fixa tls-server-name.
Write-Utf8NoBom $script:KubeHost      (New-Kubeconfig "https://127.0.0.1:6443" "")
Write-Utf8NoBom $script:KubeContainer (New-Kubeconfig "https://tunnel:6443" "    tls-server-name: localhost`n")

Write-Host ""
Write-Host "Pronto. Proximos passos:" -ForegroundColor Green
Write-Host "  .\scripts\stack.ps1 up        # sobe tunel, Prometheus, Loki, Alloy e Grafana"
Write-Host "  .\scripts\stack.ps1 status    # confere os alvos do Prometheus"
Write-Host "  `$env:KUBECONFIG = '$($script:KubeHost)'   # (opcional) usar kubectl neste terminal"

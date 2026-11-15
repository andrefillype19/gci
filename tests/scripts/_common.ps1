# Funcoes e caminhos compartilhados pelos scripts da suite. Uso: . "$PSScriptRoot\_common.ps1"

$script:TestsRoot     = Split-Path -Parent $PSScriptRoot
$script:ObsDir        = Join-Path $script:TestsRoot "observability"
$script:EnvFile       = Join-Path $script:ObsDir ".env"
$script:ComposeFile   = Join-Path $script:ObsDir "docker-compose.yml"
$script:LocalDir      = Join-Path $script:TestsRoot ".local"
$script:K3sDir        = Join-Path $script:LocalDir "k3s"
$script:KubeHost      = Join-Path $script:K3sDir "kubeconfig-host"
$script:KubeContainer = Join-Path $script:K3sDir "kubeconfig-container"
$script:ResultsDir    = Join-Path $script:TestsRoot "results"

function Write-Step([string]$Message) {
    Write-Host ""
    Write-Host "==> $Message" -ForegroundColor Cyan
}

function Assert-Command([string]$Name, [string]$Hint = "") {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "Comando '$Name' nao encontrado no PATH. $Hint"
    }
}

# Le observability/.env (CHAVE=valor) e devolve um hashtable.
function Read-DotEnv {
    $values = @{}
    if (-not (Test-Path $script:EnvFile)) { return $values }
    foreach ($line in Get-Content $script:EnvFile) {
        $text = $line.Trim()
        if ($text -eq "" -or $text.StartsWith("#")) { continue }
        $idx = $text.IndexOf("=")
        if ($idx -lt 1) { continue }
        $values[$text.Substring(0, $idx).Trim()] = $text.Substring($idx + 1).Trim()
    }
    return $values
}

function Assert-KubeConfig {
    if (-not (Test-Path $script:KubeHost)) {
        throw "kubeconfig local nao encontrado. Rode .\scripts\sync-aws.ps1 primeiro."
    }
}

# kubectl apontando para o k3s da AWS (via tunel em 127.0.0.1:6443).
# Nao altera o ~/.kube/config do usuario.
function Invoke-Kube {
    & kubectl --kubeconfig $script:KubeHost @args
}

function Write-Utf8NoBom([string]$Path, [string]$Text) {
    [System.IO.File]::WriteAllText($Path, $Text, (New-Object System.Text.UTF8Encoding($false)))
}

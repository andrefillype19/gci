# Gerencia a stack local (tunel SSH, Prometheus, Loki, Alloy, kube-state-metrics, Grafana).
#
#   .\scripts\stack.ps1 up        sobe (e reconstroi o tunel se o .env mudou)
#   .\scripts\stack.ps1 status    containers + saude dos alvos do Prometheus
#   .\scripts\stack.ps1 logs [servico]
#   .\scripts\stack.ps1 down      para os containers, MANTEM os dados
#   .\scripts\stack.ps1 wipe      para e APAGA volumes (metricas, logs, Grafana)

[CmdletBinding()]
param(
    [Parameter(Position = 0)]
    [ValidateSet("up", "down", "status", "logs", "wipe")]
    [string]$Action = "status",

    [Parameter(Position = 1)]
    [string]$Service = "",

    [switch]$Force
)

$ErrorActionPreference = "Stop"
. "$PSScriptRoot\_common.ps1"

Assert-Command docker "Instale o Docker Desktop."

function Invoke-Compose {
    # docker escreve progresso em stderr; no Windows PowerShell 5.1 isso viraria erro com "Stop".
    $previous = $ErrorActionPreference
    $ErrorActionPreference = "Continue"
    & docker compose -f $script:ComposeFile --env-file $script:EnvFile @args
    $code = $LASTEXITCODE
    $ErrorActionPreference = $previous
    if ($code -ne 0) { throw "docker compose falhou (codigo $code)." }
}

switch ($Action) {
    "up" {
        if (-not (Test-Path $script:EnvFile)) { throw "observability/.env nao existe. Rode .\scripts\sync-aws.ps1." }
        if (-not (Test-Path (Join-Path $script:K3sDir "ca.crt"))) { throw "Certificados do k3s ausentes. Rode .\scripts\sync-aws.ps1." }

        Write-Step "Subindo a stack (a primeira vez baixa as imagens)"
        Invoke-Compose up -d --build

        Write-Step "Aguardando o Grafana"
        $ready = $false
        for ($i = 0; $i -lt 40; $i++) {
            try {
                $h = Invoke-RestMethod -Uri "http://localhost:3000/api/health" -TimeoutSec 3
                if ($h.database -eq "ok") { $ready = $true; break }
            } catch { }
            Start-Sleep -Seconds 3
        }
        if (-not $ready) { Write-Warning "Grafana ainda nao respondeu; veja .\scripts\stack.ps1 logs grafana" }

        Write-Host ""
        Write-Host "Grafana    http://localhost:3000   (admin / senha do .env, padrao admin)" -ForegroundColor Green
        Write-Host "Prometheus http://localhost:9090"
        Write-Host "Alloy      http://localhost:12345"
        Write-Host "Confira os alvos com: .\scripts\stack.ps1 status"
    }

    "down" {
        Invoke-Compose down
        Write-Host "Stack parada. Os volumes (dados) foram mantidos." -ForegroundColor Green
    }

    "wipe" {
        if (-not $Force) {
            Write-Warning "Isto apaga TODAS as metricas, logs e dashboards editados no Grafana."
            $answer = Read-Host "Digite APAGAR para confirmar"
            if ($answer -ne "APAGAR") { Write-Host "Cancelado."; return }
        }
        Invoke-Compose down -v
        Write-Host "Stack parada e volumes apagados." -ForegroundColor Yellow
    }

    "logs" {
        if ($Service) { Invoke-Compose logs --tail 100 -f $Service } else { Invoke-Compose logs --tail 50 }
    }

    "status" {
        Write-Step "Containers"
        Invoke-Compose ps

        Write-Step "Alvos do Prometheus"
        try {
            $t = Invoke-RestMethod -Uri "http://localhost:9090/api/v1/targets" -TimeoutSec 5
            $t.data.activeTargets |
                Sort-Object { $_.labels.job } |
                ForEach-Object {
                    [pscustomobject]@{
                        job    = $_.labels.job
                        alvo   = $_.scrapeUrl
                        saude  = $_.health
                        erro   = if ($_.lastError) { $_.lastError.Substring(0, [Math]::Min(70, $_.lastError.Length)) } else { "" }
                    }
                } | Format-Table -AutoSize
            Write-Host "node-*/postgres ficam 'down' ate os exporters serem instalados nas EC2." -ForegroundColor DarkGray
        } catch {
            Write-Warning "Prometheus inacessivel em localhost:9090 (stack parada?)."
        }
    }
}

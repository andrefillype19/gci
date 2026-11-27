# Copia os resultados do gerador para o PC (scp). Rodar no PowerShell do PC.
# Uso:  .\fetch-results.ps1 -Ip <IP-publico-do-gerador> -Key <chave.pem> [-Dest .\resultados-rodada2] [-Run S1-r1]
# Sem -Run copia ~/results inteiro. Pode ser rodado de novo (scp -r sobrescreve).
param(
  [Parameter(Mandatory = $true)][string]$Ip,
  [Parameter(Mandatory = $true)][string]$Key,
  [string]$Dest = ".\resultados-rodada2",
  [string]$Run = "",
  [string]$User = "ubuntu"
)
New-Item -ItemType Directory -Force $Dest | Out-Null
$src = if ($Run) { "~/results/$Run" } else { "~/results" }
& scp -i $Key -o StrictHostKeyChecking=no -r "${User}@${Ip}:$src" $Dest
if ($LASTEXITCODE -ne 0) { Write-Error "scp falhou ($LASTEXITCODE)"; exit $LASTEXITCODE }
Write-Host "Copiado para $Dest"
Get-ChildItem -Recurse $Dest | Measure-Object Length -Sum | ForEach-Object { "{0} arquivos, {1:N1} MB" -f $_.Count, ($_.Sum / 1MB) }

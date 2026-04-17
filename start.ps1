param(
    [int]$Port = 8080
)

$scriptPath = Join-Path $PSScriptRoot "scripts\start.ps1"
if (!(Test-Path $scriptPath)) {
    Write-Host "Missing scripts/start.ps1" -ForegroundColor Red
    exit 1
}

& $scriptPath -Port $Port

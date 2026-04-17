param(
    [int]$Port = 8080
)

$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $projectRoot

$phpCommand = Get-Command php -ErrorAction SilentlyContinue
if ($null -eq $phpCommand) {
    Write-Host "PHP not found in PATH." -ForegroundColor Red
    Write-Host "Install PHP and reopen terminal, or add php.exe to PATH." -ForegroundColor Yellow
    exit 1
}

if (!(Test-Path ".env")) {
    if (Test-Path ".env.example") {
        Copy-Item ".env.example" ".env"
        Write-Host "Created .env from .env.example. Please update SMTP values before sending email." -ForegroundColor Yellow
    } else {
        Write-Host "Missing .env and .env.example files." -ForegroundColor Red
        exit 1
    }
}

if (!(Test-Path "vendor")) {
    Write-Host "Missing vendor/ folder. Run: composer install" -ForegroundColor Yellow
}

$legacyDir = Join-Path $projectRoot "public\submissions"
$privateDir = Join-Path $projectRoot "storage\submissions"
if (!(Test-Path $privateDir)) {
    New-Item -ItemType Directory -Path $privateDir -Force | Out-Null
}
if (Test-Path $legacyDir) {
    Get-ChildItem -Path $legacyDir -File | ForEach-Object {
        $target = Join-Path $privateDir $_.Name
        if (!(Test-Path $target)) {
            Move-Item -Path $_.FullName -Destination $target -Force
        }
    }
}

Write-Host "Starting PHP server at http://localhost:$Port" -ForegroundColor Green
php -S "localhost:$Port" -t public

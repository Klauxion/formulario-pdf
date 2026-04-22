param(
    [int]$Port = 8080
)

Write-Host "Starting PHP development server on http://127.0.0.1:$Port" -ForegroundColor Green
Write-Host "Press Ctrl+C to stop" -ForegroundColor Yellow
Write-Host ""

Set-Location $PSScriptRoot
php -S 127.0.0.1:$Port -t public

Write-Host ""
Write-Host "Server stopped." -ForegroundColor Yellow

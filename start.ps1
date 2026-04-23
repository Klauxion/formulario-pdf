param(
    [int]$Port = 8080
)

Write-Host "Starting PHP development server on http://127.0.0.1:$Port" -ForegroundColor Green
Write-Host "Press Ctrl+C to stop" -ForegroundColor Yellow
Write-Host ""

Set-Location $PSScriptRoot

$phpCmd = (Get-Command php -ErrorAction SilentlyContinue)
if ($phpCmd) {
    $phpExe = $phpCmd.Source
} else {
    $phpExe = Join-Path $env:LOCALAPPDATA "Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
    if (-not (Test-Path $phpExe)) {
        throw "PHP not found. Install PHP 8.1+ and ensure 'php' is on PATH, or reinstall via winget."
    }
}

& $phpExe -S 127.0.0.1:$Port -t public

Write-Host ""
Write-Host "Server stopped." -ForegroundColor Yellow

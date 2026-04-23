@echo off
setlocal

echo.
echo Starting PHP development server on http://127.0.0.1:8080
echo Press Ctrl+C to stop
echo.

cd /d "%~dp0"

set "PHP_EXE=php"
where php >nul 2>nul
if errorlevel 1 (
  set "PHP_EXE=%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
)

"%PHP_EXE%" -S 127.0.0.1:8080 -t public

echo.
echo Server stopped.
pause

@echo off
setlocal

echo.
echo Starting PHP development server on http://127.0.0.1:8080
echo Press Ctrl+C to stop
echo.

cd /d "%~dp0"
php -S 127.0.0.1:8080 -t public

echo.
echo Server stopped.
pause

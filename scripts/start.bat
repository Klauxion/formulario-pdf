@echo off
setlocal

set SCRIPT_DIR=%~dp0
for %%I in ("%SCRIPT_DIR%\..") do set PROJECT_ROOT=%%~fI
cd /d "%PROJECT_ROOT%"

where php >nul 2>nul
if errorlevel 1 (
  echo PHP not found in PATH.
  echo Install PHP and reopen terminal, or add php.exe to PATH.
  exit /b 1
)

if not exist ".env" (
  if exist ".env.example" (
    copy /y ".env.example" ".env" >nul
    echo Created .env from .env.example. Update SMTP values before sending email.
  ) else (
    echo Missing .env and .env.example files.
    exit /b 1
  )
)

if not exist "vendor" (
  echo Missing vendor/ folder. Run: composer install
)

if not exist "storage\submissions" mkdir "storage\submissions"
if exist "public\submissions" (
  for %%F in ("public\submissions\*") do (
    if not "%%~nxF"==".gitkeep" (
      if not exist "storage\submissions\%%~nxF" (
        move /y "%%~fF" "storage\submissions\%%~nxF" >nul
      )
    )
  )
)

set PORT=%1
if "%PORT%"=="" set PORT=8080

echo Starting PHP server at http://localhost:%PORT%
php -S localhost:%PORT% -t public

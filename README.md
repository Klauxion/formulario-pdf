# Val do Rio Formulario v1.4

Online enrollment form for Escola Profissional Val do Rio.

## What this project does

- Shows an enrollment form in the browser.
- Sends form data to PHP backend endpoints.
- Generates a PDF for each submission.
- Sends the PDF by email via SMTP.
- Saves submission metadata (including ID and IP) for admin view.

## First-time setup (Windows / PowerShell)

### 1) Open terminal in project folder

```powershell
cd "c:\Users\A_TET_TGEI\Documents\_0_Keenan\Keenan_Form_Project\valdoRio-formulario-v1.4"
```

### 2) Install PHP (if not installed yet)

Try:

```powershell
winget install --id PHP.PHP.8.3 -e
```

If this fails in your machine, use the already known local path:

```powershell
$phpDir = "C:\Users\A_TET_TGEI\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe"
```

### 3) Ensure PHP has a config file (`php.ini`)

```powershell
Copy-Item "$phpDir\php.ini-development" "$phpDir\php.ini" -Force
```

### 4) Enable required PHP extensions

```powershell
(Get-Content "$phpDir\php.ini") `
  -replace '^;extension_dir = "ext"', 'extension_dir = "ext"' `
  -replace '^;extension=mbstring', 'extension=mbstring' `
  -replace '^;extension=openssl', 'extension=openssl' `
  | Set-Content "$phpDir\php.ini"
```

### 5) Verify PHP and modules

```powershell
& "$phpDir\php.exe" --version
& "$phpDir\php.exe" -m
```

Make sure module list contains:

- `mbstring`
- `openssl`

### 6) Install PHP dependencies (Composer)

If `vendor/` does not exist or packages are missing, run:

```powershell
php composer.phar install
```

If `php` command is not global yet, use:

```powershell
& "$phpDir\php.exe" composer.phar install
```

### 7) Configure SMTP email

Edit `public/smtp-config.php`:

- `username` = your real sender mailbox
- `password` = valid app password (for Gmail, use Google App Password)
- `from_email` = same sender mailbox
- keep host/port/secure aligned with your provider

### 8) Start server

```powershell
& "$phpDir\php.exe" -S localhost:8080 -t public
```

Open:

- http://localhost:8080

## Daily run (after setup)

```powershell
cd "c:\Users\A_TET_TGEI\Documents\_0_Keenan\Keenan_Form_Project\valdoRio-formulario-v1.4"
$phpDir = "C:\Users\A_TET_TGEI\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe"
& "$phpDir\php.exe" -S localhost:8080 -t public
```

## If port 8080 is busy

```powershell
& "$phpDir\php.exe" -S localhost:8081 -t public
```

Then open:

- http://localhost:8081

## Optional: enable global `php` command

```powershell
setx PATH "$env:PATH;$phpDir"
```

Close and reopen terminal, then test:

```powershell
php --version
```

## Notes

- Do not open `index.html` with `file://`; always use `http://localhost:PORT`.
- Backend endpoints are inside `public/` (submit/admin/PDF/email flow).

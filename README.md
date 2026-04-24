# Val do Rio Formulario v1.4

Online enrollment form for Escola Profissional Val do Rio.

## What this project does

- Shows an enrollment form in the browser.
- Sends form data to PHP backend endpoints.
- Generates a PDF for each submission.
- Sends the PDF by email via SMTP.
- Saves submission metadata (including ID and IP) for admin view.

## Quick start (recommended)

1. Install PHP 8.1+ and Composer.
2. In project root, install dependencies:

```powershell
composer install
```

If you don't have the `composer` command, you can use the bundled `composer.phar`:

```powershell
php .\composer.phar install
```

3. Create your env file:

```powershell
Copy-Item .env.example .env
```

4. Edit `.env` with your SMTP values (see `docs/EMAIL_SETUP.md`) and set `ADMIN_PASSWORD`.
5. Start server:

```powershell
.\start.ps1
```

6. Open [http://localhost:8080](http://localhost:8080).

## One-command run options

- PowerShell: `.\start.ps1`
- Command Prompt: `start.bat`
- Custom port: `.\start.ps1 -Port 8081` or `start.bat 8081`

## Project structure

- `public/` web root and PHP endpoints
- `scripts/` startup helpers
- `docs/` setup documentation
- `storage/submissions/` private stored PDFs and JSONL data
- `.env.example` env template (copy to `.env`)

## Why not Live Server?

Live Server only serves static files. This project needs PHP (`public/submit.php`) to generate PDF and send email via SMTP.

Always run through PHP server, not `file://` and not Live Server.

## First-time setup (Windows, if `php` is missing)

Install PHP with winget:

```powershell
winget install --id PHP.PHP.8.3 -e
```

If `php` still does not work in terminal, restart terminal or add PHP to PATH.

## Optional: Python helper scripts

Python analysis/calibration tooling was moved out of this repository to keep the production app PHP-only.

If you want to use those tools later, use the separate repository:

- `..\formulario-pdf-python-tools`

## Notes

- Backend endpoints are inside `public/` (submit/admin/PDF/email flow).
- Keep `.env` private. Do not share it in commits or screenshots.

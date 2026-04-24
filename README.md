# Val do Rio Formulario

Simple PHP web app for school enrollment:
- form in browser
- PDF generation from template
- email delivery via SMTP
- admin area for submissions

## Requirements

- PHP 8.1+
- Composer
- Web server with document root set to `public/`

## 1) Local setup

From project root:

```powershell
composer install
Copy-Item .env.example .env
```

If `composer` is not in PATH:

```powershell
php .\composer.phar install
```

Edit `.env` with real values:

```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
SMTP_FROM_EMAIL=your-email@gmail.com
SMTP_FROM_NAME=Val do Rio Form
PDF_TEMPLATE_PATH=C:\absolute\path\to\MDDPE1406_Ficha_Candidatura_r0_fixed.pdf
ADMIN_PASSWORD=change-this-password
```

## 2) Run locally

PowerShell:

```powershell
.\start.ps1
```

Or CMD:

```bat
start.bat
```

Then open `http://127.0.0.1:8080`.

## 3) Deploy on server (production)

1. Upload project files to server.
2. Run `composer install --no-dev --optimize-autoloader`.
3. Create `.env` on server (same keys as above).
4. Set `PDF_TEMPLATE_PATH` to an absolute path valid on the server.
5. Configure web server docroot to `public/`.
6. Ensure PHP can write to `storage/submissions/`.
7. Protect `.env` (never public), and use a strong `ADMIN_PASSWORD`.
8. Test full flow: submit form -> PDF generated -> email sent -> admin can view.

## Notes

- Do not use static Live Server for this project. It needs PHP backend execution.
- Keep secrets out of git (`.env`, SMTP passwords).
- Optional Python mapping/calibration tools live in a separate repo: [python-mapping-tools](https://github.com/Klauxion/python-mapping-tools).

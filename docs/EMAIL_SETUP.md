# Email setup (PHPMailer + Gmail SMTP)

This project reads SMTP settings from `.env` (project root), not hardcoded PHP values.

## Steps

1. Copy `.env.example` to `.env` in project root.
2. Fill these variables in `.env`:

```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-16-char-app-password
SMTP_FROM_EMAIL=your-email@gmail.com
SMTP_FROM_NAME=Val do Rio Form
ADMIN_PASSWORD=change-this-admin-password
```

3. Start server with `.\start.ps1`.
4. Submit the form and confirm email delivery.

## Create Gmail App Password

1. Go to Google Account Security.
2. Enable 2-Step Verification.
3. Open App Passwords.
4. Create an app password for Mail.
5. Put it in `SMTP_PASSWORD`.

## Notes

- Normal Gmail account password does not work for SMTP in this setup.
- Keep `.env` private and never commit it.
- If email fails, the frontend shows SMTP error details returned by backend.

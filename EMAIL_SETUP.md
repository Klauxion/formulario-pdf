# Email setup (PHPMailer + Gmail SMTP)

1. Open `public/smtp-config.php`.
2. Set `password` to a Gmail App Password (16 chars).
3. Start server and test form submit.

## Create Gmail App Password

1. Go to your Google account security page.
2. Enable 2-Step Verification.
3. Open App Passwords.
4. Create a password for "Mail".
5. Copy that value into `public/smtp-config.php` as `password`.

## Notes

- Normal Gmail account password will not work here.
- If email still fails, the frontend alert now shows the SMTP error text.

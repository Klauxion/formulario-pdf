<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function env($key, $default = null) {
    return $_ENV[$key] ?? $default;
}

function processSubmission(array $data, string $pdfBinary, string $pdfName): array
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = env('SMTP_HOST');
        $mail->SMTPAuth = true;
        $mail->Username = env('SMTP_USERNAME'); // FIXED
        $mail->Password = env('SMTP_PASSWORD'); // FIXED
        $mail->SMTPSecure = env('SMTP_SECURE', 'tls');
        $mail->Port = (int) env('SMTP_PORT', 587);

        $mail->CharSet = 'UTF-8';

        $mail->setFrom(env('SMTP_FROM_EMAIL'), env('SMTP_FROM_NAME'));
        $mail->addAddress($data['email']); // IMPORTANT: correct key

        $mail->Subject = 'Formulario recebido';
        $mail->Body = 'O seu formulário foi processado com sucesso.';

        // ✅ THIS IS WHAT YOU WERE MISSING
        $mail->addStringAttachment(
            $pdfBinary,
            $pdfName,
            PHPMailer::ENCODING_BASE64,
            'application/pdf'
        );

        $mail->send();

        return ['email_sent' => true];

    } catch (Exception $e) {
        return [
            'email_sent' => false,
            'email_error' => $mail->ErrorInfo
        ];
    }
}
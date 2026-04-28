<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Public API used by the front controller (`public/index.php`).
 *
 * @return array{ok:bool,message:string,status?:int,email_sent?:bool,email_error?:?string,pdf_filename?:string,submission_id?:string,client_ip?:string}
 */
function submitForm(array $postData): array
{
    ini_set('display_errors', '0');
    error_reporting(E_ALL);

    $smtpConfig = require __DIR__ . '/../config/smtp.php';

    // Remove internal fields
    unset($postData['csrf_token']);

    $formData = normalizeFormData($postData);

    $validation = validateFormData($formData);
    if ($validation['ok'] === false) {
        return $validation;
    }

    if (!is_array($smtpConfig)
        || empty($smtpConfig['host'])
        || empty($smtpConfig['port'])
        || empty($smtpConfig['username'])
        || empty($smtpConfig['password'])
        || empty($smtpConfig['secure'])
        || empty($smtpConfig['from_email'])
        || empty($smtpConfig['from_name'])
    ) {
        return [
            'ok' => false,
            'status' => 500,
            'message' => 'Configuração SMTP inválida. Defina as variáveis SMTP_* no ficheiro .env.',
        ];
    }

    $recipientEmail = firstNonEmptyValue($formData, ['Email', 'email']);
    $studentFirstName = firstNonEmptyValue($formData, ['Primeiro Nome', 'Primeiro_Nome', 'primeiro_nome']);
    $studentLastName = firstNonEmptyValue($formData, ['Último Nome', 'Último_Nome', 'Ultimo Nome', 'Ultimo_Nome', 'ultimo_nome', 'Ãšltimo Nome']);
    $studentName = trim($studentFirstName . ' ' . $studentLastName);

    $baseName = sanitizeFilename($studentName);
    $pdfFilename = $baseName . '_' . date('Ymd_His') . '.pdf';
    $clientIp = normalizeClientIp($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $submissionId = buildSubmissionId();

    try {
        $pdfBinary = buildPdfFromFormData($formData);
        if ($pdfBinary === false) {
            return [
                'ok' => false,
                'status' => 500,
                'message' => 'Não foi possível gerar o PDF no servidor.',
            ];
        }

        $subject = 'Nova inscrição - ' . ($studentName !== '' ? $studentName : 'Sem nome');
        $message = "Nova inscrição recebida.\n\n";
        $message .= 'ID: ' . $submissionId . "\n";
        $message .= 'Nome: ' . ($studentName !== '' ? $studentName : '-') . "\n";
        $message .= 'Email: ' . $recipientEmail . "\n";
        $message .= 'Curso Pretendido: ' . (string)($formData['Curso Pretendido'] ?? '-') . "\n";
        $message .= 'Data/Hora: ' . date('Y-m-d H:i:s') . "\n";

        $emailError = null;
        $emailSent = sendEmailWithAttachment($smtpConfig, $recipientEmail, $subject, $message, $pdfFilename, $pdfBinary, $emailError);
        if (!$emailSent) {
            return [
                'ok' => false,
                'status' => 400,
                'message' => 'Não foi possível enviar o email. Verifique o endereço e tente novamente.',
                'email_sent' => false,
                'email_error' => $emailError,
            ];
        }

        $storageDir = getStorageDir();
        if (!is_dir($storageDir) && !mkdir($storageDir, 0777, true) && !is_dir($storageDir)) {
            return [
                'ok' => false,
                'status' => 500,
                'message' => 'Não foi possível criar a pasta de dados.',
                'email_sent' => true,
                'email_error' => $emailError,
            ];
        }

        $pdfPath = $storageDir . DIRECTORY_SEPARATOR . $pdfFilename;
        if (file_put_contents($pdfPath, $pdfBinary, LOCK_EX) === false) {
            return [
                'ok' => false,
                'status' => 500,
                'message' => 'Email enviado, mas falhou ao guardar o PDF no servidor.',
                'email_sent' => true,
                'email_error' => $emailError,
            ];
        }

        $entry = [
            'submission_id' => $submissionId,
            'timestamp' => date('c'),
            'ip' => $clientIp,
            'client_ip' => $clientIp,
            'data' => $formData,
            'pdf_file' => $pdfFilename,
        ];

        $filename = $storageDir . DIRECTORY_SEPARATOR . 'inscricoes.jsonl';
        $result = file_put_contents($filename, json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($result === false) {
            return [
                'ok' => false,
                'status' => 500,
                'message' => 'Email enviado, PDF guardado, mas falhou ao registar a inscrição.',
                'email_sent' => true,
                'email_error' => $emailError,
                'pdf_filename' => $pdfFilename,
                'submission_id' => $submissionId,
                'client_ip' => $clientIp,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Inscrição guardada com sucesso.',
            'email_sent' => true,
            'email_error' => $emailError,
            'pdf_filename' => $pdfFilename,
            'submission_id' => $submissionId,
            'client_ip' => $clientIp,
        ];
    } catch (\Throwable $e) {
        return [
            'ok' => false,
            'status' => 500,
            'message' => 'Erro interno ao processar o formulário.',
        ];
    }
}

function normalizeFormData(array $data): array
{
    $out = [];
    foreach ($data as $k => $v) {
        $key = trim((string)$k);
        if ($key === '') {
            continue;
        }
        if (is_array($v)) {
            $v = implode(', ', array_map(static fn($x) => trim((string)$x), $v));
        }
        $value = trim((string)$v);
        // Hard limit to avoid abuse / huge payloads
        if (strlen($value) > 2000) {
            $value = substr($value, 0, 2000);
        }
        $out[$key] = $value;

        // PHP form parsing may convert spaces in field names to underscores.
        // Keep a space-variant alias so downstream code can read canonical labels.
        if (str_contains($key, '_')) {
            $keyWithSpaces = str_replace('_', ' ', $key);
            if ($keyWithSpaces !== $key && !array_key_exists($keyWithSpaces, $out)) {
                $out[$keyWithSpaces] = $value;
            }
        }
    }
    return $out;
}

/**
 * @return array{ok:bool,message:string,status?:int}
 */
function validateFormData(array $formData): array
{
    $email = firstNonEmptyValue($formData, ['Email', 'email']);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'status' => 400, 'message' => 'Email do candidato inválido.'];
    }

    $first = firstNonEmptyValue($formData, ['Primeiro Nome', 'Primeiro_Nome', 'primeiro_nome']);
    $last = firstNonEmptyValue($formData, ['Último Nome', 'Último_Nome', 'Ultimo Nome', 'Ultimo_Nome', 'ultimo_nome', 'Ãšltimo Nome']);
    if ($first === '' || $last === '') {
        return ['ok' => false, 'status' => 400, 'message' => 'Nome do candidato inválido.'];
    }

    $curso = firstNonEmptyValue($formData, ['Curso Pretendido', 'Curso_Pretendido', 'curso_pretendido']);
    if ($curso === '') {
        return ['ok' => false, 'status' => 400, 'message' => 'Selecione o curso pretendido.'];
    }

    return ['ok' => true, 'message' => 'OK'];
}

function firstNonEmptyValue(array $data, array $keys): string
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $data)) {
            continue;
        }
        $value = trim((string)$data[$key]);
        if ($value !== '') {
            return $value;
        }
    }
    return '';
}

function toPdfText(string $value): string
{
    return mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
}

function sanitizeFilename(string $value): string
{
    $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($normalized === false) {
        $normalized = $value;
    }

    $safe = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $normalized);
    $safe = trim((string)$safe, '_');

    return $safe !== '' ? strtolower($safe) : 'inscricao';
}

function buildSubmissionId(): string
{
    return date('YmdHis') . '-' . bin2hex(random_bytes(4));
}

function normalizeClientIp(?string $ip): string
{
    $value = trim((string)$ip);
    if ($value === '' || strtolower($value) === 'unknown') {
        return 'unknown';
    }

    if ($value === '::1') {
        return '127.0.0.1';
    }

    if (str_starts_with($value, '::ffff:')) {
        $mappedIpv4 = substr($value, 7);
        if (filter_var($mappedIpv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $mappedIpv4;
        }
    }

    return $value;
}

function getStorageDir(): string
{
    $projectRoot = dirname(__DIR__);
    $preferredDir = $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'submissions';
    return $preferredDir;
}

function writeField(\setasign\Fpdi\Fpdi $pdf, float $x, float $y, array $formData, string $key, float $boxWidth = 80): void
{
    $val = '';
    if ($key === 'Nome Completo') {
        $first = trim((string)($formData['Primeiro Nome'] ?? ''));
        $last = trim((string)($formData['Último Nome'] ?? ''));
        $val = trim($first . ' ' . $last);
    } elseif ($key === 'Concelho Freguesia') {
        $conc = trim((string)($formData['Concelho'] ?? ''));
        $freg = trim((string)($formData['Freguesia'] ?? ''));
        if ($conc !== '' && $freg !== '') {
            $val = $conc . ' / ' . $freg;
        } else {
            $val = $conc . $freg;
        }
    } else {
        $val = isset($formData[$key]) ? trim((string)$formData[$key]) : '';
    }

    $boxHeight = 5.2;
    $boxYOffset = 0.4;
    $textLeftPadding = 2.0;
    $textTopOffset = 0.0;

    $pdf->SetFillColor(200, 220, 255);
    $pdf->SetDrawColor(200, 220, 255);
    $pdf->SetLineWidth(0);
    $pdf->Rect($x, $y - $boxYOffset, $boxWidth, $boxHeight, 'FD');

    $pdf->SetXY($x + $textLeftPadding, $y - $textTopOffset);
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    if ($val !== '') {
        $pdf->Cell($boxWidth - ($textLeftPadding * 2), 4.8, toPdfText($val), 0, 0, 'L');
    }
}

function buildPdfFromFormData(array $formData): string|false
{
    $pdf = new \setasign\Fpdi\Fpdi();
    $templatePath = trim((string)getenv('PDF_TEMPLATE_PATH'));
    if ($templatePath === '' || !is_file($templatePath)) {
        $templatePath = __DIR__ . '/../assets/basePDF_image/MDDPE1406_Ficha_Candidatura_r0_fixed.pdf';
    }

    if (!is_file($templatePath)) {
        return false;
    }

    $pageCount = $pdf->setSourceFile($templatePath);
    if ($pageCount < 2) {
        return false;
    }

    // ── PAGE 1 ──
    $tpl = $pdf->importPage(1);
    $pdf->AddPage();
    $pdf->useTemplate($tpl, 0, 0, 210, 297);

    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 0, 0);

    writeField($pdf, 140.0, 15.5, $formData, 'Ano Letivo Início', 22);
    writeField($pdf, 172.0, 15.5, $formData, 'Ano Letivo Fim', 22);
    writeField($pdf, 170.0, 24.4, $formData, 'Candidatura n.º', 25);
    writeField($pdf, 118.0, 31.7, $formData, 'Curso Pretendido', 65);

    writeField($pdf, 25.0, 71.3, $formData, 'Nome Completo', 167);
    writeField($pdf, 34.0, 79.7, $formData, 'Data de Nascimento', 60);
    writeField($pdf, 122.0, 80.2, $formData, 'Nacionalidade', 70);
    writeField($pdf, 45.0, 88.2, $formData, 'Nacionalidade', 49);
    writeField($pdf, 140.0, 87.5, $formData, 'Concelho Freguesia', 52);
    writeField($pdf, 32.0, 95.1, $formData, 'BI-CC', 40);
    writeField($pdf, 91.0, 95.2, $formData, 'Data de validade do Documento', 101);
    writeField($pdf, 21.0, 102.7, $formData, 'NIF', 40);
    writeField($pdf, 28.0, 112.2, $formData, 'Rua', 164);
    writeField($pdf, 34.0, 122.2, $formData, 'Cidade', 60);
    writeField($pdf, 107.0, 122.7, $formData, 'Código Postal', 85);
    writeField($pdf, 31.0, 130.7, $formData, 'Telefone', 67);
    writeField($pdf, 118.0, 131.3, $formData, 'Telemóvel', 45);
    writeField($pdf, 26.0, 139.2, $formData, 'Email', 64);
    writeField($pdf, 126.0, 139.8, $formData, 'Último Ano de Frequência', 66);

    writeField($pdf, 74.0, 210.6, $formData, 'Escola Anterior', 118);
    writeField($pdf, 74.0, 219.9, $formData, 'Escola 2º Ciclo', 118);
    writeField($pdf, 74.0, 229.2, $formData, 'Escola 3º Ciclo', 118);
    writeField($pdf, 48.0, 238.5, $formData, 'Escola Secundário', 144);

    // ── PAGE 2 ──
    $tpl2 = $pdf->importPage(2);
    $pdf->AddPage();
    $pdf->useTemplate($tpl2, 0, 0, 210, 297);

    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 0, 0);

    writeField($pdf, 33.0, 22.1, $formData, 'Nome do Pai', 157);
    writeField($pdf, 30.0, 31.1, $formData, 'Telemóvel do Pai', 81);
    writeField($pdf, 126.0, 31.1, $formData, 'Email do Pai', 64);

    writeField($pdf, 33.0, 49.4, $formData, 'Nome da Mãe', 157);
    writeField($pdf, 30.0, 59.0, $formData, 'Telemóvel da Mãe', 81);
    writeField($pdf, 126.0, 59.0, $formData, 'Email da Mãe', 64);

    writeField($pdf, 30.0, 113.3, $formData, 'Nome do Encarregado', 160);
    writeField($pdf, 28.0, 120.6, $formData, 'Morada do Encarregado', 100);
    writeField($pdf, 150.0, 120.9, $formData, 'Localidade do Encarregado', 40);
    writeField($pdf, 37.0, 127.6, $formData, 'Código Postal do Encarregado', 55);
    writeField($pdf, 29.0, 134.5, $formData, 'Telefone do Encarregado', 60);
    writeField($pdf, 118.0, 135.4, $formData, 'Telemóvel do Encarregado', 72);
    writeField($pdf, 24.0, 142.4, $formData, 'Email do Encarregado', 166);
    writeField($pdf, 54.0, 150.5, $formData, 'Habilitações do Encarregado', 136);
    writeField($pdf, 54.0, 158.0, $formData, 'Relação do Candidato', 136);

    $autoriza = isset($formData['autoriza_dados']) ? trim((string)$formData['autoriza_dados']) : '';
    if ($autoriza === 'Sim' || $autoriza === '1' || $autoriza === 'true' || $autoriza === 'on' || $autoriza === true) {
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.5);
        $pdf->Rect(188.0, 186.0, 5, 5);

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(188.0, 185.8);
        $pdf->Cell(5, 5, 'X', 0, 0, 'C');
    }

    $pdfContent = $pdf->Output('S');
    return is_string($pdfContent) && $pdfContent !== '' ? $pdfContent : false;
}

function sendEmailWithAttachment(array $config, string $recipientEmail, string $subject, string $message, string $attachmentName, string $attachmentBinary, ?string &$mailError = null): bool
{
    $mailError = null;
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = (string)$config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = (string)$config['username'];
        $mail->Password = (string)$config['password'];
        $mail->SMTPSecure = (string)$config['secure'];
        $mail->Port = (int)$config['port'];
        $mail->CharSet = 'UTF-8';

        $mail->setFrom((string)$config['from_email'], (string)$config['from_name']);
        $mail->addAddress($recipientEmail);

        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->addStringAttachment($attachmentBinary, $attachmentName, PHPMailer::ENCODING_BASE64, 'application/pdf');

        return $mail->send();
    } catch (Exception $e) {
        $mailError = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
        return false;
    }
}


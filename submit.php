<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../vendor/autoload.php';
$smtpConfig = require __DIR__ . '/smtp-config.php';

ini_set('display_errors', '0');
error_reporting(E_ALL);





function toPdfText(string $value): string
{
    return mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
}

function safeValue(array $formData, string $key): string
{
    $value = isset($formData[$key]) ? trim((string)$formData[$key]) : '';
    return $value !== '' ? $value : '-';
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

function jsonError(int $statusCode, string $message, array $extra = []): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge(['ok' => false, 'message' => $message], $extra));
    exit;
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

function writeField(\setasign\Fpdi\Fpdi $pdf, float $x, float $y, array $formData, string $key): void
{
    // Special handling for compound fields
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
            $val = $conc . $freg; // one or both empty
        }
    } else {
        $val = isset($formData[$key]) ? trim((string)$formData[$key]) : '';
    }

    if ($val === '') {
        return;
    }

    $pdf->SetXY($x, $y);
    $pdf->Cell(0, 5, toPdfText($val), 0, 0, 'L');
}

function buildPdfFromFormData(array $formData): string|false
{
    $pdf = new \setasign\Fpdi\Fpdi();
    $templatePath = __DIR__ . '/basePDF_image/MDDPE1406_Ficha_Candidatura_r0_fixed.pdf';

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

    writeField($pdf, 170.0, 24.4, $formData, 'Candidatura n.º');
    writeField($pdf, 118.0, 31.7, $formData, 'Curso Pretendido');
    
    writeField($pdf, 25.0, 71.3, $formData, 'Nome Completo');
    writeField($pdf, 34.0, 79.7, $formData, 'Data de Nascimento');
    writeField($pdf, 122.0, 80.2, $formData, 'Nacionalidade');
    writeField($pdf, 45.0, 88.2, $formData, 'Nacionalidade'); // Naturalidade(País)
    writeField($pdf, 140.0, 87.5, $formData, 'Concelho Freguesia');
    writeField($pdf, 32.0, 95.1, $formData, 'BI-CC');
    writeField($pdf, 91.0, 95.2, $formData, 'Data de validade do Documento');
    writeField($pdf, 21.0, 102.7, $formData, 'NIF');
    writeField($pdf, 28.0, 112.2, $formData, 'Rua');
    writeField($pdf, 34.0, 122.2, $formData, 'Cidade');
    writeField($pdf, 107.0, 122.7, $formData, 'Código Postal');
    writeField($pdf, 31.0, 130.7, $formData, 'Telefone');
    writeField($pdf, 118.0, 131.3, $formData, 'Telemóvel');
    writeField($pdf, 26.0, 139.2, $formData, 'Email');
    writeField($pdf, 126.0, 139.8, $formData, 'Último Ano de Frequência');

    writeField($pdf, 71.0, 210.6, $formData, 'Escola Anterior');
    writeField($pdf, 71.0, 219.9, $formData, 'Escola 2º Ciclo');
    writeField($pdf, 71.0, 229.2, $formData, 'Escola 3º Ciclo');
    writeField($pdf, 47.0, 238.5, $formData, 'Escola Secundário');

    // ── PAGE 2 ──
    $tpl2 = $pdf->importPage(2);
    $pdf->AddPage();
    $pdf->useTemplate($tpl2, 0, 0, 210, 297);
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 0, 0);

    writeField($pdf, 32.0, 22.1, $formData, 'Nome do Pai');
    writeField($pdf, 30.0, 31.9, $formData, 'Telemóvel do Pai');
    writeField($pdf, 125.0, 31.1, $formData, 'Email do Pai');
    
    writeField($pdf, 34.0, 49.4, $formData, 'Nome da Mãe');
    writeField($pdf, 32.0, 59.4, $formData, 'Telemóvel da Mãe');
    writeField($pdf, 126.0, 59.0, $formData, 'Email da Mãe');

    writeField($pdf, 30.0, 113.3, $formData, 'Nome do Encarregado');
    writeField($pdf, 28.0, 120.6, $formData, 'Morada do Encarregado');
    writeField($pdf, 150.0, 120.9, $formData, 'Localidade do Encarregado');
    writeField($pdf, 37.0, 127.6, $formData, 'Código Postal do Encarregado');
    writeField($pdf, 29.0, 134.5, $formData, 'Telefone do Encarregado');
    writeField($pdf, 118.0, 135.4, $formData, 'Telemóvel do Encarregado');
    writeField($pdf, 24.0, 142.4, $formData, 'Email do Encarregado');
    writeField($pdf, 54.0, 150.5, $formData, 'Habilitações do Encarregado');
    writeField($pdf, 54.0, 158.0, $formData, 'Relação do Candidato');

    // Checkbox for data consent
    $autoriza = isset($formData['autoriza_dados']) ? trim((string)$formData['autoriza_dados']) : '';
    if ($autoriza === 'Sim' || $autoriza === '1' || $autoriza === 'true' || $autoriza === 'on' || $autoriza === true) {
        $pdf->SetFont('Arial', 'B', 12);
        // The checkmark box is roughly at x=13, y=186.5
        $pdf->SetXY(13.0, 186.5);
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

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError(405, 'Metodo nao permitido.');
}

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput ?: '', true);

if (!is_array($payload) || empty($payload)) {
    jsonError(400, 'Payload invalido.');
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
    jsonError(500, 'SMTP config invalida em smtp-config.php');
}

if (strpos((string)$smtpConfig['password'], 'PUT_YOUR_') === 0) {
    jsonError(500, 'Define a password real no ficheiro smtp-config.php');
}

$formData = isset($payload['form_data']) && is_array($payload['form_data']) ? $payload['form_data'] : $payload;

if (empty($formData)) {
    jsonError(400, 'Dados do formulario invalidos.');
}

$recipientEmail = trim((string)($formData['Email'] ?? ''));
if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
    jsonError(400, 'Email do candidato invalido.');
}

$studentName = trim((string)($formData['Primeiro Nome'] ?? '') . ' ' . (string)($formData['Último Nome'] ?? ''));
$baseName = sanitizeFilename($studentName);
$pdfFilename = $baseName . '_' . date('Ymd_His') . '.pdf';
$clientIp = normalizeClientIp($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$submissionId = buildSubmissionId();
$pdfBinary = buildPdfFromFormData($formData);
if ($pdfBinary === false) {
    jsonError(500, 'Nao foi possivel gerar o PDF no servidor.');
}

$emailSent = false;
$emailError = null;

$subject = 'Nova inscricao - ' . ($studentName !== '' ? $studentName : 'Sem nome');
$message = "Nova inscricao recebida.\n\n";
$message .= 'ID: ' . $submissionId . "\n";
$message .= 'Nome: ' . ($studentName !== '' ? $studentName : '-') . "\n";
$message .= 'Email: ' . (string)($formData['Email'] ?? '-') . "\n";
$message .= 'Curso Pretendido: ' . (string)($formData['Curso Pretendido'] ?? '-') . "\n";
$message .= 'Data/Hora: ' . date('Y-m-d H:i:s') . "\n";

$emailSent = sendEmailWithAttachment($smtpConfig, $recipientEmail, $subject, $message, $pdfFilename, $pdfBinary, $emailError);
if (!$emailSent) {
    jsonError(400, 'Failed to send email. The destination email may be invalid or unreachable.', [
        'email_error' => $emailError,
    ]);
}

$storageDir = __DIR__ . DIRECTORY_SEPARATOR . 'submissions';
if (!is_dir($storageDir) && !mkdir($storageDir, 0777, true) && !is_dir($storageDir)) {
    jsonError(500, 'Nao foi possivel criar a pasta de dados.');
}

$pdfPath = $storageDir . DIRECTORY_SEPARATOR . $pdfFilename;
if (file_put_contents($pdfPath, $pdfBinary, LOCK_EX) === false) {
    jsonError(500, 'Email enviado, mas falhou ao guardar o PDF no servidor.');
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
    jsonError(500, 'Email enviado, PDF guardado, mas falhou ao registar inscricao.');
}

echo json_encode([
    'ok' => true,
    'message' => 'Inscricao guardada com sucesso.',
    'email_sent' => $emailSent,
    'email_error' => $emailError,
    'pdf_filename' => $pdfFilename,
    'submission_id' => $submissionId,
    'client_ip' => $clientIp,
]);

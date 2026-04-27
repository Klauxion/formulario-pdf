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

function submitEnvValue(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value === false || $value === null) {
        return $default;
    }
    return trim((string)$value);
}

function submitEnvInt(string $key, int $default): int
{
    $value = getenv($key);
    if ($value === false || $value === null || trim((string)$value) === '') {
        return $default;
    }
    $parsed = filter_var($value, FILTER_VALIDATE_INT);
    if ($parsed === false) {
        return $default;
    }
    return max(1, (int)$parsed);
}

function validateFormDataPayload(array $formData): bool
{
    // Defensive bounds to reduce abuse without changing normal behavior.
    if (count($formData) > 300) {
        return false;
    }

    foreach ($formData as $key => $value) {
        if (!is_string($key) || strlen($key) > 120) {
            return false;
        }
        if (is_array($value) || is_object($value)) {
            return false;
        }
        if (is_string($value) && strlen($value) > 2000) {
            return false;
        }
    }

    return true;
}

function writeField(\setasign\Fpdi\Fpdi $pdf, float $x, float $y, array $formData, string $key, float $boxWidth = 80): void
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

    // Match the original template field style: light-blue filled bar, no visible border.
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
    if ($templatePath === '') {
        $templatePath = __DIR__ . '/../basePDF_image/MDDPE1406_Ficha_Candidatura_r0_fixed.pdf';
    }
    
    // Fallback for root path just in case
    if (!is_file($templatePath)) {
        $templatePath = __DIR__ . '/basePDF_image/MDDPE1406_Ficha_Candidatura_r0_fixed.pdf';
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

    // Ano letivo (start/end) boxes in header
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

    // Checkbox for data consent
    $autoriza = isset($formData['autoriza_dados']) ? trim((string)$formData['autoriza_dados']) : '';
    if ($autoriza === 'Sim' || $autoriza === '1' || $autoriza === 'true' || $autoriza === 'on' || $autoriza === true) {
        // Draw checkbox box on the right side
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.5);
        $pdf->Rect(188.0, 186.0, 5, 5);  // Box
        
        // Draw X inside checkbox
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

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$allowedOrigin = submitEnvValue('SUBMIT_ALLOWED_ORIGIN', '');
if ($allowedOrigin !== '') {
    $requestOrigin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($requestOrigin !== '' && hash_equals($allowedOrigin, $requestOrigin)) {
        header('Access-Control-Allow-Origin: ' . $allowedOrigin);
        header('Vary: Origin');
    } elseif ($requestOrigin !== '') {
        jsonError(403, 'Origem nao permitida.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError(405, 'Metodo nao permitido.');
}

$requiredApiKey = submitEnvValue('SUBMIT_API_KEY', '');
if ($requiredApiKey !== '') {
    $providedApiKey = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
    if ($providedApiKey === '' || !hash_equals($requiredApiKey, $providedApiKey)) {
        jsonError(401, 'Nao autorizado.');
    }
}

$maxBodyBytes = submitEnvInt('SUBMIT_MAX_BODY_BYTES', 262144);
$rawInput = file_get_contents('php://input');
if ($rawInput === false) {
    jsonError(400, 'Payload invalido.');
}
if (strlen($rawInput) > $maxBodyBytes) {
    jsonError(413, 'Payload demasiado grande.');
}

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
    jsonError(500, 'SMTP config invalida. Define as variaveis SMTP_* no ficheiro .env');
}

$formData = isset($payload['form_data']) && is_array($payload['form_data']) ? $payload['form_data'] : $payload;

if (empty($formData)) {
    jsonError(400, 'Dados do formulario invalidos.');
}
if (!validateFormDataPayload($formData)) {
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

echo json_encode([
    'ok' => true,
    'message' => 'Inscricao guardada com sucesso.',
    'email_sent' => $emailSent,
    'email_error' => $emailError,
    'pdf_filename' => $pdfFilename,
    'submission_id' => $submissionId,
    'client_ip' => $clientIp,
]);

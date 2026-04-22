<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use setasign\Fpdi\Fpdi;

require_once __DIR__ . '/../vendor/autoload.php';
$smtpConfig = require __DIR__ . '/smtp-config.php';

ini_set('display_errors', '0');
error_reporting(E_ALL);

const PDF_SECTIONS = [
    'Dados do Aluno' => [
        'Primeiro Nome', 'Último Nome', 'Email', 'Data de Nascimento',
        'NIF', 'Acompanhante', 'Nacionalidade',
    ],
    'Identidade' => [
        'Tipo de Documento', 'BI-CC', 'Data de validade do Documento',
    ],
    'Morada' => [
        'Rua', 'Cidade', 'Concelho', 'Freguesia', 'Código Postal',
    ],
    'Escolaridade' => [
        'Escola Anterior', 'Último Ano de Frequência', 'Curso Pretendido',
    ],
    'Encarregado de Educacao' => [
        'Telemóvel do Pai', 'Telemóvel da Mãe', 'Email do Pai', 'Email da Mãe',
        'Nome do Encarregado', 'Telemóvel do Encarregado', 'Email do Encarregado',
        'Telefone do Encarregado', 'Morada do Encarregado', 'Localidade do Encarregado',
        'Código Postal do Encarregado', 'Habilitações do Encarregado', 'Relação do Candidato',
    ],
    'Autorizacoes' => [
        'autoriza_dados',
    ],
];

/**
 * Load coordinate mapping from JSON file
 */
function loadCoordinateMapping(): array
{
    $mappingFile = __DIR__ . '/../pdf_coordinate_mapping.json';
    if (!is_file($mappingFile)) {
        // Return default mapping if file doesn't exist yet
        return [];
    }
    
    $json = file_get_contents($mappingFile);
    if ($json === false) {
        return [];
    }
    
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

/**
 * Convert UTF-8 text to ISO-8859-1 for PDF compatibility
 */
function toPdfText(string $value): string
{
    return mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
}

/**
 * Get form value with fallback
 */
function safeValue(array $formData, string $key): string
{
    $value = isset($formData[$key]) ? trim((string)$formData[$key]) : '';
    return $value !== '' ? $value : '-';
}

/**
 * Sanitize filename
 */
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

/**
 * Build unique submission ID
 */
function buildSubmissionId(): string
{
    return date('YmdHis') . '-' . bin2hex(random_bytes(4));
}

/**
 * Get storage directory for PDFs
 */
function getStorageDir(): string
{
    $projectRoot = dirname(__DIR__);
    $preferredDir = $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'submissions';
    $legacyDir = __DIR__ . DIRECTORY_SEPARATOR . 'submissions';

    if (!is_dir($preferredDir) && !mkdir($preferredDir, 0777, true) && !is_dir($preferredDir)) {
        return $preferredDir;
    }

    // One-time migration from old public directory to private storage directory.
    if (is_dir($legacyDir)) {
        $legacyFiles = scandir($legacyDir);
        if (is_array($legacyFiles)) {
            foreach ($legacyFiles as $legacyFile) {
                if ($legacyFile === '.' || $legacyFile === '..') {
                    continue;
                }
                $from = $legacyDir . DIRECTORY_SEPARATOR . $legacyFile;
                $to = $preferredDir . DIRECTORY_SEPARATOR . $legacyFile;
                if (is_file($from) && !is_file($to)) {
                    @rename($from, $to);
                }
            }
        }
    }

    return $preferredDir;
}

/**
 * Send JSON error response
 */
function jsonError(int $statusCode, string $message, array $extra = []): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge(['ok' => false, 'message' => $message], $extra));
    exit;
}

/**
 * Normalize IP address
 */
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

/**
 * Add text to PDF at specific coordinates
 * Converts from mm to PDF points if needed
 */
function addTextToPdf(Fpdi $pdf, string $text, float $x, float $y, float $width = 0, string $align = 'L', int $fontSize = 10): void
{
    $pdf->SetFont('Arial', '', $fontSize);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY($x, $y);
    
    if ($width > 0) {
        $pdf->Cell($width, 5, toPdfText($text), 0, 0, $align);
    } else {
        $pdf->Text($x, $y, toPdfText($text));
    }
}

/**
 * Convert mm to PDF points (1 point = 1/72 inch = 0.352778 mm)
 */
function mmToPoints(float $mm): float
{
    return $mm / 0.352778;
}

/**
 * Convert PDF points to mm
 */
function pointsToMm(float $points): float
{
    return $points * 0.352778;
}

/**
 * Build PDF using FPDI to overlay form data on template
 */
function buildPdfFromFormData(array $formData, array $coordinateMapping): string|false
{
    $templatePdfPath = __DIR__ . '/../basePDF_image/MDDPE1406_Ficha_Candidatura_r0.pdf';
    
    if (!is_file($templatePdfPath)) {
        return false;
    }

    $pdf = new Fpdi();
    $pdf->SetMargins(0, 0, 0);
    
    try {
        $pageCount = $pdf->setSourceFile($templatePdfPath);
    } catch (Exception $e) {
        return false;
    }

    if ($pageCount < 2) {
        return false;
    }

    // PAGE 1: Import template and add form data
    $pdf->AddPage();
    $template1 = $pdf->importPage(1);
    $pdf->useImportedPage($template1);

    // Add data from form to page 1
    // Nome (Full Name)
    $fullName = safeValue($formData, 'Primeiro Nome') . ' ' . safeValue($formData, 'Último Nome');
    addTextToPdf($pdf, $fullName, 50, 165, 400);

    // Data de Nascimento
    addTextToPdf($pdf, safeValue($formData, 'Data de Nascimento'), 50, 190, 200);

    // Nacionalidade
    addTextToPdf($pdf, safeValue($formData, 'Nacionalidade'), 310, 190, 200);

    // Naturalidade(País)
    addTextToPdf($pdf, safeValue($formData, 'Nacionalidade'), 50, 215, 200);

    // Concelho/Freguesia Nasc.
    addTextToPdf($pdf, safeValue($formData, 'Freguesia'), 310, 215, 200);

    // CC|Outro (BI-CC)
    addTextToPdf($pdf, safeValue($formData, 'BI-CC'), 50, 240, 200);

    // Validade (Data de validade do Documento)
    addTextToPdf($pdf, safeValue($formData, 'Data de validade do Documento'), 310, 240, 200);

    // NIF
    addTextToPdf($pdf, safeValue($formData, 'NIF'), 50, 265, 400);

    // Morada (Rua)
    addTextToPdf($pdf, safeValue($formData, 'Rua'), 50, 290, 400);

    // Localidade (Cidade)
    addTextToPdf($pdf, safeValue($formData, 'Cidade'), 50, 315, 200);

    // Código Postal
    addTextToPdf($pdf, safeValue($formData, 'Código Postal'), 310, 315, 200);

    // Telefone (Telemóvel do Pai)
    addTextToPdf($pdf, safeValue($formData, 'Telemóvel do Pai'), 50, 340, 200);

    // Telemóvel (Telemóvel da Mãe)
    addTextToPdf($pdf, safeValue($formData, 'Telemóvel da Mãe'), 310, 340, 200);

    // Email
    addTextToPdf($pdf, safeValue($formData, 'Email'), 50, 365, 200);

    // Situação académica (Último Ano de Frequência)
    addTextToPdf($pdf, safeValue($formData, 'Último Ano de Frequência'), 310, 365, 200);

    // Curriculum section - Ciclos de Ensino
    // 1.º Ciclo do Ensino Básico
    addTextToPdf($pdf, safeValue($formData, 'Escola Anterior'), 50, 435, 400);

    // 2.º Ciclo do Ensino Básico
    addTextToPdf($pdf, '-', 50, 460, 400);

    // 3.º Ciclo do Ensino Básico
    addTextToPdf($pdf, '-', 50, 485, 400);

    // Secundário
    addTextToPdf($pdf, '-', 50, 510, 400);

    // PAGE 2: Import template and add guardian/filiation data
    $pdf->AddPage();
    $template2 = $pdf->importPage(2);
    $pdf->useImportedPage($template2);

    // Filiação e Encarregado de Educação
    // Pai - Nome
    addTextToPdf($pdf, safeValue($formData, 'Nome do Encarregado'), 50, 80, 400);

    // Telemóvel do Pai
    addTextToPdf($pdf, safeValue($formData, 'Telefone do Encarregado'), 50, 105, 200);

    // Email do Pai
    addTextToPdf($pdf, safeValue($formData, 'Email do Encarregado'), 310, 105, 200);

    // Mãe - Nome
    addTextToPdf($pdf, '-', 50, 135, 400);

    // Telemóvel da Mãe
    addTextToPdf($pdf, '-', 50, 160, 200);

    // Email da Mãe
    addTextToPdf($pdf, '-', 310, 160, 200);

    // Encarregado de Educação section
    // EE: Nome
    addTextToPdf($pdf, safeValue($formData, 'Nome do Encarregado'), 50, 240, 160);

    // Morada
    addTextToPdf($pdf, safeValue($formData, 'Morada do Encarregado'), 215, 240, 160);

    // Localidade
    addTextToPdf($pdf, safeValue($formData, 'Localidade do Encarregado'), 395, 240, 150);

    // Código Postal
    addTextToPdf($pdf, safeValue($formData, 'Código Postal do Encarregado'), 50, 265, 160);

    // Telefone
    addTextToPdf($pdf, safeValue($formData, 'Telefone do Encarregado'), 215, 265, 160);

    // Telemóvel
    addTextToPdf($pdf, safeValue($formData, 'Telemóvel do Encarregado'), 395, 265, 150);

    // Email
    addTextToPdf($pdf, safeValue($formData, 'Email do Encarregado'), 50, 290, 400);

    // Habilitações Académicas
    addTextToPdf($pdf, safeValue($formData, 'Habilitações do Encarregado'), 50, 315, 200);

    // Relação com o candidato
    addTextToPdf($pdf, safeValue($formData, 'Relação do Candidato'), 310, 315, 200);

    // Authorization checkbox - add checkmark if authorized
    if (isset($formData['autoriza_dados']) && strtolower(trim((string)$formData['autoriza_dados'])) === 'sim') {
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Text(480, 340, '✔');
    }

    $pdfContent = $pdf->Output('S');
    return is_string($pdfContent) && $pdfContent !== '' ? $pdfContent : false;
}

/**
 * Send email with PDF attachment
 */
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

// ============================================================================
// MAIN REQUEST HANDLER
// ============================================================================

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
    jsonError(500, 'SMTP config invalida. Define as variaveis SMTP_* no ficheiro .env');
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

// Load coordinate mapping
$coordinateMapping = loadCoordinateMapping();

// Generate PDF using FPDI overlay approach
$pdfBinary = buildPdfFromFormData($formData, $coordinateMapping);
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

$storageDir = getStorageDir();
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
];

$jsonlPath = getStorageDir() . DIRECTORY_SEPARATOR . 'inscricoes.jsonl';
$entryJson = json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n";
if (file_put_contents($jsonlPath, $entryJson, FILE_APPEND | LOCK_EX) === false) {
    jsonError(500, 'Email enviado e PDF guardado, mas falhou ao registar os dados.');
}

http_response_code(200);
echo json_encode([
    'ok' => true,
    'message' => 'Inscricao recebida com sucesso. Verifique o seu email para confirmacao.',
    'submission_id' => $submissionId,
]);

<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

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

class FormPDF extends FPDF
{
    public function RoundedRect(float $x, float $y, float $w, float $h, float $r, string $style = ''): void
    {
        $k = $this->k;
        $hp = $this->h;

        if ($style === 'F') {
            $op = 'f';
        } elseif ($style === 'FD' || $style === 'DF') {
            $op = 'B';
        } else {
            $op = 'S';
        }

        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $this->_out(sprintf('%.2F %.2F l', ($x + $w - $r) * $k, ($hp - $y) * $k));
        $this->arc($x + $w - $r, $y + $r, $x + $w, $y, $x + $w, $y + $r);
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - ($y + $h - $r)) * $k));
        $this->arc($x + $w - $r, $y + $h - $r, $x + $w, $y + $h, $x + $w - $r, $y + $h);
        $this->_out(sprintf('%.2F %.2F l', ($x + $r) * $k, ($hp - ($y + $h)) * $k));
        $this->arc($x + $r, $y + $h - $r, $x, $y + $h, $x, $y + $h - $r);
        $this->_out(sprintf('%.2F %.2F l', ($x) * $k, ($hp - ($y + $r)) * $k));
        $this->arc($x + $r, $y + $r, $x, $y, $x + $r, $y);
        $this->_out($op);
    }

    private function arc(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3): void
    {
        $h = $this->h;
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c',
            $x1 * $this->k,
            ($h - $y1) * $this->k,
            $x2 * $this->k,
            ($h - $y2) * $this->k,
            $x3 * $this->k,
            ($h - $y3) * $this->k
        ));
    }
}

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

function drawSectionTitle(FormPDF $pdf, string $title): void
{
    $pdf->SetFillColor(76, 175, 80);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 12);
    $startX = 10.0;
    $startY = $pdf->GetY();
    $width = 190.0;
    $height = 8.0;
    $pdf->RoundedRect($startX, $startY, $width, $height, 1.0, 'F');
    $pdf->SetXY($startX + 3, $startY + 1);
    $pdf->Cell($width - 6, 6, toPdfText($title), 0, 1, 'L');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(3);
}

function drawFields(FormPDF $pdf, array $formData, array $fieldLabels): void
{
    $pdf->SetFont('Arial', '', 10);

    $lineHeight = 6;
    $colGap = 4;
    $leftX = 10;
    $colW = 93;
    $rightX = $leftX + $colW + $colGap;

    for ($i = 0; $i < count($fieldLabels); $i += 2) {
        if ($pdf->GetY() > 270) {
            $pdf->AddPage();
        }

        $leftLabel = $fieldLabels[$i];
        $rightLabel = $fieldLabels[$i + 1] ?? null;
        $rowTopY = $pdf->GetY();

        $pdf->SetFillColor(245, 246, 248);
        $pdf->RoundedRect($leftX, $rowTopY, $colW, 12.5, 0.8, 'FD');
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetXY($leftX + 2, $rowTopY + 1);
        $pdf->Cell($colW - 4, $lineHeight, toPdfText((string)$leftLabel), 0, 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetXY($leftX + 2, $rowTopY + 6.2);
        $pdf->Cell($colW - 4, $lineHeight, toPdfText(safeValue($formData, (string)$leftLabel)), 0, 0);

        if ($rightLabel !== null) {
            $pdf->SetFillColor(245, 246, 248);
            $pdf->RoundedRect($rightX, $rowTopY, $colW, 12.5, 0.8, 'FD');
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetXY($rightX + 2, $rowTopY + 1);
            $pdf->Cell($colW - 4, $lineHeight, toPdfText((string)$rightLabel), 0, 0);
            $pdf->SetFont('Arial', '', 10);
            $pdf->SetXY($rightX + 2, $rowTopY + 6.2);
            $pdf->Cell($colW - 4, $lineHeight, toPdfText(safeValue($formData, (string)$rightLabel)), 0, 0);
        }

        $pdf->SetY($rowTopY + 14.8);
    }
}

function buildPdfFromFormData(array $formData): string|false
{
    $pdf = new FormPDF();
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 15);

    $pdf->SetFillColor(244, 246, 248);
    $pdf->Rect(0, 0, 210, 297, 'F');

    $headerX = 10;
    $headerY = 10;
    $headerW = 190;
    $headerH = 28;

    $pdf->SetFillColor(255, 255, 255);
    $pdf->RoundedRect($headerX, $headerY, $headerW, $headerH, 1.2, 'FD');
    $pdf->SetDrawColor(220, 220, 220);

    $logoPath = __DIR__ . DIRECTORY_SEPARATOR . 'vr_logo_2026.png';
    if (is_file($logoPath)) {
        $logoW = 22;
        $logoH = 22;
        $logoX = $headerX + $headerW - $logoW - 4;
        $logoY = $headerY + ($headerH - $logoH) / 2;
        $pdf->Image($logoPath, $logoX, $logoY, $logoW, $logoH);
    }

    $pdf->SetXY(14, 14);
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 8, toPdfText('Ficha de Inscricao'), 0, 1);
    $pdf->SetX(14);
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(0, 6, toPdfText('Escola Profissional Val do Rio'), 0, 1);
    $pdf->Ln(14);

    foreach (PDF_SECTIONS as $sectionTitle => $sectionFields) {
        drawSectionTitle($pdf, $sectionTitle);
        drawFields($pdf, $formData, $sectionFields);
        $pdf->Ln(2);
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

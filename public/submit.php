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

    protected string $footerImagePath = '';

    public function setFooterImagePath(string $path): void
    {
        $this->footerImagePath = $path;
    }

    public function Footer(): void
    {
        if ($this->footerImagePath !== '' && is_file($this->footerImagePath)) {
            $pageWidth = $this->GetPageWidth();
            $pageHeight = $this->GetPageHeight();
            $bottomMargin = 8;
            $imageWidth = 150;
            $imageHeight = 12;
            $x = ($pageWidth - $imageWidth) / 2;
            $y = $pageHeight - $imageHeight - $bottomMargin;
            $this->Image($this->footerImagePath, $x, $y, $imageWidth, $imageHeight);
        }
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
    $pdf->SetTextColor(0, 68, 139);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 6, toPdfText($title), 0, 1, 'L');
    $pdf->Ln(2);
}

function drawField(FormPDF $pdf, string $label, string $value, float $width): void
{
    $x = $pdf->GetX();
    $y = $pdf->GetY();

    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 68, 139);
    $pdf->Cell($width, 4, toPdfText($label), 0, 1);

    $pdf->SetFillColor(227, 238, 255);
    $pdf->Rect($x, $pdf->GetY(), $width, 10, 'F');

    $pdf->SetXY($x + 2, $pdf->GetY() + 2);
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($width - 4, 5, toPdfText($value), 0, 1);
    $pdf->SetXY($x, $y + 15);
}

function drawTwoFields(FormPDF $pdf, string $label1, string $value1, string $label2, string $value2, float $width1, float $width2): void
{
    $x = $pdf->GetX();
    $y = $pdf->GetY();

    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 68, 139);
    $pdf->Cell($width1, 4, toPdfText($label1), 0, 0);
    $pdf->Cell($width2, 4, toPdfText($label2), 0, 1);

    $pdf->SetFillColor(227, 238, 255);
    $pdf->Rect($x, $pdf->GetY(), $width1, 10, 'F');
    $pdf->Rect($x + $width1, $pdf->GetY(), $width2, 10, 'F');

    $pdf->SetXY($x + 2, $pdf->GetY() + 2);
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($width1 - 4, 5, toPdfText($value1), 0, 0);
    $pdf->SetXY($x + $width1 + 2, $pdf->GetY());
    $pdf->Cell($width2 - 4, 5, toPdfText($value2), 0, 1);
    $pdf->SetXY($x, $y + 15);
}

function drawThreeFields(FormPDF $pdf, string $label1, string $value1, string $label2, string $value2, string $label3, string $value3, float $width1, float $width2, float $width3): void
{
    $x = $pdf->GetX();
    $y = $pdf->GetY();

    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 68, 139);
    $pdf->Cell($width1, 4, toPdfText($label1), 0, 0);
    $pdf->Cell($width2, 4, toPdfText($label2), 0, 0);
    $pdf->Cell($width3, 4, toPdfText($label3), 0, 1);

    $pdf->SetFillColor(227, 238, 255);
    $pdf->Rect($x, $pdf->GetY(), $width1, 10, 'F');
    $pdf->Rect($x + $width1, $pdf->GetY(), $width2, 10, 'F');
    $pdf->Rect($x + $width1 + $width2, $pdf->GetY(), $width3, 10, 'F');

    $pdf->SetXY($x + 2, $pdf->GetY() + 2);
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($width1 - 4, 5, toPdfText($value1), 0, 0);
    $pdf->SetXY($x + $width1 + 2, $pdf->GetY());
    $pdf->Cell($width2 - 4, 5, toPdfText($value2), 0, 0);
    $pdf->SetXY($x + $width1 + $width2 + 2, $pdf->GetY());
    $pdf->Cell($width3 - 4, 5, toPdfText($value3), 0, 1);
    $pdf->SetXY($x, $y + 15);
}

function buildPdfFromFormData(array $formData): string|false
{
    $pdf = new FormPDF();
    $pdf->SetMargins(16, 18, 16);
    $pdf->SetAutoPageBreak(true, 35);
    $pdf->setFooterImagePath(__DIR__ . '/../basePDF_image/ISO_9001_e_IQNET_COR.jpg');
    $pdf->AddPage();

    $logoPath = __DIR__ . '/../basePDF_image/vr_logo_2026.png';
    if (!is_file($logoPath)) {
        $logoPath = __DIR__ . DIRECTORY_SEPARATOR . 'vr_logo_2026.png';
    }

    if (is_file($logoPath)) {
        $pdf->Image($logoPath, 16, 18, 35);
    }

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(0, 68, 139);
    $pdf->SetXY(140, 20);
    $pdf->Cell(0, 5, toPdfText('Ano Letivo'), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetXY(140, 25);
    $pdf->SetFillColor(227, 238, 255);
    $pdf->Rect(140, 25, 25, 8, 'F');
    $pdf->Cell(25, 8, toPdfText('2025'), 0, 0, 'C');
    $pdf->SetXY(171, 25);
    $pdf->Rect(171, 25, 25, 8, 'F');
    $pdf->SetXY(171, 25);
    $pdf->Cell(25, 8, toPdfText('2026'), 0, 0, 'C');

    $pdf->SetXY(140, 37);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 5, toPdfText('Curso'), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetXY(140, 42);
    $pdf->Rect(140, 42, 68, 8, 'F');
    $pdf->Cell(68, 8, toPdfText(safeValue($formData, 'Curso Pretendido')), 0, 0, 'C');

    $pdf->SetXY(140, 52);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 5, toPdfText('Candidatura n.º'), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetXY(140, 57);
    $pdf->Rect(140, 57, 68, 8, 'F');
    $pdf->Cell(68, 8, toPdfText(safeValue($formData, 'Candidatura n.º')), 0, 0, 'C');

    $pdf->Ln(22);
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(0, 68, 139);
    $pdf->Cell(0, 8, toPdfText('Ficha de Candidatura'), 0, 1, 'C');
    $pdf->Ln(4);

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(0, 68, 139);
    $pdf->Cell(0, 6, toPdfText('Identificação do Candidato'), 0, 1, 'L');
    $pdf->Ln(2);

    drawField($pdf, 'Nome', safeValue($formData, 'Primeiro Nome') . ' ' . safeValue($formData, 'Último Nome'), 178.0);
    drawTwoFields($pdf, 'Nascido em', safeValue($formData, 'Data de Nascimento'), 'Nacionalidade', safeValue($formData, 'Nacionalidade'), 88.0, 88.0);
    drawTwoFields($pdf, 'Naturalidade(País)', safeValue($formData, 'Nacionalidade'), 'Concelho/Freguesia Nasc.', safeValue($formData, 'Freguesia'), 88.0, 88.0);
    drawTwoFields($pdf, 'CC|Outro', safeValue($formData, 'BI-CC'), 'Validade', safeValue($formData, 'Data de validade do Documento'), 88.0, 88.0);
    drawField($pdf, 'NIF', safeValue($formData, 'NIF'), 178.0);
    drawField($pdf, 'Morada', safeValue($formData, 'Rua'), 178.0);
    drawTwoFields($pdf, 'Localidade', safeValue($formData, 'Cidade'), 'Código Postal', safeValue($formData, 'Código Postal'), 88.0, 88.0);
    drawTwoFields($pdf, 'Telefone', safeValue($formData, 'Telemóvel do Pai'), 'Telemóvel', safeValue($formData, 'Telemóvel da Mãe'), 88.0, 88.0);
    drawTwoFields($pdf, 'Email', safeValue($formData, 'Email'), 'Situação académica', safeValue($formData, 'Último Ano de Frequência'), 88.0, 88.0);

    $pdf->Ln(6);
    drawSectionTitle($pdf, 'Curriculum escolar e perfil do candidato');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 5, toPdfText('Estabelecimentos de ensino que frequentou:'), 0, 1, 'L');
    $pdf->Ln(2);

    drawField($pdf, '1.º Ciclo do Ensino Básico', safeValue($formData, 'Escola Anterior'), 178.0);
    drawField($pdf, '2.º Ciclo do Ensino Básico', '-', 178.0);
    drawField($pdf, '3.º Ciclo do Ensino Básico', '-', 178.0);
    drawField($pdf, 'Secundário', '-', 178.0);

    $pdf->AddPage();
    $pdf->SetMargins(16, 18, 16);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(0, 68, 139);
    $pdf->SetY(20);
    $pdf->Cell(0, 6, toPdfText('Filiação e Encarregado de Educação'), 0, 1, 'L');
    $pdf->Ln(2);

    drawField($pdf, 'Pai - Nome', safeValue($formData, 'Nome do Encarregado'), 178.0);
    drawTwoFields($pdf, 'Telemóvel', safeValue($formData, 'Telefone do Encarregado'), 'Email', safeValue($formData, 'Email do Encarregado'), 88.0, 88.0);
    $pdf->Ln(2);
    drawField($pdf, 'Mãe - Nome', '-', 178.0);
    drawTwoFields($pdf, 'Telemóvel', '-', 'Email', '-', 88.0, 88.0);
    $pdf->Ln(6);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 6, toPdfText('Encarregado de Educação (De acordo com o despacho 14026/07)'), 0, 1, 'L');
    $pdf->Ln(2);
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0, 68, 139);
    $pdf->MultiCell(0, 4, toPdfText('Nos casos do Encarregado de Educação não ser o Pai nem a Mãe, indicar o nome, morada, telefones de contacto e relação que tem com o seu Educando.'), 0, 'L');
    $pdf->Ln(3);

    drawThreeFields($pdf, 'EE: Nome', safeValue($formData, 'Nome do Encarregado'), 'Morada', safeValue($formData, 'Morada do Encarregado'), 'Localidade', safeValue($formData, 'Localidade do Encarregado'), 58.5, 58.5, 58.5);
    drawThreeFields($pdf, 'Código Postal', safeValue($formData, 'Código Postal do Encarregado'), 'Telefone', safeValue($formData, 'Telefone do Encarregado'), 'Telemóvel', safeValue($formData, 'Telemóvel do Encarregado'), 58.5, 58.5, 58.5);
    drawField($pdf, 'Email', safeValue($formData, 'Email do Encarregado'), 178.0);
    drawTwoFields($pdf, 'Habilitações Académicas', safeValue($formData, 'Habilitações do Encarregado'), 'Relação com o candidato', safeValue($formData, 'Relação do Candidato'), 88.0, 88.0);

    $pdf->Ln(6);
    $checkboxX = $pdf->GetX();
    $checkboxY = $pdf->GetY();
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 68, 139);
    $pdf->MultiCell(120, 4, toPdfText('Sim, aceito tratamento dos meus dados de candidato pela Escola profissional Val do Rio'), 0, 'L');
    $pdf->SetXY($checkboxX + 125, $checkboxY + 1);
    $pdf->Rect($checkboxX + 125, $checkboxY + 1, 6, 6, 'D');
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Text($checkboxX + 126, $checkboxY + 6, '✔');

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->SetY(-30);
    $pdf->Cell(0, 4, toPdfText('M.D. DPE 14_06'), 0, 1, 'L');
    $pdf->Cell(0, 4, toPdfText(date('Y-m-d')), 0, 1, 'L');

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

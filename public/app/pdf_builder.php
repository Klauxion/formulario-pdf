<?php
declare(strict_types=1);

function normalizeFormData(array $data): array
{
    $out = [];

    foreach ($data as $k => $v) {
        $key = trim((string)$k);
        if ($key === '') continue;

        // Convert value to string
        if (is_array($v)) {
            $v = implode(', ', $v);
        }

        $value = trim((string)$v);

        // Limit size (safety)
        if (strlen($value) > 2000) {
            $value = substr($value, 0, 2000);
        }

        // Store original
        $out[$key] = $value;

        // 🔥 THIS IS THE IMPORTANT PART
        if (str_contains($key, '_')) {
            $withSpaces = str_replace('_', ' ', $key);
            if (!isset($out[$withSpaces])) {
                $out[$withSpaces] = $value;
            }
        }

        if (str_contains($key, ' ')) {
            $withUnderscore = str_replace(' ', '_', $key);
            if (!isset($out[$withUnderscore])) {
                $out[$withUnderscore] = $value;
            }
        }
    }

    return $out;
}

function buildPdfFromFormData(array $formData): string|false
{
    $pdf = new \setasign\Fpdi\Fpdi();
    $templatePath = $_ENV['PDF_TEMPLATE_PATH'] ?? '';
    if ($templatePath === '' || !is_file($templatePath)) {
    $templatePath = __DIR__ . '/../assets/basePDF_image/temp_fixed.pdf';
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


function toPdfText(string $value): string
{
    return mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
}


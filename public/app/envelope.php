<?php

require_once __DIR__ . '/pdf_builder.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/hermes.php';

function str_starts_with_safe($haystack, $needle) {
    return strpos($haystack, $needle) === 0;
}

function str_ends_with_safe($haystack, $needle) {
    return substr($haystack, -strlen($needle)) === $needle;
}

function submitForm(array $data): array 
{
    // ⚠️ IMPORTANT: your form uses "Email", not "email"
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return [
            'ok' => false,
            'message' => 'Email inválido'
        ];
    }

    try {
        // ✅ Normalize data (prevents missing fields in PDF)
        if (function_exists('normalizeFormData')) {
            $data = normalizeFormData($data);
        }

        // ✅ STEP 1: Generate PDF
        $pdfBinary = buildPdfFromFormData($data);

        if (!$pdfBinary) {
            return [
                'ok' => false,
                'message' => 'Erro ao gerar PDF'
            ];
        }

        // ✅ STEP 2: Create filename
        $pdfName = 'formulario_' . date('Ymd_His') . '.pdf';

        // ✅ STEP 3: Send email WITH PDF
        $result = processSubmission($data, $pdfBinary, $pdfName);

        if (!($result['email_sent'] ?? false)) {
            return [
                'ok' => false,
                'message' => 'PDF gerado, mas email falhou'
            ];
        }

        return [
            'ok' => true,
            'message' => 'PDF enviado com sucesso'
        ];

    } catch (Exception $e) {
        return [
            'ok' => false,
            'message' => 'Erro ao processar formulário',
            'email_error' => $e->getMessage()
        ];
    }
}
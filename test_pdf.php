<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/public/submit.php';

$formData = [
    'Primeiro Nome' => 'Test',
    'Email' => 'test@test.com'
];
$pdf = buildPdfFromFormData($formData);
if ($pdf === false) {
    echo "PDF generation failed\n";
} else {
    echo "PDF generation success, length: " . strlen($pdf) . "\n";
}

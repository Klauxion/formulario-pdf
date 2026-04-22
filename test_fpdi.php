<?php
try {
    require_once __DIR__ . '/vendor/autoload.php';
    $pdf = new \setasign\Fpdi\Fpdi();
    $pdf->setSourceFile(__DIR__ . '/basePDF_image/MDDPE1406_Ficha_Candidatura_r0.pdf');
    $tpl = $pdf->importPage(1);
    $pdf->AddPage();
    $pdf->useTemplate($tpl);
    echo "Success!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

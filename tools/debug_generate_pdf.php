<?php
require_once __DIR__ . '/../lib/fpdf/fpdf.php';

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'Test PDF Output', 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, 'If you can read this, FPDF output works.', 0, 1);
$file = __DIR__ . '/../backups/test_debug.pdf';
$pdf->Output('F', $file);

echo 'Generated: ' . $file . PHP_EOL;

<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_role('Admin', 'Manager', 'Operatore');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    http_response_code(400);
    exit('Parametro id non valido.');
}

$stmt = $pdo->prepare('SELECT report_date, file_path FROM daily_financial_reports WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$report = $stmt->fetch();

if (!$report) {
    http_response_code(404);
    exit('Report non trovato.');
}

$relativePath = (string) ($report['file_path'] ?? '');
if ($relativePath === '') {
    http_response_code(404);
    exit('Percorso del report non disponibile.');
}

$fullPath = public_path($relativePath);
$realFullPath = realpath($fullPath);
if ($realFullPath !== false) {
    $fullPath = $realFullPath;
}

$reportsRoot = public_path('backups/daily-reports');
$realReportsRoot = realpath($reportsRoot) ?: $reportsRoot;

$normalizedFull = str_replace(chr(92), '/', $fullPath);
$normalizedRoot = rtrim(str_replace(chr(92), '/', $realReportsRoot), '/');

if (!is_file($fullPath) || $normalizedRoot === '' || strncmp($normalizedFull, $normalizedRoot, strlen($normalizedRoot)) !== 0) {
    http_response_code(404);
    exit('File del report non trovato.');
}

$filesize = filesize($fullPath);
$downloadName = 'report_finanziario_' . ($report['report_date'] ?? date('Y_m_d')) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
if ($filesize !== false) {
    header('Content-Length: ' . $filesize);
}
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$handle = fopen($fullPath, 'rb');
if ($handle === false) {
    http_response_code(500);
    exit('Impossibile aprire il file del report.');
}

while (!feof($handle)) {
    echo fread($handle, 8192);
    flush();
}

fclose($handle);
exit;

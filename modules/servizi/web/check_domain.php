<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/functions.php';

require_role('Admin', 'Operatore', 'Manager');

header('Content-Type: application/json; charset=utf-8');

if (!servizi_web_hostinger_is_configured()) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Integrazione Hostinger non configurata. Aggiungi il token API nelle variabili ambiente.',
    ]);
    exit;
}

$domain = strtolower(trim((string) ($_GET['domain'] ?? $_POST['domain'] ?? '')));
if ($domain === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Specifica un dominio da verificare.',
    ]);
    exit;
}

$result = servizi_web_hostinger_check_domain($domain);

if (!$result) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'domain' => $domain,
        'available' => null,
        'message' => 'Nessuna risposta disponibile. Verifica manualmente su Hostinger.',
    ]);
    exit;
}

$match = null;
foreach ($result as $item) {
    if (isset($item['domain']) && strtolower((string) $item['domain']) === $domain) {
        $match = $item;
        break;
    }
}

if ($match === null) {
    echo json_encode([
        'success' => true,
        'domain' => $domain,
        'available' => null,
        'message' => 'Dominio non presente nella risposta Hostinger.',
    ]);
    exit;
}

$available = isset($match['status']) ? $match['status'] === 'AVAILABLE' : (bool) ($match['available'] ?? false);

$response = [
    'success' => true,
    'domain' => $domain,
    'available' => $available,
    'status' => $match['status'] ?? null,
    'pricing' => $match['pricing'] ?? null,
];

echo json_encode($response);

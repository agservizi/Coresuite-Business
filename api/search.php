<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$term = trim($_GET['q'] ?? '');
$term = mb_substr($term, 0, 120);
$minLength = 2;

$emptyPayload = [
    'query' => $term,
    'results' => [
        'clients' => [],
        'tickets' => [],
        'documents' => [],
    ],
];

if ($term === '' || mb_strlen($term) < $minLength) {
    try {
        echo json_encode($emptyPayload, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        http_response_code(500);
        echo '{"results":{}}';
    }
    exit;
}

$likeTerm = '%' . $term . '%';

try {
    $clientsStmt = $pdo->prepare('SELECT id, nome, cognome, email, telefono FROM clienti WHERE nome LIKE :term OR cognome LIKE :term OR email LIKE :term OR cf_piva LIKE :term ORDER BY updated_at DESC LIMIT 5');
    $clientsStmt->execute([':term' => $likeTerm]);
    $clients = [];
    while ($row = $clientsStmt->fetch()) {
        $fullName = trim(($row['nome'] ?? '') . ' ' . ($row['cognome'] ?? ''));
        if ($fullName === '') {
            $fullName = 'Cliente #' . $row['id'];
        }
        $clients[] = [
            'id' => (int) $row['id'],
            'title' => $fullName,
            'subtitle' => $row['email'] ?: ($row['telefono'] ?: 'Cliente registrato'),
            'badge' => 'Cliente',
            'url' => base_url('modules/clienti/view.php?id=' . $row['id']),
        ];
    }

    $ticketsStmt = $pdo->prepare('SELECT id, titolo, stato, created_at FROM ticket WHERE titolo LIKE :term OR descrizione LIKE :term ORDER BY created_at DESC LIMIT 5');
    $ticketsStmt->execute([':term' => $likeTerm]);
    $tickets = [];
    while ($row = $ticketsStmt->fetch()) {
        $ticketDate = $row['created_at'] ?? '';
        $ticketTitle = $row['titolo'] ?? '';
        if ($ticketTitle === '') {
            $ticketTitle = 'Ticket #' . $row['id'];
        }
        $tickets[] = [
            'id' => (int) $row['id'],
            'title' => $ticketTitle,
            'subtitle' => sprintf('Stato: %s - %s', $row['stato'] ?? '—', $ticketDate !== '' ? format_datetime($ticketDate) : 'Data sconosciuta'),
            'badge' => 'Ticket',
            'url' => base_url('modules/ticket/view.php?id=' . $row['id']),
        ];
    }

    $documentsStmt = $pdo->prepare('SELECT d.id, d.titolo, d.modulo, d.stato, d.updated_at, c.nome, c.cognome FROM documents d LEFT JOIN clienti c ON c.id = d.cliente_id WHERE d.titolo LIKE :term OR d.descrizione LIKE :term ORDER BY d.updated_at DESC LIMIT 5');
    $documentsStmt->execute([':term' => $likeTerm]);
    $documents = [];
    while ($row = $documentsStmt->fetch()) {
        $customerName = trim(($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? ''));
        $documentSubtitleParts = [];
        if (!empty($row['modulo'])) {
            $documentSubtitleParts[] = $row['modulo'];
        }
        $documentSubtitleParts[] = $customerName !== '' ? $customerName : 'Documento interno';
        $documentTitle = $row['titolo'] ?? '';
        if ($documentTitle === '') {
            $documentTitle = 'Documento #' . $row['id'];
        }
        $documents[] = [
            'id' => (int) $row['id'],
            'title' => $documentTitle,
            'subtitle' => implode(' - ', $documentSubtitleParts),
            'badge' => $row['stato'] ?? 'Documento',
            'url' => base_url('modules/documenti/view.php?id=' . $row['id']),
        ];
    }

    $payload = [
        'query' => $term,
        'results' => [
            'clients' => $clients,
            'tickets' => $tickets,
            'documents' => $documents,
        ],
    ];

    echo json_encode($payload, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('Live search failed: ' . $e->getMessage());
    http_response_code(500);
    try {
        echo json_encode($emptyPayload + ['error' => 'Ricerca non disponibile.'], JSON_THROW_ON_ERROR);
    } catch (JsonException $jsonException) {
        echo '{"results":{}}';
    }
}

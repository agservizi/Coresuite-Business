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
        'loyalty' => [],
        'finance' => [],
        'appointments' => [],
        'telefonia' => [],
        'shipments' => [],
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

    $loyaltyStmt = $pdo->prepare('SELECT fm.id, fm.descrizione, fm.punti, fm.tipo_movimento, fm.data_movimento, c.nome, c.cognome
        FROM fedelta_movimenti fm
        LEFT JOIN clienti c ON c.id = fm.cliente_id
        WHERE fm.descrizione LIKE :term
            OR fm.tipo_movimento LIKE :term
            OR c.nome LIKE :term
            OR c.cognome LIKE :term
        ORDER BY fm.updated_at DESC, fm.id DESC
        LIMIT 5');
    $loyaltyStmt->execute([':term' => $likeTerm]);
    $loyalty = [];
    while ($row = $loyaltyStmt->fetch()) {
        $movementTitle = $row['descrizione'] ?? '';
        if ($movementTitle === '') {
            $movementTitle = 'Movimento #' . $row['id'];
        }
        $points = (int) ($row['punti'] ?? 0);
        $customerName = trim((string) (($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? '')));
        $subtitleParts = [];
        if ($customerName !== '') {
            $subtitleParts[] = $customerName;
        }
        $subtitleParts[] = 'Punti: ' . ($points >= 0 ? '+' : '') . $points;
        if (!empty($row['data_movimento'])) {
            $subtitleParts[] = format_datetime_locale($row['data_movimento']);
        }
        $loyalty[] = [
            'id' => (int) $row['id'],
            'title' => $movementTitle,
            'subtitle' => implode(' | ', array_filter($subtitleParts)),
            'badge' => $row['tipo_movimento'] ?? 'Fedeltà',
            'url' => base_url('modules/servizi/fedelta/view.php?id=' . $row['id']),
        ];
    }

    $financeStmt = $pdo->prepare('SELECT id, descrizione, tipo_movimento, importo, stato, data_scadenza, data_pagamento, updated_at
        FROM entrate_uscite
        WHERE descrizione LIKE :term
            OR riferimento LIKE :term
            OR note LIKE :term
        ORDER BY updated_at DESC
        LIMIT 5');
    $financeStmt->execute([':term' => $likeTerm]);
    $finance = [];
    while ($row = $financeStmt->fetch()) {
        $movementTitle = $row['descrizione'] ?? '';
        if ($movementTitle === '') {
            $movementTitle = 'Movimento #' . $row['id'];
        }
        $subtitleParts = [];
        $subtitleParts[] = ($row['tipo_movimento'] ?? 'Movimento') . ' - ' . format_currency((float) ($row['importo'] ?? 0));
        if (!empty($row['stato'])) {
            $subtitleParts[] = 'Stato: ' . $row['stato'];
        }
        $dateRef = $row['data_pagamento'] ?: ($row['data_scadenza'] ?: ($row['updated_at'] ?? null));
        if ($dateRef) {
            $subtitleParts[] = format_date_locale($dateRef);
        }
        $finance[] = [
            'id' => (int) $row['id'],
            'title' => $movementTitle,
            'subtitle' => implode(' | ', array_filter($subtitleParts)),
            'badge' => $row['tipo_movimento'] ?? 'Movimento',
            'url' => base_url('modules/servizi/entrate-uscite/view.php?id=' . $row['id']),
        ];
    }

    $appointmentsStmt = $pdo->prepare('SELECT sa.id, sa.titolo, sa.tipo_servizio, sa.responsabile, sa.data_inizio, sa.stato, c.nome, c.cognome
        FROM servizi_appuntamenti sa
        LEFT JOIN clienti c ON c.id = sa.cliente_id
        WHERE sa.titolo LIKE :term
            OR sa.tipo_servizio LIKE :term
            OR sa.responsabile LIKE :term
            OR c.nome LIKE :term
            OR c.cognome LIKE :term
        ORDER BY sa.data_inizio DESC, sa.id DESC
        LIMIT 5');
    $appointmentsStmt->execute([':term' => $likeTerm]);
    $appointments = [];
    while ($row = $appointmentsStmt->fetch()) {
        $appointmentTitle = $row['titolo'] ?? '';
        if ($appointmentTitle === '') {
            $appointmentTitle = 'Appuntamento #' . $row['id'];
        }
        $customerName = trim((string) (($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? '')));
        $subtitleParts = [];
        if ($customerName !== '') {
            $subtitleParts[] = $customerName;
        }
        if (!empty($row['responsabile'])) {
            $subtitleParts[] = 'Responsabile: ' . $row['responsabile'];
        }
        if (!empty($row['data_inizio'])) {
            $subtitleParts[] = format_datetime_locale($row['data_inizio']);
        }
        $appointments[] = [
            'id' => (int) $row['id'],
            'title' => $appointmentTitle,
            'subtitle' => implode(' | ', array_filter($subtitleParts)),
            'badge' => $row['stato'] ?? ($row['tipo_servizio'] ?? 'Appuntamento'),
            'url' => base_url('modules/servizi/ricariche/view.php?id=' . $row['id']),
        ];
    }

    $telefoniaStmt = $pdo->prepare('SELECT t.id, t.operatore, t.tipo_contratto, t.stato, t.created_at, c.nome, c.cognome
        FROM telefonia t
        LEFT JOIN clienti c ON c.id = t.cliente_id
        WHERE t.operatore LIKE :term
            OR t.tipo_contratto LIKE :term
            OR t.note LIKE :term
            OR c.nome LIKE :term
            OR c.cognome LIKE :term
        ORDER BY t.updated_at DESC, t.id DESC
        LIMIT 5');
    $telefoniaStmt->execute([':term' => $likeTerm]);
    $telefonia = [];
    while ($row = $telefoniaStmt->fetch()) {
        $telefoniaTitle = $row['operatore'] ?? '';
        if ($telefoniaTitle === '') {
            $telefoniaTitle = 'Richiesta #' . $row['id'];
        }
        $customerName = trim((string) (($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? '')));
        $subtitleParts = [];
        if ($customerName !== '') {
            $subtitleParts[] = $customerName;
        }
        if (!empty($row['tipo_contratto'])) {
            $subtitleParts[] = $row['tipo_contratto'];
        }
        if (!empty($row['created_at'])) {
            $subtitleParts[] = format_date_locale($row['created_at']);
        }
        $telefonia[] = [
            'id' => (int) $row['id'],
            'title' => $telefoniaTitle,
            'subtitle' => implode(' | ', array_filter($subtitleParts)),
            'badge' => $row['stato'] ?? 'Telefonia',
            'url' => base_url('modules/servizi/telefonia/view.php?id=' . $row['id']),
        ];
    }

    $shipmentsStmt = $pdo->prepare('SELECT s.id, s.tipo_spedizione, s.mittente, s.destinatario, s.tracking_number, s.stato, s.created_at, c.nome, c.cognome
        FROM spedizioni s
        LEFT JOIN clienti c ON c.id = s.cliente_id
        WHERE s.tipo_spedizione LIKE :term
            OR s.mittente LIKE :term
            OR s.destinatario LIKE :term
            OR s.tracking_number LIKE :term
            OR c.nome LIKE :term
            OR c.cognome LIKE :term
        ORDER BY s.updated_at DESC, s.id DESC
        LIMIT 5');
    $shipmentsStmt->execute([':term' => $likeTerm]);
    $shipments = [];
    while ($row = $shipmentsStmt->fetch()) {
        $shipmentTitle = $row['tipo_spedizione'] ?? '';
        if ($shipmentTitle === '') {
            $shipmentTitle = 'Spedizione #' . $row['id'];
        }
        $subtitleParts = [];
        $recipient = $row['destinatario'] ?? '';
        if ($recipient !== '') {
            $subtitleParts[] = 'Destinatario: ' . $recipient;
        }
        if (!empty($row['tracking_number'])) {
            $subtitleParts[] = 'Tracking: ' . $row['tracking_number'];
        }
        if (!empty($row['created_at'])) {
            $subtitleParts[] = format_date_locale($row['created_at']);
        }
        $customerName = trim((string) (($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? '')));
        if ($customerName !== '') {
            $subtitleParts[] = $customerName;
        }
        $shipments[] = [
            'id' => (int) $row['id'],
            'title' => $shipmentTitle,
            'subtitle' => implode(' | ', array_filter($subtitleParts)),
            'badge' => $row['stato'] ?? 'Logistica',
            'url' => base_url('modules/servizi/logistici/view.php?id=' . $row['id']),
        ];
    }

    $payload = [
        'query' => $term,
        'results' => [
            'clients' => $clients,
            'tickets' => $tickets,
            'documents' => $documents,
            'loyalty' => $loyalty,
            'finance' => $finance,
            'appointments' => $appointments,
            'telefonia' => $telefonia,
            'shipments' => $shipments,
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

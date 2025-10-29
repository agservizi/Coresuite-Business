<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$response = [
    'stats' => [
        'totalClients' => 0,
        'servicesInProgress' => 0,
        'dailyRevenue' => 0.0,
        'openTickets' => 0,
        'financePending' => 0,
        'energyPending' => 0,
        'appointmentsToday' => 0,
        'anprInProgress' => 0,
    ],
    'charts' => [
        'revenue' => [
            'labels' => [],
            'values' => [],
        ],
        'services' => [
            'labels' => ['Entrate/Uscite', 'Appuntamenti', 'Programma Fedeltà', 'Curriculum', 'Pickup'],
            'values' => [0, 0, 0, 0, 0],
        ],
    ],
    'tickets' => [],
    'reminders' => [],
];

try {
    $response['stats']['totalClients'] = (int) $pdo->query('SELECT COUNT(*) FROM clienti')->fetchColumn();

    $servicesInProgressStmt = $pdo->query("SELECT COUNT(*) FROM (
        SELECT id FROM entrate_uscite WHERE stato IN ('In lavorazione', 'In attesa')
        UNION ALL
        SELECT id FROM servizi_appuntamenti WHERE stato IN ('Programmato', 'In corso')
        UNION ALL
    SELECT id FROM curriculum WHERE status <> 'Archiviato'
        UNION ALL
    SELECT id FROM spedizioni WHERE stato IN ('Registrato', 'In attesa di ritiro', 'Problema', 'In corso', 'Aperto')
    ) AS in_progress");
    $response['stats']['servicesInProgress'] = (int) $servicesInProgressStmt->fetchColumn();

    $dailyRevenueStmt = $pdo->prepare("SELECT COALESCE(SUM(importo), 0) FROM (
        SELECT CASE WHEN tipo_movimento = 'Entrata' THEN importo ELSE -importo END AS importo
        FROM entrate_uscite
        WHERE stato = 'Completato' AND DATE(COALESCE(data_pagamento, updated_at)) = CURRENT_DATE
    ) AS revenues");
    $dailyRevenueStmt->execute();
    $response['stats']['dailyRevenue'] = (float) $dailyRevenueStmt->fetchColumn();

    $response['stats']['financePending'] = (int) $pdo->query("SELECT COUNT(*) FROM entrate_uscite WHERE stato IN ('In lavorazione', 'In attesa')")->fetchColumn();

    $response['stats']['energyPending'] = (int) $pdo->query('SELECT COUNT(*) FROM energia_contratti WHERE email_sent_at IS NULL')->fetchColumn();

    $appointmentsTodayStmt = $pdo->prepare("SELECT COUNT(*) FROM servizi_appuntamenti WHERE DATE(data_inizio) = CURRENT_DATE AND stato IN ('Programmato', 'In corso')");
    $appointmentsTodayStmt->execute();
    $response['stats']['appointmentsToday'] = (int) $appointmentsTodayStmt->fetchColumn();

    $response['stats']['anprInProgress'] = (int) $pdo->query("SELECT COUNT(*) FROM anpr_pratiche WHERE stato = 'In lavorazione'")->fetchColumn();

    $ticketStmt = $pdo->prepare('SELECT id, titolo, stato, created_at FROM ticket ORDER BY created_at DESC LIMIT 5');
    $ticketStmt->execute();
    $tickets = $ticketStmt->fetchAll();
    $response['tickets'] = array_map(static function ($ticket) {
        return [
            'id' => (int) $ticket['id'],
            'title' => $ticket['titolo'],
            'status' => $ticket['stato'],
            'createdAt' => $ticket['created_at'],
        ];
    }, $tickets);
    $response['stats']['openTickets'] = count($tickets);

    $revenueChartStmt = $pdo->prepare("SELECT DATE_FORMAT(data_operazione, '%b %Y') AS label, SUM(importo) AS totale
        FROM (
         SELECT COALESCE(data_pagamento, data_scadenza, created_at) AS data_operazione,
             CASE WHEN tipo_movimento = 'Entrata' THEN importo ELSE -importo END AS importo
         FROM entrate_uscite WHERE stato = 'Completato'
        ) AS unified
        WHERE data_operazione >= DATE_SUB(CURRENT_DATE, INTERVAL 5 MONTH)
        GROUP BY DATE_FORMAT(data_operazione, '%Y-%m')
        ORDER BY MIN(data_operazione)");
    $revenueChartStmt->execute();

    while ($row = $revenueChartStmt->fetch()) {
        $response['charts']['revenue']['labels'][] = $row['label'];
        $response['charts']['revenue']['values'][] = (float) $row['totale'];
    }

    $serviceTotals = [
        'entrate_uscite' => 0,
        'servizi_appuntamenti' => 0,
        'fedelta_movimenti' => 0,
        'curriculum' => 0,
        'spedizioni' => 0,
    ];

    foreach ($serviceTotals as $table => &$value) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
        $value = (int) $stmt->fetchColumn();
    }
    unset($value);
    $response['charts']['services']['values'] = array_values($serviceTotals);

    $reminders = [];

    $pendingDocumentsStmt = $pdo->query("SELECT id, titolo, updated_at FROM documents WHERE stato <> 'Archiviato' ORDER BY updated_at ASC LIMIT 1");
    if ($pendingDoc = $pendingDocumentsStmt->fetch()) {
        $reminders[] = [
            'icon' => 'fa-folder-open',
            'title' => 'Documento da aggiornare',
            'detail' => sprintf('Aggiorna %s: ultima revisione il %s.', $pendingDoc['titolo'], format_datetime($pendingDoc['updated_at'] ?? '')),
            'url' => base_url('modules/documenti/view.php?id=' . $pendingDoc['id']),
        ];
    }

    $oldestTicketStmt = $pdo->prepare("SELECT id, titolo, created_at FROM ticket WHERE stato IN ('Aperto', 'In corso') ORDER BY created_at ASC LIMIT 1");
    $oldestTicketStmt->execute();
    if ($oldestTicket = $oldestTicketStmt->fetch()) {
        $reminders[] = [
            'icon' => 'fa-life-ring',
            'title' => 'Ticket da prendere in carico',
            'detail' => sprintf('Ticket #%d aperto il %s.', $oldestTicket['id'], format_datetime($oldestTicket['created_at'] ?? '')),
            'url' => base_url('modules/ticket/view.php?id=' . $oldestTicket['id']),
        ];
    }

    $pendingMovimentiStmt = $pdo->prepare("SELECT id, descrizione, stato, tipo_movimento, data_scadenza, updated_at FROM entrate_uscite WHERE stato IN ('In lavorazione', 'In attesa') ORDER BY COALESCE(data_scadenza, updated_at) ASC LIMIT 1");
    $pendingMovimentiStmt->execute();
    if ($pendingMovimento = $pendingMovimentiStmt->fetch()) {
        $movimentoLabel = $pendingMovimento['tipo_movimento'] ?? 'Entrata';
        $icon = $movimentoLabel === 'Uscita' ? 'fa-arrow-trend-down' : 'fa-arrow-trend-up';
        $reminders[] = [
            'icon' => $icon,
            'title' => sprintf('%s da completare', $movimentoLabel),
            'detail' => sprintf('%s in stato %s. Scadenza %s.',
                $pendingMovimento['descrizione'] ?: ($movimentoLabel . ' #' . $pendingMovimento['id']),
                strtoupper($pendingMovimento['stato'] ?? ''),
                $pendingMovimento['data_scadenza'] ? format_datetime($pendingMovimento['data_scadenza'], 'd/m/Y') : 'N/D'
            ),
            'url' => base_url('modules/servizi/entrate-uscite/view.php?id=' . $pendingMovimento['id']),
        ];
    }

    $response['reminders'] = $reminders;

    echo json_encode($response, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('Dashboard API failed: ' . $e->getMessage());
    http_response_code(500);
    try {
        echo json_encode(['error' => 'Impossibile aggiornare la dashboard in questo momento.'], JSON_THROW_ON_ERROR);
    } catch (JsonException $jsonException) {
        echo '{"error":"Dashboard offline"}';
    }
}

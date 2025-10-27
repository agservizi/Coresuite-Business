<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle = 'Dashboard';
$view = $_GET['view'] ?? '';

$stats = [
    'totalClients' => 0,
    'servicesInProgress' => 0,
    'dailyRevenue' => 0.0,
    'openTickets' => [],
];

$charts = [
    'revenue' => [
        'labels' => [],
        'values' => [],
    ],
    'services' => [
        'labels' => ['Entrate/Uscite', 'Appuntamenti', 'Programma Fedeltà', 'Curriculum', 'Pickup'],
        'values' => [0, 0, 0, 0, 0],
    ],
];

$reminders = [];
$dashboardUsername = current_user_display_name();

try {
    $stats['totalClients'] = (int) $pdo->query('SELECT COUNT(*) FROM clienti')->fetchColumn();

    $servicesInProgressStmt = $pdo->query("SELECT COUNT(*) FROM (
        SELECT id FROM entrate_uscite WHERE stato IN ('In lavorazione', 'In attesa')
        UNION ALL
        SELECT id FROM servizi_appuntamenti WHERE stato IN ('Programmato', 'In corso')
        UNION ALL
    SELECT id FROM curriculum WHERE status <> 'Archiviato'
        UNION ALL
    SELECT id FROM spedizioni WHERE stato IN ('Registrato', 'In attesa di ritiro', 'Problema', 'In corso', 'Aperto')
    ) AS in_progress");
    $stats['servicesInProgress'] = (int) $servicesInProgressStmt->fetchColumn();

    $dailyRevenueStmt = $pdo->prepare("SELECT COALESCE(SUM(importo), 0) FROM (
        SELECT CASE WHEN tipo_movimento = 'Entrata' THEN importo ELSE -importo END AS importo
        FROM entrate_uscite
        WHERE stato = 'Completato' AND DATE(COALESCE(data_pagamento, updated_at)) = CURRENT_DATE
    ) AS revenues");
    $dailyRevenueStmt->execute();
    $stats['dailyRevenue'] = (float) $dailyRevenueStmt->fetchColumn();

    $ticketStmt = $pdo->prepare("SELECT id, titolo, stato, created_at FROM ticket ORDER BY created_at DESC LIMIT 5");
    $ticketStmt->execute();
    $stats['openTickets'] = $ticketStmt->fetchAll();

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
        $charts['revenue']['labels'][] = $row['label'];
        $charts['revenue']['values'][] = (float) $row['totale'];
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

    $charts['services']['values'] = array_values($serviceTotals);

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
} catch (PDOException $e) {
    error_log('Dashboard query failed: ' . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>
    <main class="content-wrapper" data-dashboard-root data-dashboard-endpoint="api/dashboard.php" data-refresh-interval="60000">
        <?php if ($view === 'cliente' && $_SESSION['role'] === 'Cliente'): ?>
            <div class="row g-4 mb-4">
                <div class="col-12">
                    <div class="card ag-card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Benvenuto</h5>
                            <p class="mb-0">Consulta lo stato delle tue pratiche, scarica documenti e invia richieste di supporto.
                                Usa il menu per navigare.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>

            <div class="card ag-card dashboard-hero mb-4">
                <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4">
                    <div class="hero-copy">
                        <div class="badge ag-badge mb-3"><i class="fa-solid fa-gauge-high me-2"></i>Dashboard</div>
                        <h2 class="hero-title mb-2">Bentornato, <?php echo sanitize_output($dashboardUsername); ?></h2>
                        <p class="hero-subtitle mb-0">Consulta gli indicatori chiave e usa la barra di ricerca in testata per trovare subito ciò che ti serve.</p>
                    </div>
                    <div class="hero-kpi-grid">
                        <div class="hero-kpi">
                            <span class="hero-kpi-label">Clienti attivi</span>
                            <span class="hero-kpi-value" data-dashboard-stat="totalClients" data-format="number"><?php echo number_format($stats['totalClients']); ?></span>
                        </div>
                        <div class="hero-kpi">
                            <span class="hero-kpi-label">Servizi in corso</span>
                            <span class="hero-kpi-value" data-dashboard-stat="servicesInProgress" data-format="number"><?php echo number_format($stats['servicesInProgress']); ?></span>
                        </div>
                        <div class="hero-kpi">
                            <span class="hero-kpi-label">Saldo oggi</span>
                            <span class="hero-kpi-value" data-dashboard-stat="dailyRevenue" data-format="currency"><?php echo sanitize_output(format_currency($stats['dailyRevenue'])); ?></span>
                        </div>
                        <div class="hero-kpi">
                            <span class="hero-kpi-label">Ticket recenti</span>
                            <span class="hero-kpi-value" data-dashboard-stat="openTickets" data-format="number"><?php echo count($stats['openTickets']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dashboard-status alert alert-warning align-items-center gap-2 mb-4" id="dashboardStatus" role="status" hidden>
                <i class="fa-solid fa-circle-exclamation"></i>
                <span class="dashboard-status-text"></span>
                <button class="btn btn-sm btn-outline-warning ms-auto" type="button" id="dashboardRetry" hidden>Riprova</button>
            </div>

            <div class="card ag-card mb-4">
                <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h5 class="card-title mb-0">Indicatori principali</h5>
                        <small class="text-muted">Visione rapida delle metriche chiave</small>
                    </div>
                    <span class="badge ag-badge text-uppercase">Agg. ogni 60s</span>
                </div>
                <div class="card-body">
                    <div class="dashboard-summary-grid">
                        <div class="summary-tile">
                            <div class="summary-icon summary-icon-clients"><i class="fa-solid fa-users"></i></div>
                            <div class="summary-content">
                                <p class="summary-label mb-1">Clienti attivi</p>
                                <div class="summary-value" data-dashboard-stat="totalClients" data-format="number"><?php echo number_format($stats['totalClients']); ?></div>
                                <small class="text-muted">Anagrafica aggiornata</small>
                            </div>
                        </div>
                        <div class="summary-tile">
                            <div class="summary-icon summary-icon-services"><i class="fa-solid fa-diagram-project"></i></div>
                            <div class="summary-content">
                                <p class="summary-label mb-1">Servizi in corso</p>
                                <div class="summary-value" data-dashboard-stat="servicesInProgress" data-format="number"><?php echo number_format($stats['servicesInProgress']); ?></div>
                                <small class="text-muted">Workflow attivi</small>
                            </div>
                        </div>
                        <div class="summary-tile">
                            <div class="summary-icon summary-icon-revenue"><i class="fa-solid fa-euro-sign"></i></div>
                            <div class="summary-content">
                                <p class="summary-label mb-1">Saldo odierno</p>
                                <div class="summary-value" data-dashboard-stat="dailyRevenue" data-format="currency"><?php echo sanitize_output(format_currency($stats['dailyRevenue'])); ?></div>
                                <small class="text-muted">Entrate - Uscite del giorno</small>
                            </div>
                        </div>
                        <div class="summary-tile">
                            <div class="summary-icon summary-icon-tickets"><i class="fa-solid fa-life-ring"></i></div>
                            <div class="summary-content">
                                <p class="summary-label mb-1">Ticket recenti</p>
                                <div class="summary-value" data-dashboard-stat="openTickets" data-format="number"><?php echo count($stats['openTickets']); ?></div>
                                <small class="text-muted">Ultimi 5 registrati</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-12 col-xxl-8">
                    <div class="card ag-card h-100">
                        <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Trend Entrate/Uscite</h5>
                            <span class="text-muted small">Ultimi 6 mesi</span>
                        </div>
                        <div class="card-body">
                            <canvas id="chartRevenue" height="240"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xxl-4">
                    <div class="card ag-card h-100">
                        <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Ripartizione servizi</h5>
                            <span class="text-muted small">Pratiche per tipologia</span>
                        </div>
                        <div class="card-body">
                            <canvas id="chartServices" height="240"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12 col-xxl-7">
                    <div class="card ag-card h-100">
                        <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between">
                            <h5 class="card-title mb-0">Ticket in evidenza</h5>
                            <a class="btn btn-sm btn-outline-warning" href="modules/ticket/index.php">Vedi tutti</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" data-dashboard-table="tickets">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Titolo</th>
                                            <th>Stato</th>
                                            <th>Data</th>
                                        </tr>
                                    </thead>
                                    <tbody id="dashboardTicketsBody">
                                        <?php if ($stats['openTickets']): ?>
                                            <?php foreach ($stats['openTickets'] as $ticket): ?>
                                                <tr>
                                                    <td>#<?php echo sanitize_output($ticket['id']); ?></td>
                                                    <td><?php echo sanitize_output($ticket['titolo']); ?></td>
                                                    <td><span class="badge ag-badge text-uppercase"><?php echo sanitize_output($ticket['stato']); ?></span></td>
                                                    <td><?php echo sanitize_output(date('d/m/Y', strtotime($ticket['created_at']))); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-4">Nessun ticket disponibile.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xxl-5">
                    <div class="card ag-card h-100">
                        <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between">
                            <h5 class="card-title mb-0">Promemoria rapidi</h5>
                            <a class="btn btn-sm btn-link text-decoration-none" href="modules/servizi/entrate-uscite/index.php">
                                <i class="fa-solid fa-list-check me-1"></i>Gestisci attività
                            </a>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0 reminder-list" id="dashboardReminders">
                                <?php if ($reminders): ?>
                                    <?php foreach ($reminders as $reminder): ?>
                                        <li class="reminder-item d-flex align-items-start">
                                            <span class="badge ag-badge me-3"><i class="fa-solid <?php echo $reminder['icon']; ?>"></i></span>
                                            <div>
                                                <div class="fw-semibold">
                                                    <?php if (!empty($reminder['url'])): ?>
                                                        <a class="link-warning" href="<?php echo sanitize_output($reminder['url']); ?>"><?php echo sanitize_output($reminder['title']); ?></a>
                                                    <?php else: ?>
                                                        <?php echo sanitize_output($reminder['title']); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <small class="text-muted"><?php echo sanitize_output($reminder['detail']); ?></small>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li class="text-muted">Nessun promemoria attivo.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
    const revenueChartData = {
        labels: <?php echo json_encode($charts['revenue']['labels'], JSON_THROW_ON_ERROR); ?>,
        datasets: [{
            label: 'Saldo',
            data: <?php echo json_encode($charts['revenue']['values'], JSON_THROW_ON_ERROR); ?>,
            borderColor: '#0b2f6b',
            backgroundColor: 'rgba(11, 47, 107, 0.14)',
            tension: 0.4,
            fill: true,
        }]
    };

    const serviceChartData = {
        labels: <?php echo json_encode($charts['services']['labels'], JSON_THROW_ON_ERROR); ?>,
        datasets: [{
            label: 'Totale pratiche',
            data: <?php echo json_encode($charts['services']['values'], JSON_THROW_ON_ERROR); ?>,
            backgroundColor: [
                'rgba(11, 47, 107, 0.32)',
                'rgba(11, 47, 107, 0.26)',
                'rgba(11, 47, 107, 0.2)',
                'rgba(11, 47, 107, 0.16)',
                'rgba(11, 47, 107, 0.12)'
            ],
            borderColor: '#0b2f6b'
        }]
    };

    document.addEventListener('DOMContentLoaded', () => {
        const revenueCtx = document.getElementById('chartRevenue');
        const servicesCtx = document.getElementById('chartServices');
        const chartStore = window.CSCharts || (window.CSCharts = {});
        const chartLib = window.Chart;
        if (revenueCtx && chartLib) {
            const existing = typeof chartLib.getChart === 'function' ? chartLib.getChart(revenueCtx) : (revenueCtx.chart || revenueCtx._chart || null);
            if (existing && typeof existing.destroy === 'function') {
                existing.destroy();
            }
            chartStore.revenue = new chartLib(revenueCtx, {
                type: 'line',
                data: revenueChartData,
                options: {
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            ticks: {
                                callback: (value) => `€ ${value.toLocaleString('it-IT', { minimumFractionDigits: 2 })}`
                            }
                        }
                    }
                }
            });
        }
        if (servicesCtx && chartLib) {
            const existing = typeof chartLib.getChart === 'function' ? chartLib.getChart(servicesCtx) : (servicesCtx.chart || servicesCtx._chart || null);
            if (existing && typeof existing.destroy === 'function') {
                existing.destroy();
            }
            chartStore.services = new chartLib(servicesCtx, {
                type: 'doughnut',
                data: serviceChartData,
                options: {
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }
    });
</script>

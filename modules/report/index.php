<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_role('Admin', 'Operatore', 'Manager');
$pageTitle = 'Report e Statistiche';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-t');
$service = $_GET['service'] ?? 'all';
if ($service === 'pagamenti') {
    $service = 'entrate-uscite';
} elseif ($service === 'digitali') {
    $service = 'fedelta';
}
$owner = trim($_GET['responsabile'] ?? ($_GET['operator'] ?? ''));
$format = $_GET['export'] ?? '';

$filters = [':from' => $from, ':to' => $to];
$serviceMap = [
    'entrate-uscite' => [
        'table' => 'entrate_uscite',
        'columns' => ['tipo_movimento', 'descrizione', 'riferimento', 'metodo', 'stato', 'importo', 'data_scadenza', 'data_pagamento'],
        'date_column' => 'created_at',
        'label' => 'Entrate/Uscite',
    ],
    'appuntamenti' => [
        'table' => 'servizi_appuntamenti',
        'columns' => ['titolo', 'tipo_servizio', 'responsabile', 'stato', 'data_inizio', 'data_fine', 'luogo'],
        'date_column' => 'data_inizio',
        'label' => 'Appuntamenti',
    ],
    'fedelta' => [
        'table' => 'fedelta_movimenti',
        'columns' => ['tipo_movimento', 'descrizione', 'punti', 'saldo_post_movimento', 'ricompensa', 'operatore'],
        'date_column' => 'data_movimento',
        'label' => 'Programma Fedeltà',
    ],
    'telefonia' => [
        'table' => 'telefonia',
        'columns' => ['operatore', 'tipo_contratto', 'stato', 'created_at'],
        'date_column' => 'created_at',
        'label' => 'Telefonia',
    ],
    'logistica' => [
        'table' => 'spedizioni',
        'columns' => ['tipo_spedizione', 'tracking_number', 'stato', 'created_at'],
        'date_column' => 'created_at',
        'label' => 'Logistica',
    ],
];

$current = $serviceMap[$service] ?? null;

$dataset = [];
if ($current) {
    $query = "SELECT * FROM {$current['table']} WHERE {$current['date_column']} BETWEEN :from AND :to";
    if ($service === 'appuntamenti' && $owner !== '') {
        $query .= ' AND responsabile = :responsabile';
        $filters[':responsabile'] = $owner;
    }
    $query .= ' ORDER BY ' . $current['date_column'] . ' DESC';
    $stmt = $pdo->prepare($query);
    $stmt->execute($filters);
    $dataset = $stmt->fetchAll();
}

$summary = [
    'clients' => (int) $pdo->query('SELECT COUNT(*) FROM clienti')->fetchColumn(),
    'tickets' => (int) $pdo->query('SELECT COUNT(*) FROM ticket WHERE stato != "Chiuso"')->fetchColumn(),
    'revenue' => 0.0,
];

$revenueStmt = $pdo->prepare("SELECT COALESCE(SUM(importo),0) FROM (
    SELECT CASE WHEN tipo_movimento = 'Entrata' THEN importo ELSE -importo END AS importo,
           COALESCE(data_pagamento, data_scadenza, created_at) AS data_riferimento
    FROM entrate_uscite WHERE stato = 'Completato'
) AS revenues WHERE data_riferimento BETWEEN :from AND :to");
$revenueStmt->execute([':from' => $from, ':to' => $to]);
$summary['revenue'] = (float) $revenueStmt->fetchColumn();

if ($format === 'csv' && $current) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report_' . $service . '_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, array_merge(['ID'], $current['columns'], ['Data']));
    foreach ($dataset as $row) {
        $dataRow = [$row['id']];
        foreach ($current['columns'] as $col) {
            $value = $row[$col] ?? '';
            if ($current['table'] === 'entrate_uscite' && $col === 'importo') {
                $sign = (($row['tipo_movimento'] ?? 'Entrata') === 'Uscita') ? -1 : 1;
                $value = number_format(((float) $value) * $sign, 2, '.', '');
            } elseif ($current['table'] === 'fedelta_movimenti' && in_array($col, ['punti', 'saldo_post_movimento'], true)) {
                $value = (string) (int) $value;
            }
            $dataRow[] = $value;
        }
        $dataRow[] = $row[$current['date_column']] ?? '';
        fputcsv($out, $dataRow);
    }
    fclose($out);
    exit;
}

$owners = $pdo->query("SELECT DISTINCT responsabile FROM servizi_appuntamenti WHERE responsabile IS NOT NULL AND responsabile <> '' ORDER BY responsabile")->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>
    <main class="content-wrapper">
        <div class="row g-4 mb-4">
            <div class="col-lg-4 col-md-6">
                <div class="card ag-card h-100">
                    <div class="card-body">
                        <div class="card-title">Clienti attivi</div>
                        <div class="fs-2 fw-bold"><?php echo number_format($summary['clients']); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="card ag-card h-100">
                    <div class="card-body">
                        <div class="card-title">Ticket aperti</div>
                        <div class="fs-2 fw-bold"><?php echo number_format($summary['tickets']); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="card ag-card h-100">
                    <div class="card-body">
                        <div class="card-title">Saldo periodo</div>
                        <div class="fs-2 fw-bold"><?php echo sanitize_output(format_currency($summary['revenue'])); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card ag-card mb-4">
            <div class="card-header bg-transparent border-0">
                <h5 class="card-title mb-0">Filtri report</h5>
            </div>
            <div class="card-body">
                <form class="row g-3 align-items-end" method="get">
                    <div class="col-md-3">
                        <label class="form-label" for="from">Dal</label>
                        <input class="form-control" id="from" type="date" name="from" value="<?php echo sanitize_output($from); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="to">Al</label>
                        <input class="form-control" id="to" type="date" name="to" value="<?php echo sanitize_output($to); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="service">Servizio</label>
                        <select class="form-select" id="service" name="service">
                            <option value="all" <?php echo $service === 'all' ? 'selected' : ''; ?>>Tutti</option>
                            <?php foreach ($serviceMap as $key => $config): ?>
                                <option value="<?php echo $key; ?>" <?php echo $service === $key ? 'selected' : ''; ?>><?php echo sanitize_output($config['label'] ?? ucfirst($key)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($service === 'appuntamenti'): ?>
                        <div class="col-md-3">
                            <label class="form-label" for="responsabile">Responsabile</label>
                            <select class="form-select" id="responsabile" name="responsabile">
                                <option value="">Tutti</option>
                                <?php foreach ($owners as $responsabile): ?>
                                    <option value="<?php echo sanitize_output($responsabile); ?>" <?php echo $owner === $responsabile ? 'selected' : ''; ?>><?php echo sanitize_output($responsabile); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-3">
                        <button class="btn btn-warning text-dark w-100" type="submit">Applica filtri</button>
                    </div>
                    <?php if ($current): ?>
                        <div class="col-md-3">
                            <button class="btn btn-outline-warning w-100" type="submit" name="export" value="csv"><i class="fa-solid fa-file-csv me-2"></i>Esportazione CSV</button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if ($current): ?>
            <div class="card ag-card">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Risultati</h5>
                    <span class="text-muted small"><?php echo count($dataset); ?> record</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover" data-datatable="true">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <?php foreach ($current['columns'] as $col): ?>
                                        <th><?php echo ucwords(str_replace('_', ' ', $col)); ?></th>
                                    <?php endforeach; ?>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dataset as $row): ?>
                                    <tr>
                                        <td>#<?php echo (int) $row['id']; ?></td>
                                        <?php foreach ($current['columns'] as $col): ?>
                                            <?php
                                                $rawValue = $row[$col] ?? null;
                                                if ($col === 'importo') {
                                                    $factor = 1;
                                                    if ($current['table'] === 'entrate_uscite') {
                                                        $factor = (($row['tipo_movimento'] ?? 'Entrata') === 'Uscita') ? -1 : 1;
                                                    }
                                                    $displayValue = format_currency(((float) $rawValue) * $factor);
                                                } elseif ($current['table'] === 'fedelta_movimenti' && $col === 'punti') {
                                                    $points = (int) $rawValue;
                                                    $prefix = $points > 0 ? '+' : ($points < 0 ? '-' : '');
                                                    $displayValue = sprintf('%s%d pt', $prefix, abs($points));
                                                } elseif ($current['table'] === 'fedelta_movimenti' && $col === 'saldo_post_movimento') {
                                                    $balancePoints = (int) $rawValue;
                                                    $displayValue = number_format($balancePoints, 0, ',', '.') . ' pt';
                                                } elseif (in_array($col, ['data_scadenza', 'data_pagamento'], true)) {
                                                    $displayValue = $rawValue ? format_date_locale((string) $rawValue) : '—';
                                                } elseif (in_array($col, ['data_operazione', 'created_at'], true)) {
                                                    $displayValue = $rawValue ? format_date_locale((string) $rawValue) : '—';
                                                } elseif (in_array($col, ['data_inizio', 'data_fine', 'data_movimento'], true)) {
                                                    $displayValue = $rawValue ? format_datetime_locale((string) $rawValue) : '—';
                                                } else {
                                                    $displayValue = (string) ($rawValue ?? '');
                                                    if ($displayValue === '') {
                                                        $displayValue = '—';
                                                    }
                                                }
                                            ?>
                                            <td><?php echo sanitize_output($displayValue); ?></td>
                                        <?php endforeach; ?>
                                        <?php
                                            $dateValue = $row[$current['date_column']] ?? null;
                                            if ($dateValue) {
                                                if (in_array($current['date_column'], ['data_inizio', 'data_fine'], true)) {
                                                    $formattedDate = format_datetime_locale((string) $dateValue);
                                                } elseif ($current['date_column'] === 'data_movimento') {
                                                    $formattedDate = format_datetime_locale((string) $dateValue);
                                                } else {
                                                    $formattedDate = format_date_locale((string) $dateValue);
                                                }
                                            } else {
                                                $formattedDate = '—';
                                            }
                                        ?>
                                        <td><?php echo sanitize_output($formattedDate); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">Seleziona un servizio per visualizzare i dati dettagliati.</div>
        <?php endif; ?>
    </main>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

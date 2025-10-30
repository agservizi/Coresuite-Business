<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';

require_role('Admin', 'Operatore', 'Manager');
$pageTitle = 'Appuntamenti';

$params = [];
$filterStatus = trim($_GET['stato'] ?? '');
$filterOwner = trim($_GET['responsabile'] ?? '');
$filterFrom = trim($_GET['dal'] ?? '');
$filterTo = trim($_GET['al'] ?? '');

$sql = "SELECT sa.id, sa.titolo, sa.tipo_servizio, sa.responsabile, sa.stato, sa.data_inizio, sa.data_fine, sa.luogo, c.nome, c.cognome
    FROM servizi_appuntamenti sa
    LEFT JOIN clienti c ON sa.cliente_id = c.id";

$where = [];
if ($filterStatus !== '') {
    $where[] = 'sa.stato = :stato';
    $params[':stato'] = $filterStatus;
}
if ($filterOwner !== '') {
    $where[] = 'sa.responsabile = :responsabile';
    $params[':responsabile'] = $filterOwner;
}
if ($filterFrom !== '') {
    $fromDate = DateTimeImmutable::createFromFormat('Y-m-d', $filterFrom) ?: null;
    if ($fromDate) {
        $where[] = 'sa.data_inizio >= :dal';
        $params[':dal'] = $fromDate->format('Y-m-d 00:00:00');
    } else {
        $filterFrom = '';
    }
}
if ($filterTo !== '') {
    $toDate = DateTimeImmutable::createFromFormat('Y-m-d', $filterTo) ?: null;
    if ($toDate) {
        $where[] = 'sa.data_inizio <= :al';
        $params[':al'] = $toDate->format('Y-m-d 23:59:59');
    } else {
        $filterTo = '';
    }
}

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY sa.data_inizio DESC, sa.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$statuses = $pdo->query('SELECT DISTINCT stato FROM servizi_appuntamenti ORDER BY stato')->fetchAll(PDO::FETCH_COLUMN);
if (!$statuses) {
    $statuses = ['Programmato', 'Confermato', 'In corso', 'Completato', 'Annullato'];
}

$owners = $pdo->query("SELECT DISTINCT responsabile FROM servizi_appuntamenti WHERE responsabile IS NOT NULL AND responsabile <> '' ORDER BY responsabile")->fetchAll(PDO::FETCH_COLUMN);
$csrfToken = csrf_token();

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/../../../includes/topbar.php'; ?>
    <main class="content-wrapper">
        <div class="page-toolbar mb-4">
            <div>
                <h1 class="h3 mb-0">Appuntamenti</h1>
                <p class="text-muted mb-0">Agenda appuntamenti, sopralluoghi e scadenze operative.</p>
            </div>
            <div class="toolbar-actions">
                <a class="btn btn-warning text-dark" href="create.php"><i class="fa-solid fa-circle-plus me-2"></i>Nuovo appuntamento</a>
            </div>
        </div>
        <div class="card ag-card mb-4">
            <div class="card-header bg-transparent border-0">
                <h2 class="h5 mb-0">Filtri</h2>
            </div>
            <div class="card-body">
                <form class="row g-3 align-items-end" method="get">
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label" for="stato">Stato</label>
                        <select class="form-select" name="stato" id="stato">
                            <option value="">Tutti</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo sanitize_output($status); ?>" <?php echo $filterStatus === $status ? 'selected' : ''; ?>><?php echo sanitize_output($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label" for="responsabile">Responsabile</label>
                        <select class="form-select" name="responsabile" id="responsabile">
                            <option value="">Tutti</option>
                            <?php foreach ($owners as $owner): ?>
                                <option value="<?php echo sanitize_output($owner); ?>" <?php echo $filterOwner === $owner ? 'selected' : ''; ?>><?php echo sanitize_output($owner); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label" for="dal">Dal</label>
                        <input class="form-control" type="date" id="dal" name="dal" value="<?php echo sanitize_output($filterFrom); ?>">
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label" for="al">Al</label>
                        <input class="form-control" type="date" id="al" name="al" value="<?php echo sanitize_output($filterTo); ?>">
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <button class="btn btn-warning text-dark w-100" type="submit">Filtra</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card ag-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle" data-datatable="true">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Titolo</th>
                                <th>Tipo</th>
                                <th>Responsabile</th>
                                <th>Inizio</th>
                                <th>Fine</th>
                                <th>Stato</th>
                                <th class="text-end">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $row): ?>
                                <tr>
                                    <td>#<?php echo (int) $row['id']; ?></td>
                                    <td><?php echo sanitize_output(trim(($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? '')) ?: 'N/D'); ?></td>
                                    <td>
                                        <strong><?php echo sanitize_output($row['titolo'] ?? ''); ?></strong><br>
                                        <?php if (!empty($row['luogo'])): ?>
                                            <small class="text-muted"><?php echo sanitize_output($row['luogo']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo sanitize_output($row['tipo_servizio'] ?? ''); ?></td>
                                    <td><?php echo $row['responsabile'] ? sanitize_output($row['responsabile']) : '<span class="text-muted">N/D</span>'; ?></td>
                                    <td>
                                        <?php
                                            $startAt = format_datetime_locale($row['data_inizio'] ?? '');
                                            echo $startAt !== '' ? sanitize_output($startAt) : '<span class="text-muted">—</span>';
                                        ?>
                                    </td>
                                    <td><?php echo $row['data_fine'] ? sanitize_output(format_datetime_locale($row['data_fine'])) : '<span class="text-muted">—</span>'; ?></td>
                                    <td><span class="badge ag-badge text-uppercase"><?php echo sanitize_output($row['stato'] ?? ''); ?></span></td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <a class="btn btn-sm btn-outline-warning" href="view.php?id=<?php echo (int) $row['id']; ?>" title="Dettagli"><i class="fa-solid fa-eye"></i></a>
                                            <a class="btn btn-sm btn-outline-warning" href="edit.php?id=<?php echo (int) $row['id']; ?>" title="Modifica"><i class="fa-solid fa-pen"></i></a>
                                            <form method="post" action="delete.php" class="d-inline" onsubmit="return confirm('Confermi eliminazione dell\'appuntamento?');">
                                                <input type="hidden" name="_token" value="<?php echo sanitize_output($csrfToken); ?>">
                                                <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                                <button class="btn btn-sm btn-outline-warning" type="submit" title="Elimina"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';

require_role('Admin', 'Operatore');
$pageTitle = 'Ricariche';

$params = [];
$filterOperatore = trim($_GET['operatore'] ?? '');
$sql = "SELECT sr.id, sr.tipo, sr.operatore, sr.numero_riferimento, sr.importo, sr.stato, sr.data_operazione, c.nome, c.cognome
    FROM servizi_ricariche sr
    LEFT JOIN clienti c ON sr.cliente_id = c.id";

$where = [];
if ($filterOperatore !== '') {
    $where[] = 'sr.operatore = :operatore';
    $params[':operatore'] = $filterOperatore;
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY sr.data_operazione DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$topup = $stmt->fetchAll();

$clients = $pdo->query('SELECT id, nome, cognome FROM clienti ORDER BY cognome, nome')->fetchAll();
$operators = $pdo->query('SELECT DISTINCT operatore FROM servizi_ricariche ORDER BY operatore')->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/../../../includes/topbar.php'; ?>
    <main class="content-wrapper">
        <div class="page-toolbar mb-4">
            <div>
                <h1 class="h3 mb-0">Ricariche</h1>
                <p class="text-muted mb-0">Gestione ricariche telefoniche e tecnologiche.</p>
            </div>
            <div class="toolbar-actions">
                <a class="btn btn-warning text-dark" href="create.php"><i class="fa-solid fa-circle-plus me-2"></i>Nuova ricarica</a>
            </div>
        </div>
        <div class="card ag-card">
            <div class="card-header bg-transparent border-0">
                <form class="row g-2 align-items-end" method="get">
                    <div class="col-sm-6 col-lg-4">
                        <label class="form-label" for="operatore">Operatore</label>
                        <select class="form-select" name="operatore" id="operatore">
                            <option value="">Tutti</option>
                            <?php foreach ($operators as $operator): ?>
                                <option value="<?php echo sanitize_output($operator); ?>" <?php echo $filterOperatore === $operator ? 'selected' : ''; ?>><?php echo sanitize_output($operator); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-3 col-lg-2">
                        <button class="btn btn-warning text-dark w-100" type="submit">Filtra</button>
                    </div>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-dark table-hover" data-datatable="true">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Tipo</th>
                                <th>Operatore</th>
                                <th>Numero/Codice</th>
                                <th>Importo</th>
                                <th>Data</th>
                                <th>Stato</th>
                                <th class="text-end">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topup as $row): ?>
                                <tr>
                                    <td>#<?php echo (int) $row['id']; ?></td>
                                    <td><?php echo sanitize_output(trim(($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? '')) ?: 'N/D'); ?></td>
                                    <td><?php echo sanitize_output($row['tipo']); ?></td>
                                    <td><?php echo sanitize_output($row['operatore']); ?></td>
                                    <td><?php echo sanitize_output($row['numero_riferimento']); ?></td>
                                    <td><?php echo sanitize_output(format_currency((float) $row['importo'])); ?></td>
                                    <td><?php echo sanitize_output(date('d/m/Y', strtotime($row['data_operazione']))); ?></td>
                                    <td><span class="badge ag-badge text-uppercase"><?php echo sanitize_output($row['stato']); ?></span></td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <a class="btn btn-sm btn-outline-warning" href="view.php?id=<?php echo (int) $row['id']; ?>"><i class="fa-solid fa-eye"></i></a>
                                            <a class="btn btn-sm btn-outline-warning" href="edit.php?id=<?php echo (int) $row['id']; ?>"><i class="fa-solid fa-pen"></i></a>
                                            <form method="post" action="delete.php" onsubmit="return confirm('Confermi eliminazione?');">
                                                <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                                <button class="btn btn-sm btn-outline-warning" type="submit"><i class="fa-solid fa-trash"></i></button>
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

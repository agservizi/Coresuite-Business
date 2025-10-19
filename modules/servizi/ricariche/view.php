<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';

require_role('Admin', 'Operatore');
$pageTitle = 'Dettaglio ricarica';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT sr.*, c.nome, c.cognome, c.email FROM servizi_ricariche sr LEFT JOIN clienti c ON sr.cliente_id = c.id WHERE sr.id = :id');
$stmt->execute([':id' => $id]);
$record = $stmt->fetch();
if (!$record) {
    header('Location: index.php?notfound=1');
    exit;
}

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/../../../includes/topbar.php'; ?>
    <main class="content-wrapper">
        <div class="page-toolbar mb-4">
            <a class="btn btn-outline-warning" href="index.php"><i class="fa-solid fa-arrow-left"></i> Tutte le ricariche</a>
            <div class="toolbar-actions">
                <a class="btn btn-warning text-dark" href="edit.php?id=<?php echo $id; ?>"><i class="fa-solid fa-pen"></i> Modifica</a>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card ag-card h-100">
                    <div class="card-header bg-transparent border-0">
                        <h5 class="card-title mb-0">Dettagli ricarica</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5">ID</dt>
                            <dd class="col-sm-7">#<?php echo (int) $record['id']; ?></dd>
                            <dt class="col-sm-5">Tipo</dt>
                            <dd class="col-sm-7"><?php echo sanitize_output($record['tipo']); ?></dd>
                            <dt class="col-sm-5">Operatore</dt>
                            <dd class="col-sm-7"><?php echo sanitize_output($record['operatore']); ?></dd>
                            <dt class="col-sm-5">Numero / Codice</dt>
                            <dd class="col-sm-7"><?php echo sanitize_output($record['numero_riferimento']); ?></dd>
                            <dt class="col-sm-5">Importo</dt>
                            <dd class="col-sm-7"><?php echo sanitize_output(format_currency((float) $record['importo'])); ?></dd>
                            <dt class="col-sm-5">Data</dt>
                            <dd class="col-sm-7"><?php echo sanitize_output(date('d/m/Y', strtotime($record['data_operazione']))); ?></dd>
                            <dt class="col-sm-5">Stato</dt>
                            <dd class="col-sm-7"><span class="badge ag-badge text-uppercase"><?php echo sanitize_output($record['stato']); ?></span></dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card ag-card h-100">
                    <div class="card-header bg-transparent border-0">
                        <h5 class="card-title mb-0">Cliente</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5">Nome</dt>
                            <dd class="col-sm-7"><?php echo sanitize_output(trim(($record['cognome'] ?? '') . ' ' . ($record['nome'] ?? '')) ?: 'N/D'); ?></dd>
                            <dt class="col-sm-5">Email</dt>
                            <dd class="col-sm-7"><a class="link-warning" href="mailto:<?php echo sanitize_output($record['email']); ?>"><?php echo sanitize_output($record['email']); ?></a></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

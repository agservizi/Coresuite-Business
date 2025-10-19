<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';

require_role('Admin', 'Operatore');
$pageTitle = 'Servizi Logistici';

$sql = "SELECT s.id, s.tipo_spedizione, s.mittente, s.destinatario, s.tracking_number, s.stato, s.created_at, c.nome, c.cognome
    FROM spedizioni s
    LEFT JOIN clienti c ON s.cliente_id = c.id
    ORDER BY s.created_at DESC";
$stmt = $pdo->query($sql);
$records = $stmt->fetchAll();

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/../../../includes/topbar.php'; ?>
    <main class="content-wrapper">
        <div class="page-toolbar mb-4">
            <div>
                <h1 class="h3 mb-0">Servizi Logistici</h1>
                <p class="text-muted mb-0">Gestione spedizioni pacchi e corrispondenza.</p>
            </div>
            <div class="toolbar-actions">
                <a class="btn btn-warning text-dark" href="create.php"><i class="fa-solid fa-circle-plus me-2"></i>Nuova spedizione</a>
            </div>
        </div>
        <div class="card ag-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-dark table-hover" data-datatable="true">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Tipo</th>
                                <th>Mittente</th>
                                <th>Destinatario</th>
                                <th>Tracking</th>
                                <th>Stato</th>
                                <th class="text-end">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $row): ?>
                                <tr>
                                    <td>#<?php echo (int) $row['id']; ?></td>
                                    <td><?php echo sanitize_output(trim(($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? '')) ?: 'N/D'); ?></td>
                                    <td><?php echo sanitize_output($row['tipo_spedizione']); ?></td>
                                    <td><?php echo sanitize_output($row['mittente']); ?></td>
                                    <td><?php echo sanitize_output($row['destinatario']); ?></td>
                                    <td><span class="badge ag-badge"><?php echo sanitize_output($row['tracking_number']); ?></span></td>
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

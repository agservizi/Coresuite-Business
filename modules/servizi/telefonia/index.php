<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';

require_role('Admin', 'Operatore');
$pageTitle = 'Telefonia';

$sql = "SELECT t.id, t.operatore, t.tipo_contratto, t.stato, t.created_at, t.contratto_path, c.nome, c.cognome
    FROM telefonia t
    LEFT JOIN clienti c ON t.cliente_id = c.id
    ORDER BY t.created_at DESC";
$stmt = $pdo->query($sql);
$records = $stmt->fetchAll();

$operators = ['WindTre', 'Fastweb', 'Iliad', 'Vodafone', 'TIM'];
$contracts = ['Mobile', 'Fisso', 'Fibra'];

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/../../../includes/topbar.php'; ?>
    <main class="content-wrapper">
        <div class="page-toolbar mb-4">
            <div>
                <h1 class="h3 mb-0">Telefonia</h1>
                <p class="text-muted mb-0">Gestione richieste e contratti telefonici.</p>
            </div>
            <div class="toolbar-actions">
                <a class="btn btn-warning text-dark" href="create.php"><i class="fa-solid fa-circle-plus me-2"></i>Nuova richiesta</a>
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
                                <th>Operatore</th>
                                <th>Tipo contratto</th>
                                <th>Stato</th>
                                <th>Contratto</th>
                                <th>Creata il</th>
                                <th class="text-end">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $row): ?>
                                <tr>
                                    <td>#<?php echo (int) $row['id']; ?></td>
                                    <td><?php echo sanitize_output(trim(($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? '')) ?: 'N/D'); ?></td>
                                    <td><?php echo sanitize_output($row['operatore']); ?></td>
                                    <td><?php echo sanitize_output($row['tipo_contratto']); ?></td>
                                    <td><span class="badge ag-badge text-uppercase"><?php echo sanitize_output($row['stato']); ?></span></td>
                                    <td>
                                        <?php if ($row['contratto_path']): ?>
                                            <a class="btn btn-sm btn-outline-warning" href="../../../<?php echo sanitize_output($row['contratto_path']); ?>" target="_blank">PDF</a>
                                        <?php else: ?>
                                            <span class="text-muted">N/D</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo sanitize_output(date('d/m/Y', strtotime($row['created_at']))); ?></td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <a class="btn btn-sm btn-outline-warning" href="view.php?id=<?php echo (int) $row['id']; ?>"><i class="fa-solid fa-eye"></i></a>
                                            <a class="btn btn-sm btn-outline-warning" href="edit.php?id=<?php echo (int) $row['id']; ?>"><i class="fa-solid fa-pen"></i></a>
                                            <form method="post" action="delete.php" onsubmit="return confirm('Confermi l\'eliminazione?');">
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

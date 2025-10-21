<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_role('Admin', 'Operatore');
$pageTitle = 'Clienti';

$searchTerm = trim($_GET['q'] ?? '');
$createdFromRaw = trim($_GET['created_from'] ?? '');
$createdToRaw = trim($_GET['created_to'] ?? '');

$createdFrom = DateTimeImmutable::createFromFormat('Y-m-d', $createdFromRaw) ?: null;
$createdTo = DateTimeImmutable::createFromFormat('Y-m-d', $createdToRaw) ?: null;

if ($createdFrom && $createdTo && $createdFrom > $createdTo) {
    add_flash('warning', 'Intervallo date non valido: la data iniziale non può superare quella finale.');
    $createdTo = null;
}

$sql = 'SELECT id, nome, cognome, cf_piva, email, telefono, indirizzo, note, created_at FROM clienti';
$params = [];
$conditions = [];

if ($searchTerm !== '') {
    $conditions[] = '(
        nome LIKE :term_nome OR
        cognome LIKE :term_cognome OR
        email LIKE :term_email OR
        cf_piva LIKE :term_cf
    )';
    $likeTerm = "%{$searchTerm}%";
    $params[':term_nome'] = $likeTerm;
    $params[':term_cognome'] = $likeTerm;
    $params[':term_email'] = $likeTerm;
    $params[':term_cf'] = $likeTerm;
}

if ($createdFrom) {
    $conditions[] = 'DATE(created_at) >= :created_from';
    $params[':created_from'] = $createdFrom->format('Y-m-d');
}

if ($createdTo) {
    $conditions[] = 'DATE(created_at) <= :created_to';
    $params[':created_to'] = $createdTo->format('Y-m-d');
}

if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY cognome, nome';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

$csrfToken = csrf_token();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>
    <main class="content-wrapper">
        <div class="page-toolbar mb-4">
            <div>
                <h1 class="h3 mb-0">Gestione Clienti</h1>
                <p class="text-muted mb-0">Amministra l'anagrafica clienti e consulta lo storico dei servizi associati.</p>
            </div>
            <div class="toolbar-actions">
                <form class="toolbar-search" method="get" role="search">
                    <div class="input-group">
                        <input class="form-control" type="search" name="q" placeholder="Cerca per nome, email o CF" value="<?php echo sanitize_output($searchTerm); ?>">
                        <input class="form-control" type="date" name="created_from" value="<?php echo sanitize_output($createdFrom ? $createdFrom->format('Y-m-d') : ''); ?>" aria-label="Registrati dal">
                        <input class="form-control" type="date" name="created_to" value="<?php echo sanitize_output($createdTo ? $createdTo->format('Y-m-d') : ''); ?>" aria-label="Registrati fino al">
                        <button class="btn btn-warning" type="submit" title="Applica filtri"><i class="fa-solid fa-search"></i></button>
                        <a class="btn btn-outline-warning" href="index.php" title="Reimposta"><i class="fa-solid fa-rotate-left"></i></a>
                    </div>
                </form>
                <a class="btn btn-warning text-dark" href="create.php"><i class="fa-solid fa-user-plus me-2"></i>Nuovo cliente</a>
            </div>
        </div>

        <div class="card ag-card">
            <div class="card-body">
                <?php if ($clients): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover" data-datatable="true">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nome</th>
                                    <th>CF / P.IVA</th>
                                    <th>Email</th>
                                    <th>Telefono</th>
                                    <th>Registrato</th>
                                    <th class="text-end">Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clients as $client): ?>
                                    <tr>
                                        <td><?php echo (int) $client['id']; ?></td>
                                        <td>
                                            <div class="fw-semibold"><?php echo sanitize_output($client['cognome'] . ' ' . $client['nome']); ?></div>
                                            <small class="text-muted"><?php echo sanitize_output($client['indirizzo']); ?></small>
                                        </td>
                                        <td><?php echo sanitize_output($client['cf_piva']); ?></td>
                                        <td><a class="link-warning" href="mailto:<?php echo sanitize_output($client['email']); ?>"><?php echo sanitize_output($client['email']); ?></a></td>
                                        <td><a class="link-warning" href="tel:<?php echo sanitize_output($client['telefono']); ?>"><?php echo sanitize_output($client['telefono']); ?></a></td>
                                        <td><?php echo sanitize_output(date('d/m/Y', strtotime($client['created_at']))); ?></td>
                                        <td class="text-end">
                                            <div class="btn-group">
                                                <a class="btn btn-sm btn-outline-warning" href="view.php?id=<?php echo (int) $client['id']; ?>" title="Dettaglio"><i class="fa-solid fa-eye"></i></a>
                                                <a class="btn btn-sm btn-outline-warning" href="edit.php?id=<?php echo (int) $client['id']; ?>" title="Modifica"><i class="fa-solid fa-pen"></i></a>
                                                <button class="btn btn-sm btn-outline-warning" type="button" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?php echo (int) $client['id']; ?>" title="Elimina">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i class="fa-solid fa-users-slash fa-2x mb-3"></i>
                        <p class="mb-1">Nessun cliente corrisponde ai filtri applicati.</p>
                        <a class="btn btn-outline-warning" href="index.php">Reimposta filtri</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="deleteModalLabel">Conferma eliminazione</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Sei sicuro di voler eliminare questo cliente? L'operazione è irreversibile.
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-warning" data-bs-dismiss="modal">Annulla</button>
                <form id="deleteForm" method="post" action="delete.php">
                    <input type="hidden" name="_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="id" id="deleteId" value="">
                    <button type="submit" class="btn btn-warning">Elimina</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<script>
    const deleteModal = document.getElementById('deleteModal');
    deleteModal?.addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const deleteId = document.getElementById('deleteId');
        deleteId.value = id;
    });
</script>

<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/functions.php';

require_role('Admin', 'Operatore', 'Manager');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    add_flash('warning', 'Richiesta non trovata.');
    header('Location: index.php');
    exit;
}

$request = cittadino_cie_fetch_request($pdo, $id);
if ($request === null) {
    add_flash('warning', 'Richiesta non trovata.');
    header('Location: index.php');
    exit;
}

$pageTitle = 'Elimina richiesta ' . $request['request_code'];
$csrfToken = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();

    if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
        if (cittadino_cie_delete($pdo, $id)) {
            add_flash('success', 'Richiesta eliminata correttamente.');
        } else {
            add_flash('warning', 'Impossibile eliminare la richiesta. Riprova.');
        }
    }

    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/../../../includes/topbar.php'; ?>
    <main class="content-wrapper">
        <div class="mb-4">
            <a class="btn btn-outline-warning" href="view.php?id=<?php echo (int) $id; ?>"><i class="fa-solid fa-arrow-left"></i> Ritorna alla richiesta</a>
        </div>
        <div class="card ag-card">
            <div class="card-header bg-transparent border-0">
                <h1 class="h4 mb-0">Elimina richiesta <?php echo sanitize_output((string) $request['request_code']); ?></h1>
            </div>
            <div class="card-body">
                <p class="text-muted">Se confermi, la richiesta verrà rimossa definitivamente e non potrà essere recuperata.</p>
                <ul class="text-muted">
                    <li>Cittadino: <?php echo sanitize_output(trim(($request['cittadino_cognome'] ?? '') . ' ' . ($request['cittadino_nome'] ?? ''))); ?></li>
                    <li>Comune: <?php echo sanitize_output((string) $request['comune']); ?></li>
                    <li>Stato corrente: <?php echo sanitize_output(cittadino_cie_status_label((string) $request['stato'])); ?></li>
                </ul>
                <form method="post" class="d-flex gap-2">
                    <input type="hidden" name="_token" value="<?php echo sanitize_output($csrfToken); ?>">
                    <input type="hidden" name="confirm" value="yes">
                    <button class="btn btn-danger" type="submit"><i class="fa-solid fa-trash me-2"></i>Elimina definitivamente</button>
                    <a class="btn btn-secondary" href="view.php?id=<?php echo (int) $id; ?>">Annulla</a>
                </form>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

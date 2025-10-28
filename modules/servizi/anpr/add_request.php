<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/functions.php';

require_role('Admin', 'Operatore', 'Manager');
$pageTitle = 'Nuova pratica ANPR';

$types = anpr_practice_types();
$statuses = ANPR_ALLOWED_STATUSES;
$clienti = anpr_fetch_clienti($pdo);
$csrfToken = csrf_token();
$flashes = get_flashes();

$errors = [];
$data = [
    'cliente_id' => '',
    'tipo_pratica' => $types[0] ?? 'Certificato di residenza',
    'stato' => 'In lavorazione',
    'note_interne' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();

    $data['cliente_id'] = trim((string) ($_POST['cliente_id'] ?? ''));
    $data['tipo_pratica'] = trim((string) ($_POST['tipo_pratica'] ?? ''));
    $data['stato'] = trim((string) ($_POST['stato'] ?? ''));
    $data['note_interne'] = trim((string) ($_POST['note_interne'] ?? ''));

    $clienteId = (int) $data['cliente_id'];
    if ($clienteId <= 0) {
        $errors[] = 'Seleziona un cliente.';
    }

    if (!in_array($data['tipo_pratica'], $types, true)) {
        $errors[] = 'Seleziona una tipologia valida.';
    }

    if (!in_array($data['stato'], $statuses, true)) {
        $data['stato'] = 'In lavorazione';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $praticaCode = '';
            $attempts = 0;
            $maxAttempts = 5;
            $inserted = false;
            $operatoreId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

            while (!$inserted && $attempts < $maxAttempts) {
                $attempts++;
                $praticaCode = anpr_generate_pratica_code($pdo);
                try {
                    $stmt = $pdo->prepare('INSERT INTO anpr_pratiche (
                        pratica_code,
                        cliente_id,
                        tipo_pratica,
                        stato,
                        note_interne,
                        operatore_id,
                        created_at,
                        updated_at
                    ) VALUES (
                        :pratica_code,
                        :cliente_id,
                        :tipo_pratica,
                        :stato,
                        :note_interne,
                        :operatore_id,
                        NOW(),
                        NOW()
                    )');
                    $stmt->execute([
                        ':pratica_code' => $praticaCode,
                        ':cliente_id' => $clienteId,
                        ':tipo_pratica' => $data['tipo_pratica'],
                        ':stato' => $data['stato'],
                        ':note_interne' => $data['note_interne'] !== '' ? $data['note_interne'] : null,
                        ':operatore_id' => $operatoreId,
                    ]);
                    $inserted = true;
                } catch (PDOException $exception) {
                    if ((int) $exception->getCode() !== 23000) {
                        throw $exception;
                    }
                }
            }

            if (!$inserted) {
                throw new RuntimeException('Impossibile generare un codice pratica univoco.');
            }

            $praticaId = (int) $pdo->lastInsertId();

            if (!empty($_FILES['certificato']['name'])) {
                $stored = anpr_store_certificate($_FILES['certificato'], $praticaId);
                $updateStmt = $pdo->prepare('UPDATE anpr_pratiche
                    SET certificato_path = :path,
                        certificato_hash = :hash,
                        certificato_caricato_at = NOW()
                    WHERE id = :id');
                $updateStmt->execute([
                    ':path' => $stored['path'],
                    ':hash' => $stored['hash'],
                    ':id' => $praticaId,
                ]);
            }

            if (!empty($_FILES['delega']['name'])) {
                $storedDelega = anpr_store_delega($_FILES['delega'], $praticaId);
                $updateStmt = $pdo->prepare('UPDATE anpr_pratiche
                    SET delega_path = :path,
                        delega_hash = :hash,
                        delega_caricato_at = NOW()
                    WHERE id = :id');
                $updateStmt->execute([
                    ':path' => $storedDelega['path'],
                    ':hash' => $storedDelega['hash'],
                    ':id' => $praticaId,
                ]);
            }

            if (!empty($_FILES['documento']['name'])) {
                $storedDocumento = anpr_store_documento($_FILES['documento'], $praticaId);
                $updateStmt = $pdo->prepare('UPDATE anpr_pratiche
                    SET documento_path = :path,
                        documento_hash = :hash,
                        documento_caricato_at = NOW()
                    WHERE id = :id');
                $updateStmt->execute([
                    ':path' => $storedDocumento['path'],
                    ':hash' => $storedDocumento['hash'],
                    ':id' => $praticaId,
                ]);
            }

            $pdo->commit();

            anpr_log_action($pdo, 'Pratica creata', 'Creata pratica ANPR ' . $praticaCode);
            add_flash('success', 'Pratica creata correttamente. Codice: ' . $praticaCode . '.');
            header('Location: index.php');
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('ANPR create failed: ' . $exception->getMessage());
            if ($exception instanceof RuntimeException) {
                $errors[] = $exception->getMessage();
            } else {
                $errors[] = 'Impossibile salvare la pratica. Riprova.';
            }
        }
    }
}

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/../../../includes/topbar.php'; ?>
    <main class="content-wrapper">
        <div class="page-toolbar mb-4">
            <div>
                <h1 class="h3 mb-0">Nuova pratica ANPR</h1>
                <p class="text-muted mb-0">Registra una nuova richiesta anagrafica.</p>
            </div>
            <div class="toolbar-actions d-flex gap-2">
                <a class="btn btn-outline-warning" href="https://www.anpr.interno.it/portale/web/guest/accesso-ai-servizi" target="_blank" rel="noopener">
                    <i class="fa-solid fa-up-right-from-square me-2"></i>Portale ANPR
                </a>
                <a class="btn btn-outline-warning" href="index.php"><i class="fa-solid fa-arrow-left me-2"></i>Indietro</a>
            </div>
        </div>

        <?php if ($flashes): ?>
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'warning'; ?>"><?php echo sanitize_output($flash['message']); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-warning">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo sanitize_output($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card ag-card">
            <div class="card-body">
                <form method="post" enctype="multipart/form-data" class="row g-4">
                    <input type="hidden" name="_token" value="<?php echo sanitize_output($csrfToken); ?>">
                    <div class="col-md-6">
                        <label class="form-label" for="cliente_id">Cliente <span class="text-warning">*</span></label>
                        <select class="form-select" id="cliente_id" name="cliente_id" required>
                            <option value="">Seleziona cliente</option>
                            <?php foreach ($clienti as $cliente): ?>
                                <?php $cid = (int) $cliente['id']; ?>
                                <option value="<?php echo $cid; ?>" <?php echo (string) $cid === $data['cliente_id'] ? 'selected' : ''; ?>><?php echo sanitize_output(trim($cliente['ragione_sociale'] ?: (($cliente['cognome'] ?? '') . ' ' . ($cliente['nome'] ?? '')))); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="tipo_pratica">Tipologia <span class="text-warning">*</span></label>
                        <select class="form-select" id="tipo_pratica" name="tipo_pratica" required>
                            <?php foreach ($types as $type): ?>
                                <option value="<?php echo sanitize_output($type); ?>" <?php echo $data['tipo_pratica'] === $type ? 'selected' : ''; ?>><?php echo sanitize_output($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="stato">Stato pratica</label>
                        <select class="form-select" id="stato" name="stato">
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo sanitize_output($status); ?>" <?php echo $data['stato'] === $status ? 'selected' : ''; ?>><?php echo sanitize_output($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="note_interne">Note interne</label>
                        <textarea class="form-control" id="note_interne" name="note_interne" rows="4" placeholder="Annotazioni operative"><?php echo sanitize_output($data['note_interne']); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="delega">Delega firmata (PDF)</label>
                        <input class="form-control" type="file" id="delega" name="delega" accept="application/pdf">
                        <small class="text-muted">Facoltativo. Dimensione massima 10 MB.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="documento">Documento identità delegante (PDF/JPG/PNG)</label>
                        <input class="form-control" type="file" id="documento" name="documento" accept="application/pdf,image/jpeg,image/png">
                        <small class="text-muted">Facoltativo. Dimensione massima 10 MB.</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="certificato">Certificato (PDF)</label>
                        <input class="form-control" type="file" id="certificato" name="certificato" accept="application/pdf">
                        <small class="text-muted">Facoltativo. Dimensione massima 15 MB. Caricare solo dopo l’estrazione dal portale ANPR.</small>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a class="btn btn-outline-warning" href="index.php">Annulla</a>
                        <button class="btn btn-warning text-dark" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Salva pratica</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

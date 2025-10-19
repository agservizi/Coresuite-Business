<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';

require_role('Admin', 'Operatore');
$pageTitle = 'Modifica servizio digitale';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM servizi_digitali WHERE id = :id');
$stmt->execute([':id' => $id]);
$record = $stmt->fetch();
if (!$record) {
    header('Location: index.php?notfound=1');
    exit;
}

$clients = $pdo->query('SELECT id, nome, cognome FROM clienti ORDER BY cognome, nome')->fetchAll();
$types = ['SPID', 'PEC', 'Firma Digitale'];
$statuses = ['In attesa', 'In lavorazione', 'Completato', 'Annullato'];

$data = $record;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['cliente_id', 'tipo', 'stato', 'note'];
    foreach ($fields as $field) {
        $data[$field] = trim($_POST[$field] ?? '');
    }

    if ((int) $data['cliente_id'] <= 0) {
        $errors[] = 'Seleziona un cliente valido.';
    }
    if (!in_array($data['tipo'], $types, true)) {
        $errors[] = 'Tipo non valido.';
    }
    if (!in_array($data['stato'], $statuses, true)) {
        $errors[] = 'Stato non valido.';
    }

    $documentPath = $record['documento_path'];
    if (!empty($_FILES['documento']['name'])) {
        $file = $_FILES['documento'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowed = ['pdf', 'zip', 'rar'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                $errors[] = 'Formato documento non supportato (solo PDF/ZIP/RAR).';
            } else {
                $fileName = sprintf('digitale_%s.%s', uniqid('', true), $ext);
                $destination = __DIR__ . '/../../../assets/uploads/digitali/' . $fileName;
                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    $errors[] = 'Errore nel caricamento del documento.';
                } else {
                    $documentPath = 'assets/uploads/digitali/' . $fileName;
                }
            }
        } else {
            $errors[] = 'Errore nel caricamento del documento.';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare('UPDATE servizi_digitali SET cliente_id = :cliente_id, tipo = :tipo, stato = :stato, note = :note, documento_path = :documento_path WHERE id = :id');
        $stmt->execute([
            ':cliente_id' => (int) $data['cliente_id'],
            ':tipo' => $data['tipo'],
            ':stato' => $data['stato'],
            ':note' => $data['note'],
            ':documento_path' => $documentPath,
            ':id' => $id,
        ]);
        header('Location: view.php?id=' . $id . '&updated=1');
        exit;
    }
}

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/../../../includes/topbar.php'; ?>
    <main class="content-wrapper">
        <div class="mb-4">
            <a class="btn btn-outline-warning" href="view.php?id=<?php echo $id; ?>"><i class="fa-solid fa-arrow-left"></i> Dettaglio pratica</a>
        </div>
        <div class="card ag-card">
            <div class="card-header bg-transparent border-0">
                <h1 class="h4 mb-0">Modifica pratica digitale</h1>
            </div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-warning"><?php echo implode('<br>', array_map('sanitize_output', $errors)); ?></div>
                <?php endif; ?>
                <form method="post" enctype="multipart/form-data" novalidate>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label" for="cliente_id">Cliente</label>
                            <select class="form-select" id="cliente_id" name="cliente_id">
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo (int) $client['id']; ?>" <?php echo ((int) $data['cliente_id'] === (int) $client['id']) ? 'selected' : ''; ?>>
                                        <?php echo sanitize_output($client['cognome'] . ' ' . $client['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="tipo">Tipo servizio</label>
                            <select class="form-select" id="tipo" name="tipo">
                                <?php foreach ($types as $type): ?>
                                    <option value="<?php echo $type; ?>" <?php echo $data['tipo'] === $type ? 'selected' : ''; ?>><?php echo $type; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="stato">Stato</label>
                            <select class="form-select" id="stato" name="stato">
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo $data['stato'] === $status ? 'selected' : ''; ?>><?php echo $status; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="note">Note</label>
                            <textarea class="form-control" id="note" name="note" rows="4"><?php echo sanitize_output($data['note']); ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="documento">Aggiorna documento</label>
                            <input class="form-control" id="documento" name="documento" type="file" accept="application/pdf,.zip,.rar">
                            <?php if ($data['documento_path']): ?>
                                <small class="d-block mt-2">Corrente: <a class="link-warning" href="../../../<?php echo sanitize_output($data['documento_path']); ?>" target="_blank">Scarica</a></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a class="btn btn-secondary" href="view.php?id=<?php echo $id; ?>">Annulla</a>
                        <button class="btn btn-warning text-dark" type="submit">Salva modifiche</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

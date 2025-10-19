<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';

require_role('Admin', 'Operatore', 'Manager');
$pageTitle = 'Modifica richiesta telefonia';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM telefonia WHERE id = :id');
$stmt->execute([':id' => $id]);
$record = $stmt->fetch();
if (!$record) {
    header('Location: index.php?notfound=1');
    exit;
}

$clients = $pdo->query('SELECT id, nome, cognome FROM clienti ORDER BY cognome, nome')->fetchAll();
$operators = ['WindTre', 'Fastweb', 'Iliad', 'Vodafone', 'TIM'];
$contracts = ['Mobile', 'Fisso', 'Fibra'];
$statuses = ['Nuova', 'In lavorazione', 'Attivato', 'Respinto'];

$data = $record;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['cliente_id', 'operatore', 'tipo_contratto', 'stato', 'note'];
    foreach ($fields as $field) {
        $data[$field] = trim($_POST[$field] ?? '');
    }

    if ((int) $data['cliente_id'] <= 0) {
        $errors[] = 'Seleziona un cliente valido.';
    }
    if ($data['operatore'] === '') {
        $errors[] = 'Seleziona un operatore.';
    }
    if (!in_array($data['tipo_contratto'], $contracts, true)) {
        $errors[] = 'Tipo contratto non valido.';
    }
    if (!in_array($data['stato'], $statuses, true)) {
        $errors[] = 'Stato non valido.';
    }

    $contractPath = $record['contratto_path'];
    if (!empty($_FILES['contratto']['name'])) {
        $file = $_FILES['contratto'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                $errors[] = 'Carica solo file PDF.';
            } else {
                $fileName = sprintf('contratto_%s.%s', uniqid('', true), $ext);
                $destination = __DIR__ . '/../../../assets/uploads/telefonia/' . $fileName;
                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    $errors[] = 'Errore caricamento contratto.';
                } else {
                    $contractPath = 'assets/uploads/telefonia/' . $fileName;
                }
            }
        } else {
            $errors[] = 'Errore caricamento contratto.';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare('UPDATE telefonia SET cliente_id = :cliente_id, operatore = :operatore, tipo_contratto = :tipo_contratto, stato = :stato, note = :note, contratto_path = :contratto_path WHERE id = :id');
        $stmt->execute([
            ':cliente_id' => (int) $data['cliente_id'],
            ':operatore' => $data['operatore'],
            ':tipo_contratto' => $data['tipo_contratto'],
            ':stato' => $data['stato'],
            ':note' => $data['note'],
            ':contratto_path' => $contractPath,
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
            <a class="btn btn-outline-warning" href="view.php?id=<?php echo $id; ?>"><i class="fa-solid fa-arrow-left"></i> Dettaglio richiesta</a>
        </div>
        <div class="card ag-card">
            <div class="card-header bg-transparent border-0">
                <h1 class="h4 mb-0">Modifica richiesta telefonia</h1>
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
                            <label class="form-label" for="operatore">Operatore</label>
                            <select class="form-select" id="operatore" name="operatore">
                                <?php foreach ($operators as $operator): ?>
                                    <option value="<?php echo $operator; ?>" <?php echo $data['operatore'] === $operator ? 'selected' : ''; ?>><?php echo $operator; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="tipo_contratto">Tipo contratto</label>
                            <select class="form-select" id="tipo_contratto" name="tipo_contratto">
                                <?php foreach ($contracts as $contract): ?>
                                    <option value="<?php echo $contract; ?>" <?php echo $data['tipo_contratto'] === $contract ? 'selected' : ''; ?>><?php echo $contract; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
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
                            <label class="form-label" for="contratto">Aggiorna contratto (PDF)</label>
                            <input class="form-control" id="contratto" name="contratto" type="file" accept="application/pdf">
                            <?php if ($data['contratto_path']): ?>
                                <small class="d-block mt-2">Corrente: <a class="link-warning" href="../../../<?php echo sanitize_output($data['contratto_path']); ?>" target="_blank">Scarica</a></small>
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

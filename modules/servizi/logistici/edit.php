<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';

require_role('Admin', 'Operatore', 'Manager');
$pageTitle = 'Modifica pickup';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM spedizioni WHERE id = :id');
$stmt->execute([':id' => $id]);
$record = $stmt->fetch();
if (!$record) {
    header('Location: index.php?notfound=1');
    exit;
}

$clients = $pdo->query('SELECT id, nome, cognome FROM clienti ORDER BY cognome, nome')->fetchAll();
$types = ['Deposito pacchi', 'Ritiro pacchi'];
$statuses = ['Registrato', 'In attesa di ritiro', 'Consegnato', 'Problema'];

if (!in_array($record['tipo_spedizione'], $types, true)) {
    $types[] = $record['tipo_spedizione'];
}
if (!in_array($record['stato'], $statuses, true)) {
    $statuses[] = $record['stato'];
}

$data = $record;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['cliente_id', 'tipo_spedizione', 'mittente', 'destinatario', 'tracking_number', 'stato', 'note'];
    foreach ($fields as $field) {
        $data[$field] = trim($_POST[$field] ?? '');
    }

    if ((int) $data['cliente_id'] <= 0) {
        $errors[] = 'Seleziona un cliente valido.';
    }
    if (!in_array($data['tipo_spedizione'], $types, true)) {
        $errors[] = 'Tipo richiesta non valido.';
    }
    if ($data['mittente'] === '' || $data['destinatario'] === '') {
        $errors[] = 'Mittente e destinatario sono obbligatori.';
    }
    if ($data['tracking_number'] === '') {
        $errors[] = 'Inserisci un codice pickup.';
    }
    if (!in_array($data['stato'], $statuses, true)) {
        $errors[] = 'Stato non valido.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('UPDATE spedizioni SET cliente_id = :cliente_id, tipo_spedizione = :tipo_spedizione, mittente = :mittente, destinatario = :destinatario, tracking_number = :tracking_number, stato = :stato, note = :note WHERE id = :id');
        $stmt->execute([
            ':cliente_id' => (int) $data['cliente_id'],
            ':tipo_spedizione' => $data['tipo_spedizione'],
            ':mittente' => $data['mittente'],
            ':destinatario' => $data['destinatario'],
            ':tracking_number' => $data['tracking_number'],
            ':stato' => $data['stato'],
            ':note' => $data['note'],
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
            <a class="btn btn-outline-warning" href="view.php?id=<?php echo $id; ?>"><i class="fa-solid fa-arrow-left"></i> Dettaglio pickup</a>
        </div>
        <div class="card ag-card">
            <div class="card-header bg-transparent border-0">
                <h1 class="h4 mb-0">Modifica pickup</h1>
            </div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-warning"><?php echo implode('<br>', array_map('sanitize_output', $errors)); ?></div>
                <?php endif; ?>
                <form method="post" novalidate>
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
                            <label class="form-label" for="tipo_spedizione">Tipo richiesta</label>
                            <select class="form-select" id="tipo_spedizione" name="tipo_spedizione">
                                <?php foreach ($types as $type): ?>
                                    <option value="<?php echo $type; ?>" <?php echo $data['tipo_spedizione'] === $type ? 'selected' : ''; ?>><?php echo $type; ?></option>
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
                        <div class="col-md-6">
                            <label class="form-label" for="mittente">Mittente</label>
                            <input class="form-control" id="mittente" name="mittente" value="<?php echo sanitize_output($data['mittente']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="destinatario">Destinatario</label>
                            <input class="form-control" id="destinatario" name="destinatario" value="<?php echo sanitize_output($data['destinatario']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="tracking_number">Codice pickup</label>
                            <input class="form-control" id="tracking_number" name="tracking_number" value="<?php echo sanitize_output($data['tracking_number']); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="note">Note</label>
                            <textarea class="form-control" id="note" name="note" rows="4"><?php echo sanitize_output($data['note']); ?></textarea>
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

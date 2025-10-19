<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';

require_role('Admin', 'Operatore');
$pageTitle = 'Nuova ricarica';

$clients = $pdo->query('SELECT id, nome, cognome FROM clienti ORDER BY cognome, nome')->fetchAll();
$types = ['Telefonica', 'Tecnologica'];
$operators = ['WindTre', 'Fastweb', 'Iliad', 'Vodafone', 'TIM', 'PosteMobile'];
$statuses = ['Aperto', 'In corso', 'Completato', 'Errore'];

$data = [
    'cliente_id' => '',
    'tipo' => 'Telefonica',
    'operatore' => '',
    'numero_riferimento' => '',
    'importo' => '',
    'stato' => 'Aperto',
    'data_operazione' => date('Y-m-d'),
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $field => $_) {
        $data[$field] = trim($_POST[$field] ?? '');
    }

    if ((int) $data['cliente_id'] <= 0) {
        $errors[] = 'Seleziona un cliente valido.';
    }
    if (!in_array($data['tipo'], $types, true)) {
        $errors[] = 'Tipo non valido.';
    }
    if ($data['operatore'] === '') {
        $errors[] = 'Seleziona un operatore.';
    }
    if ($data['numero_riferimento'] === '') {
        $errors[] = 'Inserisci numero o codice da ricaricare.';
    }
    if (!is_numeric($data['importo'])) {
        $errors[] = 'Importo non valido.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO servizi_ricariche (cliente_id, tipo, operatore, numero_riferimento, importo, stato, data_operazione) VALUES (:cliente_id, :tipo, :operatore, :numero_riferimento, :importo, :stato, :data_operazione)');
        $stmt->execute([
            ':cliente_id' => (int) $data['cliente_id'],
            ':tipo' => $data['tipo'],
            ':operatore' => $data['operatore'],
            ':numero_riferimento' => $data['numero_riferimento'],
            ':importo' => (float) $data['importo'],
            ':stato' => $data['stato'],
            ':data_operazione' => $data['data_operazione'],
        ]);

        header('Location: index.php?created=1');
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
            <a class="btn btn-outline-warning" href="index.php"><i class="fa-solid fa-arrow-left"></i> Ritorna alle ricariche</a>
        </div>
        <div class="card ag-card">
            <div class="card-header bg-transparent border-0">
                <h1 class="h4 mb-0">Nuova ricarica</h1>
            </div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-warning"><?php echo implode('<br>', array_map('sanitize_output', $errors)); ?></div>
                <?php endif; ?>
                <form method="post" novalidate>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label" for="cliente_id">Cliente</label>
                            <select class="form-select" id="cliente_id" name="cliente_id" required>
                                <option value="">Seleziona cliente</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo (int) $client['id']; ?>" <?php echo ((int) $data['cliente_id'] === (int) $client['id']) ? 'selected' : ''; ?>>
                                        <?php echo sanitize_output($client['cognome'] . ' ' . $client['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="tipo">Tipo</label>
                            <select class="form-select" id="tipo" name="tipo">
                                <?php foreach ($types as $type): ?>
                                    <option value="<?php echo $type; ?>" <?php echo $data['tipo'] === $type ? 'selected' : ''; ?>><?php echo $type; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="operatore">Operatore</label>
                            <select class="form-select" id="operatore" name="operatore" required>
                                <option value="">Seleziona</option>
                                <?php foreach ($operators as $operator): ?>
                                    <option value="<?php echo $operator; ?>" <?php echo $data['operatore'] === $operator ? 'selected' : ''; ?>><?php echo $operator; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="numero_riferimento">Numero / Codice</label>
                            <input class="form-control" id="numero_riferimento" name="numero_riferimento" value="<?php echo sanitize_output($data['numero_riferimento']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="importo">Importo</label>
                            <div class="input-group">
                                <span class="input-group-text">€</span>
                                <input class="form-control" id="importo" name="importo" type="number" step="0.01" value="<?php echo sanitize_output($data['importo']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="data_operazione">Data</label>
                            <input class="form-control" id="data_operazione" type="date" name="data_operazione" value="<?php echo sanitize_output($data['data_operazione']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="stato">Stato</label>
                            <select class="form-select" id="stato" name="stato">
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo $data['stato'] === $status ? 'selected' : ''; ?>><?php echo $status; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a class="btn btn-secondary" href="index.php">Annulla</a>
                        <button class="btn btn-warning text-dark" type="submit">Registra ricarica</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

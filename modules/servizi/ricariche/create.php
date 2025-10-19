<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';

require_role('Admin', 'Operatore');
$pageTitle = 'Nuovo appuntamento';

$clients = $pdo->query('SELECT id, nome, cognome FROM clienti ORDER BY cognome, nome')->fetchAll();
$serviceTypes = ['Consulenza', 'Sopralluogo', 'Supporto tecnico', 'Rinnovo servizio'];
$statuses = ['Programmato', 'In corso', 'Completato', 'Annullato'];
$responsabili = $pdo->query("SELECT username FROM users WHERE ruolo IN ('Admin', 'Manager', 'Operatore') ORDER BY username")->fetchAll(PDO::FETCH_COLUMN);

$data = [
    'cliente_id' => '',
    'titolo' => '',
    'tipo_servizio' => $serviceTypes[0],
    'responsabile' => '',
    'luogo' => '',
    'data_inizio' => date('Y-m-d\TH:i'),
    'data_fine' => '',
    'stato' => $statuses[0],
    'note' => '',
];
$errors = [];
$csrfToken = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    foreach ($data as $field => $_) {
        $data[$field] = trim($_POST[$field] ?? '');
    }

    if ((int) $data['cliente_id'] <= 0) {
        $errors[] = 'Seleziona un cliente valido.';
    }
    if ($data['titolo'] === '') {
        $errors[] = 'Inserisci un titolo per l\'appuntamento.';
    }
    if (!in_array($data['tipo_servizio'], $serviceTypes, true)) {
        $errors[] = 'Tipo di servizio non valido.';
    }
    if (!in_array($data['stato'], $statuses, true)) {
        $errors[] = 'Stato selezionato non valido.';
    }

    $start = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $data['data_inizio']) ?: DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $data['data_inizio']);
    if (!$start) {
        $errors[] = 'Data e ora di inizio non valide.';
    }

    $end = null;
    if ($data['data_fine'] !== '') {
        $end = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $data['data_fine']) ?: DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $data['data_fine']);
        if (!$end) {
            $errors[] = 'Data e ora di fine non valide.';
        } elseif ($start && $end < $start) {
            $errors[] = 'La data di fine non può precedere l\'inizio.';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO servizi_appuntamenti (cliente_id, titolo, tipo_servizio, responsabile, luogo, data_inizio, data_fine, stato, note) VALUES (:cliente_id, :titolo, :tipo_servizio, :responsabile, :luogo, :data_inizio, :data_fine, :stato, :note)');
        $stmt->execute([
            ':cliente_id' => (int) $data['cliente_id'],
            ':titolo' => $data['titolo'],
            ':tipo_servizio' => $data['tipo_servizio'],
            ':responsabile' => $data['responsabile'] !== '' ? $data['responsabile'] : null,
            ':luogo' => $data['luogo'] !== '' ? $data['luogo'] : null,
            ':data_inizio' => $start->format('Y-m-d H:i:s'),
            ':data_fine' => $end ? $end->format('Y-m-d H:i:s') : null,
            ':stato' => $data['stato'],
            ':note' => $data['note'] !== '' ? $data['note'] : null,
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
            <a class="btn btn-outline-warning" href="index.php"><i class="fa-solid fa-arrow-left"></i> Ritorna agli appuntamenti</a>
        </div>
        <div class="card ag-card">
            <div class="card-header bg-transparent border-0">
                <h1 class="h4 mb-0">Nuovo appuntamento</h1>
            </div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-warning"><?php echo implode('<br>', array_map('sanitize_output', $errors)); ?></div>
                <?php endif; ?>
                <form method="post" novalidate>
                    <input type="hidden" name="_token" value="<?php echo sanitize_output($csrfToken); ?>">
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
                        <div class="col-md-6">
                            <label class="form-label" for="titolo">Titolo</label>
                            <input class="form-control" id="titolo" name="titolo" value="<?php echo sanitize_output($data['titolo']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="tipo_servizio">Tipologia</label>
                            <select class="form-select" id="tipo_servizio" name="tipo_servizio">
                                <?php foreach ($serviceTypes as $type): ?>
                                    <option value="<?php echo sanitize_output($type); ?>" <?php echo $data['tipo_servizio'] === $type ? 'selected' : ''; ?>><?php echo sanitize_output($type); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="responsabile">Responsabile</label>
                            <input class="form-control" id="responsabile" name="responsabile" list="responsabileOptions" value="<?php echo sanitize_output($data['responsabile']); ?>">
                            <?php if ($responsabili): ?>
                                <datalist id="responsabileOptions">
                                    <?php foreach ($responsabili as $responsabile): ?>
                                        <option value="<?php echo sanitize_output($responsabile); ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="luogo">Luogo</label>
                            <input class="form-control" id="luogo" name="luogo" value="<?php echo sanitize_output($data['luogo']); ?>" placeholder="Sede, indirizzo o meeting online">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="data_inizio">Inizio</label>
                            <input class="form-control" id="data_inizio" type="datetime-local" name="data_inizio" value="<?php echo sanitize_output($data['data_inizio']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="data_fine">Fine</label>
                            <input class="form-control" id="data_fine" type="datetime-local" name="data_fine" value="<?php echo sanitize_output($data['data_fine']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="stato">Stato</label>
                            <select class="form-select" id="stato" name="stato">
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?php echo sanitize_output($status); ?>" <?php echo $data['stato'] === $status ? 'selected' : ''; ?>><?php echo sanitize_output($status); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="note">Note interne</label>
                            <textarea class="form-control" id="note" name="note" rows="4" placeholder="Dettagli, preparazione o ulteriori azioni"><?php echo sanitize_output($data['note']); ?></textarea>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a class="btn btn-secondary" href="index.php">Annulla</a>
                        <button class="btn btn-warning text-dark" type="submit">Registra appuntamento</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

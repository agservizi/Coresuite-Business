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

$pageTitle = 'Modifica richiesta ' . $request['request_code'];

$statuses = cittadino_cie_allowed_statuses();
$fasciaOptions = ['Mattina', 'Pomeriggio', 'Sera', 'Altro'];

$clientsStmt = $pdo->query('SELECT id, ragione_sociale, nome, cognome FROM clienti ORDER BY cognome, nome, ragione_sociale');
$clients = $clientsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$data = [
    'cliente_id' => $request['cliente_id'] ?? '',
    'cittadino_nome' => $request['cittadino_nome'] ?? '',
    'cittadino_cognome' => $request['cittadino_cognome'] ?? '',
    'cittadino_cf' => $request['cittadino_cf'] ?? '',
    'cittadino_email' => $request['cittadino_email'] ?? '',
    'cittadino_telefono' => $request['cittadino_telefono'] ?? '',
    'comune' => $request['comune'] ?? '',
    'comune_codice' => $request['comune_codice'] ?? '',
    'preferenza_data' => $request['preferenza_data'] ?? '',
    'preferenza_fascia' => $request['preferenza_fascia'] ?? '',
    'stato' => $request['stato'] ?? 'nuova',
    'slot_data' => $request['slot_data'] ?? '',
    'slot_orario' => $request['slot_orario'] ?? '',
    'slot_protocollo' => $request['slot_protocollo'] ?? '',
    'slot_note' => $request['slot_note'] ?? '',
    'note' => $request['note'] ?? '',
];

foreach ($data as $key => $value) {
    if ($value === null) {
        $data[$key] = '';
    }
}

$errors = [];
$csrfToken = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();

    foreach ($data as $field => $_) {
        $data[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    if ($data['cliente_id'] !== '') {
        $clienteId = (int) $data['cliente_id'];
        if ($clienteId > 0) {
            $data['cliente_id'] = $clienteId;
        } else {
            $errors[] = 'Cliente selezionato non valido.';
            $data['cliente_id'] = '';
        }
    }

    if ($data['cittadino_nome'] === '') {
        $errors[] = 'Inserisci il nome del cittadino.';
    }

    if ($data['cittadino_cognome'] === '') {
        $errors[] = 'Inserisci il cognome del cittadino.';
    }

    if ($data['comune'] === '') {
        $errors[] = 'Indica il comune di riferimento.';
    }

    if ($data['cittadino_cf'] !== '') {
        $data['cittadino_cf'] = strtoupper($data['cittadino_cf']);
        if (!preg_match('/^[A-Z0-9]{11,16}$/', $data['cittadino_cf'])) {
            $errors[] = 'Il codice fiscale inserito non è valido.';
        }
    }

    if ($data['cittadino_email'] !== '' && !filter_var($data['cittadino_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Inserisci un indirizzo email valido.';
    }

    if ($data['preferenza_data'] !== '') {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $data['preferenza_data']);
        if (!$date) {
            $errors[] = 'La data preferita non è valida.';
        } else {
            $data['preferenza_data'] = $date->format('Y-m-d');
        }
    } else {
        $data['preferenza_data'] = null;
    }

    if ($data['slot_data'] !== '') {
        $slotDate = DateTimeImmutable::createFromFormat('Y-m-d', $data['slot_data']);
        if (!$slotDate) {
            $errors[] = 'La data dell\'appuntamento confermato non è valida.';
        } else {
            $data['slot_data'] = $slotDate->format('Y-m-d');
        }
    } else {
        $data['slot_data'] = null;
    }

    if ($data['preferenza_fascia'] !== '' && !in_array($data['preferenza_fascia'], $fasciaOptions, true)) {
        $errors[] = 'La fascia oraria selezionata non è valida.';
        $data['preferenza_fascia'] = '';
    }

    if (!in_array($data['stato'], $statuses, true)) {
        $data['stato'] = 'nuova';
    }

    if (!$errors) {
        try {
            $payload = [
                'cliente_id' => $data['cliente_id'] !== '' ? (int) $data['cliente_id'] : null,
                'cittadino_nome' => $data['cittadino_nome'],
                'cittadino_cognome' => $data['cittadino_cognome'],
                'cittadino_cf' => $data['cittadino_cf'] !== '' ? $data['cittadino_cf'] : null,
                'cittadino_email' => $data['cittadino_email'] !== '' ? $data['cittadino_email'] : null,
                'cittadino_telefono' => $data['cittadino_telefono'] !== '' ? $data['cittadino_telefono'] : null,
                'comune' => $data['comune'],
                'comune_codice' => $data['comune_codice'] !== '' ? $data['comune_codice'] : null,
                'preferenza_data' => $data['preferenza_data'],
                'preferenza_fascia' => $data['preferenza_fascia'] !== '' ? $data['preferenza_fascia'] : null,
                'stato' => $data['stato'],
                'slot_data' => $data['slot_data'],
                'slot_orario' => $data['slot_orario'] !== '' ? $data['slot_orario'] : null,
                'slot_protocollo' => $data['slot_protocollo'] !== '' ? $data['slot_protocollo'] : null,
                'slot_note' => $data['slot_note'] !== '' ? $data['slot_note'] : null,
                'note' => $data['note'] !== '' ? $data['note'] : null,
            ];

            $updated = cittadino_cie_update($pdo, $id, $payload);
            if ($updated) {
                add_flash('success', 'Richiesta aggiornata con successo.');
                header('Location: view.php?id=' . $id);
                exit;
            }

            $errors[] = 'Nessuna modifica registrata.';
        } catch (Throwable $exception) {
            error_log('CIE update error: ' . $exception->getMessage());
            $errors[] = 'Impossibile aggiornare la richiesta. Riprova più tardi.';
        }
    }
}

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/../../../includes/topbar.php'; ?>
    <main class="content-wrapper">
        <div class="mb-4 d-flex gap-2">
            <a class="btn btn-outline-warning" href="view.php?id=<?php echo (int) $id; ?>"><i class="fa-solid fa-arrow-left"></i> Ritorna alla richiesta</a>
            <a class="btn btn-outline-light" href="index.php"><i class="fa-solid fa-rectangle-list"></i> Elenco richieste</a>
        </div>
        <div class="card ag-card">
            <div class="card-header bg-transparent border-0">
                <h1 class="h4 mb-0">Modifica richiesta <?php echo sanitize_output((string) $request['request_code']); ?></h1>
                <p class="text-muted mb-0">Aggiorna i dati del cittadino e le informazioni sulla prenotazione.</p>
            </div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-warning">
                        <?php echo implode('<br>', array_map('sanitize_output', $errors)); ?>
                    </div>
                <?php endif; ?>
                <form method="post" novalidate>
                    <input type="hidden" name="_token" value="<?php echo sanitize_output($csrfToken); ?>">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label" for="cliente_id">Cliente (facoltativo)</label>
                            <select class="form-select" id="cliente_id" name="cliente_id">
                                <option value="">Nessun cliente associato</option>
                                <?php foreach ($clients as $client): ?>
                                    <?php $clientId = (int) $client['id']; ?>
                                    <?php $labelParts = [];
                                    if (!empty($client['cognome']) || !empty($client['nome'])) {
                                        $labelParts[] = trim(($client['cognome'] ?? '') . ' ' . ($client['nome'] ?? ''));
                                    }
                                    if (!empty($client['ragione_sociale'])) {
                                        $labelParts[] = $client['ragione_sociale'];
                                    }
                                    $label = implode(' · ', array_filter($labelParts));
                                    ?>
                                    <option value="<?php echo $clientId; ?>" <?php echo ((int) $data['cliente_id'] === $clientId) ? 'selected' : ''; ?>>
                                        <?php echo sanitize_output($label !== '' ? $label : ('Cliente #' . $clientId)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="cittadino_nome">Nome</label>
                            <input class="form-control" id="cittadino_nome" name="cittadino_nome" value="<?php echo sanitize_output($data['cittadino_nome']); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="cittadino_cognome">Cognome</label>
                            <input class="form-control" id="cittadino_cognome" name="cittadino_cognome" value="<?php echo sanitize_output($data['cittadino_cognome']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="cittadino_cf">Codice fiscale</label>
                            <input class="form-control" id="cittadino_cf" name="cittadino_cf" value="<?php echo sanitize_output($data['cittadino_cf']); ?>" maxlength="16" placeholder="Facoltativo">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="cittadino_email">Email</label>
                            <input class="form-control" id="cittadino_email" name="cittadino_email" value="<?php echo sanitize_output($data['cittadino_email']); ?>" placeholder="Facoltativo">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="cittadino_telefono">Telefono</label>
                            <input class="form-control" id="cittadino_telefono" name="cittadino_telefono" value="<?php echo sanitize_output($data['cittadino_telefono']); ?>" placeholder="Facoltativo">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="comune">Comune</label>
                            <input class="form-control" id="comune" name="comune" value="<?php echo sanitize_output($data['comune']); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="comune_codice">Codice ISTAT (facoltativo)</label>
                            <input class="form-control" id="comune_codice" name="comune_codice" value="<?php echo sanitize_output($data['comune_codice']); ?>" placeholder="Es. F205">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="stato">Stato richiesta</label>
                            <select class="form-select" id="stato" name="stato">
                                <?php foreach (cittadino_cie_status_map() as $statusKey => $statusConfig): ?>
                                    <option value="<?php echo sanitize_output($statusKey); ?>" <?php echo $data['stato'] === $statusKey ? 'selected' : ''; ?>><?php echo sanitize_output($statusConfig['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="preferenza_data">Data preferita</label>
                            <input class="form-control" id="preferenza_data" type="date" name="preferenza_data" value="<?php echo sanitize_output((string) ($data['preferenza_data'] ?? '')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="preferenza_fascia">Fascia oraria</label>
                            <select class="form-select" id="preferenza_fascia" name="preferenza_fascia">
                                <option value="">Qualsiasi</option>
                                <?php foreach ($fasciaOptions as $option): ?>
                                    <option value="<?php echo sanitize_output($option); ?>" <?php echo $data['preferenza_fascia'] === $option ? 'selected' : ''; ?>><?php echo sanitize_output($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="slot_data">Data appuntamento confermato</label>
                            <input class="form-control" id="slot_data" type="date" name="slot_data" value="<?php echo sanitize_output((string) ($data['slot_data'] ?? '')); ?>" placeholder="Facoltativo">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="slot_orario">Orario appuntamento</label>
                            <input class="form-control" id="slot_orario" name="slot_orario" value="<?php echo sanitize_output($data['slot_orario']); ?>" placeholder="Es. 09:30">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="slot_protocollo">Protocollo portale</label>
                            <input class="form-control" id="slot_protocollo" name="slot_protocollo" value="<?php echo sanitize_output($data['slot_protocollo']); ?>" placeholder="Facoltativo">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="slot_note">Note appuntamento</label>
                            <textarea class="form-control" id="slot_note" name="slot_note" rows="3" placeholder="Dettagli utili per la prenotazione o informazioni ricevute dal portale."><?php echo sanitize_output($data['slot_note']); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="note">Note interne</label>
                            <textarea class="form-control" id="note" name="note" rows="4" placeholder="Informazioni aggiuntive, documenti da richiedere, istruzioni per l\'operatore."><?php echo sanitize_output($data['note']); ?></textarea>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a class="btn btn-secondary" href="view.php?id=<?php echo (int) $id; ?>">Annulla</a>
                        <button class="btn btn-warning text-dark" type="submit">Salva modifiche</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

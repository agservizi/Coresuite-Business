<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/functions.php';

require_role('Admin', 'Operatore', 'Manager');
$pageTitle = 'Nuovo progetto web';

$csrfToken = csrf_token();
$flashes = get_flashes();

$clientsStmt = $pdo->query('SELECT id, ragione_sociale, nome, cognome FROM clienti ORDER BY ragione_sociale, cognome, nome');
$clients = $clientsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$defaultService = SERVIZI_WEB_SERVICE_TYPES[0] ?? 'Sito vetrina';
$hostingerEnabled = servizi_web_hostinger_is_configured();
$hostingerDatacenters = $hostingerEnabled ? servizi_web_hostinger_datacenters() : [];
$hostingerDatacenterOptions = [];
foreach ($hostingerDatacenters as $datacenter) {
    $identifier = (string) ($datacenter['id'] ?? $datacenter['code'] ?? '');
    if ($identifier === '') {
        continue;
    }

    $labelParts = array_filter([
        $datacenter['name'] ?? null,
        $datacenter['location'] ?? null,
        $datacenter['country'] ?? ($datacenter['country_code'] ?? null),
    ]);

    $hostingerDatacenterOptions[$identifier] = $labelParts ? implode(' • ', $labelParts) : $identifier;
}

$data = [
    'cliente_id' => '',
    'tipo_servizio' => $defaultService,
    'titolo' => '',
    'descrizione' => '',
    'include_domini' => '0',
    'include_email_professionali' => '0',
    'include_hosting' => '0',
    'include_stampa' => '0',
    'stato' => 'preventivo',
    'preventivo_numero' => '',
    'preventivo_importo' => '',
    'ordine_numero' => '',
    'ordine_importo' => '',
    'consegna_prevista' => '',
    'note_interne' => '',
    'dominio_richiesto' => '',
    'hostinger_datacenter' => '',
    'hostinger_plan' => '',
    'hostinger_email_plan' => '',
    'hostinger_domain_status' => '',
    'hostinger_order_reference' => '',
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();

    $checkboxes = ['include_domini', 'include_email_professionali', 'include_hosting', 'include_stampa'];

    foreach (array_keys($data) as $field) {
        if (in_array($field, $checkboxes, true)) {
            $data[$field] = isset($_POST[$field]) ? '1' : '0';
            continue;
        }
        $data[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    $clienteId = (int) $data['cliente_id'];
    if ($clienteId <= 0) {
        $errors[] = 'Seleziona un cliente valido.';
    }

    if (!in_array($data['tipo_servizio'], SERVIZI_WEB_SERVICE_TYPES, true)) {
        $data['tipo_servizio'] = $defaultService;
        $errors[] = 'Seleziona una tipologia di servizio valida.';
    }

    if ($data['titolo'] === '') {
        $errors[] = 'Inserisci un titolo progetto.';
    } elseif (mb_strlen($data['titolo']) > 160) {
        $errors[] = 'Il titolo può contenere al massimo 160 caratteri.';
    }

    if ($data['descrizione'] !== '' && mb_strlen($data['descrizione']) > 4000) {
        $errors[] = 'La descrizione può contenere al massimo 4000 caratteri.';
    }

    if ($data['dominio_richiesto'] !== '') {
        $domain = strtolower($data['dominio_richiesto']);
        if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
            $errors[] = 'Inserisci un dominio valido (esempio: example.com).';
        }
        $data['dominio_richiesto'] = $domain;
    }

    foreach (['hostinger_datacenter' => 120, 'hostinger_plan' => 120, 'hostinger_email_plan' => 120, 'hostinger_order_reference' => 120] as $field => $limit) {
        if ($data[$field] !== '' && mb_strlen($data[$field]) > $limit) {
            $errors[] = 'Il campo ' . str_replace('_', ' ', $field) . ' non può superare ' . $limit . ' caratteri.';
        }
    }

    if ($data['hostinger_domain_status'] !== '' && mb_strlen($data['hostinger_domain_status']) > 40) {
        $errors[] = 'Lo stato Hostinger non può superare 40 caratteri.';
    }

    if ($data['hostinger_domain_status'] !== '') {
        $data['hostinger_domain_status'] = strtoupper($data['hostinger_domain_status']);
    }

    if (!in_array($data['stato'], SERVIZI_WEB_ALLOWED_STATUSES, true)) {
        $data['stato'] = 'preventivo';
    }

    $preventivoImporto = null;
    if ($data['preventivo_importo'] !== '') {
        $normalized = str_replace(['.', ' '], '', $data['preventivo_importo']);
        $normalized = str_replace(',', '.', $normalized);
        if (!is_numeric($normalized)) {
            $errors[] = 'Importo preventivo non valido.';
        } else {
            $preventivoImporto = round((float) $normalized, 2);
        }
    }

    $ordineImporto = null;
    if ($data['ordine_importo'] !== '') {
        $normalized = str_replace(['.', ' '], '', $data['ordine_importo']);
        $normalized = str_replace(',', '.', $normalized);
        if (!is_numeric($normalized)) {
            $errors[] = 'Importo ordine non valido.';
        } else {
            $ordineImporto = round((float) $normalized, 2);
        }
    }

    $consegnaPrevista = null;
    if ($data['consegna_prevista'] !== '') {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $data['consegna_prevista']);
        if ($date === false) {
            $errors[] = 'Data consegna prevista non valida.';
        } else {
            $consegnaPrevista = $date->format('Y-m-d');
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $generatedCode = servizi_web_generate_code($pdo);

            $stmt = $pdo->prepare('INSERT INTO servizi_web_progetti (
                codice,
                cliente_id,
                tipo_servizio,
                titolo,
                descrizione,
                include_domini,
                include_email_professionali,
                include_hosting,
                include_stampa,
                stato,
                preventivo_numero,
                preventivo_importo,
                ordine_numero,
                ordine_importo,
                consegna_prevista,
                note_interne,
                dominio_richiesto,
                hostinger_datacenter,
                hostinger_plan,
                hostinger_email_plan,
                hostinger_domain_status,
                hostinger_order_reference,
                created_at,
                updated_at
            ) VALUES (
                :codice,
                :cliente_id,
                :tipo_servizio,
                :titolo,
                :descrizione,
                :include_domini,
                :include_email_professionali,
                :include_hosting,
                :include_stampa,
                :stato,
                :preventivo_numero,
                :preventivo_importo,
                :ordine_numero,
                :ordine_importo,
                :consegna_prevista,
                :note_interne,
                :dominio_richiesto,
                :hostinger_datacenter,
                :hostinger_plan,
                :hostinger_email_plan,
                :hostinger_domain_status,
                :hostinger_order_reference,
                NOW(),
                NOW()
            )');

            $stmt->execute([
                ':codice' => $generatedCode,
                ':cliente_id' => $clienteId,
                ':tipo_servizio' => $data['tipo_servizio'],
                ':titolo' => $data['titolo'],
                ':descrizione' => $data['descrizione'] !== '' ? $data['descrizione'] : null,
                ':include_domini' => $data['include_domini'] === '1' ? 1 : 0,
                ':include_email_professionali' => $data['include_email_professionali'] === '1' ? 1 : 0,
                ':include_hosting' => $data['include_hosting'] === '1' ? 1 : 0,
                ':include_stampa' => $data['include_stampa'] === '1' ? 1 : 0,
                ':stato' => $data['stato'],
                ':preventivo_numero' => $data['preventivo_numero'] !== '' ? $data['preventivo_numero'] : null,
                ':preventivo_importo' => $preventivoImporto,
                ':ordine_numero' => $data['ordine_numero'] !== '' ? $data['ordine_numero'] : null,
                ':ordine_importo' => $ordineImporto,
                ':consegna_prevista' => $consegnaPrevista,
                ':note_interne' => $data['note_interne'] !== '' ? $data['note_interne'] : null,
                ':dominio_richiesto' => $data['dominio_richiesto'] !== '' ? $data['dominio_richiesto'] : null,
                ':hostinger_datacenter' => $data['hostinger_datacenter'] !== '' ? $data['hostinger_datacenter'] : null,
                ':hostinger_plan' => $data['hostinger_plan'] !== '' ? $data['hostinger_plan'] : null,
                ':hostinger_email_plan' => $data['hostinger_email_plan'] !== '' ? $data['hostinger_email_plan'] : null,
                ':hostinger_domain_status' => $data['hostinger_domain_status'] !== '' ? strtoupper($data['hostinger_domain_status']) : null,
                ':hostinger_order_reference' => $data['hostinger_order_reference'] !== '' ? $data['hostinger_order_reference'] : null,
            ]);

            $projectId = (int) $pdo->lastInsertId();

            if (!empty($_FILES['allegato']['name'])) {
                $attachment = servizi_web_store_attachment($_FILES['allegato'], $projectId);
                $update = $pdo->prepare('UPDATE servizi_web_progetti
                    SET allegato_path = :path,
                        allegato_hash = :hash,
                        allegato_caricato_at = NOW()
                    WHERE id = :id');
                $update->execute([
                    ':path' => $attachment['path'],
                    ':hash' => $attachment['hash'],
                    ':id' => $projectId,
                ]);
            }

            servizi_web_log_action($pdo, 'create', 'Creato progetto ' . $generatedCode . ' per cliente ID ' . $clienteId);

            $pdo->commit();

            add_flash('success', 'Progetto web creato con successo.');
            header('Location: index.php');
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Servizi web create failed: ' . $exception->getMessage());
            $errors[] = 'Si è verificato un errore durante il salvataggio del progetto. Riprova.';
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
                <h1 class="h3 mb-0">Nuovo progetto digitale</h1>
                <p class="text-muted mb-0">Registra un nuovo preventivo o ordine per i servizi web dei clienti.</p>
            </div>
            <div class="toolbar-actions">
                <a class="btn btn-outline-warning" href="index.php"><i class="fa-solid fa-arrow-left me-2"></i>Lista progetti</a>
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
                <form method="post" enctype="multipart/form-data" class="row g-4" novalidate>
                    <input type="hidden" name="_token" value="<?php echo sanitize_output($csrfToken); ?>">
                    <div class="col-md-6">
                        <label class="form-label" for="cliente_id">Cliente <span class="text-warning">*</span></label>
                        <select class="form-select" id="cliente_id" name="cliente_id" required>
                            <option value="">Seleziona cliente</option>
                            <?php foreach ($clients as $client): ?>
                                <?php
                                $labelParts = array_filter([
                                    $client['ragione_sociale'] ?? null,
                                    trim(($client['cognome'] ?? '') . ' ' . ($client['nome'] ?? '')) ?: null,
                                ]);
                                $label = $labelParts ? implode(' • ', $labelParts) : 'Cliente #' . (int) $client['id'];
                                ?>
                                <option value="<?php echo (int) $client['id']; ?>" <?php echo (int) $data['cliente_id'] === (int) $client['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize_output($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="tipo_servizio">Tipologia servizio</label>
                        <select class="form-select" id="tipo_servizio" name="tipo_servizio">
                            <?php foreach (SERVIZI_WEB_SERVICE_TYPES as $serviceType): ?>
                                <option value="<?php echo sanitize_output($serviceType); ?>" <?php echo $data['tipo_servizio'] === $serviceType ? 'selected' : ''; ?>><?php echo sanitize_output($serviceType); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="titolo">Titolo progetto <span class="text-warning">*</span></label>
                        <input class="form-control" id="titolo" name="titolo" maxlength="160" value="<?php echo sanitize_output($data['titolo']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="stato">Stato</label>
                        <select class="form-select" id="stato" name="stato">
                            <?php foreach (SERVIZI_WEB_ALLOWED_STATUSES as $statusKey): ?>
                                <?php $label = $statusKey === 'in_attesa_cliente' ? 'In attesa cliente' : ($statusKey === 'in_lavorazione' ? 'In lavorazione' : ucfirst(str_replace('_', ' ', $statusKey))); ?>
                                <option value="<?php echo sanitize_output($statusKey); ?>" <?php echo $data['stato'] === $statusKey ? 'selected' : ''; ?>><?php echo sanitize_output($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="descrizione">Descrizione progetto</label>
                        <textarea class="form-control" id="descrizione" name="descrizione" rows="4" maxlength="4000" placeholder="Attività previste, obiettivi, note sul progetto."><?php echo sanitize_output($data['descrizione']); ?></textarea>
                    </div>
                    <div class="col-12">
                        <div class="row g-3">
                            <div class="col-sm-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="include_domini" name="include_domini" value="1" <?php echo $data['include_domini'] === '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="include_domini">Gestione domini</label>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="include_email_professionali" name="include_email_professionali" value="1" <?php echo $data['include_email_professionali'] === '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="include_email_professionali">Email professionali</label>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="include_hosting" name="include_hosting" value="1" <?php echo $data['include_hosting'] === '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="include_hosting">Piano hosting</label>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="include_stampa" name="include_stampa" value="1" <?php echo $data['include_stampa'] === '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="include_stampa">Materiali stampa</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="border border-secondary rounded-3 p-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div>
                                    <h2 class="h6 mb-1">Gestione tecnica Hostinger</h2>
                                    <p class="text-muted mb-0">Usa queste note per coordinare attivazione domini, hosting ed email professionali.</p>
                                </div>
                                <?php if (!$hostingerEnabled): ?>
                                    <span class="badge bg-warning text-dark">Configura il token API Hostinger per abilitare il check automatico.</span>
                                <?php endif; ?>
                            </div>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label" for="dominio_richiesto">Dominio richiesto</label>
                                    <div class="input-group">
                                        <input class="form-control" id="dominio_richiesto" name="dominio_richiesto" placeholder="esempio.it" value="<?php echo sanitize_output($data['dominio_richiesto']); ?>" <?php echo $hostingerEnabled ? '' : 'aria-disabled="true"'; ?>>
                                        <?php if ($hostingerEnabled): ?>
                                            <button class="btn btn-outline-warning" type="button" id="checkDomainButton"><i class="fa-solid fa-magnifying-glass"></i></button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-text" id="domainCheckResult"></div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="hostinger_datacenter">Datacenter Hostinger</label>
                                    <select class="form-select" id="hostinger_datacenter" name="hostinger_datacenter" <?php echo $hostingerEnabled && $hostingerDatacenterOptions ? '' : 'aria-disabled="true"'; ?>>
                                        <option value="">Non specificato</option>
                                        <?php foreach ($hostingerDatacenterOptions as $value => $label): ?>
                                            <option value="<?php echo sanitize_output($value); ?>" <?php echo $data['hostinger_datacenter'] === $value ? 'selected' : ''; ?>><?php echo sanitize_output($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="hostinger_domain_status">Stato dominio Hostinger</label>
                                    <input class="form-control" id="hostinger_domain_status" name="hostinger_domain_status" maxlength="40" value="<?php echo sanitize_output($data['hostinger_domain_status']); ?>" placeholder="Es. AVAILABLE, ORDERED">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="hostinger_plan">Piano hosting / bundle</label>
                                    <input class="form-control" id="hostinger_plan" name="hostinger_plan" maxlength="120" value="<?php echo sanitize_output($data['hostinger_plan']); ?>" placeholder="Es. Premium Shared">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="hostinger_email_plan">Piano email professionale</label>
                                    <input class="form-control" id="hostinger_email_plan" name="hostinger_email_plan" maxlength="120" value="<?php echo sanitize_output($data['hostinger_email_plan']); ?>" placeholder="Es. Titan Business">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="hostinger_order_reference">Riferimento ordine/ID Hostinger</label>
                                    <input class="form-control" id="hostinger_order_reference" name="hostinger_order_reference" maxlength="120" value="<?php echo sanitize_output($data['hostinger_order_reference']); ?>" placeholder="Es. ORD-123456">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="preventivo_numero">Numero preventivo</label>
                        <input class="form-control" id="preventivo_numero" name="preventivo_numero" maxlength="80" value="<?php echo sanitize_output($data['preventivo_numero']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="preventivo_importo">Importo preventivo (€)</label>
                        <input class="form-control" id="preventivo_importo" name="preventivo_importo" value="<?php echo sanitize_output($data['preventivo_importo']); ?>" inputmode="decimal">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="ordine_numero">Numero ordine</label>
                        <input class="form-control" id="ordine_numero" name="ordine_numero" maxlength="80" value="<?php echo sanitize_output($data['ordine_numero']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="ordine_importo">Importo ordine (€)</label>
                        <input class="form-control" id="ordine_importo" name="ordine_importo" value="<?php echo sanitize_output($data['ordine_importo']); ?>" inputmode="decimal">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="consegna_prevista">Consegna prevista</label>
                        <input class="form-control" id="consegna_prevista" name="consegna_prevista" type="date" value="<?php echo sanitize_output($data['consegna_prevista']); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="note_interne">Note interne</label>
                        <textarea class="form-control" id="note_interne" name="note_interne" rows="4" maxlength="4000" placeholder="Aggiornamenti operativi per il team."><?php echo sanitize_output($data['note_interne']); ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="allegato">Allegato</label>
                        <input class="form-control" id="allegato" name="allegato" type="file" accept=".pdf,.jpg,.jpeg,.png">
                        <small class="text-muted">Accetta PDF o immagini (JPG/PNG). Dimensione massima consigliata 10 MB.</small>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a class="btn btn-secondary" href="index.php">Annulla</a>
                        <button class="btn btn-warning text-dark" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Salva progetto</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
<?php if ($hostingerEnabled): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var checkButton = document.getElementById('checkDomainButton');
        var domainInput = document.getElementById('dominio_richiesto');
        var resultBox = document.getElementById('domainCheckResult');
        if (!checkButton || !domainInput || !resultBox) {
            return;
        }

        var endpoint = '<?php echo sanitize_output(base_url('modules/servizi/web/check_domain.php')); ?>';

        var renderMessage = function (message, success) {
            resultBox.textContent = message;
            resultBox.className = success === true ? 'form-text text-success' : (success === false ? 'form-text text-danger' : 'form-text text-muted');
        };

        checkButton.addEventListener('click', function () {
            var domain = domainInput.value.trim();
            if (domain === '') {
                renderMessage('Inserisci un dominio prima di effettuare la verifica.', false);
                return;
            }

            renderMessage('Verifica disponibilità in corso…', null);

            fetch(endpoint + '?domain=' + encodeURIComponent(domain), {
                credentials: 'same-origin',
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data || data.success !== true) {
                        renderMessage(data && data.message ? data.message : 'Impossibile completare la verifica.', false);
                        return;
                    }

                    if (data.available === true) {
                        renderMessage('Dominio disponibile su Hostinger.', true);
                    } else if (data.available === false) {
                        renderMessage('Dominio non disponibile su Hostinger.', false);
                    } else {
                        renderMessage(data.message || 'Risposta Hostinger non deterministica.', null);
                    }
                })
                .catch(function () {
                    renderMessage('Errore durante la comunicazione con Hostinger.', false);
                });
        });
    });
</script>
<?php endif; ?>

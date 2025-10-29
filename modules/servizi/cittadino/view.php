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

$pageTitle = 'Dettagli richiesta ' . $request['request_code'];
$portalUrl = 'https://www.prenotazionicie.interno.gov.it/cittadino/n/sc/wizardAppuntamentoCittadino/sceltaComune';
$csrfToken = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $newStatus = trim((string) ($_POST['stato'] ?? ''));
        if (!in_array($newStatus, cittadino_cie_allowed_statuses(), true)) {
            add_flash('warning', 'Stato selezionato non valido.');
            header('Location: view.php?id=' . $id);
            exit;
        }

        if (cittadino_cie_update_status($pdo, $id, $newStatus)) {
            add_flash('success', 'Stato aggiornato correttamente.');
        } else {
            add_flash('warning', 'Impossibile aggiornare lo stato in questo momento.');
        }

        header('Location: view.php?id=' . $id);
        exit;
    }
}

$request = cittadino_cie_fetch_request($pdo, $id);
if ($request === null) {
    add_flash('warning', 'Richiesta non trovata dopo l\'aggiornamento.');
    header('Location: index.php');
    exit;
}

$citizenFullName = trim(($request['cittadino_cognome'] ?? '') . ' ' . ($request['cittadino_nome'] ?? '')) ?: 'Cittadino';
$clientLabel = null;
if (!empty($request['cliente_id'])) {
    $pieces = [];
    if (!empty($request['cliente_cognome']) || !empty($request['cliente_nome'])) {
        $pieces[] = trim(($request['cliente_cognome'] ?? '') . ' ' . ($request['cliente_nome'] ?? ''));
    }
    if (!empty($request['cliente_ragione_sociale'])) {
        $pieces[] = $request['cliente_ragione_sociale'];
    }
    $clientLabel = implode(' · ', array_filter(array_map('trim', $pieces)));
}

$flashes = get_flashes();

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/../../../includes/topbar.php'; ?>
    <main class="content-wrapper">
        <div class="mb-4 d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <div>
                <a class="btn btn-outline-warning" href="index.php"><i class="fa-solid fa-arrow-left"></i> Torna all'elenco</a>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-light" href="edit.php?id=<?php echo (int) $id; ?>"><i class="fa-solid fa-pen"></i> Modifica</a>
                <a class="btn btn-outline-danger" href="delete.php?id=<?php echo (int) $id; ?>"><i class="fa-solid fa-trash"></i> Elimina</a>
            </div>
        </div>

        <?php if ($flashes): ?>
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'warning'; ?>">
                    <?php echo sanitize_output($flash['message']); ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-7">
                <div class="card ag-card h-100">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h1 class="h4 mb-0"><?php echo sanitize_output($request['request_code']); ?></h1>
                            <p class="text-muted mb-0">Richiesta di prenotazione CIE per <?php echo sanitize_output($citizenFullName); ?></p>
                        </div>
                        <span class="<?php echo sanitize_output(cittadino_cie_status_badge((string) $request['stato'])); ?>">
                            <?php echo sanitize_output(cittadino_cie_status_label((string) $request['stato'])); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Cittadino</dt>
                            <dd class="col-sm-8">
                                <strong><?php echo sanitize_output($citizenFullName); ?></strong><br>
                                <?php if (!empty($request['cittadino_cf'])): ?>
                                    <span class="text-muted">CF: <?php echo sanitize_output($request['cittadino_cf']); ?></span><br>
                                <?php endif; ?>
                                <?php if (!empty($request['cittadino_email'])): ?>
                                    <span class="text-muted"><i class="fa-solid fa-envelope me-1"></i><?php echo sanitize_output($request['cittadino_email']); ?></span><br>
                                <?php endif; ?>
                                <?php if (!empty($request['cittadino_telefono'])): ?>
                                    <span class="text-muted"><i class="fa-solid fa-phone me-1"></i><?php echo sanitize_output($request['cittadino_telefono']); ?></span>
                                <?php endif; ?>
                            </dd>
                            <dt class="col-sm-4">Cliente collegato</dt>
                            <dd class="col-sm-8">
                                <?php if ($clientLabel): ?>
                                    <?php echo sanitize_output($clientLabel); ?>
                                <?php else: ?>
                                    <span class="text-muted">Nessun cliente associato</span>
                                <?php endif; ?>
                            </dd>
                            <dt class="col-sm-4">Comune</dt>
                            <dd class="col-sm-8">
                                <?php echo sanitize_output((string) $request['comune']); ?><br>
                                <?php if (!empty($request['comune_codice'])): ?>
                                    <span class="text-muted">Codice ISTAT: <?php echo sanitize_output((string) $request['comune_codice']); ?></span>
                                <?php endif; ?>
                            </dd>
                            <dt class="col-sm-4">Preferenze</dt>
                            <dd class="col-sm-8">
                                <?php if (!empty($request['preferenza_data']) || !empty($request['preferenza_fascia'])): ?>
                                    <?php if (!empty($request['preferenza_data'])): ?>
                                        <div>Data: <?php echo sanitize_output(format_date_locale((string) $request['preferenza_data'])); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($request['preferenza_fascia'])): ?>
                                        <div>Fascia: <?php echo sanitize_output((string) $request['preferenza_fascia']); ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Nessuna preferenza indicata</span>
                                <?php endif; ?>
                            </dd>
                            <dt class="col-sm-4">Appuntamento confermato</dt>
                            <dd class="col-sm-8">
                                <?php if (!empty($request['slot_data'])): ?>
                                    <div>Data: <?php echo sanitize_output(format_date_locale((string) $request['slot_data'])); ?></div>
                                    <?php if (!empty($request['slot_orario'])): ?>
                                        <div>Orario: <?php echo sanitize_output((string) $request['slot_orario']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($request['slot_protocollo'])): ?>
                                        <div>Protocollo: <span class="badge bg-success-subtle text-success">#<?php echo sanitize_output((string) $request['slot_protocollo']); ?></span></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Nessun appuntamento confermato</span>
                                <?php endif; ?>
                                <?php if (!empty($request['slot_note'])): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">Note appuntamento:</small><br>
                                        <?php echo nl2br(sanitize_output((string) $request['slot_note'])); ?>
                                    </div>
                                <?php endif; ?>
                            </dd>
                            <dt class="col-sm-4">Note interne</dt>
                            <dd class="col-sm-8">
                                <?php if (!empty($request['note'])): ?>
                                    <?php echo nl2br(sanitize_output((string) $request['note'])); ?>
                                <?php else: ?>
                                    <span class="text-muted">Nessuna nota</span>
                                <?php endif; ?>
                            </dd>
                            <dt class="col-sm-4">Operatore</dt>
                            <dd class="col-sm-8">
                                <?php if (!empty($request['operator_username'])): ?>
                                    <?php echo sanitize_output((string) $request['operator_username']); ?>
                                <?php else: ?>
                                    <span class="text-muted">Non assegnato</span>
                                <?php endif; ?>
                            </dd>
                            <dt class="col-sm-4">Cronologia</dt>
                            <dd class="col-sm-8 small text-muted">
                                <div>Creata: <?php echo sanitize_output(format_datetime_locale((string) $request['created_at'])); ?></div>
                                <div>Aggiornata: <?php echo sanitize_output(format_datetime_locale((string) $request['updated_at'])); ?></div>
                                <?php if (!empty($request['prenotato_il'])): ?>
                                    <div>Prenotata: <?php echo sanitize_output(format_datetime_locale((string) $request['prenotato_il'])); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($request['completato_il'])): ?>
                                    <div>Completata: <?php echo sanitize_output(format_datetime_locale((string) $request['completato_il'])); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($request['annullato_il'])): ?>
                                    <div>Annullata: <?php echo sanitize_output(format_datetime_locale((string) $request['annullato_il'])); ?></div>
                                <?php endif; ?>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="card ag-card mb-3">
                    <div class="card-header bg-transparent border-0">
                        <h2 class="h5 mb-0">Aggiorna stato</h2>
                        <small class="text-muted">Mantieni allineato il flusso di lavorazione della richiesta.</small>
                    </div>
                    <div class="card-body">
                        <form method="post" class="d-flex flex-column gap-3">
                            <input type="hidden" name="_token" value="<?php echo sanitize_output($csrfToken); ?>">
                            <input type="hidden" name="action" value="update_status">
                            <div>
                                <label class="form-label" for="stato">Stato richiesta</label>
                                <select class="form-select" id="stato" name="stato">
                                    <?php foreach (cittadino_cie_status_map() as $statusKey => $statusConfig): ?>
                                        <option value="<?php echo sanitize_output($statusKey); ?>" <?php echo ((string) $request['stato'] === $statusKey) ? 'selected' : ''; ?>><?php echo sanitize_output($statusConfig['label']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button class="btn btn-warning text-dark align-self-end" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Salva stato</button>
                        </form>
                    </div>
                </div>

                <div class="card ag-card">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="h5 mb-0">Portale ministeriale</h2>
                            <small class="text-muted">Completa la prenotazione senza uscire.</small>
                        </div>
                        <a class="btn btn-sm btn-outline-warning" href="<?php echo sanitize_output($portalUrl); ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                    </div>
                    <div class="card-body">
                        <div class="ratio ratio-4x3 border border-warning-subtle rounded overflow-hidden" id="ciePortalWrapper">
                            <iframe src="<?php echo sanitize_output($portalUrl); ?>" title="Portale Prenotazione CIE" class="w-100 h-100 border-0" loading="lazy" referrerpolicy="no-referrer" sandbox="allow-forms allow-same-origin allow-scripts allow-popups allow-popups-to-escape-sandbox"></iframe>
                        </div>
                        <div class="alert alert-warning mt-3" id="ciePortalFallback" hidden>
                            Il portale ministeriale potrebbe bloccare l\'integrazione. Se non lo visualizzi, utilizza il pulsante per aprirlo in una nuova scheda e aggiorna la richiesta con i dati ottenuti.
                        </div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                var wrapper = document.getElementById('ciePortalWrapper');
                                var fallback = document.getElementById('ciePortalFallback');
                                if (!wrapper || !fallback) {
                                    return;
                                }

                                var iframe = wrapper.querySelector('iframe');
                                if (!iframe) {
                                    return;
                                }

                                var portalLoaded = false;
                                iframe.addEventListener('load', function () {
                                    portalLoaded = true;
                                });

                                setTimeout(function () {
                                    if (!portalLoaded) {
                                        fallback.removeAttribute('hidden');
                                    }
                                }, 4000);
                            });
                        </script>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

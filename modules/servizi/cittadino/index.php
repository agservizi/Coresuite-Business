<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/functions.php';

require_role('Admin', 'Operatore', 'Manager');
$pageTitle = 'Servizi al Cittadino · Prenotazione CIE';

$portalUrl = 'https://www.prenotazionicie.interno.gov.it/cittadino/n/sc/wizardAppuntamentoCittadino/sceltaComune';

$filters = [
    'stato' => isset($_GET['stato']) ? trim((string) $_GET['stato']) : '',
    'search' => isset($_GET['search']) ? trim((string) $_GET['search']) : '',
];

if ($filters['stato'] !== '' && !in_array($filters['stato'], cittadino_cie_allowed_statuses(), true)) {
    $filters['stato'] = '';
}

$requests = cittadino_cie_fetch_requests($pdo, $filters);
$stats = cittadino_cie_fetch_stats($pdo);
$totalRequests = array_sum($stats);

$flashes = get_flashes();
$csrfToken = csrf_token();

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/../../../includes/topbar.php'; ?>
        <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2" role="status">
            <div>
                Per completare la prenotazione accedi al portale ministeriale. Una volta ottenuto il protocollo aggiorna la richiesta dal gestionale.
            </div>
            <a class="btn btn-outline-warning" href="<?php echo sanitize_output($portalUrl); ?>" target="_blank" rel="noopener">
                <i class="fa-solid fa-arrow-up-right-from-square me-2"></i>Apri portale CIE
            </a>
        </div>
                        <label class="form-label" for="search">Ricerca</label>
                        <input class="form-control" id="search" name="search" placeholder="Cerca per codice, cittadino o comune" value="<?php echo sanitize_output($filters['search']); ?>">
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <label class="form-label" for="stato">Stato</label>
                        <select class="form-select" id="stato" name="stato">
                            <option value="">Tutti</option>
                            <?php foreach (cittadino_cie_status_map() as $key => $config): ?>
                                <option value="<?php echo sanitize_output($key); ?>" <?php echo $filters['stato'] === $key ? 'selected' : ''; ?>><?php echo sanitize_output($config['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <button class="btn btn-outline-warning text-dark mt-1" type="submit"><i class="fa-solid fa-magnifying-glass me-2"></i>Filtra</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card ag-card mb-4">
            <div class="card-body">
                <?php if (!$requests): ?>
                    <p class="text-muted mb-0">Non sono presenti richieste registrate. Inizia inserendo una nuova richiesta di prenotazione CIE.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle" data-datatable="true">
                            <thead>
                                <tr>
                                    <th>Codice</th>
                                    <th>Cittadino</th>
                                    <th>Comune</th>
                                    <th>Preferenza</th>
                                    <th>Slot confermato</th>
                                    <th>Stato</th>
                                    <th>Operatore</th>
                                    <th>Creato</th>
                                    <th class="text-end">Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary"><?php echo sanitize_output($request['request_code']); ?></span></td>
                                        <td>
                                            <strong><?php echo sanitize_output(trim(($request['cittadino_cognome'] ?? '') . ' ' . ($request['cittadino_nome'] ?? ''))); ?></strong><br>
                                            <?php if (!empty($request['cittadino_cf'])): ?>
                                                <small class="text-muted">CF: <?php echo sanitize_output($request['cittadino_cf']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo sanitize_output($request['comune']); ?><br>
                                            <?php if (!empty($request['preferenza_fascia']) || !empty($request['preferenza_data'])): ?>
                                                <small class="text-muted">
                                                    <?php if (!empty($request['preferenza_data'])): ?>
                                                        <?php echo sanitize_output(format_date_locale($request['preferenza_data'])); ?>
                                                    <?php endif; ?>
                                                    <?php if (!empty($request['preferenza_fascia'])): ?>
                                                        · <?php echo sanitize_output($request['preferenza_fascia']); ?>
                                                    <?php endif; ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($request['slot_data'])): ?>
                                                <div class="text-white"><?php echo sanitize_output(format_date_locale($request['slot_data'])); ?></div>
                                                <?php if (!empty($request['slot_orario'])): ?>
                                                    <small class="text-muted"><?php echo sanitize_output($request['slot_orario']); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($request['slot_protocollo'])): ?>
                                                <span class="badge bg-success-subtle text-success">#<?php echo sanitize_output($request['slot_protocollo']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="<?php echo sanitize_output(cittadino_cie_status_badge((string) $request['stato'])); ?>">
                                                <?php echo sanitize_output(cittadino_cie_status_label((string) $request['stato'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($request['operator_username'])): ?>
                                                <?php echo sanitize_output($request['operator_username']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo sanitize_output(format_datetime_locale((string) $request['created_at'])); ?></small>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group" role="group">
                                                <a class="btn btn-sm btn-outline-warning" href="view.php?id=<?php echo (int) $request['id']; ?>" title="Dettagli"><i class="fa-solid fa-eye"></i></a>
                                                <a class="btn btn-sm btn-outline-warning" href="edit.php?id=<?php echo (int) $request['id']; ?>" title="Modifica"><i class="fa-solid fa-pen"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card ag-card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <div>
                        <h2 class="h5 mb-1">Portale prenotazione CIE integrato</h2>
                        <p class="text-muted mb-0">Accedi al portale del Ministero senza uscire dal gestionale. Utilizza i dati già raccolti per completare la prenotazione.</p>
                    </div>
                    <a class="btn btn-outline-warning" href="<?php echo sanitize_output($portalUrl); ?>" target="_blank" rel="noopener">
                        <i class="fa-solid fa-arrow-up-right-from-square me-2"></i>Apri in nuova scheda
                    </a>
                </div>
                <div class="ratio ratio-16x9 border border-warning-subtle rounded overflow-hidden" id="ciePortalWrapper">
                    <iframe src="<?php echo sanitize_output($portalUrl); ?>" title="Portale Prenotazione CIE" class="w-100 h-100 border-0" loading="lazy" referrerpolicy="no-referrer" sandbox="allow-forms allow-same-origin allow-scripts allow-popups allow-popups-to-escape-sandbox"></iframe>
                </div>
                <div class="alert alert-warning mt-3" id="ciePortalFallback" hidden>
                    Il portale ministeriale non consente l\'integrazione diretta in questa pagina. Usa il pulsante "Apri in nuova scheda" per completare la prenotazione e aggiorna la richiesta una volta ottenuto il protocollo.
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
    </main>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

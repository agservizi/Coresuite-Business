<?php
use App\Services\SettingsService;

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_role('Admin', 'Manager');
require_capability('settings.manage');
$pageTitle = 'Impostazioni';

$csrfToken = csrf_token();

$vatCountries = [
    'AT' => 'Austria',
    'BE' => 'Belgio',
    'BG' => 'Bulgaria',
    'HR' => 'Croazia',
    'CY' => 'Cipro',
    'CZ' => 'Repubblica Ceca',
    'DK' => 'Danimarca',
    'EE' => 'Estonia',
    'FI' => 'Finlandia',
    'FR' => 'Francia',
    'DE' => 'Germania',
    'GR' => 'Grecia',
    'HU' => 'Ungheria',
    'IE' => 'Irlanda',
    'IT' => 'Italia',
    'LV' => 'Lettonia',
    'LT' => 'Lituania',
    'LU' => 'Lussemburgo',
    'MT' => 'Malta',
    'NL' => 'Paesi Bassi',
    'PL' => 'Polonia',
    'PT' => 'Portogallo',
    'RO' => 'Romania',
    'SK' => 'Slovacchia',
    'SI' => 'Slovenia',
    'ES' => 'Spagna',
    'SE' => 'Svezia',
    'XI' => 'Irlanda del Nord',
];

$companyDefaults = [
    'ragione_sociale' => 'Coresuite Business SRL',
    'indirizzo' => 'Via Plinio 72',
    'cap' => '20129',
    'citta' => 'Milano',
    'provincia' => 'MI',
    'telefono' => '+39 02 1234567',
    'email' => 'info@coresuitebusiness.com',
    'pec' => '',
    'sdi' => '',
    'vat_country' => 'IT',
    'piva' => '',
    'iban' => '',
    'note' => '',
    'company_logo' => '',
];

$projectRoot = realpath(__DIR__ . '/../../') ?: __DIR__ . '/../../';
$settingsService = new SettingsService($pdo, $projectRoot);

$companyConfig = $settingsService->fetchCompanySettings($companyDefaults);
$availableBackups = $settingsService->recentBackups();
$movementDescriptions = $settingsService->getMovementDescriptions();

$alerts = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'company') {
        $payload = [
            'ragione_sociale' => $_POST['ragione_sociale'] ?? '',
            'indirizzo' => $_POST['indirizzo'] ?? '',
            'cap' => $_POST['cap'] ?? '',
            'citta' => $_POST['citta'] ?? '',
            'provincia' => $_POST['provincia'] ?? '',
            'telefono' => $_POST['telefono'] ?? '',
            'email' => $_POST['email'] ?? '',
            'pec' => $_POST['pec'] ?? '',
            'sdi' => $_POST['sdi'] ?? '',
            'vat_country' => $_POST['vat_country'] ?? '',
            'piva' => $_POST['piva'] ?? '',
            'iban' => $_POST['iban'] ?? '',
            'note' => $_POST['note'] ?? '',
        ];

        $logoFile = $_FILES['company_logo'] ?? null;
        $result = $settingsService->updateCompanySettings(
            $payload,
            $vatCountries,
            $companyConfig,
            $logoFile,
            isset($_POST['remove_logo']),
            (int) ($_SESSION['user_id'] ?? 0)
        );

        if ($result['success']) {
            add_flash('success', 'Dati aziendali aggiornati con successo.');
            header('Location: index.php');
            exit;
        }

        foreach ($result['errors'] as $error) {
            $alerts[] = ['type' => 'danger', 'text' => $error];
        }
        $companyConfig = $result['config'];
    }

    if ($action === 'backup') {
        $result = $settingsService->generateBackup((int) ($_SESSION['user_id'] ?? 0));
        if ($result['success']) {
            add_flash('success', 'Backup generato correttamente: ' . $result['file']);
            header('Location: index.php');
            exit;
        }

        $alerts[] = ['type' => 'danger', 'text' => $result['error'] ?? 'Errore durante la generazione del backup.'];
    }

    if ($action === 'movements') {
        $entrateRaw = (string) ($_POST['descrizioni_entrata'] ?? '');
        $usciteRaw = (string) ($_POST['descrizioni_uscita'] ?? '');

        $entrateList = array_filter(array_map('trim', preg_split('/\r?\n/', $entrateRaw) ?: []));
        $usciteList = array_filter(array_map('trim', preg_split('/\r?\n/', $usciteRaw) ?: []));

        $result = $settingsService->saveMovementDescriptions($entrateList, $usciteList, (int) ($_SESSION['user_id'] ?? 0));
        if ($result['success']) {
            add_flash('success', 'Descrizioni movimenti aggiornate con successo.');
            header('Location: index.php#movement-descriptions');
            exit;
        }

        foreach ($result['errors'] as $error) {
            $alerts[] = ['type' => 'danger', 'text' => $error];
        }

        $movementDescriptions = [
            'entrate' => $entrateList,
            'uscite' => $usciteList,
        ];
    }

    $availableBackups = $settingsService->recentBackups();
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>
    <main class="content-wrapper">
        <div class="page-toolbar mb-4">
            <div>
                <h1 class="h3 mb-0">Impostazioni sistema</h1>
                <p class="text-muted mb-0">Configura utenti, azienda, backup e preferenze.</p>
            </div>
            <div class="toolbar-actions">
                <a class="btn btn-outline-light" href="logs.php"><i class="fa-solid fa-scroll me-2"></i>Registro attività</a>
                <a class="btn btn-warning text-dark" href="users.php"><i class="fa-solid fa-users-gear me-2"></i>Gestione utenti</a>
            </div>
        </div>
        <?php foreach ($alerts as $alert): ?>
            <div class="alert alert-<?php echo sanitize_output($alert['type']); ?>"><?php echo sanitize_output($alert['text']); ?></div>
        <?php endforeach; ?>
        <div class="row g-4">
            <div class="col-xl-6">
                <div class="card ag-card h-100">
                    <div class="card-header bg-transparent border-0">
                        <h5 class="card-title mb-0">Dati aziendali</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data" novalidate>
                            <input type="hidden" name="action" value="company">
                            <input type="hidden" name="_token" value="<?php echo $csrfToken; ?>">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" for="ragione_sociale">Ragione sociale *</label>
                                    <input class="form-control" id="ragione_sociale" name="ragione_sociale" required value="<?php echo sanitize_output($companyConfig['ragione_sociale']); ?>">
                                </div>
                                <div class="col-12 col-lg-8">
                                    <label class="form-label" for="indirizzo">Indirizzo</label>
                                    <input class="form-control" id="indirizzo" name="indirizzo" value="<?php echo sanitize_output($companyConfig['indirizzo']); ?>">
                                </div>
                                <div class="col-6 col-lg-2">
                                    <label class="form-label" for="cap">CAP</label>
                                    <input class="form-control" id="cap" name="cap" value="<?php echo sanitize_output($companyConfig['cap']); ?>" maxlength="5">
                                </div>
                                <div class="col-6 col-lg-2">
                                    <label class="form-label" for="provincia">Provincia</label>
                                    <input class="form-control text-uppercase" id="provincia" name="provincia" value="<?php echo sanitize_output($companyConfig['provincia']); ?>" maxlength="2">
                                </div>
                                <div class="col-12 col-lg-6">
                                    <label class="form-label" for="citta">Città</label>
                                    <input class="form-control" id="citta" name="citta" value="<?php echo sanitize_output($companyConfig['citta']); ?>">
                                </div>
                                <div class="col-12 col-lg-6">
                                    <label class="form-label" for="telefono">Telefono</label>
                                    <input class="form-control" id="telefono" name="telefono" value="<?php echo sanitize_output($companyConfig['telefono']); ?>">
                                </div>
                                <div class="col-12 col-lg-6">
                                    <label class="form-label" for="email">Email</label>
                                    <input class="form-control" id="email" name="email" type="email" value="<?php echo sanitize_output($companyConfig['email']); ?>">
                                </div>
                                <div class="col-12 col-lg-6">
                                    <label class="form-label" for="pec">PEC</label>
                                    <input class="form-control" id="pec" name="pec" type="email" value="<?php echo sanitize_output($companyConfig['pec']); ?>">
                                </div>
                                <div class="col-12 col-lg-4">
                                    <label class="form-label" for="sdi">Codice SDI</label>
                                    <input class="form-control text-uppercase" id="sdi" name="sdi" value="<?php echo sanitize_output($companyConfig['sdi']); ?>" maxlength="7">
                                </div>
                                <div class="col-12 col-lg-4">
                                    <label class="form-label" for="vat_country">Paese IVA</label>
                                    <select class="form-select text-uppercase" id="vat_country" name="vat_country">
                                        <?php foreach ($vatCountries as $code => $label): ?>
                                            <option value="<?php echo sanitize_output($code); ?>" <?php echo $companyConfig['vat_country'] === $code ? 'selected' : ''; ?>><?php echo sanitize_output($label); ?> (<?php echo sanitize_output($code); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 col-lg-4">
                                    <label class="form-label" for="iban">IBAN</label>
                                    <input class="form-control text-uppercase" id="iban" name="iban" value="<?php echo sanitize_output($companyConfig['iban']); ?>" maxlength="34">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="piva">Partita IVA</label>
                                    <div class="input-group">
                                        <input class="form-control text-uppercase" id="piva" name="piva" value="<?php echo sanitize_output($companyConfig['piva']); ?>" maxlength="16" placeholder="Es. 12345678901">
                                        <button class="btn btn-outline-warning" type="button" id="viesFetch"><i class="fa-solid fa-building-columns me-2"></i>Recupera da VIES</button>
                                    </div>
                                    <div class="form-text">Inserisci il numero senza prefisso paese. Il servizio VIES è disponibile per le aziende iscritte all'archivio IVA UE.</div>
                                </div>
                                <div class="col-12" id="viesFeedback"></div>
                                <div class="col-12">
                                    <label class="form-label" for="note">Note operative</label>
                                    <textarea class="form-control" id="note" name="note" rows="3" maxlength="2000"><?php echo sanitize_output($companyConfig['note']); ?></textarea>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <label class="form-label" for="company_logo">Logo aziendale</label>
                                    <input class="form-control" id="company_logo" name="company_logo" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                                    <small class="text-muted">Max 2MB. Formati consentiti: PNG, JPG, WEBP, SVG.</small>
                                </div>
                                <div class="col-12 col-lg-6 align-self-end">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="remove_logo" name="remove_logo" value="1">
                                        <label class="form-check-label" for="remove_logo">Rimuovi logo esistente</label>
                                    </div>
                                </div>
                                <?php if (!empty($companyConfig['company_logo'])): ?>
                                    <div class="col-12">
                                        <label class="form-label">Logo attuale</label>
                                        <div class="border rounded-3 p-3 bg-body-secondary">
                                            <img src="<?php echo sanitize_output(base_url($companyConfig['company_logo'])); ?>" alt="Logo aziendale" class="img-fluid" style="max-height: 120px;">
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="text-end mt-4">
                                <button class="btn btn-warning text-dark" type="submit"><i class="fa-solid fa-save me-2"></i>Salva dati</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card ag-card mb-4">
                    <div class="card-header bg-transparent border-0">
                        <h5 class="card-title mb-0">Aspetto interfaccia</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">Coresuite Business utilizza un tema blu navy uniforme per garantire leggibilità e coerenza su tutte le sezioni dell'applicazione.</p>
                        <span class="badge ag-badge rounded-pill px-3 py-2">Tema Navy attivo</span>
                    </div>
                </div>
                <div class="card ag-card">
                    <div class="card-header bg-transparent border-0">
                        <h5 class="card-title mb-0">Backup database</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Esegui un'esportazione completa del database. L'operazione può richiedere alcuni secondi; evita di chiudere la pagina finché non compare la conferma.</p>
                        <form method="post" class="d-flex flex-column flex-md-row align-items-md-center gap-3 mb-3">
                            <input type="hidden" name="action" value="backup">
                            <input type="hidden" name="_token" value="<?php echo $csrfToken; ?>">
                            <button class="btn btn-warning text-dark" type="submit"><i class="fa-solid fa-database me-2"></i>Genera backup SQL</button>
                            <span class="text-muted small">I file vengono salvati in <code>backups/</code> ed elencati qui sotto.</span>
                        </form>
                        <?php if ($availableBackups): ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>File</th>
                                            <th>Generato il</th>
                                            <th>Dimensione</th>
                                            <th class="text-end">Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($availableBackups as $backup): ?>
                                            <tr>
                                                <td><?php echo sanitize_output($backup['name']); ?></td>
                                                <td>
                                                    <?php if (!empty($backup['mtime'])): ?>
                                                        <?php echo sanitize_output(format_datetime(date('Y-m-d H:i:s', (int) $backup['mtime']))); ?>
                                                    <?php else: ?>
                                                        —
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo sanitize_output($backup['size']); ?></td>
                                                <td class="text-end">
                                                    <a class="btn btn-sm btn-outline-warning" href="download_backup.php?file=<?php echo urlencode($backup['name']); ?>" title="Scarica backup"><i class="fa-solid fa-cloud-arrow-down"></i></a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">Non sono ancora presenti backup. Generane uno per iniziare la cronologia.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card ag-card mt-4" id="movement-descriptions">
                    <div class="card-header bg-transparent border-0">
                        <h5 class="card-title mb-0">Descrizioni movimenti</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Imposta le descrizioni predefinite per entrate e uscite. Verranno proposte nel modulo Entrate/Uscite.</p>
                        <form method="post" novalidate>
                            <input type="hidden" name="action" value="movements">
                            <input type="hidden" name="_token" value="<?php echo $csrfToken; ?>">
                            <div class="accordion" id="movementDescriptionsAccordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="headingEntrate">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEntrate" aria-expanded="true" aria-controls="collapseEntrate">
                                            Descrizioni entrate
                                        </button>
                                    </h2>
                                    <div id="collapseEntrate" class="accordion-collapse collapse show" aria-labelledby="headingEntrate" data-bs-parent="#movementDescriptionsAccordion">
                                        <div class="accordion-body">
                                            <label class="form-label" for="descrizioni_entrata">Una descrizione per riga</label>
                                            <textarea class="form-control" id="descrizioni_entrata" name="descrizioni_entrata" rows="6" placeholder="Es. Incasso giornaliero&#10;Vendita servizi"><?php echo sanitize_output(implode("\n", $movementDescriptions['entrate'])); ?></textarea>
                                            <div class="form-text">Limite 180 caratteri per descrizione. Le righe vuote saranno ignorate.</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="headingUscite">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseUscite" aria-expanded="false" aria-controls="collapseUscite">
                                            Descrizioni uscite
                                        </button>
                                    </h2>
                                    <div id="collapseUscite" class="accordion-collapse collapse" aria-labelledby="headingUscite" data-bs-parent="#movementDescriptionsAccordion">
                                        <div class="accordion-body">
                                            <label class="form-label" for="descrizioni_uscita">Una descrizione per riga</label>
                                            <textarea class="form-control" id="descrizioni_uscita" name="descrizioni_uscita" rows="6" placeholder="Es. Pagamento fornitori&#10;Spese operative"><?php echo sanitize_output(implode("\n", $movementDescriptions['uscite'])); ?></textarea>
                                            <div class="form-text">Limite 180 caratteri per descrizione. Le righe vuote saranno ignorate.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end mt-3">
                                <button class="btn btn-warning text-dark" type="submit"><i class="fa-solid fa-save me-2"></i>Salva descrizioni</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="card ag-card mt-4">
            <div class="card-header bg-transparent border-0">
                <h5 class="card-title mb-0">Log attività recenti</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Utente</th>
                                <th>Azione</th>
                                <th>Modulo</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $logs = $pdo->query('SELECT la.*, u.username FROM log_attivita la LEFT JOIN users u ON la.user_id = u.id ORDER BY la.created_at DESC LIMIT 20')->fetchAll();
                            foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo sanitize_output($log['username'] ?? 'Sistema'); ?></td>
                                    <td><?php echo sanitize_output($log['azione']); ?></td>
                                    <td><?php echo sanitize_output($log['modulo']); ?></td>
                                    <td><?php echo sanitize_output(date('d/m/Y H:i', strtotime($log['created_at']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$logs): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">Nessuna attività registrata.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const fetchButton = document.getElementById('viesFetch');
    if (!fetchButton) {
        return;
    }
    const form = fetchButton.closest('form');
    const feedbackContainer = document.getElementById('viesFeedback');
    const originalButtonHtml = fetchButton.innerHTML;

    const setFeedback = (html) => {
        if (feedbackContainer) {
            feedbackContainer.innerHTML = html ? '<div class="mt-3">' + html + '</div>' : '';
        }
    };

    fetchButton.addEventListener('click', async () => {
        if (!form) {
            return;
        }

        const tokenField = form.querySelector('input[name="_token"]');
        const countryField = document.getElementById('vat_country');
        const vatField = document.getElementById('piva');
        if (!tokenField || !countryField || !vatField) {
            setFeedback('<div class="alert alert-danger mb-0">Impossibile completare la richiesta: token mancante.</div>');
            return;
        }

        const country = countryField.value.trim().toUpperCase();
        const vat = vatField.value.trim().replace(/\s+/g, '').toUpperCase();

        if (!country || !vat) {
            setFeedback('<div class="alert alert-warning mb-0">Inserisci il paese e la partita IVA prima di interrogare VIES.</div>');
            vatField.focus();
            return;
        }

        setFeedback('<div class="alert alert-info mb-0">Verifica presso VIES in corso…</div>');
        fetchButton.disabled = true;
        fetchButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Verifico…';

        try {
            const formData = new FormData();
            formData.append('_token', tokenField.value);
            formData.append('country', country);
            formData.append('vat', vat);

            const response = await fetch('vies_lookup.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const payload = await response.json();
            if (!response.ok || !payload || !payload.success) {
                const message = payload && payload.message ? payload.message : 'Impossibile completare la verifica VIES.';
                setFeedback('<div class="alert alert-danger mb-0">' + message + '</div>');
                return;
            }

            const data = payload.data || {};
            if (data.name) {
                form.ragione_sociale.value = data.name;
            }
            if (data.address) {
                form.indirizzo.value = data.address;
            }
            if (data.cap) {
                form.cap.value = data.cap;
            }
            if (data.city) {
                form.citta.value = data.city;
            }
            if (data.provincia) {
                form.provincia.value = data.provincia;
            }

            let detailMessage = 'Dati recuperati da VIES. Verifica le informazioni prima di salvare.';
            if (data.rawAddress) {
                detailMessage += '<br><small class="text-muted">Indirizzo completo: ' + data.rawAddress + '</small>';
            }
            setFeedback('<div class="alert alert-success mb-0">' + detailMessage + '</div>');
        } catch (error) {
            console.error('Errore VIES:', error);
            setFeedback('<div class="alert alert-danger mb-0">Errore durante la comunicazione con VIES. Riprova più tardi.</div>');
        } finally {
            fetchButton.disabled = false;
            fetchButton.innerHTML = originalButtonHtml;
        }
    });
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

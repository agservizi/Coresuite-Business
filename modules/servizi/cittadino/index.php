<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';

require_role('Admin', 'Operatore', 'Manager');
$pageTitle = 'Servizi al Cittadino';

$services = require __DIR__ . '/services.php';

$flashes = get_flashes();

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/../../../includes/topbar.php'; ?>
    <main class="content-wrapper">
        <div class="page-toolbar mb-4">
            <div>
                <h1 class="h3 mb-0">Servizi al Cittadino</h1>
                <p class="text-muted mb-0">Punto unico di accesso ai servizi pubblici digitali dedicati ai cittadini.</p>
            </div>
            <div class="toolbar-actions">
                <span class="badge bg-warning text-dark">Nuovo</span>
            </div>
        </div>

        <?php if ($flashes): ?>
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'warning'; ?>">
                    <?php echo sanitize_output($flash['message']); ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="row g-3 align-items-stretch mb-4">
            <div class="col-12 col-lg-8">
                <div class="card ag-card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="rounded-circle bg-warning-subtle text-warning d-inline-flex align-items-center justify-content-center" style="width: 3rem; height: 3rem;">
                                <i class="fa-solid fa-user-shield"></i>
                            </span>
                            <div>
                                <h2 class="h5 mb-1">Accesso rapido ai servizi essenziali</h2>
                                <p class="text-muted mb-0">Gestisci prenotazioni, richieste e pratiche digitali in pochi click grazie ai collegamenti rapidi ai portali istituzionali.</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="border border-warning-subtle rounded-3 p-3 h-100">
                                    <div class="text-muted text-uppercase small mb-2">Servizi disponibili</div>
                                    <div class="fs-3 fw-semibold mb-0"><?php echo number_format(count($services), 0, ',', '.'); ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border border-warning-subtle rounded-3 p-3 h-100">
                                    <div class="text-muted text-uppercase small mb-2">Integrazioni future</div>
                                    <div class="fs-4 fw-semibold text-warning mb-0">In arrivo</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border border-warning-subtle rounded-3 p-3 h-100">
                                    <div class="text-muted text-uppercase small mb-2">Autenticazione</div>
                                    <div class="fw-semibold">SPID / CIE / CNS</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="card ag-card h-100 border-warning-subtle">
                    <div class="card-body">
                        <h2 class="h6 text-uppercase text-muted mb-3">Come funziona</h2>
                        <ul class="list-unstyled small text-muted mb-0">
                            <li class="d-flex align-items-start gap-2 mb-2">
                                <i class="fa-solid fa-circle-check text-warning mt-1"></i>
                                <span>Seleziona un servizio dall'elenco disponibile.</span>
                            </li>
                            <li class="d-flex align-items-start gap-2 mb-2">
                                <i class="fa-solid fa-circle-check text-warning mt-1"></i>
                                <span>Verrai indirizzato al portale istituzionale corrispondente.</span>
                            </li>
                            <li class="d-flex align-items-start gap-2 mb-0">
                                <i class="fa-solid fa-circle-check text-warning mt-1"></i>
                                <span>Autenticati con le credenziali richieste e completa la procedura.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
            <?php foreach ($services as $service): ?>
                <div class="col">
                    <div class="card ag-card h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <span class="rounded-circle bg-warning-subtle text-warning d-inline-flex align-items-center justify-content-center" style="width: 2.75rem; height: 2.75rem;">
                                    <i class="<?php echo sanitize_output($service['icon']); ?>"></i>
                                </span>
                                <div>
                                    <h3 class="h5 mb-1"><?php echo sanitize_output($service['title']); ?></h3>
                                    <p class="text-muted small mb-0"><?php echo sanitize_output($service['description']); ?></p>
                                </div>
                            </div>

                            <?php if (!empty($service['tags'])): ?>
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <?php foreach ($service['tags'] as $tag): ?>
                                        <span class="badge bg-dark-subtle text-muted"><?php echo sanitize_output($tag); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($service['notes'])): ?>
                                <ul class="list-unstyled small text-muted mb-3">
                                    <?php foreach ($service['notes'] as $note): ?>
                                        <li class="d-flex align-items-start gap-2 mb-1">
                                            <i class="fa-solid fa-circle-exclamation text-warning mt-1"></i>
                                            <span><?php echo sanitize_output($note); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <div class="mt-auto pt-2 d-flex align-items-center justify-content-between gap-2">
                                <a class="btn btn-warning text-dark" href="<?php echo sanitize_output($service['cta_url']); ?>" target="_blank" rel="noopener">
                                    <i class="fa-solid fa-arrow-up-right-from-square me-2"></i><?php echo sanitize_output($service['cta_label']); ?>
                                </a>
                                <span class="text-muted small">Portale esterno</span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (!$services): ?>
                <div class="col">
                    <div class="card ag-card h-100">
                        <div class="card-body d-flex flex-column justify-content-center align-items-center text-center text-muted">
                            <i class="fa-regular fa-calendar-xmark fa-2x mb-3"></i>
                            <p class="mb-0">Nessun servizio disponibile al momento. Torna presto per nuove integrazioni dedicate ai cittadini.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

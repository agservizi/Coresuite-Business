<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/functions.php';

require_role('Admin', 'Operatore', 'Manager');
$pageTitle = 'Dettaglio pratica ANPR';

$praticaId = (int) ($_GET['id'] ?? 0);
if ($praticaId <= 0) {
    add_flash('warning', 'Pratica non valida.');
    header('Location: index.php');
    exit;
}

$pratica = anpr_fetch_pratica($pdo, $praticaId);
if (!$pratica) {
    add_flash('warning', 'Pratica non trovata.');
    header('Location: index.php');
    exit;
}

$csrfToken = csrf_token();
$flashes = get_flashes();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>
    <main class="content-wrapper">
        <div class="page-toolbar mb-4">
            <div>
                <h1 class="h3 mb-0">Pratica <?php echo sanitize_output($pratica['pratica_code'] ?? ''); ?></h1>
                <p class="text-muted mb-0">Dettaglio anagrafico e lavorazione.</p>
            </div>
            <div class="toolbar-actions d-flex flex-wrap gap-2">
                <a class="btn btn-outline-warning" href="https://www.anpr.interno.it/portale/web/guest/accesso-ai-servizi" target="_blank" rel="noopener">
                    <i class="fa-solid fa-up-right-from-square me-2"></i>Portale ANPR
                </a>
                <a class="btn btn-outline-warning" href="index.php"><i class="fa-solid fa-arrow-left me-2"></i>Indietro</a>
                <a class="btn btn-outline-warning" href="edit_request.php?id=<?php echo $praticaId; ?>"><i class="fa-solid fa-pen me-2"></i>Modifica</a>
                <a class="btn btn-outline-warning" href="upload_certificate.php?id=<?php echo $praticaId; ?>"><i class="fa-solid fa-file-arrow-up me-2"></i>Certificato</a>
            </div>
        </div>

        <?php if ($flashes): ?>
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'warning'; ?>"><?php echo sanitize_output($flash['message']); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card ag-card">
                    <div class="card-header bg-transparent border-0">
                        <h2 class="h5 mb-0">Informazioni pratica</h2>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Codice</dt>
                            <dd class="col-sm-8"><?php echo sanitize_output($pratica['pratica_code'] ?? ''); ?></dd>

                            <dt class="col-sm-4">Cliente</dt>
                            <dd class="col-sm-8">
                                <?php
                                    $displayName = trim(($pratica['ragione_sociale'] ?? '') !== ''
                                        ? $pratica['ragione_sociale']
                                        : trim(($pratica['cognome'] ?? '') . ' ' . ($pratica['nome'] ?? '')));
                                    echo $displayName !== '' ? sanitize_output($displayName) : '<span class="text-muted">N/D</span>';
                                ?>
                            </dd>

                            <dt class="col-sm-4">Tipologia</dt>
                            <dd class="col-sm-8"><?php echo sanitize_output($pratica['tipo_pratica'] ?? ''); ?></dd>

                            <dt class="col-sm-4">Stato pratica</dt>
                            <dd class="col-sm-8"><span class="badge ag-badge text-uppercase"><?php echo sanitize_output($pratica['stato'] ?? ''); ?></span></dd>

                            <dt class="col-sm-4">Operatore</dt>
                            <dd class="col-sm-8"><?php echo !empty($pratica['operatore_username']) ? sanitize_output($pratica['operatore_username']) : '<span class="text-muted">N/D</span>'; ?></dd>

                            <dt class="col-sm-4">Creato il</dt>
                            <dd class="col-sm-8"><?php echo sanitize_output(format_datetime_locale($pratica['created_at'] ?? '')); ?></dd>

                            <dt class="col-sm-4">Aggiornato il</dt>
                            <dd class="col-sm-8"><?php echo sanitize_output(format_datetime_locale($pratica['updated_at'] ?? '')); ?></dd>
                        </dl>
                        <div class="mt-3">
                            <h3 class="h6 text-uppercase text-muted">Note interne</h3>
                            <p class="mb-0"><?php echo $pratica['note_interne'] ? nl2br(sanitize_output($pratica['note_interne'])) : '<span class="text-muted">Nessuna nota</span>'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card ag-card">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0">Certificato</h2>
                        <?php if (!empty($pratica['certificato_path'])): ?>
                            <form method="post" action="upload_certificate.php?id=<?php echo $praticaId; ?>" onsubmit="return confirm('Rimuovere il certificato archiviato?');">
                                <input type="hidden" name="_token" value="<?php echo sanitize_output($csrfToken); ?>">
                                <input type="hidden" name="id" value="<?php echo $praticaId; ?>">
                                <input type="hidden" name="action" value="remove">
                                <button class="btn btn-sm btn-outline-warning" type="submit"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($pratica['certificato_path'])): ?>
                            <p class="mb-2">Ultimo caricamento: <strong><?php echo sanitize_output(format_datetime_locale($pratica['certificato_caricato_at'] ?? '')); ?></strong></p>
                            <?php if (!empty($pratica['certificato_hash'])): ?>
                                <p class="mb-3">Hash SHA-256: <code><?php echo sanitize_output($pratica['certificato_hash']); ?></code></p>
                            <?php endif; ?>
                            <a class="btn btn-warning text-dark" href="<?php echo sanitize_output(base_url($pratica['certificato_path'])); ?>" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf me-2"></i>Scarica certificato</a>
                            <p class="text-muted mt-3 mb-0 small">Consigliato upload su storage di backup esterno dopo ogni emissione.</p>
                        <?php else: ?>
                            <p class="text-muted mb-2">Nessun certificato caricato.</p>
                            <a class="btn btn-outline-warning" href="upload_certificate.php?id=<?php echo $praticaId; ?>">Carica ora</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card ag-card mt-4">
                    <div class="card-header bg-transparent border-0">
                        <h2 class="h5 mb-0">Documentazione raccolta</h2>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h3 class="h6 text-uppercase text-muted">Delega firmata</h3>
                            <?php if (!empty($pratica['delega_path'])): ?>
                                <p class="mb-2">Ultimo caricamento: <strong><?php echo sanitize_output(format_datetime_locale($pratica['delega_caricato_at'] ?? '')); ?></strong></p>
                                <?php if (!empty($pratica['delega_hash'])): ?>
                                    <p class="mb-3">Hash SHA-256: <code><?php echo sanitize_output($pratica['delega_hash']); ?></code></p>
                                <?php endif; ?>
                                <a class="btn btn-outline-warning" href="<?php echo sanitize_output(base_url($pratica['delega_path'])); ?>" target="_blank" rel="noopener">
                                    <i class="fa-solid fa-file-lines me-2"></i>Scarica delega
                                </a>
                            <?php else: ?>
                                <p class="text-muted mb-0">Nessuna delega caricata.</p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h3 class="h6 text-uppercase text-muted">Documento identità</h3>
                            <?php if (!empty($pratica['documento_path'])): ?>
                                <p class="mb-2">Ultimo caricamento: <strong><?php echo sanitize_output(format_datetime_locale($pratica['documento_caricato_at'] ?? '')); ?></strong></p>
                                <?php if (!empty($pratica['documento_hash'])): ?>
                                    <p class="mb-3">Hash SHA-256: <code><?php echo sanitize_output($pratica['documento_hash']); ?></code></p>
                                <?php endif; ?>
                                <a class="btn btn-outline-warning" href="<?php echo sanitize_output(base_url($pratica['documento_path'])); ?>" target="_blank" rel="noopener">
                                    <i class="fa-solid fa-id-card me-2"></i>Scarica documento
                                </a>
                            <?php else: ?>
                                <p class="text-muted mb-0">Nessun documento caricato.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card ag-card mt-4">
                    <div class="card-header bg-transparent border-0">
                        <h2 class="h5 mb-0">Contatti cliente</h2>
                    </div>
                    <div class="card-body">
                        <p class="mb-1"><strong>Email:</strong> <?php echo !empty($pratica['cliente_email']) ? sanitize_output($pratica['cliente_email']) : '<span class="text-muted">N/D</span>'; ?></p>
                        <p class="mb-0"><strong>Telefono:</strong> <?php echo !empty($pratica['cliente_telefono']) ? sanitize_output($pratica['cliente_telefono']) : '<span class="text-muted">N/D</span>'; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

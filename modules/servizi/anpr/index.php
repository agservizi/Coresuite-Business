<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/functions.php';

require_role('Admin', 'Operatore', 'Manager');
$pageTitle = 'Servizi ANPR';

$statuses = ANPR_ALLOWED_STATUSES;
$types = anpr_practice_types();
$csrfToken = csrf_token();
$flashes = get_flashes();

$filterStatus = trim($_GET['stato'] ?? '');
$filterType = trim($_GET['tipo_pratica'] ?? '');
$filterQuery = trim($_GET['q'] ?? '');
$filterCliente = (int) ($_GET['cliente_id'] ?? 0);

$filters = [];
if ($filterStatus !== '') {
    $filters['stato'] = $filterStatus;
}
if ($filterType !== '') {
    $filters['tipo_pratica'] = $filterType;
}
if ($filterQuery !== '') {
    $filters['query'] = $filterQuery;
}
if ($filterCliente > 0) {
    $filters['cliente_id'] = $filterCliente;
}

$pratiche = anpr_fetch_pratiche($pdo, $filters);
$clienti = anpr_fetch_clienti($pdo);

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/../../../includes/topbar.php'; ?>
    <main class="content-wrapper">
        <div class="page-toolbar mb-4">
            <div>
                <h1 class="h3 mb-0">Servizi ANPR</h1>
                <p class="text-muted mb-0">Gestione pratiche anagrafiche e certificati digitali.</p>
            </div>
            <div class="toolbar-actions d-flex gap-2">
                <a class="btn btn-outline-warning" href="https://www.anpr.interno.it/portale/web/guest/accesso-ai-servizi" target="_blank" rel="noopener">
                    <i class="fa-solid fa-up-right-from-square me-2"></i>Portale ANPR
                </a>
                <a class="btn btn-warning text-dark" href="add_request.php"><i class="fa-solid fa-circle-plus me-2"></i>Nuova pratica</a>
            </div>
        </div>

        <?php if ($flashes): ?>
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'warning'; ?>"><?php echo sanitize_output($flash['message']); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="card ag-card mb-4">
            <div class="card-header bg-transparent border-0">
                <h2 class="h5 mb-0">Filtri pratiche</h2>
            </div>
            <div class="card-body">
                <form class="row g-3 align-items-end" method="get" action="index.php">
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label" for="stato">Stato</label>
                        <select class="form-select" id="stato" name="stato">
                            <option value="">Tutti</option>
                            <?php foreach ($statuses as $statusOption): ?>
                                <option value="<?php echo sanitize_output($statusOption); ?>" <?php echo $filterStatus === $statusOption ? 'selected' : ''; ?>><?php echo sanitize_output($statusOption); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label" for="tipo_pratica">Tipologia</label>
                        <select class="form-select" id="tipo_pratica" name="tipo_pratica">
                            <option value="">Tutte</option>
                            <?php foreach ($types as $type): ?>
                                <option value="<?php echo sanitize_output($type); ?>" <?php echo $filterType === $type ? 'selected' : ''; ?>><?php echo sanitize_output($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label" for="cliente_id">Cliente</label>
                        <select class="form-select" id="cliente_id" name="cliente_id">
                            <option value="">Tutti</option>
                            <?php foreach ($clienti as $cliente): ?>
                                <?php $cid = (int) $cliente['id']; ?>
                                <option value="<?php echo $cid; ?>" <?php echo $filterCliente === $cid ? 'selected' : ''; ?>><?php echo sanitize_output(trim($cliente['ragione_sociale'] ?: (($cliente['cognome'] ?? '') . ' ' . ($cliente['nome'] ?? '')))); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label" for="q">Ricerca</label>
                        <input class="form-control" id="q" name="q" value="<?php echo sanitize_output($filterQuery); ?>" placeholder="Codice pratica o cliente">
                    </div>
                    <div class="col-12 col-lg-3">
                        <button class="btn btn-warning text-dark w-100" type="submit"><i class="fa-solid fa-filter me-2"></i>Applica filtri</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card ag-card">
            <div class="card-body">
                <?php if (!$pratiche): ?>
                    <p class="text-muted mb-0">Nessuna pratica trovata con i filtri correnti.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Codice</th>
                                    <th>Cliente</th>
                                    <th>Tipologia</th>
                                    <th>Stato</th>
                                    <th>Operatore</th>
                                    <th>Creato il</th>
                                    <th>Certificato</th>
                                    <th class="text-end">Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pratiche as $pratica): ?>
                                    <tr>
                                        <td><strong><?php echo sanitize_output($pratica['pratica_code']); ?></strong></td>
                                        <td>
                                            <?php
                                                $displayName = trim(($pratica['ragione_sociale'] ?? '') !== ''
                                                    ? $pratica['ragione_sociale']
                                                    : trim(($pratica['cognome'] ?? '') . ' ' . ($pratica['nome'] ?? '')));
                                                echo $displayName !== '' ? sanitize_output($displayName) : '<span class="text-muted">N/D</span>';
                                            ?>
                                        </td>
                                        <td><?php echo sanitize_output($pratica['tipo_pratica']); ?></td>
                                        <td><span class="badge ag-badge text-uppercase"><?php echo sanitize_output($pratica['stato']); ?></span></td>
                                        <td><?php echo $pratica['operatore_username'] ? sanitize_output($pratica['operatore_username']) : '<span class="text-muted">N/D</span>'; ?></td>
                                        <td><?php echo sanitize_output(format_datetime_locale($pratica['created_at'] ?? '')); ?></td>
                                        <td>
                                            <?php if (!empty($pratica['certificato_path'])): ?>
                                                <a class="btn btn-sm btn-outline-warning" href="<?php echo sanitize_output(base_url($pratica['certificato_path'])); ?>" target="_blank" rel="noopener">Scarica</a>
                                            <?php else: ?>
                                                <span class="text-muted">Non caricato</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group" role="group">
                                                <a class="btn btn-sm btn-outline-warning" href="view_request.php?id=<?php echo (int) $pratica['id']; ?>" title="Dettagli"><i class="fa-solid fa-eye"></i></a>
                                                <a class="btn btn-sm btn-outline-warning" href="edit_request.php?id=<?php echo (int) $pratica['id']; ?>" title="Modifica"><i class="fa-solid fa-pen"></i></a>
                                                <a class="btn btn-sm btn-outline-warning" href="upload_certificate.php?id=<?php echo (int) $pratica['id']; ?>" title="Carica certificato"><i class="fa-solid fa-file-arrow-up"></i></a>
                                                <form method="post" action="delete_request.php" class="d-inline" onsubmit="return confirm('Confermi eliminazione della pratica?');">
                                                    <input type="hidden" name="_token" value="<?php echo sanitize_output($csrfToken); ?>">
                                                    <input type="hidden" name="id" value="<?php echo (int) $pratica['id']; ?>">
                                                    <button class="btn btn-sm btn-outline-warning" type="submit" title="Elimina"><i class="fa-solid fa-trash"></i></button>
                                                </form>
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
    </main>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

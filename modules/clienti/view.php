<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_role('Admin', 'Operatore');
$pageTitle = 'Dettaglio cliente';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$clientStmt = $pdo->prepare('SELECT * FROM clienti WHERE id = :id');
$clientStmt->execute([':id' => $id]);
$client = $clientStmt->fetch();

if (!$client) {
    header('Location: index.php?notfound=1');
    exit;
}

$serviceSummaryQuery = "SELECT 'Entrate' AS tipo, COUNT(*) AS totale, COALESCE(SUM(importo), 0) AS importo
    FROM entrate_uscite WHERE cliente_id = ? AND tipo_movimento = 'Entrata'
    UNION ALL
    SELECT 'Uscite', COUNT(*), COALESCE(SUM(importo * -1), 0) FROM entrate_uscite WHERE cliente_id = ? AND tipo_movimento = 'Uscita'
    UNION ALL
    SELECT 'Appuntamenti', COUNT(*), 0 FROM servizi_appuntamenti WHERE cliente_id = ?
    UNION ALL
    SELECT 'Digitali', COUNT(*), 0 FROM servizi_digitali WHERE cliente_id = ?
    UNION ALL
    SELECT 'Telefonia', COUNT(*), 0 FROM telefonia WHERE cliente_id = ?
    UNION ALL
    SELECT 'Logistici', COUNT(*), 0 FROM spedizioni WHERE cliente_id = ?";

$summaryStmt = $pdo->prepare($serviceSummaryQuery);
$summaryStmt->execute(array_fill(0, 6, $id));
$summary = $summaryStmt->fetchAll();

$latestPracticesStmt = $pdo->prepare("(
    SELECT CASE WHEN tipo_movimento = 'Entrata' THEN 'Entrata' ELSE 'Uscita' END AS categoria,
        descrizione AS riferimento, stato, COALESCE(data_pagamento, data_scadenza, updated_at) AS data
    FROM entrate_uscite WHERE cliente_id = ?
    ) UNION ALL (
    SELECT 'Appuntamento' AS categoria, titolo AS riferimento, stato, data_inizio AS data
    FROM servizi_appuntamenti WHERE cliente_id = ?
    ) UNION ALL (
        SELECT 'Digitale' AS categoria, tipo AS riferimento, stato, created_at AS data
        FROM servizi_digitali WHERE cliente_id = ?
    ) UNION ALL (
        SELECT 'Telefonia' AS categoria, operatore AS riferimento, stato, created_at AS data
        FROM telefonia WHERE cliente_id = ?
    ) UNION ALL (
        SELECT 'Logistica' AS categoria, tracking_number AS riferimento, stato, created_at AS data
        FROM spedizioni WHERE cliente_id = ?
    ) ORDER BY data DESC LIMIT 10");
$latestPracticesStmt->execute(array_fill(0, 5, $id));
$practices = $latestPracticesStmt->fetchAll();

$ticketsStmt = $pdo->prepare('SELECT id, titolo, stato, created_at FROM ticket WHERE cliente_id = :id ORDER BY created_at DESC LIMIT 5');
$ticketsStmt->execute([':id' => $id]);
$tickets = $ticketsStmt->fetchAll();

$documentsStmt = $pdo->prepare('SELECT id, titolo, modulo, stato, updated_at FROM documents WHERE cliente_id = :id ORDER BY updated_at DESC LIMIT 5');
$documentsStmt->execute([':id' => $id]);
$documents = $documentsStmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/../../includes/topbar.php'; ?>
    <main class="content-wrapper">
        <div class="page-toolbar mb-4">
            <div>
                <h1 class="h3 mb-0"><?php echo sanitize_output($client['cognome'] . ' ' . $client['nome']); ?></h1>
                <p class="text-muted mb-0">ID Cliente #<?php echo (int) $client['id']; ?></p>
            </div>
            <div class="toolbar-actions btn-group">
                <a class="btn btn-outline-warning" href="index.php"><i class="fa-solid fa-arrow-left"></i> Tutti i clienti</a>
                <a class="btn btn-warning text-dark" href="edit.php?id=<?php echo (int) $client['id']; ?>"><i class="fa-solid fa-pen"></i> Modifica</a>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card ag-card h-100">
                    <div class="card-header bg-transparent border-0">
                        <h5 class="card-title mb-0">Dettagli anagrafici</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">CF / P.IVA</dt>
                            <dd class="col-sm-8"><?php echo sanitize_output($client['cf_piva'] ?: 'N/D'); ?></dd>
                            <dt class="col-sm-4">Email</dt>
                            <dd class="col-sm-8"><a class="link-warning" href="mailto:<?php echo sanitize_output($client['email']); ?>"><?php echo sanitize_output($client['email']); ?></a></dd>
                            <dt class="col-sm-4">Telefono</dt>
                            <dd class="col-sm-8"><a class="link-warning" href="tel:<?php echo sanitize_output($client['telefono']); ?>"><?php echo sanitize_output($client['telefono']); ?></a></dd>
                            <dt class="col-sm-4">Indirizzo</dt>
                            <dd class="col-sm-8"><?php echo sanitize_output($client['indirizzo'] ?: 'N/D'); ?></dd>
                            <dt class="col-sm-4">Note</dt>
                            <dd class="col-sm-8"><?php echo nl2br(sanitize_output($client['note'] ?: 'Nessuna nota.')); ?></dd>
                            <dt class="col-sm-4">Creato il</dt>
                            <dd class="col-sm-8"><?php echo sanitize_output(date('d/m/Y H:i', strtotime($client['created_at']))); ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card ag-card h-100">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Storico servizi</h5>
                        <a class="btn btn-sm btn-outline-warning" href="../servizi/entrate-uscite/create.php?cliente_id=<?php echo (int) $client['id']; ?>">Nuovo movimento</a>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($summary as $row): ?>
                                <div class="col-6">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="text-muted small text-uppercase"><?php echo sanitize_output($row['tipo']); ?></div>
                                        <div class="fs-3 fw-semibold"><?php echo (int) $row['totale']; ?></div>
                                        <?php if ((float) $row['importo'] !== 0.0): ?>
                                            <?php $importo = (float) $row['importo']; ?>
                                            <?php $importoClass = $importo >= 0 ? 'text-success' : 'text-danger'; ?>
                                            <div class="<?php echo $importoClass; ?>">Valore: <?php echo sanitize_output(format_currency($importo)); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card ag-card h-100">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Ticket recenti</h5>
                        <a class="btn btn-sm btn-outline-warning" href="../ticket/create.php?cliente_id=<?php echo (int) $client['id']; ?>">Nuovo ticket</a>
                    </div>
                    <div class="card-body">
                        <?php if ($tickets): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($tickets as $ticket): ?>
                                    <li class="list-group-item bg-transparent text-light d-flex justify-content-between align-items-start">
                                        <div>
                                            <a class="link-warning fw-semibold" href="../ticket/view.php?id=<?php echo (int) $ticket['id']; ?>">#<?php echo (int) $ticket['id']; ?> &middot; <?php echo sanitize_output($ticket['titolo']); ?></a>
                                            <div class="small text-muted">Aperto il <?php echo sanitize_output(date('d/m/Y H:i', strtotime($ticket['created_at']))); ?></div>
                                        </div>
                                        <span class="badge ag-badge text-uppercase ms-2 flex-shrink-0"><?php echo sanitize_output($ticket['stato']); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted mb-0">Nessun ticket registrato per questo cliente.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card ag-card h-100">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Documenti collegati</h5>
                        <a class="btn btn-sm btn-outline-warning" href="../documenti/create.php?cliente_id=<?php echo (int) $client['id']; ?>">Carica documento</a>
                    </div>
                    <div class="card-body">
                        <?php if ($documents): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($documents as $document): ?>
                                    <li class="list-group-item bg-transparent text-light">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <a class="link-warning fw-semibold" href="../documenti/view.php?id=<?php echo (int) $document['id']; ?>"><?php echo sanitize_output($document['titolo']); ?></a>
                                                <div class="small text-muted">Modulo: <?php echo sanitize_output($document['modulo'] ?? '—'); ?></div>
                                            </div>
                                            <span class="badge bg-secondary text-uppercase ms-2 flex-shrink-0"><?php echo sanitize_output($document['stato']); ?></span>
                                        </div>
                                        <div class="small text-muted mt-2">Aggiornato il <?php echo sanitize_output(date('d/m/Y H:i', strtotime($document['updated_at']))); ?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted mb-0">Nessun documento archiviato per questo cliente.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card ag-card">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Ultime pratiche</h5>
                <span class="text-muted small">Mostrati ultimi 10 record</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Categoria</th>
                                <th>Riferimento</th>
                                <th>Stato</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($practices): ?>
                                <?php foreach ($practices as $practice): ?>
                                    <tr>
                                        <td><?php echo sanitize_output($practice['categoria']); ?></td>
                                        <td><?php echo sanitize_output($practice['riferimento']); ?></td>
                                        <td><span class="badge ag-badge text-uppercase"><?php echo sanitize_output($practice['stato']); ?></span></td>
                                        <td><?php echo sanitize_output(date('d/m/Y', strtotime($practice['data']))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">Nessuna pratica registrata.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

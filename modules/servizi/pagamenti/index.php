<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';

require_role('Admin', 'Operatore', 'Viewer');
$pageTitle = 'Pagamenti';

$stati = ['In lavorazione', 'In attesa', 'Completato', 'Annullato'];
$metodi = ['Bonifico', 'Carta di credito', 'Carta di debito', 'Contanti', 'RID', 'Altro'];

$puoCreare = current_user_can('Admin', 'Operatore');
$puoModificare = current_user_can('Admin', 'Operatore');
$puoEliminare = current_user_can('Admin');

$filters = [
	'cliente_id' => isset($_GET['cliente_id']) ? (int) $_GET['cliente_id'] : null,
	'stato' => isset($_GET['stato']) && in_array($_GET['stato'], $stati, true) ? $_GET['stato'] : null,
	'search' => trim($_GET['search'] ?? ''),
];

$params = [];
$sql = "SELECT p.*, c.nome, c.cognome, c.ragione_sociale
	FROM pagamenti p
	LEFT JOIN clienti c ON p.cliente_id = c.id
	WHERE 1 = 1";

if ($filters['cliente_id']) {
	$sql .= ' AND p.cliente_id = :cliente_id';
	$params[':cliente_id'] = $filters['cliente_id'];
}

if ($filters['stato']) {
	$sql .= ' AND p.stato = :stato';
	$params[':stato'] = $filters['stato'];
}

if ($filters['search'] !== '') {
	$sql .= ' AND (p.descrizione LIKE :search OR p.riferimento LIKE :search)';
	$params[':search'] = '%' . $filters['search'] . '%';
}

$sql .= ' ORDER BY COALESCE(p.data_pagamento, p.data_scadenza, p.updated_at) DESC, p.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pagamenti = $stmt->fetchAll();

$clientsStmt = $pdo->query('SELECT id, nome, cognome, ragione_sociale FROM clienti ORDER BY ragione_sociale, cognome, nome');
$clients = $clientsStmt->fetchAll();

$csrfToken = csrf_token();

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
	<?php require_once __DIR__ . '/../../../includes/topbar.php'; ?>
	<main class="content-wrapper">
		<?php if (isset($_GET['notfound'])): ?>
			<div class="alert alert-warning alert-dismissible fade show" role="alert">
				Il pagamento richiesto non è stato trovato o è già stato rimosso.
				<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>
			</div>
		<?php endif; ?>
		<div class="page-toolbar mb-4 d-flex justify-content-between align-items-start flex-wrap gap-3">
			<div>
				<h1 class="h3 mb-1">Pagamenti</h1>
				<p class="text-muted mb-0">Gestisci in modo centralizzato le richieste di pagamento dei clienti.</p>
			</div>
			<div class="toolbar-actions d-flex gap-2">
				<a class="btn btn-outline-warning" href="../../../dashboard.php"><i class="fa-solid fa-gauge-high me-2"></i>Dashboard</a>
				<?php if ($puoCreare): ?>
					<a class="btn btn-warning text-dark" href="create.php"><i class="fa-solid fa-circle-plus me-2"></i>Nuovo pagamento</a>
				<?php endif; ?>
			</div>
		</div>

		<div class="card ag-card mb-4">
			<div class="card-header bg-transparent border-0">
				<h2 class="h5 mb-0">Filtri</h2>
			</div>
			<div class="card-body">
				<form class="row g-3" method="get" novalidate>
					<div class="col-md-4">
						<label class="form-label" for="cliente_id">Cliente</label>
						<select class="form-select" id="cliente_id" name="cliente_id">
							<option value="">Tutti</option>
							<?php foreach ($clients as $client): ?>
								<option value="<?php echo (int) $client['id']; ?>" <?php echo $filters['cliente_id'] === (int) $client['id'] ? 'selected' : ''; ?>>
									<?php
										$labelPieces = array_filter([
											$client['ragione_sociale'] ?: null,
											trim(($client['cognome'] ?? '') . ' ' . ($client['nome'] ?? '')) ?: null,
										]);
										echo sanitize_output($labelPieces ? implode(' • ', $labelPieces) : ('#' . $client['id']));
									?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-3">
						<label class="form-label" for="stato">Stato</label>
						<select class="form-select" id="stato" name="stato">
							<option value="">Tutti</option>
							<?php foreach ($stati as $stato): ?>
								<option value="<?php echo $stato; ?>" <?php echo $filters['stato'] === $stato ? 'selected' : ''; ?>><?php echo $stato; ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-3">
						<label class="form-label" for="search">Ricerca</label>
						<input class="form-control" id="search" type="text" name="search" value="<?php echo sanitize_output($filters['search']); ?>" placeholder="Descrizione o riferimento">
					</div>
					<div class="col-md-2 d-flex align-items-end gap-2">
						<button class="btn btn-warning text-dark w-100" type="submit">Applica</button>
					</div>
				</form>
			</div>
		</div>

		<div class="card ag-card">
			<div class="card-header bg-transparent border-0">
				<h2 class="h5 mb-0">Pagamenti registrati</h2>
			</div>
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-dark table-hover align-middle" data-datatable="true">
						<thead>
							<tr>
								<th>ID</th>
								<th>Cliente</th>
								<th>Descrizione</th>
								<th>Importo</th>
								<th>Stato</th>
								<th>Metodo</th>
								<th>Scadenza</th>
								<th>Pagamento</th>
								<th class="text-end">Azioni</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($pagamenti as $pagamento): ?>
								<tr>
									<td>#<?php echo (int) $pagamento['id']; ?></td>
									<td>
										<?php
											$clientName = trim(($pagamento['cognome'] ?? '') . ' ' . ($pagamento['nome'] ?? ''));
											$ragione = $pagamento['ragione_sociale'] ?? '';
											echo sanitize_output($ragione ?: ($clientName ?: 'N/D'));
										?>
									</td>
									<td>
										<strong><?php echo sanitize_output($pagamento['descrizione']); ?></strong><br>
										<small class="text-muted"><?php echo $pagamento['riferimento'] ? sanitize_output($pagamento['riferimento']) : '—'; ?></small>
									</td>
									<td><?php echo sanitize_output(format_currency((float) $pagamento['importo'])); ?></td>
									<td><span class="badge ag-badge text-uppercase"><?php echo sanitize_output($pagamento['stato']); ?></span></td>
									<td><?php echo sanitize_output($pagamento['metodo']); ?></td>
									<td><?php echo $pagamento['data_scadenza'] ? sanitize_output(date('d/m/Y', strtotime($pagamento['data_scadenza']))) : '<span class="text-muted">—</span>'; ?></td>
									<td><?php echo $pagamento['data_pagamento'] ? sanitize_output(date('d/m/Y', strtotime($pagamento['data_pagamento']))) : '<span class="text-muted">—</span>'; ?></td>
									<td class="text-end">
										<div class="btn-group">
											<a class="btn btn-sm btn-outline-warning" href="view.php?id=<?php echo (int) $pagamento['id']; ?>" title="Dettagli"><i class="fa-solid fa-eye"></i></a>
											<?php if ($puoModificare): ?>
												<a class="btn btn-sm btn-outline-warning" href="edit.php?id=<?php echo (int) $pagamento['id']; ?>" title="Modifica"><i class="fa-solid fa-pen"></i></a>
											<?php endif; ?>
											<?php if ($puoEliminare): ?>
												<form method="post" action="delete.php" class="d-inline" onsubmit="return confirm('Confermi l\'eliminazione di questo pagamento?');">
													<input type="hidden" name="_token" value="<?php echo $csrfToken; ?>">
													<input type="hidden" name="id" value="<?php echo (int) $pagamento['id']; ?>">
													<button class="btn btn-sm btn-outline-warning" type="submit" title="Elimina"><i class="fa-solid fa-trash"></i></button>
												</form>
											<?php endif; ?>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</main>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

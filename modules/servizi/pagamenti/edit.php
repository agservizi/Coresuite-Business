<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';

require_role('Admin', 'Operatore');
$pageTitle = 'Modifica pagamento';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
	header('Location: index.php');
	exit;
}

$stmt = $pdo->prepare('SELECT * FROM pagamenti WHERE id = :id');
$stmt->execute([':id' => $id]);
$pagamento = $stmt->fetch();

if (!$pagamento) {
	header('Location: index.php?notfound=1');
	exit;
}

$stati = ['In lavorazione', 'In attesa', 'Completato', 'Annullato'];
$metodi = ['Bonifico', 'Carta di credito', 'Carta di debito', 'Contanti', 'RID', 'Altro'];

$clientsStmt = $pdo->query('SELECT id, nome, cognome, ragione_sociale FROM clienti ORDER BY ragione_sociale, cognome, nome');
$clients = $clientsStmt->fetchAll();

$data = $pagamento;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	require_valid_csrf();

	$fields = ['cliente_id', 'descrizione', 'riferimento', 'metodo', 'stato', 'importo', 'data_scadenza', 'data_pagamento', 'note'];
	foreach ($fields as $field) {
		$data[$field] = trim($_POST[$field] ?? '');
	}

	if ((int) $data['cliente_id'] <= 0) {
		$errors[] = 'Seleziona un cliente valido.';
	}

	if ($data['descrizione'] === '') {
		$errors[] = 'Inserisci una descrizione del pagamento.';
	}

	if (!in_array($data['metodo'], $metodi, true)) {
		$data['metodo'] = 'Altro';
	}

	if (!in_array($data['stato'], $stati, true)) {
		$data['stato'] = 'In lavorazione';
	}

	if (!is_numeric($data['importo'])) {
		$errors[] = 'Inserisci un importo numerico valido.';
	}

	$data['data_scadenza'] = $data['data_scadenza'] ?: null;
	if ($data['data_scadenza'] && !DateTimeImmutable::createFromFormat('Y-m-d', $data['data_scadenza'])) {
		$errors[] = 'La data di scadenza non è valida.';
	}

	$data['data_pagamento'] = $data['data_pagamento'] ?: null;
	if ($data['data_pagamento'] && !DateTimeImmutable::createFromFormat('Y-m-d', $data['data_pagamento'])) {
		$errors[] = 'La data di pagamento non è valida.';
	}

	$newPath = $pagamento['allegato_path'] ?? null;
	$newHash = $pagamento['allegato_hash'] ?? null;
	$uploadedFile = $_FILES['allegato'] ?? null;

	if ($uploadedFile && !empty($uploadedFile['name']) && $uploadedFile['error'] !== UPLOAD_ERR_OK) {
		$errors[] = 'Errore nel caricamento del file allegato.';
	}

	if (!$errors && $uploadedFile && !empty($uploadedFile['name']) && $uploadedFile['error'] === UPLOAD_ERR_OK) {
		$storageDir = public_path('assets/uploads/pagamenti');
		if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
			$errors[] = 'Impossibile creare la cartella di archiviazione allegati.';
		} else {
			$original = sanitize_filename($uploadedFile['name']);
			$fileName = sprintf('pagamento_%s_%s', date('YmdHis'), $original);
			$destination = $storageDir . DIRECTORY_SEPARATOR . $fileName;
			if (!move_uploaded_file($uploadedFile['tmp_name'], $destination)) {
				$errors[] = 'Impossibile salvare il file allegato.';
			} else {
				if (!empty($pagamento['allegato_path'])) {
					$oldAbsolute = public_path($pagamento['allegato_path']);
					if (is_file($oldAbsolute)) {
						@unlink($oldAbsolute);
					}
				}
				$newPath = 'assets/uploads/pagamenti/' . $fileName;
				$newHash = hash_file('sha256', $destination);
			}
		}
	}

	if (!$errors) {
		$stmt = $pdo->prepare('UPDATE pagamenti SET
			cliente_id = :cliente_id,
			descrizione = :descrizione,
			riferimento = :riferimento,
			metodo = :metodo,
			stato = :stato,
			importo = :importo,
			data_scadenza = :data_scadenza,
			data_pagamento = :data_pagamento,
			note = :note,
			allegato_path = :allegato_path,
			allegato_hash = :allegato_hash,
			updated_at = NOW()
		WHERE id = :id');
		$stmt->execute([
			':cliente_id' => (int) $data['cliente_id'],
			':descrizione' => $data['descrizione'],
			':riferimento' => $data['riferimento'] ?: null,
			':metodo' => $data['metodo'],
			':stato' => $data['stato'],
			':importo' => number_format((float) $data['importo'], 2, '.', ''),
			':data_scadenza' => $data['data_scadenza'] ?: null,
			':data_pagamento' => $data['data_pagamento'] ?: null,
			':note' => $data['note'] ?: null,
			':allegato_path' => $newPath,
			':allegato_hash' => $newHash,
			':id' => $id,
		]);

		add_flash('success', 'Pagamento aggiornato correttamente.');
		header('Location: view.php?id=' . $id);
		exit;
	}
}

$csrfToken = csrf_token();

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
	<?php require_once __DIR__ . '/../../../includes/topbar.php'; ?>
	<main class="content-wrapper">
		<div class="mb-4">
			<a class="btn btn-outline-warning" href="view.php?id=<?php echo $id; ?>"><i class="fa-solid fa-arrow-left"></i> Dettaglio pagamento</a>
		</div>
		<div class="card ag-card">
			<div class="card-header bg-transparent border-0">
				<h1 class="h4 mb-0">Modifica pagamento</h1>
			</div>
			<div class="card-body">
				<?php if ($errors): ?>
					<div class="alert alert-warning">
						<?php echo implode('<br>', array_map('sanitize_output', $errors)); ?>
					</div>
				<?php endif; ?>
				<form method="post" enctype="multipart/form-data" class="row g-4" novalidate>
					<input type="hidden" name="_token" value="<?php echo $csrfToken; ?>">
					<div class="col-md-6">
						<label class="form-label" for="cliente_id">Cliente</label>
						<select class="form-select" id="cliente_id" name="cliente_id" required>
							<?php foreach ($clients as $client): ?>
								<option value="<?php echo (int) $client['id']; ?>" <?php echo ((int) $data['cliente_id'] === (int) $client['id']) ? 'selected' : ''; ?>>
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
					<div class="col-md-6">
						<label class="form-label" for="descrizione">Descrizione</label>
						<input class="form-control" id="descrizione" name="descrizione" value="<?php echo sanitize_output($data['descrizione']); ?>" maxlength="180" required>
					</div>
					<div class="col-md-4">
						<label class="form-label" for="riferimento">Riferimento</label>
						<input class="form-control" id="riferimento" name="riferimento" value="<?php echo sanitize_output($data['riferimento'] ?? ''); ?>" maxlength="80">
					</div>
					<div class="col-md-4">
						<label class="form-label" for="metodo">Metodo</label>
						<select class="form-select" id="metodo" name="metodo">
							<?php foreach ($metodi as $metodo): ?>
								<option value="<?php echo $metodo; ?>" <?php echo $data['metodo'] === $metodo ? 'selected' : ''; ?>><?php echo $metodo; ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-4">
						<label class="form-label" for="stato">Stato</label>
						<select class="form-select" id="stato" name="stato">
							<?php foreach ($stati as $stato): ?>
								<option value="<?php echo $stato; ?>" <?php echo $data['stato'] === $stato ? 'selected' : ''; ?>><?php echo $stato; ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-4">
						<label class="form-label" for="importo">Importo</label>
						<div class="input-group">
							<span class="input-group-text">€</span>
							<input class="form-control" id="importo" name="importo" type="number" step="0.01" min="0" value="<?php echo sanitize_output((string) $data['importo']); ?>" required>
						</div>
					</div>
					<div class="col-md-4">
						<label class="form-label" for="data_scadenza">Data scadenza</label>
						<input class="form-control" id="data_scadenza" name="data_scadenza" type="date" value="<?php echo sanitize_output((string) $data['data_scadenza']); ?>">
					</div>
					<div class="col-md-4">
						<label class="form-label" for="data_pagamento">Data pagamento</label>
						<input class="form-control" id="data_pagamento" name="data_pagamento" type="date" value="<?php echo sanitize_output((string) $data['data_pagamento']); ?>">
					</div>
					<div class="col-12">
						<label class="form-label" for="note">Note</label>
						<textarea class="form-control" id="note" name="note" rows="4"><?php echo sanitize_output($data['note'] ?? ''); ?></textarea>
					</div>
					<div class="col-md-6">
						<label class="form-label" for="allegato">Allegato (opzionale)</label>
						<input class="form-control" id="allegato" name="allegato" type="file" accept="application/pdf,image/*">
						<?php if (!empty($pagamento['allegato_path'])): ?>
							<small class="d-block mt-2">Allegato attuale: <a class="link-warning" href="../../../<?php echo sanitize_output($pagamento['allegato_path']); ?>" target="_blank">Scarica</a></small>
						<?php else: ?>
							<small class="text-muted">Nessun file caricato.</small>
						<?php endif; ?>
					</div>
					<div class="col-12 d-flex justify-content-end gap-2">
						<a class="btn btn-secondary" href="view.php?id=<?php echo $id; ?>">Annulla</a>
						<button class="btn btn-warning text-dark" type="submit">Salva modifiche</button>
					</div>
				</form>
			</div>
		</div>
	</main>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

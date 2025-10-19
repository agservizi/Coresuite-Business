<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';

require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: index.php');
	exit;
}

require_valid_csrf();

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
	header('Location: index.php');
	exit;
}

$stmt = $pdo->prepare('SELECT allegato_path FROM pagamenti WHERE id = :id');
$stmt->execute([':id' => $id]);
$pagamento = $stmt->fetch();

if (!$pagamento) {
	header('Location: index.php?notfound=1');
	exit;
}

$pdo->beginTransaction();

try {
	$deleteStmt = $pdo->prepare('DELETE FROM pagamenti WHERE id = :id');
	$deleteStmt->execute([':id' => $id]);

	if (!empty($pagamento['allegato_path'])) {
		$filePath = public_path($pagamento['allegato_path']);
		if (is_file($filePath)) {
			@unlink($filePath);
		}
	}

	$pdo->commit();
	add_flash('success', 'Pagamento eliminato correttamente.');
} catch (Throwable $e) {
	$pdo->rollBack();
	error_log('Errore eliminazione pagamento ID ' . $id . ': ' . $e->getMessage());
	add_flash('danger', 'Impossibile eliminare il pagamento. Riprova più tardi.');
}

header('Location: index.php');
exit;

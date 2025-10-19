<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';

require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php?error=1');
    exit;
}

try {
    $stmt = $pdo->prepare('DELETE FROM servizi_digitali WHERE id = :id');
    $stmt->execute([':id' => $id]);
    header('Location: index.php?deleted=1');
} catch (PDOException $e) {
    error_log('Delete digitale failed: ' . $e->getMessage());
    header('Location: index.php?error=1');
}
exit;

<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';

require_role('Admin', 'Operatore', 'Manager');

if (!defined('CORESUITE_PICKUP_BOOTSTRAP')) {
    define('CORESUITE_PICKUP_BOOTSTRAP', true);
}

require_once __DIR__ . '/functions.php';

ensure_pickup_tables();

$statuses = pickup_statuses();
$couriers = get_all_couriers();

$data = [
    'customer_name' => '',
    'customer_phone' => '',
    'customer_email' => '',
    'tracking' => '',
    'status' => 'in_arrivo',
    'courier_id' => '',
    'expected_at' => '',
    'notes' => '',
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();

    $data['customer_name'] = trim((string) ($_POST['customer_name'] ?? ''));
    $data['customer_phone'] = trim((string) ($_POST['customer_phone'] ?? ''));
    $data['customer_email'] = trim((string) ($_POST['customer_email'] ?? ''));
    $data['tracking'] = trim((string) ($_POST['tracking'] ?? ''));
    $data['status'] = (string) ($_POST['status'] ?? 'in_arrivo');
    $data['courier_id'] = (string) ($_POST['courier_id'] ?? '');
    $data['expected_at'] = trim((string) ($_POST['expected_at'] ?? ''));
    $data['notes'] = trim((string) ($_POST['notes'] ?? ''));

    try {
        $packageId = add_package([
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'customer_email' => $data['customer_email'],
            'tracking' => $data['tracking'],
            'status' => $data['status'],
            'courier_id' => $data['courier_id'] !== '' ? (int) $data['courier_id'] : null,
            'expected_at' => $data['expected_at'],
            'notes' => $data['notes'],
        ]);

        add_flash('success', 'Pickup #' . $data['tracking'] . ' creato con successo.');
        header('Location: view.php?id=' . $packageId);
        exit;
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

$pageTitle = 'Nuovo pickup';
$extraStyles = [asset('modules/servizi/logistici/css/style.css')];
$extraScripts = [asset('modules/servizi/logistici/js/script.js')];
$formToken = csrf_token();

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100 pickup-module">
    <?php require_once __DIR__ . '/../../../includes/topbar.php'; ?>
    <main class="content-wrapper">
        <div class="mb-4">
            <a class="btn btn-outline-warning" href="index.php"><i class="fa-solid fa-arrow-left"></i> Torna ai pickup</a>
        </div>
        <div class="card ag-card">
            <div class="card-header bg-transparent border-0">
                <h1 class="h4 mb-0">Nuovo pickup</h1>
            </div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-warning"><?php echo implode('<br>', array_map('sanitize_output', $errors)); ?></div>
                <?php endif; ?>
                <form method="post" novalidate>
                    <input type="hidden" name="_token" value="<?php echo $formToken; ?>">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label" for="customer_name">Nome cliente</label>
                            <input class="form-control" id="customer_name" name="customer_name" value="<?php echo sanitize_output($data['customer_name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="customer_phone">Telefono cliente</label>
                            <input class="form-control" id="customer_phone" name="customer_phone" value="<?php echo sanitize_output($data['customer_phone']); ?>" placeholder="+391234567890" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="customer_email">Email cliente</label>
                            <input class="form-control" id="customer_email" name="customer_email" type="email" value="<?php echo sanitize_output($data['customer_email']); ?>" placeholder="cliente@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="tracking">Codice tracking</label>
                            <input class="form-control" id="tracking" name="tracking" value="<?php echo sanitize_output($data['tracking']); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="status">Stato iniziale</label>
                            <select class="form-select" id="status" name="status">
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo $status === $data['status'] ? 'selected' : ''; ?>><?php echo pickup_status_label($status); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="courier_id">Corriere</label>
                            <select class="form-select" id="courier_id" name="courier_id">
                                <option value="">Nessuno</option>
                                <?php foreach ($couriers as $courier): ?>
                                    <option value="<?php echo (int) $courier['id']; ?>" <?php echo (string) $courier['id'] === $data['courier_id'] ? 'selected' : ''; ?>><?php echo sanitize_output($courier['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="expected_at">Data prevista</label>
                            <input class="form-control" id="expected_at" name="expected_at" type="date" value="<?php echo sanitize_output($data['expected_at']); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="notes">Note interne</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo sanitize_output($data['notes']); ?></textarea>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a class="btn btn-secondary" href="index.php">Annulla</a>
                        <button class="btn btn-warning text-dark" type="submit">Registra pickup</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

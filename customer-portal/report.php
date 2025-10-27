<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pickup_service.php';

// Verifica autenticazione
if (!CustomerAuth::isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$customer = CustomerAuth::getAuthenticatedCustomer();
$pickupService = new PickupService();

$errors = [];
$success = false;
$reportData = [];

// Gestione form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verifica CSRF token
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new Exception('Token di sicurezza non valido');
        }
        
        $reportData = [
            'tracking_code' => trim($_POST['tracking_code'] ?? ''),
            'courier_name' => trim($_POST['courier_name'] ?? ''),
            'recipient_name' => trim($_POST['recipient_name'] ?? ''),
            'expected_delivery_date' => trim($_POST['expected_delivery_date'] ?? '') ?: null,
            'delivery_location' => trim($_POST['delivery_location'] ?? ''),
            'notes' => trim($_POST['notes'] ?? '')
        ];
        
        // Validazione base
        if (empty($reportData['tracking_code'])) {
            $errors[] = 'Il codice tracking è obbligatorio';
        }
        
        if (strlen($reportData['tracking_code']) < portal_config('min_tracking_length')) {
            $errors[] = 'Il codice tracking deve essere di almeno ' . portal_config('min_tracking_length') . ' caratteri';
        }
        
        if (strlen($reportData['tracking_code']) > portal_config('max_tracking_length')) {
            $errors[] = 'Il codice tracking non può superare ' . portal_config('max_tracking_length') . ' caratteri';
        }
        
        if ($reportData['expected_delivery_date'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $reportData['expected_delivery_date'])) {
            $errors[] = 'Formato data consegna non valido';
        }
        
        if (empty($errors)) {
            $report = $pickupService->reportPackage($customer['id'], $reportData);
            $success = true;
            $reportData = []; // Reset form
            
            // Log attività
            $pickupService->logCustomerActivity($customer['id'], 'package_report_created', 'report', $report['id']);
        }
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
        portal_error_log('Package report error: ' . $e->getMessage(), [
            'customer_id' => $customer['id'],
            'data' => $reportData
        ]);
    }
}

$pageTitle = 'Segnala Pacco';
?>

<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-plus me-2 text-primary"></i>
                    Segnala Pacco
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="packages.php" class="btn btn-outline-primary">
                            <i class="fas fa-boxes me-1"></i> I Miei Pacchi
                        </a>
                    </div>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Errori:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Perfetto!</strong> Il pacco è stato segnalato correttamente. Riceverai una notifica quando arriverà.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-box me-2"></i>
                                Informazioni Pacco
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="tracking_code" class="form-label">
                                            <i class="fas fa-barcode me-1"></i>
                                            Codice Tracking <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               class="form-control form-control-lg <?= isset($errors[0]) && strpos($errors[0], 'tracking') !== false ? 'is-invalid' : '' ?>" 
                                               id="tracking_code" 
                                               name="tracking_code" 
                                               value="<?= htmlspecialchars($reportData['tracking_code'] ?? '') ?>"
                                               placeholder="es. 1Z999AA1234567890"
                                               required
                                               maxlength="<?= portal_config('max_tracking_length') ?>">
                                        <div class="form-text">
                                            Il numero di tracking del corriere (minimo <?= portal_config('min_tracking_length') ?> caratteri)
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="courier_name" class="form-label">
                                            <i class="fas fa-truck me-1"></i>
                                            Corriere
                                        </label>
                                        <input type="text" 
                                               class="form-control form-control-lg" 
                                               id="courier_name" 
                                               name="courier_name" 
                                               value="<?= htmlspecialchars($reportData['courier_name'] ?? '') ?>"
                                               placeholder="es. Amazon, DHL, GLS, Poste">
                                        <div class="form-text">
                                            Nome del corriere che consegna il pacco
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="recipient_name" class="form-label">
                                            <i class="fas fa-user me-1"></i>
                                            Destinatario
                                        </label>
                                        <input type="text" 
                                               class="form-control form-control-lg" 
                                               id="recipient_name" 
                                               name="recipient_name" 
                                               value="<?= htmlspecialchars($reportData['recipient_name'] ?? $customer['name'] ?? '') ?>"
                                               placeholder="Nome del destinatario">
                                        <div class="form-text">
                                            Chi deve ritirare il pacco
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="expected_delivery_date" class="form-label">
                                            <i class="fas fa-calendar me-1"></i>
                                            Data Consegna Prevista
                                        </label>
                                        <input type="date" 
                                               class="form-control form-control-lg" 
                                               id="expected_delivery_date" 
                                               name="expected_delivery_date" 
                                               value="<?= htmlspecialchars($reportData['expected_delivery_date'] ?? '') ?>"
                                               min="<?= date('Y-m-d') ?>">
                                        <div class="form-text">
                                            Quando dovrebbe arrivare il pacco
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="delivery_location" class="form-label">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        Luogo di Consegna
                                    </label>
                                    <input type="text" 
                                           class="form-control form-control-lg" 
                                           id="delivery_location" 
                                           name="delivery_location" 
                                           value="<?= htmlspecialchars($reportData['delivery_location'] ?? '') ?>"
                                           placeholder="es. Tabaccheria XYZ, Via Roma 123">
                                    <div class="form-text">
                                        Dove dovrebbe essere consegnato il pacco
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="notes" class="form-label">
                                        <i class="fas fa-sticky-note me-1"></i>
                                        Note Aggiuntive
                                    </label>
                                    <textarea class="form-control" 
                                              id="notes" 
                                              name="notes" 
                                              rows="3" 
                                              placeholder="Eventuali note o informazioni aggiuntive..."><?= htmlspecialchars($reportData['notes'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="packages.php" class="btn btn-outline-secondary btn-lg me-md-2">
                                        <i class="fas fa-times me-1"></i> Annulla
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-paper-plane me-1"></i> Segnala Pacco
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <!-- Guida -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Come Funziona
                            </h6>
                        </div>
                        <div class="card-body">
                            <ol class="mb-0">
                                <li class="mb-2">
                                    <strong>Segnala</strong> il pacco con il codice tracking
                                </li>
                                <li class="mb-2">
                                    <strong>Attendi</strong> la notifica di arrivo
                                </li>
                                <li class="mb-2">
                                    <strong>Ritira</strong> presso il punto di consegna
                                </li>
                            </ol>
                        </div>
                    </div>
                    
                    <!-- Consigli -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0">
                                <i class="fas fa-lightbulb me-2"></i>
                                Consigli Utili
                            </h6>
                        </div>
                        <div class="card-body">
                            <ul class="mb-0">
                                <li class="mb-2">
                                    <small>Il codice tracking lo trovi nell'email di conferma ordine</small>
                                </li>
                                <li class="mb-2">
                                    <small>Segnala il pacco appena effettui l'ordine</small>
                                </li>
                                <li class="mb-2">
                                    <small>Riceverai notifiche via email o SMS</small>
                                </li>
                                <li class="mb-0">
                                    <small>Porta un documento valido per il ritiro</small>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-focus sul campo tracking code
    const trackingInput = document.getElementById('tracking_code');
    if (trackingInput && !trackingInput.value) {
        trackingInput.focus();
    }
    
    // Validazione in tempo reale del tracking code
    trackingInput?.addEventListener('input', function() {
        const value = this.value.trim();
        const minLength = <?= portal_config('min_tracking_length') ?>;
        const maxLength = <?= portal_config('max_tracking_length') ?>;
        
        if (value.length > 0 && value.length < minLength) {
            this.classList.add('is-invalid');
        } else if (value.length > maxLength) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
            if (value.length >= minLength) {
                this.classList.add('is-valid');
            }
        }
    });
    
    // Suggerimenti automatici per il corriere
    const courierInput = document.getElementById('courier_name');
    const courierSuggestions = [
        'Amazon', 'DHL', 'GLS', 'Poste Italiane', 'UPS', 'FedEx', 
        'TNT', 'Bartolini', 'SDA', 'Nexive', 'INPOST'
    ];
    
    // Aggiungi datalist per suggerimenti
    if (courierInput) {
        const datalist = document.createElement('datalist');
        datalist.id = 'courier-suggestions';
        courierSuggestions.forEach(suggestion => {
            const option = document.createElement('option');
            option.value = suggestion;
            datalist.appendChild(option);
        });
        courierInput.setAttribute('list', 'courier-suggestions');
        courierInput.parentNode.appendChild(datalist);
    }
    
    // Auto-completamento nome destinatario
    const recipientInput = document.getElementById('recipient_name');
    if (recipientInput && !recipientInput.value) {
        recipientInput.value = '<?= htmlspecialchars($customer['name'] ?? '') ?>';
    }
});
</script>
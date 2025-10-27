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

// Ottieni statistiche del cliente
$stats = $pickupService->getCustomerStats($customer['id']);

// Ottieni pacchi recenti
$recentPackages = $pickupService->getCustomerPackages($customer['id'], ['limit' => 5]);

// Ottieni notifiche recenti
$recentNotifications = $pickupService->getCustomerNotifications($customer['id'], ['limit' => 5, 'unread_only' => false]);

$pageTitle = 'Dashboard';

$pickupService = new PickupService();

// Statistiche pacchi
$stats = $pickupService->getCustomerStats($customer['id']);

// Pacchi recenti
$recentPackages = $pickupService->getCustomerPackages($customer['id'], ['limit' => 5]);

// Notifiche non lette
$notifications = $pickupService->getCustomerNotifications($customer['id'], ['unread_only' => true, 'limit' => 5]);

// Segnalazioni in attesa
$pendingReports = $pickupService->getCustomerReports($customer['id'], ['status' => 'reported']);

$pageTitle = 'Dashboard';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-tachometer-alt me-2 text-primary"></i>
                    Dashboard
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reportPackageModal">
                            <i class="fas fa-plus me-2"></i>Segnala Pacco
                        </button>
                    </div>
                </div>
            </div>

            <!-- Alert per notifiche importanti -->
            <?php if (!empty($notifications)): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-bell me-2"></i>
                <strong>Hai <?= count($notifications) ?> notifiche non lette</strong>
                <a href="notifications.php" class="alert-link ms-2">Visualizza tutte</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Statistiche principali -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Pacchi in Attesa
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?= $stats['pending_packages'] ?? 0 ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Pronti per il Ritiro
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?= $stats['ready_packages'] ?? 0 ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-box fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Ritirati Questo Mese
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?= $stats['monthly_delivered'] ?? 0 ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Segnalazioni in Attesa
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?= count($pendingReports) ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Pacchi recenti -->
                <div class="col-lg-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-boxes me-2"></i>I Tuoi Pacchi
                            </h6>
                            <a href="packages.php" class="btn btn-sm btn-outline-primary">
                                Vedi Tutti
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentPackages)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Non hai ancora pacchi registrati</p>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reportPackageModal">
                                    <i class="fas fa-plus me-2"></i>Segnala il tuo primo pacco
                                </button>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tracking</th>
                                            <th>Stato</th>
                                            <th>Corriere</th>
                                            <th>Data</th>
                                            <th>Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentPackages as $package): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($package['tracking_code']) ?></strong>
                                                <?php if ($package['recipient_name']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($package['recipient_name']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= $pickupService->getStatusBadge($package['status']) ?>
                                            </td>
                                            <td><?= htmlspecialchars($package['courier_name'] ?? 'N/A') ?></td>
                                            <td>
                                                <?= date('d/m/Y', strtotime($package['created_at'])) ?>
                                                <br><small class="text-muted"><?= date('H:i', strtotime($package['created_at'])) ?></small>
                                            </td>
                                            <td>
                                                <a href="packages.php?view=<?= $package['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Notifiche e attività -->
                <div class="col-lg-4">
                    <!-- Notifiche recenti -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-bell me-2"></i>Notifiche
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($notifications)): ?>
                            <p class="text-muted text-center">Nessuna notifica</p>
                            <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                            <div class="d-flex align-items-center mb-3">
                                <div class="me-3">
                                    <i class="fas fa-<?= $pickupService->getNotificationIcon($notification['type']) ?> text-primary"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold"><?= htmlspecialchars($notification['title']) ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($notification['message']) ?></div>
                                    <div class="text-muted small"><?= date('d/m H:i', strtotime($notification['created_at'])) ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <div class="text-center">
                                <a href="notifications.php" class="btn btn-sm btn-outline-primary">
                                    Vedi tutte le notifiche
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Guida rapida -->
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-question-circle me-2"></i>Guida Rapida
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>1. Segnala un pacco</strong>
                                <p class="text-muted small">Inserisci il codice tracking quando ordini qualcosa</p>
                            </div>
                            <div class="mb-3">
                                <strong>2. Ricevi notifiche</strong>
                                <p class="text-muted small">Ti avviseremo quando il pacco arriva</p>
                            </div>
                            <div class="mb-0">
                                <strong>3. Ritira con il codice</strong>
                                <p class="text-muted small">Mostra il codice OTP al ritiro</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal per segnalazione pacco -->
<div class="modal fade" id="reportPackageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>Segnala Nuovo Pacco
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="reportPackageForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
                    
                    <div class="mb-3">
                        <label for="tracking_code" class="form-label">Codice Tracking *</label>
                        <input type="text" class="form-control" id="tracking_code" name="tracking_code" required>
                        <div class="form-text">Il codice tracking del corriere</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="courier_name" class="form-label">Corriere</label>
                        <select class="form-select" id="courier_name" name="courier_name">
                            <option value="">Seleziona corriere</option>
                            <option value="BRT">BRT</option>
                            <option value="GLS">GLS</option>
                            <option value="DHL">DHL</option>
                            <option value="UPS">UPS</option>
                            <option value="FedEx">FedEx</option>
                            <option value="Poste Italiane">Poste Italiane</option>
                            <option value="TNT">TNT</option>
                            <option value="SDA">SDA</option>
                            <option value="Altro">Altro</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="recipient_name" class="form-label">Nome Destinatario</label>
                        <input type="text" class="form-control" id="recipient_name" name="recipient_name" value="<?= htmlspecialchars($customer['name'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="expected_delivery_date" class="form-label">Data Consegna Prevista</label>
                        <input type="date" class="form-control" id="expected_delivery_date" name="expected_delivery_date">
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Note</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Segnala Pacco
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestione form segnalazione pacco
    const reportForm = document.getElementById('reportPackageForm');
    if (reportForm) {
        reportForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('api/report-package.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('reportPackageModal')).hide();
                    showAlert('success', 'Pacco segnalato con successo!');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('error', data.message || 'Errore durante la segnalazione');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'Errore di comunicazione');
            });
        });
    }
});
</script>
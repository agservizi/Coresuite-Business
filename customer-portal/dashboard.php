<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pickup_service.php';

if (!CustomerAuth::isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$customer = CustomerAuth::getAuthenticatedCustomer();
$pickupService = new PickupService();
$customerId = (int) $customer['id'];

$stats = $pickupService->getCustomerStats($customerId);
$recentPackages = $pickupService->getCustomerPackages($customerId, ['limit' => 5]);
$recentNotifications = $pickupService->getCustomerNotifications($customerId, ['limit' => 5, 'unread_only' => false]);
$unreadNotifications = $pickupService->getCustomerNotifications($customerId, ['limit' => 5, 'unread_only' => true]);
$pendingReports = $pickupService->getCustomerReports($customerId, ['status' => 'reported']);

$pageTitle = 'Dashboard';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="portal-main d-flex flex-column flex-grow-1">
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <main class="portal-content">
        <div class="d-flex align-items-start align-items-md-center justify-content-between flex-column flex-md-row gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1">Ciao <?= htmlspecialchars($customer['name'] ?? $customer['email'] ?? 'Cliente') ?> 👋</h1>
                <p class="text-muted-soft mb-0">Qui trovi il riepilogo aggiornato dei tuoi pacchi.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn topbar-btn" data-bs-toggle="modal" data-bs-target="#reportPackageModal">
                    <i class="fa-solid fa-plus"></i>
                    <span class="topbar-btn-label">Segnala pacco</span>
                </button>
                <a class="btn topbar-btn" href="packages.php">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <span class="topbar-btn-label">Tutti i pacchi</span>
                </a>
            </div>
        </div>

        <?php if (!empty($unreadNotifications)): ?>
            <div class="alert alert-info alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
                <div class="d-flex align-items-center gap-3">
                    <span class="portal-stat-icon"><i class="fa-solid fa-bell"></i></span>
                    <div>
                        <strong>Hai <?= count($unreadNotifications) ?> notifiche non lette</strong>
                        <div class="small text-muted">Ti consigliamo di leggerle per non perdere aggiornamenti importanti.</div>
                    </div>
                    <a class="btn btn-sm btn-outline-primary ms-auto" href="notifications.php">Apri notifiche</a>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <div class="col-12 col-md-6 col-xl-3">
                <div class="portal-stat-card h-100">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="portal-stat-icon"><i class="fa-solid fa-box-open"></i></span>
                        <span class="badge bg-light text-primary">In attesa</span>
                    </div>
                    <h2 class="display-6 fw-semibold mb-1"><?= $stats['pending_packages'] ?? 0 ?></h2>
                    <p class="text-muted mb-0">Pacchi registrati ma non ancora arrivati al pickup point.</p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="portal-stat-card h-100">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="portal-stat-icon"><i class="fa-solid fa-truck-ramp-box"></i></span>
                        <span class="badge bg-light text-success">Pronti</span>
                    </div>
                    <h2 class="display-6 fw-semibold mb-1"><?= $stats['ready_packages'] ?? 0 ?></h2>
                    <p class="text-muted mb-0">I tuoi pacchi arrivati e pronti al ritiro presso la sede.</p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="portal-stat-card h-100">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="portal-stat-icon"><i class="fa-solid fa-calendar-check"></i></span>
                        <span class="badge bg-light text-info">Questo mese</span>
                    </div>
                    <h2 class="display-6 fw-semibold mb-1"><?= $stats['monthly_delivered'] ?? 0 ?></h2>
                    <p class="text-muted mb-0">Pacchi ritirati negli ultimi 30 giorni dal tuo account.</p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="portal-stat-card h-100">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="portal-stat-icon"><i class="fa-solid fa-circle-exclamation"></i></span>
                        <span class="badge bg-light text-warning">Segnalazioni</span>
                    </div>
                    <h2 class="display-6 fw-semibold mb-1"><?= count($pendingReports) ?></h2>
                    <p class="text-muted mb-0">Segnalazioni in attesa di revisione da parte del team Coresuite.</p>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-xl-8">
                <div class="card mb-4 mb-xl-0">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="h5 mb-0">Ultimi pacchi</h2>
                            <small class="text-muted">Gli ultimi aggiornamenti sui pacchi registrati</small>
                        </div>
                        <a class="btn btn-sm btn-outline-primary" href="packages.php">Vedi tutti</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentPackages)): ?>
                            <div class="portal-empty-state">
                                <i class="fa-solid fa-box-open"></i>
                                <h3 class="h5">Ancora nessun pacco</h3>
                                <p class="text-muted">Segnala il tuo primo pacco per iniziare a ricevere aggiornamenti.</p>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reportPackageModal">
                                    <i class="fa-solid fa-plus me-1"></i>Segnala pacco
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr class="text-muted">
                                            <th>Tracking</th>
                                            <th>Stato</th>
                                            <th>Corriere</th>
                                            <th>Registrato</th>
                                            <th class="text-end">Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentPackages as $package): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold package-tracking"><?= htmlspecialchars($package['tracking_code']) ?></div>
                                                    <?php if (!empty($package['recipient_name'])): ?>
                                                        <div class="text-muted small">Destinatario: <?= htmlspecialchars($package['recipient_name']) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= $pickupService->getStatusBadge($package['status']) ?></td>
                                                <td><?= htmlspecialchars($package['courier_name'] ?? 'N/D') ?></td>
                                                <td>
                                                    <div><?= date('d/m/Y', strtotime($package['created_at'])) ?></div>
                                                    <div class="text-muted small">ore <?= date('H:i', strtotime($package['created_at'])) ?></div>
                                                </td>
                                                <td class="text-end">
                                                    <a class="btn btn-sm btn-outline-primary" href="packages.php?view=<?= (int) $package['id'] ?>" title="Vedi dettagli"><i class="fa-solid fa-eye"></i></a>
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

            <div class="col-12 col-xl-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="h6 mb-0">Notifiche recenti</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentNotifications)): ?>
                            <p class="text-muted text-center mb-0">Non ci sono notifiche da mostrare.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentNotifications as $notification): ?>
                                    <a class="list-group-item list-group-item-action d-flex gap-3" href="notifications.php#<?= (int) $notification['id'] ?>">
                                        <span class="portal-stat-icon flex-shrink-0"><i class="fa-solid fa-<?= $pickupService->getNotificationIcon($notification['type']) ?>"></i></span>
                                        <span class="flex-grow-1">
                                            <span class="fw-semibold d-block text-truncate"><?= htmlspecialchars($notification['title']) ?></span>
                                            <span class="text-muted small d-block text-truncate-2"><?= htmlspecialchars($notification['message']) ?></span>
                                            <span class="text-muted small"><?= date('d/m H:i', strtotime($notification['created_at'])) ?></span>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a class="btn btn-sm btn-outline-primary" href="notifications.php">Vedi tutte</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="h6 mb-0">Come funziona</h2>
                    </div>
                    <div class="card-body">
                        <ol class="ps-3 mb-0">
                            <li class="mb-3">
                                <strong>Segnala il pacco</strong>
                                <p class="text-muted small mb-0">Inserisci il tracking appena ricevi l'ordine dal corriere.</p>
                            </li>
                            <li class="mb-3">
                                <strong>Ricevi notifiche</strong>
                                <p class="text-muted small mb-0">Ti avvisiamo quando il pacco arriva o sta per scadere.</p>
                            </li>
                            <li>
                                <strong>Ritira con il codice OTP</strong>
                                <p class="text-muted small mb-0">Mostra il codice a 6 cifre per completare il ritiro in sicurezza.</p>
                            </li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<div class="modal fade" id="reportPackageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-plus me-2"></i>Segnala nuovo pacco</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
            </div>
            <form id="reportPackageForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="tracking_code">Codice tracking *</label>
                            <input class="form-control" id="tracking_code" name="tracking_code" placeholder="es. 1Z999AA1234567890" required maxlength="<?= portal_config('max_tracking_length') ?>">
                            <small class="text-muted">Almeno <?= portal_config('min_tracking_length') ?> caratteri.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="courier_name">Corriere</label>
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
                        <div class="col-md-6">
                            <label class="form-label" for="recipient_name">Destinatario</label>
                            <input class="form-control" id="recipient_name" name="recipient_name" value="<?= htmlspecialchars($customer['name'] ?? '') ?>" placeholder="Nome della persona che ritira">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="expected_delivery_date">Data prevista</label>
                            <input class="form-control" type="date" id="expected_delivery_date" name="expected_delivery_date" min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="notes">Note</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Informazioni utili sul pacco"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Annulla</button>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-paper-plane me-2"></i>Invia segnalazione</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const reportForm = document.getElementById('reportPackageForm');
    if (!reportForm) {
        return;
    }
    reportForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const formData = new FormData(reportForm);
        fetch('api/report-package.php', {
            method: 'POST',
            body: formData
        })
            .then((response) => response.json())
            .then((data) => {
                if (!data.success) {
                    window.PickupPortal.showAlert(data.message || 'Errore durante la segnalazione', 'danger');
                    return;
                }
                const modalElement = document.getElementById('reportPackageModal');
                const modalInstance = bootstrap.Modal.getInstance(modalElement);
                modalInstance?.hide();
                window.PickupPortal.showAlert('Pacco segnalato con successo!', 'success');
                setTimeout(() => window.location.reload(), 1200);
            })
            .catch((error) => {
                console.error('Report package error:', error);
                window.PickupPortal.showAlert('Errore di comunicazione con il server', 'danger');
            });
    });
});
</script>
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
        <?php
        $pendingCount = (int) ($stats['pending_packages'] ?? 0);
        $readyCount = (int) ($stats['ready_packages'] ?? 0);
        $monthlyCount = (int) ($stats['monthly_delivered'] ?? 0);
        $totalPackages = (int) ($stats['total_packages'] ?? 0);
    $reportsCount = count($pendingReports);
    $unreadCount = count($unreadNotifications);

        if ($readyCount > 0) {
            $heroSubtitle = sprintf('Hai %d pacchi pronti per il ritiro: passa in sede quando preferisci.', $readyCount);
        } elseif ($pendingCount > 0) {
            $heroSubtitle = sprintf('Ci sono %d segnalazioni in attesa di aggiornamenti dal team Coresuite.', $pendingCount);
        } elseif ($totalPackages > 0) {
            $heroSubtitle = "Tieni d'occhio la tua area per scoprire quando i pacchi saranno pronti.";
        } else {
            $heroSubtitle = "Inizia segnalando il prossimo pacco: ti aggiorneremo in tempo reale sullo stato.";
        }

        $statusIconMap = [
            'reported' => 'fa-clipboard-list',
            'confirmed' => 'fa-circle-check',
            'arrived' => 'fa-box-open',
            'cancelled' => 'fa-ban',
            'in_arrivo' => 'fa-truck-loading',
            'consegnato' => 'fa-boxes-stacked',
            'ritirato' => 'fa-hand-holding-box',
            'in_giacenza' => 'fa-warehouse',
            'in_giacenza_scaduto' => 'fa-triangle-exclamation'
        ];
        ?>

        <section class="dashboard-hero">
            <div class="dashboard-hero-content">
                <span class="dashboard-hero-pill">Aggiornamento operativo</span>
                <h1 class="dashboard-hero-title">Bentornato <?= htmlspecialchars($customer['name'] ?? $customer['email'] ?? 'Cliente') ?> 👋</h1>
                <p class="dashboard-hero-subtitle"><?= htmlspecialchars($heroSubtitle) ?></p>

                <div class="dashboard-hero-actions">
                    <button type="button" class="btn btn-light dashboard-hero-btn" data-bs-toggle="modal" data-bs-target="#reportPackageModal">
                        <i class="fa-solid fa-plus"></i>
                        Segnala pacco
                    </button>
                    <a class="btn btn-outline-light dashboard-hero-btn" href="packages.php">
                        <i class="fa-solid fa-boxes-stacked"></i>
                        Vedi tutti i pacchi
                    </a>
                    <a class="btn btn-outline-light dashboard-hero-btn" href="notifications.php">
                        <i class="fa-solid fa-bell"></i>
                        Notifiche
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge bg-danger-subtle text-danger-emphasis ms-2"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
            <div class="dashboard-hero-stats">
                <div class="dashboard-hero-stat">
                    <span class="dashboard-hero-stat-label">Pronti al ritiro</span>
                    <span class="dashboard-hero-stat-value"><?= number_format($readyCount, 0, ',', '.') ?></span>
                    <span class="dashboard-hero-stat-hint">Aggiornato <?= date('d/m') ?></span>
                </div>
                <div class="dashboard-hero-stat">
                    <span class="dashboard-hero-stat-label">Segnalazioni aperte</span>
                    <span class="dashboard-hero-stat-value"><?= number_format($pendingCount, 0, ',', '.') ?></span>
                    <span class="dashboard-hero-stat-hint">Monitorate dal team</span>
                </div>
                <div class="dashboard-hero-stat">
                    <span class="dashboard-hero-stat-label">Ritirati nel mese</span>
                    <span class="dashboard-hero-stat-value"><?= number_format($monthlyCount, 0, ',', '.') ?></span>
                    <span class="dashboard-hero-stat-hint">Ultimi 30 giorni</span>
                </div>
                <div class="dashboard-hero-stat">
                    <span class="dashboard-hero-stat-label">Totale pacchi</span>
                    <span class="dashboard-hero-stat-value"><?= number_format($totalPackages, 0, ',', '.') ?></span>
                    <span class="dashboard-hero-stat-hint">Dal primo accesso</span>
                </div>
            </div>
        </section>

        <section class="dashboard-quick-actions">
            <a class="dashboard-action-card" href="report.php">
                <span class="dashboard-action-icon" data-variant="primary"><i class="fa-solid fa-clipboard"></i></span>
                <div class="dashboard-action-content">
                    <span class="dashboard-action-label">Nuova segnalazione</span>
                    <p>Registra un nuovo pacco per iniziare il monitoraggio.</p>
                </div>
                <i class="fa-solid fa-arrow-right dashboard-action-arrow"></i>
            </a>
            <a class="dashboard-action-card" href="packages.php">
                <span class="dashboard-action-icon" data-variant="success"><i class="fa-solid fa-box"></i></span>
                <div class="dashboard-action-content">
                    <span class="dashboard-action-label">Situazione pacchi</span>
                    <p>Consulta lo stato dettagliato e scarica i documenti.</p>
                </div>
                <i class="fa-solid fa-arrow-right dashboard-action-arrow"></i>
            </a>
            <a class="dashboard-action-card" href="notifications.php">
                <span class="dashboard-action-icon" data-variant="warning"><i class="fa-solid fa-bell"></i></span>
                <div class="dashboard-action-content">
                    <span class="dashboard-action-label">Centro notifiche</span>
                    <p>Rivedi gli avvisi recenti e segna come letti quelli gestiti.</p>
                </div>
                <i class="fa-solid fa-arrow-right dashboard-action-arrow"></i>
            </a>
        </section>

        <section class="dashboard-metrics">
            <article class="portal-stat-card">
                <div class="portal-stat-card-head">
                    <span class="portal-stat-icon"><i class="fa-solid fa-box-open"></i></span>
                    <span class="badge bg-light text-primary">In attesa</span>
                </div>
                <div class="portal-stat-card-body">
                    <span class="portal-stat-value"><?= number_format($pendingCount, 0, ',', '.') ?></span>
                    <p>Pacchi segnalati ma non ancora arrivati al punto Pickup.</p>
                </div>
            </article>
            <article class="portal-stat-card">
                <div class="portal-stat-card-head">
                    <span class="portal-stat-icon"><i class="fa-solid fa-truck-ramp-box"></i></span>
                    <span class="badge bg-light text-success">Pronti</span>
                </div>
                <div class="portal-stat-card-body">
                    <span class="portal-stat-value"><?= number_format($readyCount, 0, ',', '.') ?></span>
                    <p>Puntiamo a consegnarli entro 48 ore: riceverai un avviso appena disponibili.</p>
                </div>
            </article>
            <article class="portal-stat-card">
                <div class="portal-stat-card-head">
                    <span class="portal-stat-icon"><i class="fa-solid fa-calendar-check"></i></span>
                    <span class="badge bg-light text-info">Ultimi 30 giorni</span>
                </div>
                <div class="portal-stat-card-body">
                    <span class="portal-stat-value"><?= number_format($monthlyCount, 0, ',', '.') ?></span>
                    <p>Pacchi ritirati recentemente dal tuo team operativo.</p>
                </div>
            </article>
            <article class="portal-stat-card">
                <div class="portal-stat-card-head">
                    <span class="portal-stat-icon"><i class="fa-solid fa-circle-exclamation"></i></span>
                    <span class="badge bg-light text-warning">Follow-up</span>
                </div>
                <div class="portal-stat-card-body">
                    <span class="portal-stat-value"><?= number_format($reportsCount, 0, ',', '.') ?></span>
                    <p>Segnalazioni con attività aperta: ti aggiorniamo appena ci sono novità.</p>
                </div>
            </article>
        </section>

        <section class="dashboard-grid">
            <div class="dashboard-column">
                <div class="card dashboard-panel">
                    <div class="card-header">
                        <div class="dashboard-panel-heading">
                            <div>
                                <h2 class="dashboard-panel-title">Ultimi pacchi</h2>
                                <p class="dashboard-panel-subtitle">Monitoriamo gli aggiornamenti in tempo reale</p>
                            </div>
                            <a class="btn btn-sm btn-outline-primary" href="packages.php">Vai all'elenco</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentPackages)): ?>
                            <div class="dashboard-empty">
                                <span class="dashboard-empty-icon"><i class="fa-solid fa-box"></i></span>
                                <h3>Nessun pacco registrato</h3>
                                <p>Segnala il primo pacco per iniziare a ricevere notifiche e aggiornamenti sul ritiro.</p>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reportPackageModal">
                                    <i class="fa-solid fa-plus me-2"></i>Segnala ora
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="dashboard-list">
                                <?php foreach ($recentPackages as $package): ?>
                                    <?php
                                    $status = $package['status'] ?? 'reported';
                                    $statusIcon = $statusIconMap[$status] ?? 'fa-box';
                                    ?>
                                    <article class="dashboard-list-item">
                                        <span class="dashboard-list-icon" data-status="<?= htmlspecialchars($status) ?>">
                                            <i class="fa-solid <?= $statusIcon ?>"></i>
                                        </span>
                                        <div class="dashboard-list-content">
                                            <div class="dashboard-list-header">
                                                <span class="dashboard-list-title"><?= htmlspecialchars($package['tracking_code']) ?></span>
                                                <span class="dashboard-list-badge"><?= $pickupService->getStatusBadge($status) ?></span>
                                            </div>
                                            <div class="dashboard-list-meta">
                                                <span><i class="fa-solid fa-user"></i><?= htmlspecialchars($package['recipient_name'] ?: 'Destinatario non indicato') ?></span>
                                                <span><i class="fa-solid fa-truck"></i><?= htmlspecialchars($package['courier_name'] ?? 'Corriere N/D') ?></span>
                                                <span><i class="fa-regular fa-clock"></i><?= date('d/m H:i', strtotime($package['created_at'])) ?></span>
                                            </div>
                                        </div>
                                        <div class="dashboard-list-actions">
                                            <a class="btn btn-sm btn-outline-primary" href="packages.php?view=<?= (int) $package['id'] ?>">Dettagli</a>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card dashboard-panel">
                    <div class="card-header">
                        <div class="dashboard-panel-heading">
                            <div>
                                <h2 class="dashboard-panel-title">Segnalazioni aperte</h2>
                                <p class="dashboard-panel-subtitle">Pacchi che richiedono ancora un intervento</p>
                            </div>
                            <span class="badge bg-primary-subtle text-primary-emphasis"><?= $reportsCount ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($reportsCount === 0): ?>
                            <div class="dashboard-empty dashboard-empty-compact">
                                <span class="dashboard-empty-icon"><i class="fa-solid fa-circle-check"></i></span>
                                <p>Tutte le segnalazioni risultano gestite. Ti avviseremo se dovessero presentarsi anomalie.</p>
                            </div>
                        <?php else: ?>
                            <ul class="dashboard-report-list">
                                <?php foreach ($pendingReports as $report): ?>
                                    <li>
                                        <div>
                                            <strong><?= htmlspecialchars($report['tracking_code']) ?></strong>
                                            <span class="badge bg-warning-subtle text-warning-emphasis ms-2">In attesa</span>
                                        </div>
                                        <div class="dashboard-report-meta">
                                            <span><i class="fa-regular fa-calendar"></i><?= date('d/m/Y', strtotime($report['created_at'])) ?></span>
                                            <?php if (!empty($report['notes'])): ?>
                                                <span><i class="fa-regular fa-note-sticky"></i><?= htmlspecialchars($report['notes']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="dashboard-column dashboard-column-side">
                <div class="card dashboard-panel">
                    <div class="card-header">
                        <div class="dashboard-panel-heading">
                            <div>
                                <h2 class="dashboard-panel-title">Notifiche recenti</h2>
                                <p class="dashboard-panel-subtitle">Aggiornamenti e promemoria dal sistema</p>
                            </div>
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge bg-danger-subtle text-danger-emphasis"><?= $unreadCount ?> nuove</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentNotifications)): ?>
                            <div class="dashboard-empty dashboard-empty-compact">
                                <span class="dashboard-empty-icon"><i class="fa-regular fa-bell"></i></span>
                                <p>Zero notifiche pendenti. Puoi sempre rivedere la cronologia completa dalla sezione dedicata.</p>
                            </div>
                        <?php else: ?>
                            <div class="dashboard-list dashboard-list-compact">
                                <?php foreach ($recentNotifications as $notification): ?>
                                    <article class="dashboard-list-item">
                                        <span class="dashboard-list-icon" data-status="notification">
                                            <i class="fa-solid fa-<?= $pickupService->getNotificationIcon($notification['type']) ?>"></i>
                                        </span>
                                        <div class="dashboard-list-content">
                                            <div class="dashboard-list-header">
                                                <span class="dashboard-list-title"><?= htmlspecialchars($notification['title']) ?></span>
                                                <span class="dashboard-list-time"><?= date('d/m H:i', strtotime($notification['created_at'])) ?></span>
                                            </div>
                                            <p class="dashboard-list-text"><?= htmlspecialchars($notification['message']) ?></p>
                                        </div>
                                        <div class="dashboard-list-actions">
                                            <a class="btn btn-sm btn-outline-secondary" href="notifications.php#<?= (int) $notification['id'] ?>">Apri</a>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a class="btn btn-sm btn-outline-primary" href="notifications.php">Vai al centro notifiche</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card dashboard-panel">
                    <div class="card-header">
                        <h2 class="dashboard-panel-title mb-0">Supporto operativo</h2>
                    </div>
                    <div class="card-body">
                        <p class="mb-3">Hai bisogno di assistenza rapida o vuoi segnalare un problema urgente?</p>
                        <ul class="dashboard-support-list">
                            <li>
                                <span class="dashboard-support-icon"><i class="fa-solid fa-headset"></i></span>
                                <div>
                                    <strong>Helpdesk Pickup</strong>
                                    <a href="mailto:support@coresuite.it">support@coresuite.it</a>
                                    <small>Risposta entro 4 ore lavorative</small>
                                </div>
                            </li>
                            <li>
                                <span class="dashboard-support-icon"><i class="fa-solid fa-phone"></i></span>
                                <div>
                                    <strong>Linea operativa</strong>
                                    <a href="tel:+3903311589468">+39 0331 158 9468</a>
                                    <small>Lun-Ven · 09:00-18:00</small>
                                </div>
                            </li>
                            <li>
                                <span class="dashboard-support-icon"><i class="fa-solid fa-circle-info"></i></span>
                                <div>
                                    <strong>Documentazione rapida</strong>
                                    <a href="help.php">Guide e FAQ</a>
                                    <small>Procedure passo-passo per il tuo team</small>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>
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
                    window.PickupPortal?.showAlert?.(data.message || 'Errore durante la segnalazione', 'danger');
                    return;
                }

                const modalElement = document.getElementById('reportPackageModal');
                const modalInstance = bootstrap.Modal.getInstance(modalElement);
                modalInstance?.hide();

                reportForm.reset();
                window.PickupPortal?.showAlert?.('Segnalazione registrata correttamente. Aggiorniamo i dati...', 'success');

                // Ricarichiamo i dati per mostrare il nuovo pacco nella lista.
                setTimeout(() => window.location.reload(), 1200);
            })
            .catch(() => {
                window.PickupPortal?.showAlert?.('Impossibile completare la richiesta in questo momento.', 'danger');
            });
    });
});
</script>

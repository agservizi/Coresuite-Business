<?php
$username = current_user_display_name();
$role = $_SESSION['role'] ?? '';
$notifications = [];
$notificationsCount = 0;
$notificationsUpdatedLabel = format_datetime_locale(date('Y-m-d H:i:s'));

if (isset($pdo) && $pdo instanceof PDO) {
    require_once __DIR__ . '/notifications.php';
    try {
        $notifications = fetch_global_notifications($pdo, 8);
    } catch (Throwable $exception) {
        error_log('Topbar notifications load failed: ' . $exception->getMessage());
    }
}

$notificationsCount = count($notifications);
?>
<header class="topbar border-bottom sticky-top">
    <div class="container-fluid">
        <div class="topbar-toolbar">
            <div class="topbar-left">
                <button class="btn topbar-btn topbar-btn-icon d-lg-none" type="button" id="sidebarMobileToggle" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Apri menu laterale">
                    <i class="fa-solid fa-bars" aria-hidden="true"></i>
                </button>
                <button class="btn topbar-btn topbar-btn-icon d-none d-lg-inline-flex" type="button" id="sidebarToggle" aria-label="Riduci barra laterale" aria-expanded="true">
                    <i class="fa-solid fa-angles-left" aria-hidden="true"></i>
                </button>
                <div class="dropdown topbar-notifications" data-notifications-root data-notifications-endpoint="<?php echo base_url('api/notifications.php'); ?>" data-refresh-interval="60000" data-limit="8">
                    <button class="btn topbar-btn topbar-btn-icon position-relative" type="button" id="topbarNotificationsToggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-haspopup="true" aria-expanded="false" aria-label="Apri notifiche" data-notifications-toggle>
                        <i class="fa-solid fa-bell" aria-hidden="true"></i>
                        <span class="visually-hidden">Notifiche</span>
                        <span class="topbar-notifications-badge badge rounded-pill bg-danger" data-notifications-badge <?php echo $notificationsCount > 0 ? '' : 'hidden'; ?>>
                            <?php echo $notificationsCount > 99 ? '99+' : (string) $notificationsCount; ?>
                        </span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end topbar-dropdown topbar-notifications-menu" aria-labelledby="topbarNotificationsToggle">
                        <div class="topbar-notifications-header d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">Notifiche</span>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-light text-dark topbar-notifications-total" data-notifications-total <?php echo $notificationsCount > 0 ? '' : 'hidden'; ?>>
                                    <?php echo (string) $notificationsCount; ?>
                                </span>
                                <button class="btn btn-link btn-sm p-0 topbar-notifications-refresh" type="button" data-notifications-refresh>Aggiorna</button>
                            </div>
                        </div>
                        <div class="topbar-notifications-body mt-2" data-notifications-list>
                            <?php if ($notificationsCount === 0): ?>
                                <div class="topbar-notifications-empty text-muted small" data-notifications-empty>Nessuna notifica disponibile.</div>
                            <?php else: ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <?php $isLink = !empty($notification['url']); ?>
                                    <<?php echo $isLink ? 'a' : 'div'; ?>
                                        class="topbar-notification-item"
                                        <?php if ($isLink): ?>href="<?php echo sanitize_output($notification['url']); ?>"<?php endif; ?>
                                        data-notification-id="<?php echo sanitize_output($notification['id']); ?>">
                                        <span class="topbar-notification-icon">
                                            <i class="fa-solid <?php echo sanitize_output($notification['icon']); ?>" aria-hidden="true"></i>
                                        </span>
                                        <span class="topbar-notification-content">
                                            <span class="topbar-notification-title"><?php echo sanitize_output($notification['title']); ?></span>
                                            <?php if (!empty($notification['message'])): ?>
                                                <span class="topbar-notification-message"><?php echo sanitize_output($notification['message']); ?></span>
                                            <?php endif; ?>
                                            <?php
                                            $metaParts = [];
                                            if (!empty($notification['meta'])) {
                                                $metaParts[] = (string) $notification['meta'];
                                            }
                                            if (!empty($notification['timeLabel'])) {
                                                $metaParts[] = (string) $notification['timeLabel'];
                                            }
                                            ?>
                                            <?php if ($metaParts): ?>
                                                <span class="topbar-notification-meta"><?php echo sanitize_output(implode(' | ', $metaParts)); ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </<?php echo $isLink ? 'a' : 'div'; ?>>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="topbar-notifications-footer mt-2 small text-muted" data-notifications-updated>
                            Aggiornato <?php echo sanitize_output($notificationsUpdatedLabel); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="topbar-actions">
                <?php if ($role !== 'Cliente'): ?>
                    <div class="topbar-quick-actions d-none d-md-flex">
                        <a class="btn topbar-btn topbar-btn-action" href="<?php echo base_url('modules/clienti/create.php'); ?>" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-title="Nuovo cliente" data-bs-tooltip="global" aria-label="Crea un nuovo cliente">
                            <i class="fa-solid fa-user-plus topbar-btn-icon-lead" aria-hidden="true"></i>
                            <span class="topbar-btn-label d-none d-xxl-inline">Nuovo cliente</span>
                        </a>
                        <a class="btn topbar-btn topbar-btn-action" href="<?php echo base_url('modules/ticket/create.php'); ?>" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-title="Nuovo ticket" data-bs-tooltip="global" aria-label="Apri un nuovo ticket">
                            <i class="fa-solid fa-ticket topbar-btn-icon-lead" aria-hidden="true"></i>
                            <span class="topbar-btn-label d-none d-xxl-inline">Nuovo ticket</span>
                        </a>
                        <a class="btn topbar-btn topbar-btn-action" href="<?php echo base_url('modules/documenti/create.php'); ?>" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-title="Carica documento" data-bs-tooltip="global" aria-label="Carica un nuovo documento">
                            <i class="fa-solid fa-upload topbar-btn-icon-lead" aria-hidden="true"></i>
                            <span class="topbar-btn-label d-none d-xxl-inline">Carica documento</span>
                        </a>
                    </div>
                    <div class="dropdown d-md-none">
                        <button class="btn topbar-btn topbar-btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Azioni rapide">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo base_url('modules/clienti/create.php'); ?>"><i class="fa-solid fa-user-plus me-2"></i>Nuovo cliente</a></li>
                            <li><a class="dropdown-item" href="<?php echo base_url('modules/ticket/create.php'); ?>"><i class="fa-solid fa-ticket me-2"></i>Nuovo ticket</a></li>
                            <li><a class="dropdown-item" href="<?php echo base_url('modules/documenti/create.php'); ?>"><i class="fa-solid fa-upload me-2"></i>Carica documento</a></li>
                        </ul>
                    </div>
                <?php endif; ?>
                <div class="dropdown">
                    <button class="btn topbar-btn topbar-btn-user dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-user-circle topbar-btn-icon-lead" aria-hidden="true"></i>
                        <span class="topbar-btn-label"><?php echo sanitize_output($username); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end topbar-dropdown">
                        <li class="dropdown-header">
                            <span class="text-muted small">Ruolo</span>
                            <div class="fw-semibold text-capitalize"><?php echo sanitize_output($role); ?></div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo base_url('modules/impostazioni/profile.php'); ?>"><i class="fa-solid fa-id-badge me-2"></i>Profilo</a></li>
                        <li><a class="dropdown-item" href="<?php echo base_url('logout.php'); ?>"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</header>

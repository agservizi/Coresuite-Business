    <footer class="portal-footer mt-auto py-4 border-top">
        <div class="container-fluid">
            <div class="row align-items-center gy-3">
                <div class="col-md-6">
                    <span class="fw-semibold text-primary">Pickup Portal</span>
                    <p class="text-muted-soft small mb-0">Gestisci i tuoi ritiri con la piattaforma clienti Coresuite Business.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="help.php" class="text-muted-soft text-decoration-none me-3"><i class="fa-solid fa-circle-question me-1"></i>Aiuto</a>
                    <a href="privacy.php" class="text-muted-soft text-decoration-none me-3"><i class="fa-solid fa-shield-halved me-1"></i>Privacy</a>
                    <a href="mailto:support@coresuite.it" class="text-muted-soft text-decoration-none"><i class="fa-solid fa-envelope me-1"></i>Supporto</a>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12 d-flex justify-content-between flex-wrap small text-muted-soft">
                    <span>© <?= date('Y') ?> Coresuite Business · Pickup Portal v<?= portal_config('portal_version') ?></span>
                    <span><i class="fa-solid fa-shield-halved me-1"></i>Connessione protetta</span>
                </div>
            </div>
        </div>
    </footer>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="assets/js/portal.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (window.portalConfig && Number(window.portalConfig.customerId) > 0) {
        loadNotifications();
        setInterval(loadNotifications, 60000);
    }
});

function loadNotifications() {
    fetch('api/notifications.php?unread=1')
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                return;
            }
            updateNotificationBadge(data.count || 0);
            updateNotificationDropdown(data.notifications || []);
        })
        .catch((error) => {
            console.error('Error loading notifications:', error);
        });
}

function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationCount');
    if (!badge) {
        return;
    }
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'inline-flex';
    } else {
        badge.style.display = 'none';
    }
}

function updateNotificationDropdown(notifications) {
    const list = document.getElementById('notificationList');
    if (!list) {
        return;
    }
    if (notifications.length === 0) {
        list.innerHTML = '<li><span class="dropdown-item-text text-muted">Nessuna notifica</span></li>';
        return;
    }
    list.innerHTML = notifications.slice(0, 5).map((notification) => `
        <li>
            <a class="dropdown-item notification-item" href="notifications.php#${notification.id}">
                <div class="d-flex">
                    <div class="me-3 pt-1">
                        <i class="fa-solid fa-${getNotificationIcon(notification.type)} text-primary"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold text-truncate">${notification.title}</div>
                        <div class="text-muted small text-truncate-2">${notification.message}</div>
                        <div class="text-muted small">${formatDate(notification.created_at)}</div>
                    </div>
                </div>
            </a>
        </li>
    `).join('');
}

function getNotificationIcon(type) {
    const icons = {
        package_arrived: 'box',
        package_ready: 'check-circle',
        package_reminder: 'clock',
        package_expired: 'triangle-exclamation',
        system_message: 'info-circle'
    };
    return icons[type] || 'bell';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) {
        return 'Ora';
    }
    if (diffMins < 60) {
        return `${diffMins}m fa`;
    }
    if (diffHours < 24) {
        return `${diffHours}h fa`;
    }
    if (diffDays < 7) {
        return `${diffDays}g fa`;
    }
    return date.toLocaleDateString('it-IT');
}
</script>
</body>
</html>
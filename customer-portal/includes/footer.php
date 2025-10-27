    <!-- Footer -->
    <footer class="bg-white border-top mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="fw-bold text-primary">Pickup Portal</h6>
                    <p class="text-muted small mb-0">
                        La soluzione semplice per gestire i tuoi pacchi
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i>
                        I tuoi dati sono protetti e sicuri
                    </small>
                    <div class="mt-2">
                        <a href="help.php" class="text-decoration-none text-muted me-3">
                            <i class="fas fa-question-circle me-1"></i> Aiuto
                        </a>
                        <a href="privacy.php" class="text-decoration-none text-muted me-3">
                            <i class="fas fa-shield-alt me-1"></i> Privacy
                        </a>
                        <a href="mailto:support@coresuite.it" class="text-decoration-none text-muted">
                            <i class="fas fa-envelope me-1"></i> Contatti
                        </a>
                    </div>
                </div>
            </div>
            <hr class="my-3">
            <div class="row">
                <div class="col-12 text-center">
                    <small class="text-muted">
                        © <?= date('Y') ?> Coresuite Business - Pickup Portal v<?= portal_config('portal_version') ?>
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="assets/js/portal.js"></script>
    
    <!-- Caricamento notifiche in tempo reale -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Carica notifiche periodicamente
        if (window.portalConfig && window.portalConfig.customerId > 0) {
            loadNotifications();
            setInterval(loadNotifications, 60000); // Ogni minuto
        }
    });

    function loadNotifications() {
        fetch('api/notifications.php?unread=1')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateNotificationBadge(data.count);
                    updateNotificationDropdown(data.notifications);
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
            });
    }

    function updateNotificationBadge(count) {
        const badge = document.getElementById('notificationCount');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }
    }

    function updateNotificationDropdown(notifications) {
        const list = document.getElementById('notificationList');
        if (!list) return;

        if (notifications.length === 0) {
            list.innerHTML = '<li><span class="dropdown-item-text text-muted">Nessuna notifica</span></li>';
            return;
        }

        list.innerHTML = notifications.slice(0, 5).map(notification => `
            <li>
                <a class="dropdown-item notification-item" href="notifications.php#${notification.id}">
                    <div class="d-flex">
                        <div class="me-2">
                            <i class="fas fa-${getNotificationIcon(notification.type)} text-primary"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-bold text-truncate" style="max-width: 200px;">${notification.title}</div>
                            <div class="text-muted small text-truncate" style="max-width: 200px;">${notification.message}</div>
                            <div class="text-muted small">${formatDate(notification.created_at)}</div>
                        </div>
                    </div>
                </a>
            </li>
        `).join('');
    }

    function getNotificationIcon(type) {
        const icons = {
            'package_arrived': 'box',
            'package_ready': 'check-circle',
            'package_reminder': 'clock',
            'package_expired': 'exclamation-triangle',
            'system_message': 'info-circle'
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

        if (diffMins < 1) return 'Ora';
        if (diffMins < 60) return `${diffMins}m fa`;
        if (diffHours < 24) return `${diffHours}h fa`;
        if (diffDays < 7) return `${diffDays}g fa`;
        
        return date.toLocaleDateString('it-IT');
    }
    </script>
</body>
</html>
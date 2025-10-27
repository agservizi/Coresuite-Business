<!-- Sidebar per layout con menu laterale -->
<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-white sidebar border-end">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'packages.php' ? 'active' : '' ?>" href="packages.php">
                    <i class="fas fa-boxes me-2"></i>
                    I Miei Pacchi
                    <?php if (isset($stats) && $stats['ready_packages'] > 0): ?>
                    <span class="badge bg-success ms-2"><?= $stats['ready_packages'] ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'report.php' ? 'active' : '' ?>" href="report.php">
                    <i class="fas fa-plus me-2"></i>
                    Segnala Pacco
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'notifications.php' ? 'active' : '' ?>" href="notifications.php">
                    <i class="fas fa-bell me-2"></i>
                    Notifiche
                    <span class="notification-count-sidebar badge bg-primary ms-2" style="display: none;">0</span>
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Account</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>" href="profile.php">
                    <i class="fas fa-user me-2"></i>
                    Il Mio Profilo
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>" href="settings.php">
                    <i class="fas fa-cog me-2"></i>
                    Impostazioni
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'help.php' ? 'active' : '' ?>" href="help.php">
                    <i class="fas fa-question-circle me-2"></i>
                    Aiuto
                </a>
            </li>
        </ul>

        <hr>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link text-danger" href="logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    Esci
                </a>
            </li>
        </ul>

        <!-- Info rapide -->
        <div class="mt-4 p-3 bg-light rounded">
            <h6 class="text-muted small">Info Rapide</h6>
            <div class="d-flex justify-content-between small">
                <span>Pacchi in attesa:</span>
                <span class="fw-bold text-warning" id="quick-pending">-</span>
            </div>
            <div class="d-flex justify-content-between small">
                <span>Pronti al ritiro:</span>
                <span class="fw-bold text-success" id="quick-ready">-</span>
            </div>
            <div class="d-flex justify-content-between small">
                <span>Questo mese:</span>
                <span class="fw-bold text-info" id="quick-monthly">-</span>
            </div>
        </div>
    </div>
</nav>

<script>
// Aggiorna i contatori nella sidebar
function updateSidebarCounters() {
    fetch('api/stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('quick-pending').textContent = data.stats.pending_packages || 0;
                document.getElementById('quick-ready').textContent = data.stats.ready_packages || 0;
                document.getElementById('quick-monthly').textContent = data.stats.monthly_delivered || 0;
                
                // Aggiorna badge notifiche in sidebar
                const sidebarBadge = document.querySelector('.notification-count-sidebar');
                if (data.stats.unread_notifications > 0) {
                    sidebarBadge.textContent = data.stats.unread_notifications;
                    sidebarBadge.style.display = 'inline';
                } else {
                    sidebarBadge.style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.error('Error updating sidebar counters:', error);
        });
}

// Aggiorna contatori all'avvio e periodicamente
document.addEventListener('DOMContentLoaded', function() {
    updateSidebarCounters();
    setInterval(updateSidebarCounters, 120000); // Ogni 2 minuti
});
</script>
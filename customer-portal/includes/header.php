<?php
// Header per il portale cliente
if (!isset($customer)) {
    $customer = CustomerAuth::getAuthenticatedCustomer();
}

$pageTitle = $pageTitle ?? 'Pickup Portal';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Pickup Portal</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="assets/css/portal.css" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
    
    <!-- Meta tags -->
    <meta name="description" content="Gestisci i tuoi pacchi con il Pickup Portal">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0d6efd">
</head>
<body class="bg-light">

<!-- Top Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="fas fa-box-open me-2"></i>
            Pickup Portal
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'packages.php' ? 'active' : '' ?>" href="packages.php">
                        <i class="fas fa-boxes me-1"></i> I Miei Pacchi
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'report.php' ? 'active' : '' ?>" href="report.php">
                        <i class="fas fa-plus me-1"></i> Segnala Pacco
                    </a>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationCount" style="display: none;">
                            0
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end notification-dropdown">
                        <li><h6 class="dropdown-header">Notifiche</h6></li>
                        <div id="notificationList">
                            <li><span class="dropdown-item-text text-muted">Caricamento...</span></li>
                        </div>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="notifications.php">Vedi tutte</a></li>
                    </ul>
                </li>
                
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i>
                        <?= htmlspecialchars($customer['name'] ?? $customer['email'] ?? 'Cliente') ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php">
                            <i class="fas fa-user me-2"></i> Il Mio Profilo
                        </a></li>
                        <li><a class="dropdown-item" href="settings.php">
                            <i class="fas fa-cog me-2"></i> Impostazioni
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="help.php">
                            <i class="fas fa-question-circle me-2"></i> Aiuto
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Esci
                        </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Alert container per messaggi globali -->
<div id="global-alert-container" class="container-fluid mt-3" style="display: none;">
    <div class="row">
        <div class="col-12">
            <div id="global-alert" class="alert alert-dismissible fade show" role="alert">
                <span id="global-alert-message"></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    </div>
</div>

<script>
// Variabili globali per JavaScript
window.portalConfig = {
    csrfToken: '<?= htmlspecialchars(get_csrf_token()) ?>',
    customerId: <?= $customer['id'] ?? 0 ?>,
    apiBaseUrl: 'api/',
    currentPage: '<?= basename($_SERVER['PHP_SELF']) ?>'
};
</script>
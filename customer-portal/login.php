<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Se già autenticato, reindirizza alla dashboard
if (CustomerAuth::isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}

// Gestione errori/messaggi dalla query string
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accesso - Pickup Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/portal.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid vh-100 d-flex align-items-center justify-content-center">
        <div class="row w-100">
            <div class="col-md-6 col-lg-4 mx-auto">
                <div class="card shadow-lg border-0 rounded-3">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-box-open fa-3x text-primary mb-3"></i>
                            <h2 class="fw-bold text-primary">Pickup Portal</h2>
                            <p class="text-muted">Gestisci i tuoi pacchi in modo semplice</p>
                        </div>

                        <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($message): ?>
                        <div class="alert alert-info" role="alert">
                            <i class="fas fa-info-circle me-2"></i>
                            <?= htmlspecialchars($message) ?>
                        </div>
                        <?php endif; ?>

                        <div id="login-form" class="login-step active">
                            <form id="loginForm">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control form-control-lg" id="email" name="email" 
                                           placeholder="La tua email">
                                </div>
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Telefono (opzionale)</label>
                                    <input type="tel" class="form-control form-control-lg" id="phone" name="phone" 
                                           placeholder="+39 123 456 7890">
                                </div>
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nome (opzionale)</label>
                                    <input type="text" class="form-control form-control-lg" id="name" name="name" 
                                           placeholder="Il tuo nome">
                                </div>
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-paper-plane me-2"></i>Invia Codice
                                </button>
                            </form>
                        </div>

                        <div id="otp-form" class="login-step">
                            <div class="text-center mb-4">
                                <i class="fas fa-mobile-alt fa-2x text-success mb-3"></i>
                                <h4>Inserisci il codice</h4>
                                <p class="text-muted" id="otp-destination-text">
                                    Abbiamo inviato un codice di 6 cifre
                                </p>
                            </div>
                            <form id="otpForm">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
                                <input type="hidden" id="customer_id" name="customer_id">
                                <div class="mb-3">
                                    <label for="otp" class="form-label text-center d-block">Codice di verifica</label>
                                    <input type="text" class="form-control form-control-lg text-center" id="otp" name="otp" 
                                           placeholder="000000" maxlength="6" style="letter-spacing: 0.5rem; font-size: 1.5rem;">
                                </div>
                                <button type="submit" class="btn btn-success btn-lg w-100 mb-3">
                                    <i class="fas fa-check me-2"></i>Verifica
                                </button>
                                <button type="button" class="btn btn-outline-secondary w-100" id="resendOtp">
                                    <i class="fas fa-redo me-2"></i>Invia di nuovo
                                </button>
                                <div class="text-center mt-3">
                                    <small class="text-muted">
                                        <span id="countdown-text"></span>
                                    </small>
                                </div>
                            </form>
                        </div>

                        <div id="loading" class="login-step text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Caricamento...</span>
                            </div>
                            <p class="mt-3 text-muted">Elaborazione in corso...</p>
                        </div>

                        <div class="text-center mt-4">
                            <small class="text-muted">
                                Hai problemi? <a href="mailto:support@coresuite.it" class="text-decoration-none">Contatta il supporto</a>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert container -->
    <div id="alert-container" class="position-fixed top-0 start-50 translate-middle-x" style="z-index: 9999; margin-top: 20px;"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/portal.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initializeLogin();
        });
    </script>
</body>
</html>
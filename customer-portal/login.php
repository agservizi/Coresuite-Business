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
<html lang="it" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accesso · Pickup Portal</title>
    <meta name="description" content="Accedi al Pickup Portal di Coresuite Business per monitorare i tuoi pacchi.">
    <meta name="theme-color" content="#0b2f6b">
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" referrerpolicy="no-referrer">
    <link href="assets/css/portal.css" rel="stylesheet">
</head>
<body class="login-body">
    <div class="login-shell">
        <div class="row g-0">
            <div class="col-lg-5 login-side-brand d-none d-lg-flex flex-column justify-content-between">
                <div>
                    <span class="badge rounded-pill">Coresuite Business</span>
                    <h1 class="fw-semibold mt-3 mb-4">Pickup Portal</h1>
                    <ul>
                        <li><i class="fa-solid fa-bell"></i><span>Notifiche in tempo reale sull'arrivo dei pacchi</span></li>
                        <li><i class="fa-solid fa-shield-halved"></i><span>Accesso sicuro con codice OTP</span></li>
                        <li><i class="fa-solid fa-calendar-check"></i><span>Storico ritiri sempre disponibile</span></li>
                    </ul>
                </div>
                <div class="login-meta">
                    <strong>Serve aiuto?</strong><br>
                    Scrivici a <a class="text-white" href="mailto:support@coresuite.it">support@coresuite.it</a>
                </div>
            </div>
            <div class="col-12 col-lg-7 login-form-area">
                <div class="mb-4 d-lg-none text-center">
                    <i class="fa-solid fa-box-open fa-2x text-primary mb-3"></i>
                    <h2 class="fw-semibold mb-1">Pickup Portal</h2>
                    <p class="text-muted-soft">Gestisci i tuoi pacchi in modo semplice e sicuro.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger border-0 shadow-sm" role="alert">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($message): ?>
                    <div class="alert alert-info border-0 shadow-sm" role="alert">
                        <i class="fa-solid fa-circle-info me-2"></i><?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <div id="login-form" class="login-step active">
                    <h2 class="h4 fw-semibold mb-3">Inserisci i tuoi dati</h2>
                    <p class="text-muted-soft mb-4">Riceverai un codice di verifica per accedere alla tua area riservata.</p>
                    <form id="loginForm" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
                        <div class="mb-3">
                            <label class="form-label" for="email">Email *</label>
                            <input class="form-control form-control-lg" id="email" name="email" type="email" placeholder="nome@azienda.it" required>
                            <div class="invalid-feedback">Inserisci un indirizzo email valido.</div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="phone">Telefono (opzionale)</label>
                                <input class="form-control form-control-lg" id="phone" name="phone" type="tel" placeholder="+39 123 456 7890">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="name">Nome (opzionale)</label>
                                <input class="form-control form-control-lg" id="name" name="name" type="text" placeholder="Il tuo nome">
                            </div>
                        </div>
                        <button class="btn btn-primary btn-lg w-100 mt-4" type="submit"><i class="fa-solid fa-paper-plane me-2"></i>Invia codice</button>
                    </form>
                </div>

                <div id="otp-form" class="login-step">
                    <div class="text-center mb-4">
                        <span class="badge bg-light text-primary mb-3">Verifica</span>
                        <h2 class="h4 fw-semibold mb-1">Controlla la tua casella</h2>
                        <p class="text-muted-soft mb-0" id="otp-destination-text">Abbiamo inviato un codice di 6 cifre</p>
                    </div>
                    <form id="otpForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
                        <input type="hidden" id="customer_id" name="customer_id">
                        <div class="mb-3">
                            <label class="form-label" for="otp">Codice di verifica</label>
                            <input class="form-control form-control-lg text-center" id="otp" name="otp" maxlength="6" placeholder="000000" style="letter-spacing: 0.5rem; font-size: 1.8rem;">
                            <div class="form-text">Inserisci il codice ricevuto via email o SMS.</div>
                        </div>
                        <button class="btn btn-success btn-lg w-100 mb-3" type="submit"><i class="fa-solid fa-check me-2"></i>Verifica e accedi</button>
                        <button class="btn btn-outline-secondary w-100" type="button" id="resendOtp"><i class="fa-solid fa-rotate me-2"></i>Invia un nuovo codice</button>
                        <div class="text-center mt-3">
                            <small class="text-muted"><span id="countdown-text"></span></small>
                        </div>
                    </form>
                </div>

                <div id="loading" class="login-step text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Caricamento...</span>
                    </div>
                    <p class="mt-3 text-muted">Elaborazione in corso...</p>
                </div>
            </div>
        </div>
    </div>

    <div id="alert-container" class="position-fixed top-0 start-50 translate-middle-x w-100 px-3" style="max-width: 420px; z-index: 1080; margin-top: 1.5rem;"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="assets/js/portal.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            initializeLogin();
        });
    </script>
</body>
</html>
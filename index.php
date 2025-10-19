<?php
use App\Security\SecurityAuditLogger;

session_start();
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/helpers.php';

if (isset($_SESSION['user_id'])) {
    redirect_by_role($_SESSION['role'] ?? '');
    exit;
}

$csrfToken = csrf_token();
$auditLogger = new SecurityAuditLogger($pdo);

$maxAttempts = 5;
$lockSeconds = 300;
$lockedUntil = $_SESSION['login_locked_until'] ?? 0;

$errors = [];
if ($lockedUntil > time()) {
    $remaining = $lockedUntil - time();
    $errors[] = 'Troppi tentativi. Riprova tra ' . ceil($remaining / 60) . ' minuti.';
} else {
    unset($_SESSION['login_locked_until']);
    $_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errors) {
    require_valid_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ipAddress = request_ip();
    $userAgent = request_user_agent();

    if ($username === '' || $password === '') {
        $errors[] = 'Inserisci username e password.';
        $auditLogger->logLoginAttempt(null, $username, false, $ipAddress, $userAgent, 'missing_credentials');
    } elseif (strlen($password) < 8) {
        $errors[] = 'La password deve contenere almeno 8 caratteri.';
        $auditLogger->logLoginAttempt(null, $username, false, $ipAddress, $userAgent, 'weak_password');
    } else {
        $stmt = $pdo->prepare('SELECT id, username, password, ruolo, email, theme_preference, nome, cognome FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Credenziali non valide.';
            $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
            if ($_SESSION['login_attempts'] >= $maxAttempts) {
                $_SESSION['login_locked_until'] = time() + $lockSeconds;
            }
            $auditLogger->logLoginAttempt($user['id'] ?? null, $username, false, $ipAddress, $userAgent, $_SESSION['login_attempts'] >= $maxAttempts ? 'locked' : null);
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['ruolo'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['first_name'] = $user['nome'] ?? '';
            $_SESSION['last_name'] = $user['cognome'] ?? '';
            $_SESSION['display_name'] = format_user_display_name($user['username'], $user['email'], $user['nome'] ?? null, $user['cognome'] ?? null);
            $_SESSION['theme_preference'] = $user['theme_preference'] ?? 'dark';
            $_SESSION['login_attempts'] = 0;
            unset($_SESSION['login_locked_until']);

            $auditLogger->logLoginAttempt($user['id'], $username, true, $ipAddress, $userAgent);
            redirect_by_role($user['ruolo']);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Coresuite Business - Login</title>
    <link href="<?php echo asset('assets/vendor/bootstrap/css/bootstrap.min.css'); ?>" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" integrity="sha512-yddCLT9gs7nE466+vWXTjhBMWrMUR3pvN8MPv+2MvmXrKE+g6u40qCMgfHdCqkfNNpJBWlAbIYW/W4v6dngc8g==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="<?php echo asset('assets/css/custom.css'); ?>" rel="stylesheet">
</head>
<body class="login-body" data-bs-theme="light">
    <main class="login-shell">
        <div class="row g-0">
            <div class="col-md-5 login-side-brand d-flex flex-column justify-content-between">
                <div>
                    <span class="badge rounded-pill px-3 py-2 mb-4">Coresuite Business</span>
                    <h1 class="display-6 fw-semibold mb-3">Connessioni più smart, decisioni più rapide.</h1>
                    <p class="text-secondary mb-4">Un'unica piattaforma per governare pipeline, documenti e assistenza clienti con insight in tempo reale e processi automatizzati.</p>
                    <ul class="mb-4">
                        <li><i class="fa-solid fa-chart-line"></i><span>Dashboard dinamiche e controllo metriche</span></li>
                        <li><i class="fa-solid fa-people-group"></i><span>Gestione team e workflow condivisi</span></li>
                        <li><i class="fa-solid fa-shield-halved"></i><span>Sicurezza enterprise con audit completo</span></li>
                    </ul>
                </div>
                <div class="login-meta">
                    &copy; <?php echo date('Y'); ?> Coresuite Business · Via Plinio 72 · support@coresuitebusiness.com
                </div>
            </div>
            <div class="col-md-7 login-form-area">
                <div class="mb-4 text-center text-md-start">
                    <h2 class="h4 fw-semibold mb-2">Accedi al tuo workspace</h2>
                    <p class="login-meta mb-0">Hai bisogno di assistenza? <a class="link-warning text-decoration-none" href="forgot_password.php">Recupera l'accesso</a>.</p>
                </div>
                <?php if ($errors): ?>
                    <div class="alert alert-danger border-0 shadow-sm mb-4" role="alert">
                        <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
                    </div>
                <?php endif; ?>
                <form method="post" novalidate>
                    <input type="hidden" name="_token" value="<?php echo $csrfToken; ?>">
                    <div class="mb-4">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                            <input type="text" class="form-control" id="username" name="username" required autocomplete="username" placeholder="es. nome.cognome">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                            <button class="btn btn-outline-warning" type="button" id="togglePassword" aria-label="Mostra password"><i class="fa-solid fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-center mb-5">
                        <div class="form-check m-0">
                            <input class="form-check-input" type="checkbox" value="1" id="rememberMe" name="remember">
                            <label class="form-check-label" for="rememberMe">Mantieni l'accesso su questo dispositivo</label>
                        </div>
                        <a class="link-warning text-decoration-none" href="forgot_password.php">Hai dimenticato la password?</a>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-warning fw-semibold">Entra in Coresuite Business</button>
                    </div>
                </form>
                <div class="login-meta mt-5">
                    Accesso riservato al personale autorizzato. Ogni attività viene registrata per motivi di sicurezza e compliance.
                </div>
            </div>
        </div>
    </main>
    <script src="<?php echo asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js'); ?>"></script>
    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordField = document.getElementById('password');
        togglePassword?.addEventListener('click', () => {
            const isPassword = passwordField.getAttribute('type') === 'password';
            passwordField.setAttribute('type', isPassword ? 'text' : 'password');
            togglePassword.querySelector('i')?.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>

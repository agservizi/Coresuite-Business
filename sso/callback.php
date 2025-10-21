<?php
declare(strict_types=1);

use App\Auth\OidcAuthenticator;
use App\Security\SecurityAuditLogger;
use Throwable;

session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/helpers.php';

if (!function_exists('base_url')) {
    http_response_code(500);
    exit('Funzioni di supporto mancanti.');
}

$authenticator = new OidcAuthenticator($pdo);
if (!$authenticator->isEnabled()) {
    header('Location: ' . base_url('index.php'));
    exit;
}

$auditLogger = new SecurityAuditLogger($pdo);

try {
    $result = $authenticator->completeAuthentication();
} catch (Throwable $exception) {
    error_log('OIDC authentication failed: ' . $exception->getMessage());
    $_SESSION['sso_error'] = 'Accesso SSO non disponibile. Contattare l\'amministratore.';
    $authenticator->logFailure($auditLogger, null, 'sso_exception');
    header('Location: ' . base_url('index.php'));
    exit;
}

$claims = $result['claims'];
$user = $authenticator->findLocalUser($claims);

if (!$user) {
    $identifier = $claims['email'] ?? $claims['preferred_username'] ?? $claims['sub'] ?? null;
    $authenticator->logFailure($auditLogger, $identifier, 'sso_user_not_found');
    $_SESSION['sso_error'] = 'Il tuo account non è autorizzato all\'accesso SSO.';
    header('Location: ' . base_url('index.php'));
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['ruolo'];
$_SESSION['email'] = $user['email'];
$_SESSION['first_name'] = $user['nome'] ?? '';
$_SESSION['last_name'] = $user['cognome'] ?? '';
$_SESSION['display_name'] = format_user_display_name($user['username'], $user['email'], $user['nome'] ?? null, $user['cognome'] ?? null);
$_SESSION['theme_preference'] = $user['theme_preference'] ?? 'dark';
$_SESSION['auth_method'] = 'sso';
$_SESSION['sso_provider'] = env('OIDC_PROVIDER_NAME', 'SSO');
if (!empty($result['id_token'])) {
    $_SESSION['sso_id_token'] = $result['id_token'];
} else {
    unset($_SESSION['sso_id_token']);
}

if (!empty($result['access_token'])) {
    $_SESSION['sso_access_token'] = $result['access_token'];
} else {
    unset($_SESSION['sso_access_token']);
}

if (!empty($result['refresh_token'])) {
    $_SESSION['sso_refresh_token'] = $result['refresh_token'];
} else {
    unset($_SESSION['sso_refresh_token']);
}

unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);

$authenticator->logSuccess($auditLogger, $user, $claims);

header('Location: ' . base_url('dashboard.php'));
exit;

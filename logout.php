<?php
declare(strict_types=1);

use App\Auth\OidcAuthenticator;
use Throwable;

session_start();

require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/helpers.php';

$idToken = $_SESSION['sso_id_token'] ?? null;

$redirect = base_url('index.php');

try {
    $oidc = new OidcAuthenticator($pdo);
    if ($oidc->isEnabled()) {
        $logoutUrl = $oidc->buildLogoutUrl(is_string($idToken) ? $idToken : null);
        if ($logoutUrl) {
            $redirect = $logoutUrl;
        }
    }
} catch (Throwable $exception) {
    error_log('OIDC logout cleanup failed: ' . $exception->getMessage());
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

header('Location: ' . $redirect);
exit;

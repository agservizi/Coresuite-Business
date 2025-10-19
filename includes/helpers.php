<?php
require_once __DIR__ . '/../bootstrap/autoload.php';
require_once __DIR__ . '/env.php';

load_env(__DIR__ . '/../.env');

$debugFlag = filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL);
$displayFlag = filter_var(env('PHP_DISPLAY_ERRORS', false), FILTER_VALIDATE_BOOL);

if ($debugFlag || $displayFlag) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
}
function redirect_by_role(string $role): void
{
    switch ($role) {
        case 'Admin':
        case 'Operatore':
            header('Location: dashboard.php');
            break;
        case 'Cliente':
            header('Location: dashboard.php?view=cliente');
            break;
        default:
            header('Location: dashboard.php');
    }
}

function sanitize_output(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function format_currency(?float $amount): string
{
    if ($amount === null) {
        return '€ 0,00';
    }
    return '€ ' . number_format($amount, 2, ',', '.');
}

function base_url(string $path = ''): string
{
    static $cached;
    if ($cached === null) {
        $currentHost = $_SERVER['HTTP_HOST'] ?? null;
        $appUrl = env('APP_URL');
        if ($appUrl) {
            $appUrl = rtrim($appUrl, '/');
            $appHost = parse_url($appUrl, PHP_URL_HOST);
            $isLocalHost = in_array($appHost, ['localhost', '127.0.0.1'], true);
            if ($currentHost && $appHost && $appHost !== $currentHost && $isLocalHost) {
                $appUrl = null;
            }
        }

        if ($appUrl) {
            $cached = $appUrl;
        } else {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $currentHost ?: 'localhost';
            $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
            $projectRoot = realpath(__DIR__ . '/..') ?: '';
            $basePath = '';

            if ($docRoot !== '' && $projectRoot !== '' && strncmp($projectRoot, $docRoot, strlen($docRoot)) === 0) {
                $relative = str_replace('\\', '/', substr($projectRoot, strlen($docRoot)));
                $basePath = '/' . ltrim($relative, '/');
                if ($basePath === '/') {
                    $basePath = '';
                }
            } else {
                $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
                $basePath = $scriptDir && $scriptDir !== '.' ? $scriptDir : '';
            }

            $cached = rtrim($scheme . '://' . $host . $basePath, '/');
        }
    }

    $path = ltrim($path, '/');
    return $cached . ($path !== '' ? '/' . $path : '');
}

function public_path(string $path = ''): string
{
    $base = realpath(__DIR__ . '/..');
    if ($base === false) {
        $base = __DIR__ . '/..';
    }
    return rtrim($base, '/') . '/' . ltrim(str_replace('\\', '/', $path), '/');
}

function asset(string $path): string
{
    $relative = ltrim($path, '/');
    $file = public_path($relative);
    $timestamp = is_file($file) ? filemtime($file) : time();
    return base_url($relative) . '?v=' . $timestamp;
}

function format_datetime(?string $value, string $format = 'd/m/Y H:i'): string
{
    if ($value === null || $value === '') {
        return '';
    }

    try {
        $date = new DateTimeImmutable($value);
    } catch (Exception $e) {
        return '';
    }

    return $date->format($format);
}

function format_date_locale(?string $value): string
{
    return format_datetime($value, 'd/m/Y');
}

function format_datetime_locale(?string $value): string
{
    return format_datetime($value, 'd/m/Y H:i');
}

function format_user_display_name(?string $username, ?string $email = null, ?string $firstName = null, ?string $lastName = null): string
{
    $first = trim((string) ($firstName ?? ''));
    $last = trim((string) ($lastName ?? ''));
    if ($first !== '' || $last !== '') {
        $pieces = array_filter([$first, $last], static fn($part) => $part !== '');
        $full = implode(' ', $pieces);
        return mb_convert_case(mb_strtolower($full, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    $candidate = $username ?? '';
    if ($candidate === '' && $email) {
        $candidate = strstr($email, '@', true) ?: $email;
    }
    if ($candidate === '' && $email === null) {
        return 'Utente';
    }
    $candidate = preg_replace('/[._-]+/', ' ', $candidate);
    $candidate = trim($candidate);
    if ($candidate === '') {
        $candidate = $username ?? 'Utente';
    }
    $lower = mb_strtolower($candidate, 'UTF-8');
    return mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');
}

function current_user_display_name(): string
{
    $username = $_SESSION['username'] ?? '';
    $email = $_SESSION['email'] ?? null;
    $first = $_SESSION['first_name'] ?? null;
    $last = $_SESSION['last_name'] ?? null;
    $display = format_user_display_name($username, $email, $first, $last);
    $_SESSION['display_name'] = $display;
    return $display;
}

function csrf_token(): string
{
    if (!isset($_SESSION['__csrf']) || !is_string($_SESSION['__csrf'])) {
        $_SESSION['__csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['__csrf'];
}

function require_valid_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $token = $_POST['_token'] ?? '';
    if (!hash_equals($_SESSION['__csrf'] ?? '', (string) $token)) {
        http_response_code(419);
        exit('Token CSRF non valido.');
    }
}

function add_flash(string $type, string $message): void
{
    $_SESSION['__flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['__flash'] ?? [];
    unset($_SESSION['__flash']);
    return $flashes;
}

function sanitize_filename(string $filename): string
{
    $clean = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
    return $clean ?: bin2hex(random_bytes(8));
}

function current_user_can(string ...$roles): bool
{
    if (!isset($_SESSION['role'])) {
        return false;
    }

    if (!$roles) {
        return true;
    }

    return in_array($_SESSION['role'], $roles, true);
}

function current_user_has_capability(string ...$capabilities): bool
{
    if (!isset($_SESSION['role'])) {
        return false;
    }

    if (!$capabilities) {
        return true;
    }

    $role = $_SESSION['role'];

    return App\Auth\Authorization::roleAllows($role, ...$capabilities);
}

function require_capability(string ...$capabilities): void
{
    if (!current_user_has_capability(...$capabilities)) {
        header('Location: dashboard.php');
        exit;
    }
}

function request_ip(): string
{
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR',
    ];

    foreach ($headers as $header) {
        if (empty($_SERVER[$header])) {
            continue;
        }

        $value = $_SERVER[$header];
        if ($header === 'HTTP_X_FORWARDED_FOR') {
            $parts = explode(',', $value);
            $value = trim($parts[0] ?? '');
        }

        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return $value;
        }
    }

    return '0.0.0.0';
}

function request_user_agent(): string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 500);
}

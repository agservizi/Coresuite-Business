<?php
require_once __DIR__ . '/../bootstrap/autoload.php';
require_once __DIR__ . '/env.php';

load_env(__DIR__ . '/../.env');

$database = [
    'host' => env('DB_HOST'),
    'port' => env('DB_PORT'),
    'dbname' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    'charset' => env('DB_CHARSET', 'utf8mb4'),
];

$debug = filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL);

$missing = [];
foreach (['host', 'port', 'dbname', 'username', 'password'] as $key) {
    if ($database[$key] === null || $database[$key] === '') {
        $missing[] = 'DB_' . strtoupper($key);
    }
}

if ($missing) {
    $message = 'Configurazione database non valida. Contatta l\'amministratore.';
    error_log('Missing database configuration keys: ' . implode(', ', $missing));
    if ($debug) {
        $envPath = realpath(__DIR__ . '/../.env') ?: '.env';
        $details = [
            'file' => $envPath,
            'missing' => $missing,
            'values' => array_map(static fn($value) => $value === null ? 'null' : ($value === '' ? '[vuoto]' : '[impostato]'), $database),
        ];
        $message .= '<br><pre>' . htmlspecialchars(print_r($details, true), ENT_QUOTES, 'UTF-8') . '</pre>';
    }
    http_response_code(500);
    echo $message;
    exit;
}

$database['port'] = (int)$database['port'];

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $database['host'],
    $database['port'],
    $database['dbname'],
    $database['charset']
);

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $database['username'], $database['password'], $options);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    $message = 'Errore di connessione al database. Contatta l\'amministratore.';
    if ($debug) {
        $message .= '<br><pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    }
    echo $message;
    exit;
}

require_once __DIR__ . '/appointment_scheduler.php';
maybe_dispatch_appointment_reminders($pdo);
require_once __DIR__ . '/daily_report_scheduler.php';
maybe_generate_daily_financial_reports($pdo);

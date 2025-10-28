<?php
declare(strict_types=1);

use App\Services\ServiziWeb\HostingerClient;

require_once __DIR__ . '/../../../includes/helpers.php';

const SERVIZI_WEB_LOG_MODULE = 'Servizi/Web';

const SERVIZI_WEB_ALLOWED_STATUSES = [
    'preventivo',
    'in_attesa_cliente',
    'in_lavorazione',
    'consegnato',
    'annullato',
];

const SERVIZI_WEB_SERVICE_TYPES = [
    'Sito vetrina',
    'E-commerce',
    'Branding e grafica',
    'Domini e hosting',
    'Servizi di stampa',
];

function servizi_web_generate_code(PDO $pdo): string
{
    $prefix = 'WEB-' . date('Y');
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM servizi_web_progetti WHERE YEAR(created_at) = :year');
    $stmt->execute([':year' => date('Y')]);
    $count = (int) $stmt->fetchColumn();

    return sprintf('%s-%04d', $prefix, $count + 1);
}

function servizi_web_project_storage_path(int $projectId): string
{
    $relative = 'uploads/servizi-web/allegati/' . $projectId;

    return public_path($relative);
}

function servizi_web_cleanup_project_storage(int $projectId): void
{
    $storageDir = servizi_web_project_storage_path($projectId);
    if (!is_dir($storageDir)) {
        return;
    }

    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($storageDir, \RecursiveDirectoryIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isDir()) {
            @rmdir($fileInfo->getPathname());
        } else {
            @unlink($fileInfo->getPathname());
        }
    }

    @rmdir($storageDir);
}

function servizi_web_store_attachment(array $file, int $projectId): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Errore durante il caricamento del file.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Upload non valido.');
    }

    if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
        throw new RuntimeException('Il file supera la dimensione massima consentita di 10 MB.');
    }

    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['application/pdf', 'image/png', 'image/jpeg'];
    if ($mime === false || !in_array($mime, $allowed, true)) {
        throw new RuntimeException('Formato file non supportato.');
    }

    $storageDir = servizi_web_project_storage_path($projectId);
    if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
        throw new RuntimeException('Impossibile creare la cartella di archiviazione.');
    }

    $name = sanitize_filename($file['name']);
    $fileName = sprintf('allegato_%s_%s', date('YmdHis'), $name);
    $destination = $storageDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Impossibile salvare il file caricato.');
    }

    $relative = 'uploads/servizi-web/allegati/' . $projectId . '/' . $fileName;

    return [
        'path' => $relative,
        'hash' => hash_file('sha256', $destination),
    ];
}

function servizi_web_delete_attachment(?string $relativePath): void
{
    if (!$relativePath) {
        return;
    }

    $absolute = public_path($relativePath);
    if (is_file($absolute)) {
        @unlink($absolute);
    }
}

function servizi_web_fetch_projects(PDO $pdo, array $filters = []): array
{
    $sql = 'SELECT swp.*, c.nome, c.cognome, c.ragione_sociale
        FROM servizi_web_progetti swp
        LEFT JOIN clienti c ON swp.cliente_id = c.id';

    $where = [];
    $params = [];

    if (!empty($filters['stato']) && in_array($filters['stato'], SERVIZI_WEB_ALLOWED_STATUSES, true)) {
        $where[] = 'swp.stato = :stato';
        $params[':stato'] = $filters['stato'];
    }

    if (!empty($filters['search'])) {
        $where[] = '(swp.codice LIKE :search OR swp.titolo LIKE :search OR c.ragione_sociale LIKE :search OR c.cognome LIKE :search OR c.nome LIKE :search)';
        $params[':search'] = '%' . $filters['search'] . '%';
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY swp.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll() ?: [];
}

function servizi_web_fetch_project(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT swp.*, c.nome, c.cognome, c.ragione_sociale, c.email, c.telefono
        FROM servizi_web_progetti swp
        LEFT JOIN clienti c ON swp.cliente_id = c.id
        WHERE swp.id = :id');
    $stmt->execute([':id' => $id]);
    $project = $stmt->fetch();

    return $project ?: null;
}

function servizi_web_hostinger_is_configured(): bool
{
    if (!function_exists('env')) {
        return false;
    }

    return trim((string) env('HOSTINGER_API_TOKEN', '')) !== '';
}

function servizi_web_hostinger_client(): ?HostingerClient
{
    static $client;

    if ($client instanceof HostingerClient) {
        return $client;
    }

    if (!servizi_web_hostinger_is_configured()) {
        return null;
    }

    $token = (string) env('HOSTINGER_API_TOKEN');
    $baseUri = (string) env('HOSTINGER_API_BASE_URI', '');
    $options = [];

    $verifySetting = env('HOSTINGER_API_VERIFY_SSL', null);
    if ($verifySetting !== null && $verifySetting !== '') {
        $normalized = strtolower((string) $verifySetting);
        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            $options['verify_ssl'] = false;
        }
    }

    $caBundle = (string) env('HOSTINGER_API_CA_PATH', '');
    if ($caBundle !== '') {
        $options['ca_path'] = $caBundle;
    }

    $timeoutSetting = env('HOSTINGER_API_TIMEOUT', null);
    if ($timeoutSetting !== null && $timeoutSetting !== '') {
        $timeout = (int) $timeoutSetting;
        if ($timeout > 0) {
            $options['timeout'] = $timeout;
        }
    }

    try {
        $client = new HostingerClient($token, $baseUri !== '' ? $baseUri : null, $options);
    } catch (\Throwable $exception) {
        error_log('Servizi Web hostinger init failed: ' . $exception->getMessage());
        return null;
    }

    return $client;
}

function servizi_web_hostinger_datacenters(): array
{
    $client = servizi_web_hostinger_client();
    if (!$client) {
        return [];
    }

    try {
        return $client->listDatacenters();
    } catch (\Throwable $exception) {
        error_log('Servizi Web hostinger datacenters failed: ' . $exception->getMessage());
        return [];
    }
}

function servizi_web_hostinger_catalog(?string $category = null): array
{
    $client = servizi_web_hostinger_client();
    if (!$client) {
        return [];
    }

    try {
        return $client->listCatalog($category);
    } catch (\Throwable $exception) {
        error_log('Servizi Web hostinger catalog failed: ' . $exception->getMessage());
        return [];
    }
}

function servizi_web_hostinger_check_domain(string $domain): array
{
    $client = servizi_web_hostinger_client();
    if (!$client) {
        return [
            'items' => [],
            'error' => 'Integrazione Hostinger non disponibile.',
        ];
    }

    try {
        $items = $client->checkDomainAvailability([$domain]);

        return [
            'items' => $items,
            'error' => null,
        ];
    } catch (\Throwable $exception) {
        error_log('Servizi Web hostinger domain check failed: ' . $exception->getMessage());

        return [
            'items' => [],
            'error' => $exception->getMessage(),
        ];
    }
}

function servizi_web_log_action(PDO $pdo, string $action, string $details): void
{
    try {
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $stmt = $pdo->prepare('INSERT INTO log_attivita (user_id, modulo, azione, dettagli, created_at)
            VALUES (:user_id, :modulo, :azione, :dettagli, NOW())');
        $stmt->execute([
            ':user_id' => $userId,
            ':modulo' => SERVIZI_WEB_LOG_MODULE,
            ':azione' => $action,
            ':dettagli' => $details,
        ]);
    } catch (\Throwable $exception) {
        error_log('Servizi Web log error: ' . $exception->getMessage());
    }
}

function servizi_web_format_cliente(array $project): string
{
    $ragione = trim((string) ($project['ragione_sociale'] ?? ''));
    $fullName = trim(($project['cognome'] ?? '') . ' ' . ($project['nome'] ?? ''));

    return $ragione !== '' ? $ragione : ($fullName !== '' ? $fullName : 'Cliente');
}

<?php
declare(strict_types=1);

const ANPR_MODULE_LOG = 'Servizi/ANPR';
const ANPR_ALLOWED_STATUSES = [
    'In lavorazione',
    'Completato',
    'Annullato',
];

const ANPR_ATTACHMENT_RULES = [
    'certificato' => [
        'dir' => 'uploads/anpr/certificati',
        'allowed_mimes' => ['application/pdf'],
        'max_size' => 15728640, // 15 MB
        'columns' => [
            'path' => 'certificato_path',
            'hash' => 'certificato_hash',
            'uploaded_at' => 'certificato_caricato_at',
        ],
    ],
    'delega' => [
        'dir' => 'uploads/anpr/deleghe',
        'allowed_mimes' => ['application/pdf'],
        'max_size' => 10485760, // 10 MB
        'columns' => [
            'path' => 'delega_path',
            'hash' => 'delega_hash',
            'uploaded_at' => 'delega_caricato_at',
        ],
    ],
    'documento' => [
        'dir' => 'uploads/anpr/documenti',
        'allowed_mimes' => ['application/pdf', 'image/jpeg', 'image/png'],
        'max_size' => 10485760, // 10 MB
        'columns' => [
            'path' => 'documento_path',
            'hash' => 'documento_hash',
            'uploaded_at' => 'documento_caricato_at',
        ],
    ],
];

function anpr_practice_types(): array
{
    return [
        'Certificato di residenza',
        'Certificato di nascita',
        'Certificato di cittadinanza',
        'Certificato di stato di famiglia',
        'Certificato di matrimonio',
        'Certificato di morte',
        'Certificato contestuale',
        'Altra certificazione',
    ];
}

function anpr_fetch_pratiche(PDO $pdo, array $filters = []): array
{
    $sql = 'SELECT ap.*, c.nome, c.cognome, c.ragione_sociale, u.username AS operatore_username
        FROM anpr_pratiche ap
        LEFT JOIN clienti c ON ap.cliente_id = c.id
        LEFT JOIN users u ON ap.operatore_id = u.id';

    $where = [];
    $params = [];

    if (!empty($filters['stato'])) {
        $where[] = 'ap.stato = :stato';
        $params[':stato'] = $filters['stato'];
    }

    if (!empty($filters['tipo_pratica'])) {
        $where[] = 'ap.tipo_pratica = :tipo';
        $params[':tipo'] = $filters['tipo_pratica'];
    }

    if (!empty($filters['query'])) {
        $where[] = '(ap.pratica_code LIKE :search OR c.nome LIKE :search OR c.cognome LIKE :search OR c.ragione_sociale LIKE :search)';
        $params[':search'] = '%' . $filters['query'] . '%';
    }

    if (!empty($filters['cliente_id'])) {
        $where[] = 'ap.cliente_id = :cliente_id';
        $params[':cliente_id'] = (int) $filters['cliente_id'];
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY ap.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll() ?: [];
}

function anpr_fetch_pratica(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT ap.*, c.nome, c.cognome, c.ragione_sociale, c.email AS cliente_email,
            c.telefono AS cliente_telefono, u.username AS operatore_username
        FROM anpr_pratiche ap
        LEFT JOIN clienti c ON ap.cliente_id = c.id
        LEFT JOIN users u ON ap.operatore_id = u.id
        WHERE ap.id = :id');
    $stmt->execute([':id' => $id]);
    $pratica = $stmt->fetch();

    return $pratica ?: null;
}

function anpr_generate_pratica_code(PDO $pdo): string
{
    $year = (new DateTimeImmutable('now'))->format('Y');
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM anpr_pratiche WHERE YEAR(created_at) = :year');
    $stmt->execute([':year' => $year]);
    $count = (int) $stmt->fetchColumn();
    $next = $count + 1;

    return sprintf('ANPR-%s-%05d', $year, $next);
}

function anpr_attachment_storage_path(int $praticaId, string $type): string
{
    $type = strtolower($type);
    $rules = ANPR_ATTACHMENT_RULES[$type] ?? null;
    if (!$rules) {
        throw new InvalidArgumentException('Tipo allegato non supportato.');
    }

    $relative = rtrim($rules['dir'], '/') . '/' . $praticaId;
    return public_path($relative);
}

function anpr_log_action(PDO $pdo, string $action, string $details): void
{
    try {
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $stmt = $pdo->prepare('INSERT INTO log_attivita (user_id, modulo, azione, dettagli, created_at)
            VALUES (:user_id, :modulo, :azione, :dettagli, NOW())');
        $stmt->execute([
            ':user_id' => $userId ?: null,
            ':modulo' => ANPR_MODULE_LOG,
            ':azione' => $action,
            ':dettagli' => $details,
        ]);
    } catch (Throwable $exception) {
        error_log('ANPR log error: ' . $exception->getMessage());
    }
}

function anpr_fetch_clienti(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, ragione_sociale, nome, cognome FROM clienti ORDER BY ragione_sociale, cognome, nome');
    return $stmt->fetchAll() ?: [];
}

function anpr_get_attachment_rules(string $type): array
{
    $type = strtolower($type);
    $rules = ANPR_ATTACHMENT_RULES[$type] ?? null;
    if (!$rules) {
        throw new InvalidArgumentException('Tipo allegato non supportato.');
    }

    return $rules;
}

function anpr_store_attachment(array $file, int $praticaId, string $type): array
{
    $rules = anpr_get_attachment_rules($type);

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Errore durante il caricamento del file.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Upload non valido.');
    }

    $maxSize = (int) ($rules['max_size'] ?? 0);
    if ($maxSize > 0 && (int) ($file['size'] ?? 0) > $maxSize) {
        throw new RuntimeException('Il file supera la dimensione massima consentita.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
    if ($finfo) {
        finfo_close($finfo);
    }
    $allowed = $rules['allowed_mimes'] ?? [];
    if ($mime === false || !in_array((string) $mime, $allowed, true)) {
        throw new RuntimeException('Tipo di file non supportato per questo allegato.');
    }

    $storageDir = anpr_attachment_storage_path($praticaId, $type);
    if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
        throw new RuntimeException('Impossibile creare la cartella di archiviazione.');
    }

    $sanitizedName = sanitize_filename($file['name']);
    $random = bin2hex(random_bytes(4));
    $fileName = sprintf('%s_%s_%s_%s', strtolower($type), date('YmdHis'), $random, $sanitizedName);
    $destination = $storageDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Impossibile salvare il file caricato.');
    }

    $relative = rtrim($rules['dir'], '/') . '/' . $praticaId . '/' . $fileName;

    return [
        'path' => $relative,
        'hash' => hash_file('sha256', $destination),
    ];
}

function anpr_delete_attachment(?string $relativePath): void
{
    if (!$relativePath) {
        return;
    }

    $absolute = public_path($relativePath);
    if (is_file($absolute)) {
        @unlink($absolute);
    }
}

function anpr_store_certificate(array $file, int $praticaId): array
{
    return anpr_store_attachment($file, $praticaId, 'certificato');
}

function anpr_delete_certificate(?string $relativePath): void
{
    anpr_delete_attachment($relativePath);
}

function anpr_store_delega(array $file, int $praticaId): array
{
    return anpr_store_attachment($file, $praticaId, 'delega');
}

function anpr_delete_delega(?string $relativePath): void
{
    anpr_delete_attachment($relativePath);
}

function anpr_store_documento(array $file, int $praticaId): array
{
    return anpr_store_attachment($file, $praticaId, 'documento');
}

function anpr_delete_documento(?string $relativePath): void
{
    anpr_delete_attachment($relativePath);
}

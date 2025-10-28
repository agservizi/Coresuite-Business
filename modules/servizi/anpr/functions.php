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
        'Certificato cumulativo',
        'Certificato contestuale',
        'Certificato di matrimonio',
        'Certificato di morte',
        'Cambio residenza assistito',
        'Delega / autocertificazione',
        'Altra certificazione',
    ];
}

function anpr_service_catalog(): array
{
    return [
        [
            'servizio' => 'Certificato di residenza',
            'prezzo' => '€3–5',
            'note' => 'Rilascio in pochi minuti',
        ],
        [
            'servizio' => 'Certificato di nascita',
            'prezzo' => '€3–5',
            'note' => '',
        ],
        [
            'servizio' => 'Stato di famiglia',
            'prezzo' => '€3–6',
            'note' => '',
        ],
        [
            'servizio' => 'Certificato cumulativo',
            'prezzo' => '€5–8',
            'note' => '',
        ],
        [
            'servizio' => 'Cambio residenza assistito',
            'prezzo' => '€15–25',
            'note' => 'Con caricamento moduli e PEC',
        ],
        [
            'servizio' => 'Delega / autocertificazione',
            'prezzo' => '€2–3',
            'note' => 'Generata dal gestionale',
        ],
    ];
}

function anpr_fetch_pratiche(PDO $pdo, array $filters = []): array
{
    $sql = 'SELECT ap.*, c.nome, c.cognome, c.ragione_sociale, c.email AS cliente_email, u.username AS operatore_username, us.username AS spid_operatore_username
        FROM anpr_pratiche ap
        LEFT JOIN clienti c ON ap.cliente_id = c.id
        LEFT JOIN users u ON ap.operatore_id = u.id
        LEFT JOIN users us ON ap.spid_operatore_id = us.id';

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

    if (!empty($filters['has_certificate'])) {
        $where[] = 'ap.certificato_path IS NOT NULL';
    }

    if (!empty($filters['created_from'])) {
        $where[] = 'ap.created_at >= :created_from';
        $params[':created_from'] = $filters['created_from'] . ' 00:00:00';
    }

    if (!empty($filters['created_to'])) {
        $where[] = 'ap.created_at <= :created_to';
        $params[':created_to'] = $filters['created_to'] . ' 23:59:59';
    }

    if (!empty($filters['certificate_from'])) {
        $where[] = 'ap.certificato_caricato_at >= :cert_from';
        $params[':cert_from'] = $filters['certificate_from'] . ' 00:00:00';
    }

    if (!empty($filters['certificate_to'])) {
        $where[] = 'ap.certificato_caricato_at <= :cert_to';
        $params[':cert_to'] = $filters['certificate_to'] . ' 23:59:59';
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $orderBy = 'ap.created_at';
    $orderDir = 'DESC';

    if (!empty($filters['order_by'])) {
        $allowed = ['ap.created_at', 'ap.certificato_caricato_at', 'ap.pratica_code'];
        if (in_array($filters['order_by'], $allowed, true)) {
            $orderBy = $filters['order_by'];
        }
    }

    if (!empty($filters['order_dir']) && strtoupper($filters['order_dir']) === 'ASC') {
        $orderDir = 'ASC';
    }

    $sql .= ' ORDER BY ' . $orderBy . ' ' . $orderDir;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll() ?: [];
}

function anpr_fetch_pratica(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT ap.*, c.nome, c.cognome, c.ragione_sociale, c.email AS cliente_email,
            c.telefono AS cliente_telefono, u.username AS operatore_username, us.username AS spid_operatore_username
        FROM anpr_pratiche ap
        LEFT JOIN clienti c ON ap.cliente_id = c.id
        LEFT JOIN users u ON ap.operatore_id = u.id
        LEFT JOIN users us ON ap.spid_operatore_id = us.id
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

function anpr_set_spid_status(PDO $pdo, int $praticaId, ?int $operatoreId): void
{
    if ($operatoreId) {
        $stmt = $pdo->prepare('UPDATE anpr_pratiche
            SET spid_verificato_at = NOW(), spid_operatore_id = :operatore_id, updated_at = NOW()
            WHERE id = :id');
        $stmt->execute([
            ':operatore_id' => $operatoreId,
            ':id' => $praticaId,
        ]);
    } else {
        $stmt = $pdo->prepare('UPDATE anpr_pratiche
            SET spid_verificato_at = NULL, spid_operatore_id = NULL, updated_at = NOW()
            WHERE id = :id');
        $stmt->execute([':id' => $praticaId]);
    }
}

function anpr_record_certificate_delivery(PDO $pdo, int $praticaId, string $channel, string $recipient): void
{
    $stmt = $pdo->prepare('UPDATE anpr_pratiche
        SET certificato_inviato_at = NOW(),
            certificato_inviato_via = :via,
            certificato_inviato_destinatario = :recipient,
            updated_at = NOW()
        WHERE id = :id');
    $stmt->execute([
        ':via' => $channel,
        ':recipient' => $recipient,
        ':id' => $praticaId,
    ]);
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

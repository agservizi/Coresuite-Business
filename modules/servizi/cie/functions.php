<?php

declare(strict_types=1);

const CIE_MODULE_LOG = 'Servizi/CIE';

const CIE_UPLOAD_RULES = [
    'documento_identita' => [
        'dir' => 'uploads/cie/documenti',
        'allowed_mimes' => [
            'application/pdf',
            'image/jpeg',
            'image/png',
        ],
        'max_size' => 10_485_760, // 10 MB
    ],
    'foto_cittadino' => [
        'dir' => 'uploads/cie/foto',
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
        ],
        'max_size' => 5_242_880, // 5 MB
    ],
    'ricevuta' => [
        'dir' => 'uploads/cie/ricevute',
        'allowed_mimes' => [
            'application/pdf',
        ],
        'max_size' => 15_728_640, // 15 MB
    ],
];

function cie_prenotazioni_has_column(PDO $pdo, string $column): bool
{
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM cie_prenotazioni LIKE :column');
        $stmt->execute([':column' => $column]);
        $cache[$column] = $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    } catch (PDOException) {
        $cache[$column] = false;
    }

    return $cache[$column];
}

function cie_supports_prenotazione_code(PDO $pdo): bool
{
    return cie_prenotazioni_has_column($pdo, 'prenotazione_code');
}

function cie_fallback_booking_code(int $id, ?string $createdAt): string
{
    $datePart = '00000000';
    if ($createdAt) {
        $patterns = ['Y-m-d H:i:s', 'Y-m-d'];
        foreach ($patterns as $pattern) {
            $dt = DateTime::createFromFormat($pattern, $createdAt);
            if ($dt instanceof DateTime) {
                $datePart = $dt->format('Ymd');
                break;
            }
        }
    }

    if ($datePart === '00000000') {
        $datePart = date('Ymd');
    }

    if ($id > 0) {
        return sprintf('CIE-%s-%04d', $datePart, $id);
    }

    return sprintf('CIE-%s-%s', $datePart, strtoupper(bin2hex(random_bytes(3))));
}

function cie_booking_code(array $booking): string
{
    $code = (string) ($booking['booking_code'] ?? '');
    if ($code !== '') {
        return $code;
    }

    $code = (string) ($booking['prenotazione_code'] ?? '');
    if ($code !== '') {
        return $code;
    }

    $id = (int) ($booking['id'] ?? 0);
    $createdAt = $booking['created_at'] ?? null;

    return cie_fallback_booking_code($id, is_string($createdAt) ? $createdAt : null);
}

function cie_status_map(): array
{
    return [
        'nuova' => [
            'label' => 'Nuova richiesta',
            'badge' => 'badge bg-secondary',
        ],
        'dati_inviati' => [
            'label' => 'Dati inviati',
            'badge' => 'badge bg-info text-dark',
        ],
        'appuntamento_confermato' => [
            'label' => 'Appuntamento confermato',
            'badge' => 'badge bg-primary',
        ],
        'completata' => [
            'label' => 'Completata',
            'badge' => 'badge bg-success',
        ],
        'annullata' => [
            'label' => 'Annullata',
            'badge' => 'badge bg-danger',
        ],
    ];
}

function cie_allowed_statuses(): array
{
    return array_keys(cie_status_map());
}

function cie_status_label(string $status): string
{
    $map = cie_status_map();
    return $map[$status]['label'] ?? ucfirst(str_replace('_', ' ', $status));
}

function cie_status_badge(string $status): string
{
    $map = cie_status_map();
    return $map[$status]['badge'] ?? 'badge bg-light text-dark';
}

function cie_generate_code(PDO $pdo): string
{
    if (!cie_supports_prenotazione_code($pdo)) {
        return '';
    }

    do {
        $code = 'CIE-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM cie_prenotazioni WHERE prenotazione_code = :code');
        $stmt->execute([':code' => $code]);
    } while ((int) $stmt->fetchColumn() > 0);

    return $code;
}

function cie_fetch_clients(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, nome, cognome, cf_piva, email, telefono, indirizzo FROM clienti ORDER BY cognome, nome');
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function cie_fetch_bookings(PDO $pdo, array $filters = []): array
{
    $sql = 'SELECT cp.*, c.nome AS cliente_nome, c.cognome AS cliente_cognome, c.cf_piva AS cliente_cf,
            u.username AS created_by_username, uu.username AS updated_by_username
        FROM cie_prenotazioni cp
        LEFT JOIN clienti c ON cp.cliente_id = c.id
        LEFT JOIN users u ON cp.created_by = u.id
        LEFT JOIN users uu ON cp.updated_by = uu.id';

    $where = [];
    $params = [];
    $hasBookingCodeColumn = cie_supports_prenotazione_code($pdo);

    if (!empty($filters['stato']) && in_array($filters['stato'], cie_allowed_statuses(), true)) {
        $where[] = 'cp.stato = :stato';
        $params[':stato'] = $filters['stato'];
    }

    if (!empty($filters['cliente_id'])) {
        $where[] = 'cp.cliente_id = :cliente_id';
        $params[':cliente_id'] = (int) $filters['cliente_id'];
    }

    if (!empty($filters['search'])) {
        $searchable = [
            'cp.cittadino_nome',
            'cp.cittadino_cognome',
            'cp.cittadino_cf',
            'cp.comune_richiesta',
        ];

        if ($hasBookingCodeColumn) {
            array_unshift($searchable, 'cp.prenotazione_code');
        } else {
            $searchable[] = 'CAST(cp.id AS CHAR)';
        }

        $where[] = '(' . implode(' OR ', array_map(static fn (string $column): string => $column . ' LIKE :search', $searchable)) . ')';
        $params[':search'] = '%' . $filters['search'] . '%';
    }

    if (!empty($filters['created_from'])) {
        $where[] = 'cp.created_at >= :created_from';
        $params[':created_from'] = $filters['created_from'] . ' 00:00:00';
    }

    if (!empty($filters['created_to'])) {
        $where[] = 'cp.created_at <= :created_to';
        $params[':created_to'] = $filters['created_to'] . ' 23:59:59';
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY cp.created_at DESC, cp.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($results as &$row) {
        $row['booking_code'] = cie_booking_code($row);
    }
    unset($row);

    return $results;
}

function cie_fetch_stats(PDO $pdo): array
{
    $statuses = array_fill_keys(cie_allowed_statuses(), 0);
    $stmt = $pdo->query('SELECT stato, COUNT(*) AS total FROM cie_prenotazioni GROUP BY stato');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status = (string) ($row['stato'] ?? '');
        if ($status !== '' && isset($statuses[$status])) {
            $statuses[$status] = (int) $row['total'];
        }
    }

    $stmtTotal = $pdo->query('SELECT COUNT(*) FROM cie_prenotazioni');
    $total = (int) $stmtTotal->fetchColumn();

    return [
        'by_status' => $statuses,
        'total' => $total,
    ];
}

function cie_fetch_booking(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT cp.*, c.nome AS cliente_nome, c.cognome AS cliente_cognome, c.cf_piva AS cliente_cf,
            c.email AS cliente_email, c.telefono AS cliente_telefono, c.indirizzo AS cliente_indirizzo,
            u.username AS created_by_username, uu.username AS updated_by_username
        FROM cie_prenotazioni cp
        LEFT JOIN clienti c ON cp.cliente_id = c.id
        LEFT JOIN users u ON cp.created_by = u.id
        LEFT JOIN users uu ON cp.updated_by = uu.id
        WHERE cp.id = :id');
    $stmt->execute([':id' => $id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        return null;
    }

    $booking['booking_code'] = cie_booking_code($booking);

    $historyStmt = $pdo->prepare('SELECT id, channel, message_subject, sent_at, notes
        FROM cie_prenotazioni_notifiche
        WHERE prenotazione_id = :id
        ORDER BY sent_at DESC, id DESC');
    try {
        $historyStmt->execute([':id' => $id]);
        $booking['notification_history'] = $historyStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
        $booking['notification_history'] = [];
    }

    return $booking;
}

function cie_create(PDO $pdo, array $data, array $files): int
{
    $uploads = [];
    $pdo->beginTransaction();

    try {
        $code = cie_generate_code($pdo);
        $uploads = cie_process_uploads($files, []);

        $hasBookingCodeColumn = $code !== '';

        $columns = [
            'cliente_id',
            'cittadino_nome',
            'cittadino_cognome',
            'cittadino_cf',
            'cittadino_email',
            'cittadino_telefono',
            'data_nascita',
            'luogo_nascita',
            'residenza_indirizzo',
            'residenza_cap',
            'residenza_citta',
            'residenza_provincia',
            'comune_richiesta',
            'disponibilita_data',
            'disponibilita_fascia',
            'appuntamento_data',
            'appuntamento_orario',
            'appuntamento_numero',
            'stato',
            'documento_identita_path',
            'documento_identita_nome',
            'documento_identita_mime',
            'foto_cittadino_path',
            'foto_cittadino_nome',
            'foto_cittadino_mime',
            'ricevuta_path',
            'ricevuta_nome',
            'ricevuta_mime',
            'note',
            'esito',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
        ];

        $placeholders = [
            ':cliente_id',
            ':cittadino_nome',
            ':cittadino_cognome',
            ':cittadino_cf',
            ':cittadino_email',
            ':cittadino_telefono',
            ':data_nascita',
            ':luogo_nascita',
            ':residenza_indirizzo',
            ':residenza_cap',
            ':residenza_citta',
            ':residenza_provincia',
            ':comune_richiesta',
            ':disponibilita_data',
            ':disponibilita_fascia',
            ':appuntamento_data',
            ':appuntamento_orario',
            ':appuntamento_numero',
            ':stato',
            ':documento_identita_path',
            ':documento_identita_nome',
            ':documento_identita_mime',
            ':foto_cittadino_path',
            ':foto_cittadino_nome',
            ':foto_cittadino_mime',
            ':ricevuta_path',
            ':ricevuta_nome',
            ':ricevuta_mime',
            ':note',
            ':esito',
            ':created_by',
            ':updated_by',
            'NOW()',
            'NOW()',
        ];

        if ($hasBookingCodeColumn) {
            array_unshift($columns, 'prenotazione_code');
            array_unshift($placeholders, ':prenotazione_code');
        }

        $sql = sprintf(
            'INSERT INTO cie_prenotazioni (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $pdo->prepare($sql);

        $userId = cie_current_user_id();
        $params = [
            ':cliente_id' => $data['cliente_id'] ?? null,
            ':cittadino_nome' => $data['cittadino_nome'],
            ':cittadino_cognome' => $data['cittadino_cognome'],
            ':cittadino_cf' => $data['cittadino_cf'] ?? null,
            ':cittadino_email' => $data['cittadino_email'] ?? null,
            ':cittadino_telefono' => $data['cittadino_telefono'] ?? null,
            ':data_nascita' => $data['data_nascita'] ?? null,
            ':luogo_nascita' => $data['luogo_nascita'] ?? null,
            ':residenza_indirizzo' => $data['residenza_indirizzo'] ?? null,
            ':residenza_cap' => $data['residenza_cap'] ?? null,
            ':residenza_citta' => $data['residenza_citta'] ?? null,
            ':residenza_provincia' => $data['residenza_provincia'] ?? null,
            ':comune_richiesta' => $data['comune_richiesta'],
            ':disponibilita_data' => $data['disponibilita_data'] ?? null,
            ':disponibilita_fascia' => $data['disponibilita_fascia'] ?? null,
            ':appuntamento_data' => $data['appuntamento_data'] ?? null,
            ':appuntamento_orario' => $data['appuntamento_orario'] ?? null,
            ':appuntamento_numero' => $data['appuntamento_numero'] ?? null,
            ':stato' => $data['stato'] ?? 'nuova',
            ':documento_identita_path' => $uploads['documento_identita']['path'] ?? null,
            ':documento_identita_nome' => $uploads['documento_identita']['name'] ?? null,
            ':documento_identita_mime' => $uploads['documento_identita']['mime'] ?? null,
            ':foto_cittadino_path' => $uploads['foto_cittadino']['path'] ?? null,
            ':foto_cittadino_nome' => $uploads['foto_cittadino']['name'] ?? null,
            ':foto_cittadino_mime' => $uploads['foto_cittadino']['mime'] ?? null,
            ':ricevuta_path' => $uploads['ricevuta']['path'] ?? null,
            ':ricevuta_nome' => $uploads['ricevuta']['name'] ?? null,
            ':ricevuta_mime' => $uploads['ricevuta']['mime'] ?? null,
            ':note' => $data['note'] ?? null,
            ':esito' => $data['esito'] ?? null,
            ':created_by' => $userId,
            ':updated_by' => $userId,
        ];

        if ($hasBookingCodeColumn) {
            $params[':prenotazione_code'] = $code;
        }

        $stmt->execute($params);

        $id = (int) $pdo->lastInsertId();
        cie_log_action($pdo, 'Creazione prenotazione', 'Prenotazione CIE #' . $id . ' creata');

        $pdo->commit();
        return $id;
    } catch (Throwable $exception) {
        $pdo->rollBack();
        cie_cleanup_uploads($uploads);
        throw $exception;
    }
}

function cie_update(PDO $pdo, int $id, array $data, array $files, array $options = []): bool
{
    $existing = cie_fetch_booking($pdo, $id);
    if ($existing === null) {
        return false;
    }

    $uploads = [];
    $pdo->beginTransaction();

    try {
        $uploads = cie_process_uploads($files, $existing, $options);

        $stmt = $pdo->prepare('UPDATE cie_prenotazioni SET
                cliente_id = :cliente_id,
                cittadino_nome = :cittadino_nome,
                cittadino_cognome = :cittadino_cognome,
                cittadino_cf = :cittadino_cf,
                cittadino_email = :cittadino_email,
                cittadino_telefono = :cittadino_telefono,
                data_nascita = :data_nascita,
                luogo_nascita = :luogo_nascita,
                residenza_indirizzo = :residenza_indirizzo,
                residenza_cap = :residenza_cap,
                residenza_citta = :residenza_citta,
                residenza_provincia = :residenza_provincia,
                comune_richiesta = :comune_richiesta,
                disponibilita_data = :disponibilita_data,
                disponibilita_fascia = :disponibilita_fascia,
                appuntamento_data = :appuntamento_data,
                appuntamento_orario = :appuntamento_orario,
                appuntamento_numero = :appuntamento_numero,
                stato = :stato,
                documento_identita_path = :documento_identita_path,
                documento_identita_nome = :documento_identita_nome,
                documento_identita_mime = :documento_identita_mime,
                foto_cittadino_path = :foto_cittadino_path,
                foto_cittadino_nome = :foto_cittadino_nome,
                foto_cittadino_mime = :foto_cittadino_mime,
                ricevuta_path = :ricevuta_path,
                ricevuta_nome = :ricevuta_nome,
                ricevuta_mime = :ricevuta_mime,
                note = :note,
                esito = :esito,
                updated_by = :updated_by,
                updated_at = NOW()
            WHERE id = :id');

        $userId = cie_current_user_id();
        $stmt->execute([
            ':cliente_id' => $data['cliente_id'] ?? null,
            ':cittadino_nome' => $data['cittadino_nome'],
            ':cittadino_cognome' => $data['cittadino_cognome'],
            ':cittadino_cf' => $data['cittadino_cf'] ?? null,
            ':cittadino_email' => $data['cittadino_email'] ?? null,
            ':cittadino_telefono' => $data['cittadino_telefono'] ?? null,
            ':data_nascita' => $data['data_nascita'] ?? null,
            ':luogo_nascita' => $data['luogo_nascita'] ?? null,
            ':residenza_indirizzo' => $data['residenza_indirizzo'] ?? null,
            ':residenza_cap' => $data['residenza_cap'] ?? null,
            ':residenza_citta' => $data['residenza_citta'] ?? null,
            ':residenza_provincia' => $data['residenza_provincia'] ?? null,
            ':comune_richiesta' => $data['comune_richiesta'],
            ':disponibilita_data' => $data['disponibilita_data'] ?? null,
            ':disponibilita_fascia' => $data['disponibilita_fascia'] ?? null,
            ':appuntamento_data' => $data['appuntamento_data'] ?? null,
            ':appuntamento_orario' => $data['appuntamento_orario'] ?? null,
            ':appuntamento_numero' => $data['appuntamento_numero'] ?? null,
            ':stato' => $data['stato'] ?? $existing['stato'],
            ':documento_identita_path' => $uploads['documento_identita']['path'] ?? null,
            ':documento_identita_nome' => $uploads['documento_identita']['name'] ?? null,
            ':documento_identita_mime' => $uploads['documento_identita']['mime'] ?? null,
            ':foto_cittadino_path' => $uploads['foto_cittadino']['path'] ?? null,
            ':foto_cittadino_nome' => $uploads['foto_cittadino']['name'] ?? null,
            ':foto_cittadino_mime' => $uploads['foto_cittadino']['mime'] ?? null,
            ':ricevuta_path' => $uploads['ricevuta']['path'] ?? null,
            ':ricevuta_nome' => $uploads['ricevuta']['name'] ?? null,
            ':ricevuta_mime' => $uploads['ricevuta']['mime'] ?? null,
            ':note' => $data['note'] ?? null,
            ':esito' => $data['esito'] ?? null,
            ':updated_by' => $userId,
            ':id' => $id,
        ]);

        cie_log_action($pdo, 'Aggiornamento prenotazione', 'Prenotazione CIE #' . $id . ' aggiornata');
        $pdo->commit();
        return true;
    } catch (Throwable $exception) {
        $pdo->rollBack();
        cie_cleanup_uploads($uploads);
        throw $exception;
    }
}

function cie_update_status(PDO $pdo, int $id, string $status): bool
{
    if (!in_array($status, cie_allowed_statuses(), true)) {
        return false;
    }

    $stmt = $pdo->prepare('UPDATE cie_prenotazioni SET stato = :stato, updated_at = NOW(), updated_by = :updated_by WHERE id = :id');
    $stmt->execute([
        ':stato' => $status,
        ':updated_by' => cie_current_user_id(),
        ':id' => $id,
    ]);

    cie_log_action($pdo, 'Cambio stato prenotazione', 'Prenotazione CIE #' . $id . ' impostata a ' . $status);
    return $stmt->rowCount() > 0;
}

function cie_delete(PDO $pdo, int $id): bool
{
    $booking = cie_fetch_booking($pdo, $id);
    if ($booking === null) {
        return false;
    }

    $paths = array_filter([
        $booking['documento_identita_path'] ?? null,
        $booking['foto_cittadino_path'] ?? null,
        $booking['ricevuta_path'] ?? null,
    ]);

    $stmt = $pdo->prepare('DELETE FROM cie_prenotazioni WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        foreach ($paths as $path) {
            cie_delete_file($path);
        }
        cie_log_action($pdo, 'Eliminazione prenotazione', 'Prenotazione CIE #' . $id . ' eliminata');
        return true;
    }

    return false;
}

function cie_process_uploads(array $files, array $existing = [], array $options = []): array
{
    $results = [];
    foreach (CIE_UPLOAD_RULES as $field => $rule) {
        $removeFlag = !empty($options['remove_' . $field]);
        $fileInfo = $files[$field] ?? null;

        if ($fileInfo && isset($fileInfo['error']) && (int) $fileInfo['error'] !== UPLOAD_ERR_NO_FILE) {
            $results[$field] = cie_store_upload($field, $fileInfo, $rule);
            if (!empty($existing[$field . '_path'])) {
                cie_delete_file((string) $existing[$field . '_path']);
            }
        } elseif ($removeFlag) {
            if (!empty($existing[$field . '_path'])) {
                cie_delete_file((string) $existing[$field . '_path']);
            }
            $results[$field] = ['path' => null, 'name' => null, 'mime' => null];
        } else {
            if (!empty($existing[$field . '_path'])) {
                $results[$field] = [
                    'path' => $existing[$field . '_path'],
                    'name' => $existing[$field . '_nome'] ?? $existing[$field . '_name'] ?? null,
                    'mime' => $existing[$field . '_mime'] ?? null,
                ];
            } else {
                $results[$field] = ['path' => null, 'name' => null, 'mime' => null];
            }
        }
    }

    return $results;
}

function cie_store_upload(string $field, array $fileInfo, array $rule): array
{
    if (!isset($fileInfo['tmp_name'], $fileInfo['name'], $fileInfo['type'], $fileInfo['error'], $fileInfo['size'])) {
        throw new RuntimeException('Upload non valido per ' . $field);
    }

    if ((int) $fileInfo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Errore nel caricamento del file ' . $fileInfo['name']);
    }

    if ($fileInfo['size'] > $rule['max_size']) {
        throw new RuntimeException('Il file ' . $fileInfo['name'] . ' supera la dimensione massima consentita.');
    }

    $mime = cie_detect_mime_type($fileInfo['tmp_name'], (string) $fileInfo['type']);
    if (!in_array($mime, $rule['allowed_mimes'], true)) {
        throw new RuntimeException('Tipo di file non supportato per ' . $fileInfo['name']);
    }

    $destinationDir = public_path($rule['dir']);
    if (!is_dir($destinationDir) && !mkdir($destinationDir, 0775, true) && !is_dir($destinationDir)) {
        throw new RuntimeException('Impossibile creare la directory di caricamento.');
    }

    $extension = pathinfo((string) $fileInfo['name'], PATHINFO_EXTENSION);
    $safeName = sanitize_filename(pathinfo((string) $fileInfo['name'], PATHINFO_FILENAME));
    $newName = $safeName . '_' . bin2hex(random_bytes(4)) . ($extension ? '.' . strtolower((string) $extension) : '');
    $relativePath = $rule['dir'] . '/' . $newName;
    $destinationPath = public_path($relativePath);

    if (!move_uploaded_file($fileInfo['tmp_name'], $destinationPath)) {
        throw new RuntimeException('Impossibile spostare il file caricato.');
    }

    return [
        'path' => $relativePath,
        'name' => (string) $fileInfo['name'],
        'mime' => $mime,
    ];
}

function cie_delete_file(string $relativePath): void
{
    $absolute = public_path($relativePath);
    if (is_file($absolute)) {
        unlink($absolute);
    }
}

function cie_cleanup_uploads(array $uploads): void
{
    foreach ($uploads as $upload) {
        if (!empty($upload['path'])) {
            cie_delete_file((string) $upload['path']);
        }
    }
}

function cie_detect_mime_type(string $path, string $fallback): string
{
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, $path);
            finfo_close($finfo);
            if (is_string($detected) && $detected !== '') {
                return $detected;
            }
        }
    }

    return $fallback;
}

function cie_current_user_id(): ?int
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $userId = (int) $_SESSION['user_id'];
    return $userId > 0 ? $userId : null;
}

function cie_log_action(PDO $pdo, string $action, string $details): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO log_attivita (user_id, modulo, azione, dettagli, created_at)
            VALUES (:user_id, :modulo, :azione, :dettagli, NOW())');
        $stmt->execute([
            ':user_id' => cie_current_user_id(),
            ':modulo' => CIE_MODULE_LOG,
            ':azione' => $action,
            ':dettagli' => $details,
        ]);
    } catch (Throwable) {
        // Il logging non deve bloccare il flusso principale.
    }
}

function cie_build_portal_url(array $booking): string
{
    $base = 'https://www.prenotazionicie.interno.gov.it/cittadino/n/sc/wizardAppuntamentoCittadino/sceltaComune';
    $params = [];

    if (!empty($booking['cittadino_nome'])) {
        $params['nome'] = $booking['cittadino_nome'];
    }
    if (!empty($booking['cittadino_cognome'])) {
        $params['cognome'] = $booking['cittadino_cognome'];
    }
    if (!empty($booking['cittadino_cf'])) {
        $params['codiceFiscale'] = $booking['cittadino_cf'];
    }
    if (!empty($booking['comune_richiesta'])) {
        $params['comune'] = $booking['comune_richiesta'];
    }

    return $base . (!empty($params) ? '?' . http_build_query($params) : '');
}

function cie_send_email_notification(PDO $pdo, array $booking, string $type): bool
{
    if (empty($booking['cittadino_email'])) {
        return false;
    }

    $bookingCode = cie_booking_code($booking);

    $subject = $type === 'reminder'
        ? 'Reminder appuntamento CIE - ' . $bookingCode
        : 'Conferma prenotazione CIE - ' . $bookingCode;

    $details = [
        'Codice prenotazione' => $bookingCode,
        'Cittadino' => trim((string) ($booking['cittadino_cognome'] ?? '') . ' ' . ($booking['cittadino_nome'] ?? '')),
        'Codice fiscale' => (string) ($booking['cittadino_cf'] ?? ''),
        'Comune richiesta' => (string) ($booking['comune_richiesta'] ?? ''),
        'Disponibilità preferita' => ($booking['disponibilita_data'] ?? '') !== ''
            ? format_date_locale($booking['disponibilita_data']) . ' ' . ($booking['disponibilita_fascia'] ?? '')
            : '—',
        'Appuntamento' => ($booking['appuntamento_data'] ?? '') !== ''
            ? format_date_locale($booking['appuntamento_data']) . ' ' . ($booking['appuntamento_orario'] ?? '')
            : 'In attesa di conferma',
    ];

    $rows = '';
    foreach ($details as $label => $value) {
        $rows .= '<tr><th align="left" style="padding:6px 12px;background:#f8f9fc;width:220px;">' . htmlspecialchars($label, ENT_QUOTES) . '</th>';
        $rows .= '<td style="padding:6px 12px;">' . htmlspecialchars($value, ENT_QUOTES) . '</td></tr>';
    }

    $content = '<p style="margin:0 0 12px;">Gentile cittadino,</p>';
    $content .= $type === 'reminder'
        ? '<p style="margin:0 0 12px;">ti ricordiamo l\'appuntamento per la Carta d\'Identità Elettronica.</p>'
        : '<p style="margin:0 0 12px;">abbiamo registrato la tua richiesta per la Carta d\'Identità Elettronica.</p>';
    $content .= '<table cellspacing="0" cellpadding="0" style="border-collapse:collapse;width:100%;background:#ffffff;border:1px solid #dee2e6;border-radius:8px;overflow:hidden;">' . $rows . '</table>';
    $content .= '<p style="margin:12px 0 0;">Per completare la procedura visita il portale del Ministero: <a href="' . htmlspecialchars(cie_build_portal_url($booking), ENT_QUOTES) . '">prenotazionicie.interno.gov.it</a>.</p>';

    $htmlBody = render_mail_template('Prenotazione Carta d\'Identità Elettronica', $content);
    $sent = send_system_mail((string) $booking['cittadino_email'], $subject, $htmlBody);

    $channel = $type === 'reminder' ? 'email_reminder' : 'email';
    cie_record_notification($pdo, (int) $booking['id'], $channel, $subject, $sent ? null : 'Invio email non riuscito');

    if ($sent) {
        $field = $type === 'reminder' ? 'reminder_email_sent_at' : 'conferma_email_sent_at';
        $stmt = $pdo->prepare('UPDATE cie_prenotazioni SET ' . $field . ' = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $booking['id']]);
    }

    return $sent;
}

function cie_record_notification(PDO $pdo, int $bookingId, string $channel, string $subject, ?string $notes = null): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO cie_prenotazioni_notifiche (prenotazione_id, channel, message_subject, notes, sent_at)
            VALUES (:prenotazione_id, :channel, :message_subject, :notes, NOW())');
        $stmt->execute([
            ':prenotazione_id' => $bookingId,
            ':channel' => $channel,
            ':message_subject' => $subject,
            ':notes' => $notes,
        ]);
    } catch (Throwable) {
        // Ignoriamo errori di tracciamento.
    }
}

function cie_record_whatsapp_trigger(PDO $pdo, int $bookingId, string $recipient, string $message): void
{
    $subject = 'Messaggio WhatsApp verso ' . $recipient;
    cie_record_notification($pdo, $bookingId, 'whatsapp', $subject, $message);
    $stmt = $pdo->prepare('UPDATE cie_prenotazioni SET reminder_whatsapp_sent_at = NOW(), updated_at = NOW() WHERE id = :id');
    $stmt->execute([':id' => $bookingId]);
}

function cie_build_whatsapp_link(array $booking): string
{
    $phone = preg_replace('/[^0-9+]/', '', (string) ($booking['cittadino_telefono'] ?? ''));
    $messageLines = [
        'Ciao ' . trim((string) ($booking['cittadino_nome'] ?? '')), 
        'ti ricordiamo la prenotazione CIE.',
    ];

    if (!empty($booking['appuntamento_data'])) {
        $messageLines[] = 'Data appuntamento: ' . format_date_locale($booking['appuntamento_data']);
    }
    if (!empty($booking['appuntamento_orario'])) {
        $messageLines[] = 'Orario: ' . $booking['appuntamento_orario'];
    }
    if (!empty($booking['comune_richiesta'])) {
        $messageLines[] = 'Comune: ' . $booking['comune_richiesta'];
    }
    $messageLines[] = 'Codice pratica: ' . cie_booking_code($booking);

    $text = urlencode(implode("\n", array_filter($messageLines)));
    $number = $phone !== '' ? $phone : '';

    return 'https://api.whatsapp.com/send?' . http_build_query([
        'phone' => $number,
        'text' => $text,
    ]);
}

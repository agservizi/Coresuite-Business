<?php
declare(strict_types=1);

const CITTADINO_CIE_MODULE_LOG = 'Servizi/CIE';

function cittadino_cie_status_map(): array
{
    return [
        'nuova' => [
            'label' => 'Nuova richiesta',
            'badge' => 'badge bg-secondary',
        ],
        'in_verifica' => [
            'label' => 'In verifica',
            'badge' => 'badge bg-info text-dark',
        ],
        'ricerca_slot' => [
            'label' => 'Ricerca slot',
            'badge' => 'badge bg-warning text-dark',
        ],
        'prenotata' => [
            'label' => 'Prenotazione effettuata',
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

function cittadino_cie_allowed_statuses(): array
{
    return array_keys(cittadino_cie_status_map());
}

function cittadino_cie_status_label(string $status): string
{
    $map = cittadino_cie_status_map();
    return $map[$status]['label'] ?? ucfirst(str_replace('_', ' ', $status));
}

function cittadino_cie_status_badge(string $status): string
{
    $map = cittadino_cie_status_map();
    return $map[$status]['badge'] ?? 'badge bg-dark';
}

function cittadino_cie_generate_code(PDO $pdo): string
{
    $year = (new DateTimeImmutable('now'))->format('Y');
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM cie_prenotazioni WHERE YEAR(created_at) = :year');
    $stmt->execute([':year' => $year]);
    $count = (int) $stmt->fetchColumn();
    return sprintf('CIE-%s-%05d', $year, $count + 1);
}

function cittadino_cie_fetch_requests(PDO $pdo, array $filters = []): array
{
    $sql = 'SELECT cp.*, 
                u.username AS operator_username,
                c.ragione_sociale AS cliente_ragione_sociale,
                c.nome AS cliente_nome,
                c.cognome AS cliente_cognome
            FROM cie_prenotazioni cp
            LEFT JOIN users u ON cp.operator_id = u.id
            LEFT JOIN clienti c ON cp.cliente_id = c.id';

    $where = [];
    $params = [];

    if (!empty($filters['stato']) && in_array($filters['stato'], cittadino_cie_allowed_statuses(), true)) {
        $where[] = 'cp.stato = :stato';
        $params[':stato'] = $filters['stato'];
    }

    if (!empty($filters['search'])) {
        $where[] = '(cp.request_code LIKE :search
            OR cp.cittadino_nome LIKE :search
            OR cp.cittadino_cognome LIKE :search
            OR cp.cittadino_cf LIKE :search
            OR cp.comune LIKE :search)';
        $params[':search'] = '%' . $filters['search'] . '%';
    }

    if (!empty($filters['comune'])) {
        $where[] = 'cp.comune = :comune';
        $params[':comune'] = $filters['comune'];
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY cp.created_at DESC, cp.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll() ?: [];
}

function cittadino_cie_fetch_request(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT cp.*, 
            u.username AS operator_username,
            u.nome AS operator_nome,
            u.cognome AS operator_cognome,
            c.ragione_sociale AS cliente_ragione_sociale,
            c.nome AS cliente_nome,
            c.cognome AS cliente_cognome
        FROM cie_prenotazioni cp
        LEFT JOIN users u ON cp.operator_id = u.id
        LEFT JOIN clienti c ON cp.cliente_id = c.id
        WHERE cp.id = :id');
    $stmt->execute([':id' => $id]);

    $result = $stmt->fetch();
    return $result ?: null;
}

function cittadino_cie_fetch_stats(PDO $pdo): array
{
    $statuses = array_fill_keys(cittadino_cie_allowed_statuses(), 0);

    $stmt = $pdo->query('SELECT stato, COUNT(*) AS total FROM cie_prenotazioni GROUP BY stato');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = (string) ($row['stato'] ?? '');
        if (isset($statuses[$key])) {
            $statuses[$key] = (int) $row['total'];
        }
    }

    return $statuses;
}

function cittadino_cie_log(PDO $pdo, string $action, string $details): void
{
    try {
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $stmt = $pdo->prepare('INSERT INTO log_attivita (user_id, modulo, azione, dettagli, created_at)
            VALUES (:user_id, :modulo, :azione, :dettagli, NOW())');
        $stmt->execute([
            ':user_id' => $userId ?: null,
            ':modulo' => CITTADINO_CIE_MODULE_LOG,
            ':azione' => $action,
            ':dettagli' => $details,
        ]);
    } catch (Throwable $exception) {
        error_log('CIE log error: ' . $exception->getMessage());
    }
}

function cittadino_cie_create(PDO $pdo, array $data): int
{
    $code = cittadino_cie_generate_code($pdo);
    $operatorId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

    $stmt = $pdo->prepare('INSERT INTO cie_prenotazioni
        (request_code, cliente_id, cittadino_nome, cittadino_cognome, cittadino_cf, cittadino_email, cittadino_telefono,
         comune, comune_codice, preferenza_data, preferenza_fascia, stato, slot_data, slot_orario, slot_protocollo,
         slot_note, note, operator_id, prenotato_il, completato_il, annullato_il)
        VALUES
        (:request_code, :cliente_id, :cittadino_nome, :cittadino_cognome, :cittadino_cf, :cittadino_email, :cittadino_telefono,
         :comune, :comune_codice, :preferenza_data, :preferenza_fascia, :stato, :slot_data, :slot_orario, :slot_protocollo,
         :slot_note, :note, :operator_id, :prenotato_il, :completato_il, :annullato_il)');

    $stmt->execute([
        ':request_code' => $code,
        ':cliente_id' => $data['cliente_id'] ?? null,
        ':cittadino_nome' => $data['cittadino_nome'],
        ':cittadino_cognome' => $data['cittadino_cognome'],
        ':cittadino_cf' => $data['cittadino_cf'] ?? null,
        ':cittadino_email' => $data['cittadino_email'] ?? null,
        ':cittadino_telefono' => $data['cittadino_telefono'] ?? null,
        ':comune' => $data['comune'],
        ':comune_codice' => $data['comune_codice'] ?? null,
        ':preferenza_data' => $data['preferenza_data'] ?? null,
        ':preferenza_fascia' => $data['preferenza_fascia'] ?? null,
        ':stato' => $data['stato'] ?? 'nuova',
        ':slot_data' => $data['slot_data'] ?? null,
        ':slot_orario' => $data['slot_orario'] ?? null,
        ':slot_protocollo' => $data['slot_protocollo'] ?? null,
        ':slot_note' => $data['slot_note'] ?? null,
        ':note' => $data['note'] ?? null,
        ':operator_id' => $operatorId ?: null,
        ':prenotato_il' => $data['stato'] === 'prenotata' ? (new DateTimeImmutable('now'))->format('Y-m-d H:i:s') : null,
        ':completato_il' => $data['stato'] === 'completata' ? (new DateTimeImmutable('now'))->format('Y-m-d H:i:s') : null,
        ':annullato_il' => $data['stato'] === 'annullata' ? (new DateTimeImmutable('now'))->format('Y-m-d H:i:s') : null,
    ]);

    $id = (int) $pdo->lastInsertId();
    cittadino_cie_log($pdo, 'Creazione richiesta', 'Richiesta CIE #' . $id . ' creata');

    return $id;
}

function cittadino_cie_update(PDO $pdo, int $id, array $data): bool
{
    $id = (int) $id;
    if ($id <= 0) {
        return false;
    }

    $fields = [
        'cliente_id = :cliente_id',
        'cittadino_nome = :cittadino_nome',
        'cittadino_cognome = :cittadino_cognome',
        'cittadino_cf = :cittadino_cf',
        'cittadino_email = :cittadino_email',
        'cittadino_telefono = :cittadino_telefono',
        'comune = :comune',
        'comune_codice = :comune_codice',
        'preferenza_data = :preferenza_data',
        'preferenza_fascia = :preferenza_fascia',
        'slot_data = :slot_data',
        'slot_orario = :slot_orario',
        'slot_protocollo = :slot_protocollo',
        'slot_note = :slot_note',
        'note = :note',
        'stato = :stato',
    ];

    $now = new DateTimeImmutable('now');
    $params = [
        ':id' => $id,
        ':cliente_id' => $data['cliente_id'] ?? null,
        ':cittadino_nome' => $data['cittadino_nome'],
        ':cittadino_cognome' => $data['cittadino_cognome'],
        ':cittadino_cf' => $data['cittadino_cf'] ?? null,
        ':cittadino_email' => $data['cittadino_email'] ?? null,
        ':cittadino_telefono' => $data['cittadino_telefono'] ?? null,
        ':comune' => $data['comune'],
        ':comune_codice' => $data['comune_codice'] ?? null,
        ':preferenza_data' => $data['preferenza_data'] ?? null,
        ':preferenza_fascia' => $data['preferenza_fascia'] ?? null,
        ':slot_data' => $data['slot_data'] ?? null,
        ':slot_orario' => $data['slot_orario'] ?? null,
        ':slot_protocollo' => $data['slot_protocollo'] ?? null,
        ':slot_note' => $data['slot_note'] ?? null,
        ':note' => $data['note'] ?? null,
        ':stato' => $data['stato'],
    ];

    if ($data['stato'] === 'prenotata') {
        $fields[] = 'prenotato_il = COALESCE(prenotato_il, :prenotato_il)';
        $params[':prenotato_il'] = $now->format('Y-m-d H:i:s');
    } elseif ($data['stato'] === 'completata') {
        $fields[] = 'completato_il = COALESCE(completato_il, :completato_il)';
        $params[':completato_il'] = $now->format('Y-m-d H:i:s');
    } elseif ($data['stato'] === 'annullata') {
        $fields[] = 'annullato_il = COALESCE(annullato_il, :annullato_il)';
        $params[':annullato_il'] = $now->format('Y-m-d H:i:s');
    }

    $sql = 'UPDATE cie_prenotazioni SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $updated = $stmt->execute($params);

    if ($updated) {
        cittadino_cie_log($pdo, 'Aggiornamento richiesta', 'Richiesta CIE #' . $id . ' aggiornata');
    }

    return $updated;
}

function cittadino_cie_update_status(PDO $pdo, int $id, string $status): bool
{
    $allowed = cittadino_cie_allowed_statuses();
    if (!in_array($status, $allowed, true)) {
        return false;
    }

    $id = (int) $id;
    if ($id <= 0) {
        return false;
    }

    $now = new DateTimeImmutable('now');

    $fields = ['stato = :stato'];
    $params = [':id' => $id, ':stato' => $status];

    if ($status === 'prenotata') {
        $fields[] = 'prenotato_il = COALESCE(prenotato_il, :prenotato_il)';
        $params[':prenotato_il'] = $now->format('Y-m-d H:i:s');
    } elseif ($status === 'completata') {
        $fields[] = 'completato_il = COALESCE(completato_il, :completato_il)';
        $params[':completato_il'] = $now->format('Y-m-d H:i:s');
    } elseif ($status === 'annullata') {
        $fields[] = 'annullato_il = COALESCE(annullato_il, :annullato_il)';
        $params[':annullato_il'] = $now->format('Y-m-d H:i:s');
    }

    $sql = 'UPDATE cie_prenotazioni SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $updated = $stmt->execute($params);

    if ($updated) {
        cittadino_cie_log($pdo, 'Cambio stato', 'Richiesta CIE #' . $id . ' impostata a ' . $status);
    }

    return $updated;
}

function cittadino_cie_delete(PDO $pdo, int $id): bool
{
    $id = (int) $id;
    if ($id <= 0) {
        return false;
    }

    $stmt = $pdo->prepare('DELETE FROM cie_prenotazioni WHERE id = :id');
    $deleted = $stmt->execute([':id' => $id]);

    if ($deleted) {
        cittadino_cie_log($pdo, 'Eliminazione richiesta', 'Richiesta CIE #' . $id . ' eliminata');
    }

    return $deleted;
}

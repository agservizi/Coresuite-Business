<?php
declare(strict_types=1);

if (!defined('CORESUITE_PICKUP_BOOTSTRAP')) {
    http_response_code(403);
    exit('Accesso non autorizzato.');
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../../includes/mailer.php';
require_once __DIR__ . '/../../../includes/helpers.php';

const PICKUP_STATUS_MAP = [
    'in_arrivo' => ['label' => 'In Arrivo'],
    'consegnato' => ['label' => 'Consegnato'],
    'ritirato' => ['label' => 'Ritirato'],
    'in_giacenza' => ['label' => 'In Giacenza'],
];

const PICKUP_DEFAULT_ARCHIVE_DAYS = 30;

function pickup_statuses(): array
{
    return array_keys(PICKUP_STATUS_MAP);
}

function pickup_status_label(string $status): string
{
    return PICKUP_STATUS_MAP[$status]['label'] ?? ucfirst(str_replace('_', ' ', $status));
}

function clean_input(?string $value, int $maxLength = 255): string
{
    if ($value === null) {
        return '';
    }

    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = strip_tags($value);
    $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value) ?? '';
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_substr')) {
        $value = mb_substr($value, 0, $maxLength, 'UTF-8');
    } else {
        $value = substr($value, 0, $maxLength);
    }

    return $value;
}

function ensure_pickup_tables(): void
{
    create_couriers_table();
    create_packages_table();
    create_notifications_table();
}

function create_couriers_table(): void
{
    $pdo = pickup_db();
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS pickup_couriers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    logo_url VARCHAR(255) NULL,
    contact_name VARCHAR(120) NULL,
    support_email VARCHAR(160) NULL,
    support_phone VARCHAR(40) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pickup_courier_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
    $pdo->exec($sql);
}

function create_packages_table(): void
{
    $pdo = pickup_db();
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS pickup_packages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tracking VARCHAR(100) NOT NULL,
    customer_name VARCHAR(150) NOT NULL,
    customer_phone VARCHAR(50) NOT NULL,
    courier_id INT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'in_arrivo',
    expected_at DATETIME NULL,
    notes TEXT NULL,
    archived_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pickup_tracking (tracking),
    INDEX idx_pickup_status (status),
    INDEX idx_pickup_courier (courier_id),
    INDEX idx_pickup_created (created_at),
    INDEX idx_pickup_archived (archived_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
    $pdo->exec($sql);

    if (!pickup_foreign_key_exists('pickup_packages', 'pickup_packages_courier_fk')) {
        $pdo->exec('ALTER TABLE pickup_packages ADD CONSTRAINT pickup_packages_courier_fk FOREIGN KEY (courier_id) REFERENCES pickup_couriers(id) ON DELETE SET NULL ON UPDATE CASCADE');
    }
}

function create_notifications_table(): void
{
    $pdo = pickup_db();
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS pickup_notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    package_id INT UNSIGNED NOT NULL,
    channel VARCHAR(20) NOT NULL,
    status VARCHAR(80) NOT NULL,
    message TEXT NOT NULL,
    meta TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pickup_notification_package (package_id),
    INDEX idx_pickup_notification_channel (channel),
    CONSTRAINT pickup_notifications_package_fk FOREIGN KEY (package_id) REFERENCES pickup_packages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
    $pdo->exec($sql);
}

function pickup_foreign_key_exists(string $table, string $constraint): bool
{
    $pdo = pickup_db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :table AND CONSTRAINT_NAME = :constraint');
    $stmt->execute([
        ':table' => $table,
        ':constraint' => $constraint,
    ]);
    return (int) $stmt->fetchColumn() > 0;
}

function add_package(array $data): int
{
    $pdo = pickup_db();

    $tracking = clean_input($data['tracking'] ?? '', 100);
    $customerName = clean_input($data['customer_name'] ?? '', 150);
    $customerPhone = clean_input($data['customer_phone'] ?? '', 50);
    $status = clean_input($data['status'] ?? 'in_arrivo', 20);
    $courierId = isset($data['courier_id']) ? (int) $data['courier_id'] : null;
    $expectedAt = clean_input($data['expected_at'] ?? '', 32);
    $notes = clean_input($data['notes'] ?? '', 500);

    if ($tracking === '' || $customerName === '' || $customerPhone === '') {
        throw new InvalidArgumentException('Tracking, nome cliente e telefono sono obbligatori.');
    }

    if (!in_array($status, pickup_statuses(), true)) {
        throw new InvalidArgumentException('Stato pacco non valido.');
    }

    if ($courierId !== null && $courierId > 0 && !courier_exists($courierId)) {
        throw new InvalidArgumentException('Corriere selezionato non valido.');
    }

    $stmt = $pdo->prepare('SELECT id FROM pickup_packages WHERE tracking = :tracking LIMIT 1');
    $stmt->execute([':tracking' => $tracking]);
    if ($stmt->fetch()) {
        throw new RuntimeException('Tracking già registrato.');
    }

    $expectedDate = null;
    if ($expectedAt !== '') {
        $expectedDate = \DateTime::createFromFormat('Y-m-d', $expectedAt) ?: \DateTime::createFromFormat('Y-m-d H:i', $expectedAt);
        if (!$expectedDate) {
            throw new InvalidArgumentException('Data prevista non valida.');
        }
        $expectedDate = $expectedDate->format('Y-m-d H:i:s');
    }

    $stmt = $pdo->prepare('INSERT INTO pickup_packages (tracking, customer_name, customer_phone, courier_id, status, expected_at, notes) VALUES (:tracking, :customer_name, :customer_phone, :courier_id, :status, :expected_at, :notes)');
    $stmt->execute([
        ':tracking' => $tracking,
        ':customer_name' => $customerName,
        ':customer_phone' => $customerPhone,
        ':courier_id' => $courierId ?: null,
        ':status' => $status,
        ':expected_at' => $expectedDate,
        ':notes' => $notes !== '' ? $notes : null,
    ]);

    return (int) $pdo->lastInsertId();
}

function update_package_status(int $packageId, string $status): array
{
    $status = clean_input($status, 20);
    if (!in_array($status, pickup_statuses(), true)) {
        throw new InvalidArgumentException('Stato pacco non valido.');
    }

    $pdo = pickup_db();
    $stmt = $pdo->prepare('UPDATE pickup_packages SET status = :status, updated_at = NOW() WHERE id = :id');
    $stmt->execute([
        ':status' => $status,
        ':id' => $packageId,
    ]);

    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('Pacco non trovato o stato invariato.');
    }

    $details = get_package_details($packageId);
    if (!$details) {
        throw new RuntimeException('Impossibile recuperare i dettagli del pacco aggiornato.');
    }

    return $details;
}

function update_package(int $packageId, array $data): bool
{
    $pdo = pickup_db();
    $existing = get_package_details($packageId);
    if (!$existing) {
        throw new RuntimeException('Pacco non trovato.');
    }

    $tracking = clean_input($data['tracking'] ?? '', 100);
    $customerName = clean_input($data['customer_name'] ?? '', 150);
    $customerPhone = clean_input($data['customer_phone'] ?? '', 50);
    $status = clean_input($data['status'] ?? $existing['status'], 20);
    $courierId = isset($data['courier_id']) ? (int) $data['courier_id'] : null;
    $expectedAt = clean_input($data['expected_at'] ?? '', 32);
    $notes = clean_input($data['notes'] ?? '', 500);

    if ($tracking === '' || $customerName === '' || $customerPhone === '') {
        throw new InvalidArgumentException('Tracking, nome cliente e telefono sono obbligatori.');
    }

    if (!in_array($status, pickup_statuses(), true)) {
        throw new InvalidArgumentException('Stato pacco non valido.');
    }

    if ($courierId !== null && $courierId > 0 && !courier_exists($courierId)) {
        throw new InvalidArgumentException('Corriere selezionato non valido.');
    }

    if (strcasecmp($tracking, (string) $existing['tracking']) !== 0) {
        $stmt = $pdo->prepare('SELECT id FROM pickup_packages WHERE tracking = :tracking AND id <> :id LIMIT 1');
        $stmt->execute([
            ':tracking' => $tracking,
            ':id' => $packageId,
        ]);
        if ($stmt->fetch()) {
            throw new RuntimeException('Tracking già registrato su un altro pacco.');
        }
    }

    $expectedDate = null;
    if ($expectedAt !== '') {
        $expectedDate = \DateTime::createFromFormat('Y-m-d', $expectedAt) ?: \DateTime::createFromFormat('Y-m-d H:i', $expectedAt);
        if (!$expectedDate) {
            throw new InvalidArgumentException('Data prevista non valida.');
        }
        $expectedDate = $expectedDate->format('Y-m-d H:i:s');
    }

    $stmt = $pdo->prepare('UPDATE pickup_packages SET tracking = :tracking, customer_name = :customer_name, customer_phone = :customer_phone, courier_id = :courier_id, status = :status, expected_at = :expected_at, notes = :notes, updated_at = NOW() WHERE id = :id');
    $stmt->execute([
        ':tracking' => $tracking,
        ':customer_name' => $customerName,
        ':customer_phone' => $customerPhone,
        ':courier_id' => $courierId ?: null,
        ':status' => $status,
        ':expected_at' => $expectedDate,
        ':notes' => $notes !== '' ? $notes : null,
        ':id' => $packageId,
    ]);

    return $stmt->rowCount() > 0;
}

function delete_package(int $packageId): bool
{
    $pdo = pickup_db();
    $stmt = $pdo->prepare('DELETE FROM pickup_packages WHERE id = :id');
    $stmt->execute([':id' => $packageId]);
    return $stmt->rowCount() > 0;
}

function get_all_packages(array $filters = []): array
{
    $pdo = pickup_db();
    $conditions = [];
    $params = [];

    $conditions[] = '1 = 1';

    $archived = (bool) ($filters['archived'] ?? false);
    if ($archived) {
        $conditions[] = 'p.archived_at IS NOT NULL';
    } else {
        $conditions[] = 'p.archived_at IS NULL';
    }

    if (!empty($filters['status']) && in_array($filters['status'], pickup_statuses(), true)) {
        $conditions[] = 'p.status = :status';
        $params[':status'] = $filters['status'];
    }

    if (!empty($filters['courier_id'])) {
        $conditions[] = 'p.courier_id = :courier_id';
        $params[':courier_id'] = (int) $filters['courier_id'];
    }

    if (!empty($filters['search'])) {
        $search = '%' . $filters['search'] . '%';
        $conditions[] = '(p.tracking LIKE :search OR p.customer_name LIKE :search OR p.customer_phone LIKE :search)';
        $params[':search'] = $search;
    }

    if (!empty($filters['from'])) {
        $conditions[] = 'p.created_at >= :from';
        $params[':from'] = $filters['from'] . ' 00:00:00';
    }

    if (!empty($filters['to'])) {
        $conditions[] = 'p.created_at <= :to';
        $params[':to'] = $filters['to'] . ' 23:59:59';
    }

    $sql = 'SELECT p.*, c.name AS courier_name, c.logo_url, c.support_email, c.support_phone
        FROM pickup_packages p
        LEFT JOIN pickup_couriers c ON c.id = p.courier_id
        WHERE ' . implode(' AND ', $conditions) . '
        ORDER BY p.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function get_packages_by_status(string $status): array
{
    return get_all_packages(['status' => $status]);
}

function search_packages(string $query, int $limit = 25): array
{
    $pdo = pickup_db();
    $query = clean_input($query, 120);
    if ($query === '') {
        return [];
    }

    $stmt = $pdo->prepare('SELECT p.*, c.name AS courier_name FROM pickup_packages p LEFT JOIN pickup_couriers c ON c.id = p.courier_id WHERE p.archived_at IS NULL AND (p.tracking LIKE :query OR p.customer_name LIKE :query) ORDER BY p.created_at DESC LIMIT :limit');
    $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_package_details(int $packageId): ?array
{
    $pdo = pickup_db();
    $stmt = $pdo->prepare('SELECT p.*, c.name AS courier_name, c.support_email, c.support_phone FROM pickup_packages p LEFT JOIN pickup_couriers c ON c.id = p.courier_id WHERE p.id = :id');
    $stmt->execute([':id' => $packageId]);
    $result = $stmt->fetch();
    return $result ?: null;
}

function courier_exists(int $courierId): bool
{
    $pdo = pickup_db();
    $stmt = $pdo->prepare('SELECT 1 FROM pickup_couriers WHERE id = :id');
    $stmt->execute([':id' => $courierId]);
    return (bool) $stmt->fetchColumn();
}

function add_courier(array $data): int
{
    $pdo = pickup_db();
    $name = clean_input($data['name'] ?? '', 120);
    if ($name === '') {
        throw new InvalidArgumentException('Il nome del corriere è obbligatorio.');
    }

    $logoUrl = clean_input($data['logo_url'] ?? '', 255);
    $contactName = clean_input($data['contact_name'] ?? '', 120);
    $supportEmail = clean_input($data['support_email'] ?? '', 160);
    $supportPhone = clean_input($data['support_phone'] ?? '', 40);

    if ($supportEmail !== '' && !filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Email assistenza non valida.');
    }

    $stmt = $pdo->prepare('INSERT INTO pickup_couriers (name, logo_url, contact_name, support_email, support_phone) VALUES (:name, :logo_url, :contact_name, :support_email, :support_phone)');
    $stmt->execute([
        ':name' => $name,
        ':logo_url' => $logoUrl !== '' ? $logoUrl : null,
        ':contact_name' => $contactName !== '' ? $contactName : null,
        ':support_email' => $supportEmail !== '' ? $supportEmail : null,
        ':support_phone' => $supportPhone !== '' ? $supportPhone : null,
    ]);

    return (int) $pdo->lastInsertId();
}

function update_courier(int $courierId, array $data): bool
{
    if (!courier_exists($courierId)) {
        throw new InvalidArgumentException('Corriere non trovato.');
    }

    $pdo = pickup_db();
    $name = clean_input($data['name'] ?? '', 120);
    if ($name === '') {
        throw new InvalidArgumentException('Il nome del corriere è obbligatorio.');
    }

    $logoUrl = clean_input($data['logo_url'] ?? '', 255);
    $contactName = clean_input($data['contact_name'] ?? '', 120);
    $supportEmail = clean_input($data['support_email'] ?? '', 160);
    $supportPhone = clean_input($data['support_phone'] ?? '', 40);

    if ($supportEmail !== '' && !filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Email assistenza non valida.');
    }

    $stmt = $pdo->prepare('UPDATE pickup_couriers SET name = :name, logo_url = :logo_url, contact_name = :contact_name, support_email = :support_email, support_phone = :support_phone WHERE id = :id');
    $stmt->execute([
        ':name' => $name,
        ':logo_url' => $logoUrl !== '' ? $logoUrl : null,
        ':contact_name' => $contactName !== '' ? $contactName : null,
        ':support_email' => $supportEmail !== '' ? $supportEmail : null,
        ':support_phone' => $supportPhone !== '' ? $supportPhone : null,
        ':id' => $courierId,
    ]);

    return $stmt->rowCount() > 0;
}

function delete_courier(int $courierId): bool
{
    $pdo = pickup_db();
    $stmt = $pdo->prepare('UPDATE pickup_packages SET courier_id = NULL WHERE courier_id = :id');
    $stmt->execute([':id' => $courierId]);

    $stmt = $pdo->prepare('DELETE FROM pickup_couriers WHERE id = :id');
    $stmt->execute([':id' => $courierId]);
    return $stmt->rowCount() > 0;
}

function get_all_couriers(): array
{
    $pdo = pickup_db();
    $stmt = $pdo->query('SELECT * FROM pickup_couriers ORDER BY name ASC');
    return $stmt->fetchAll();
}

function log_notification(int $packageId, string $type, string $status, string $message = '', ?array $meta = null): int
{
    $pdo = pickup_db();
    $metaValue = null;
    if ($meta) {
        try {
            $metaValue = json_encode($meta, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $exception) {
            error_log('Serializzazione log notifiche fallita: ' . $exception->getMessage());
        }
    }

    $stmt = $pdo->prepare('INSERT INTO pickup_notifications (package_id, channel, status, message, meta) VALUES (:package_id, :channel, :status, :message, :meta)');
    $stmt->execute([
        ':package_id' => $packageId,
        ':channel' => clean_input($type, 20),
        ':status' => clean_input($status, 80),
        ':message' => $message,
        ':meta' => $metaValue,
    ]);

    return (int) $pdo->lastInsertId();
}

function send_notification_email(string $email, string $subject, string $message): bool
{
    $email = filter_var($email, FILTER_VALIDATE_EMAIL);
    if ($email === false) {
        throw new InvalidArgumentException('Indirizzo email non valido.');
    }

    $subject = clean_input($subject, 160);
    if ($subject === '') {
        $subject = 'Aggiornamento pacco pickup';
    }

    $bodyContent = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    $htmlBody = render_mail_template($subject, '<p style="font-size:15px;">' . $bodyContent . '</p>');

    return send_system_mail($email, $subject, $htmlBody);
}

function send_notification_whatsapp(string $phone, string $message): bool
{
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if ($phone === '') {
        throw new InvalidArgumentException('Numero di telefono non valido.');
    }

    $endpoint = env('WHATSAPP_API_URL', '');
    $token = env('WHATSAPP_API_TOKEN', '');

    if ($endpoint === '' || $token === '') {
        error_log('WhatsApp API non configurata.');
        return false;
    }

    if (!function_exists('curl_init')) {
        error_log('cURL non disponibile per le notifiche WhatsApp.');
        return false;
    }

    $payload = [
        'to' => $phone,
        'message' => $message,
    ];

    try {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    } catch (\JsonException $exception) {
        error_log('Serializzazione WhatsApp fallita: ' . $exception->getMessage());
        return false;
    }

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $json,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $error !== '') {
        error_log('Invio WhatsApp fallito: ' . ($error !== '' ? $error : 'risposta vuota'));
        return false;
    }

    if ($status >= 200 && $status < 300) {
        return true;
    }

    error_log('WhatsApp API status: ' . $status . ' body: ' . $response);
    return false;
}

function generate_statistics(array $filters = []): array
{
    $pdo = pickup_db();
    $conditions = [];
    $params = [];

    $conditions[] = 'p.archived_at IS NULL';

    if (!empty($filters['from'])) {
        $conditions[] = 'p.created_at >= :from';
        $params[':from'] = $filters['from'] . ' 00:00:00';
    }

    if (!empty($filters['to'])) {
        $conditions[] = 'p.created_at <= :to';
        $params[':to'] = $filters['to'] . ' 23:59:59';
    }

    $sql = 'SELECT p.status, COUNT(*) AS total FROM pickup_packages p WHERE ' . implode(' AND ', $conditions) . ' GROUP BY p.status';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $data = array_fill_keys(pickup_statuses(), 0);
    foreach ($stmt->fetchAll() as $row) {
        $status = $row['status'];
        $data[$status] = (int) $row['total'];
    }

    $data['totale'] = array_sum($data);
    return $data;
}

function export_packages_csv(array $filters = []): void
{
    $rows = get_all_packages($filters);
    $filename = 'pickup_packages_' . date('Ymd_His') . '.csv';

    if (ob_get_length()) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Tracking', 'Cliente', 'Telefono', 'Corriere', 'Stato', 'Creato il', 'Aggiornato il']);

    foreach ($rows as $row) {
        fputcsv($output, [
            $row['id'],
            $row['tracking'],
            $row['customer_name'],
            $row['customer_phone'],
            $row['courier_name'] ?? 'N/D',
            pickup_status_label($row['status']),
            $row['created_at'],
            $row['updated_at'],
        ]);
    }

    fclose($output);
    exit;
}

function export_packages_pdf(array $filters = []): void
{
    $rows = get_all_packages($filters);
    $filename = 'pickup_packages_' . date('Ymd_His') . '.pdf';

    $lines = [];
    $lines[] = 'Report pacchi pickup - generato il ' . date('d/m/Y H:i');
    $lines[] = str_repeat('-', 110);

    $pad = static function (string $value, int $length): string {
        if (function_exists('mb_substr')) {
            $value = mb_substr($value, 0, $length, 'UTF-8');
        } else {
            $value = substr($value, 0, $length);
        }
        return str_pad($value, $length, ' ');
    };

    $lines[] = $pad('ID', 6) . $pad('Tracking', 16) . $pad('Cliente', 26) . $pad('Telefono', 16) . $pad('Corriere', 18) . $pad('Stato', 12) . 'Aggiornato';

    foreach ($rows as $row) {
        $lines[] = $pad((string) $row['id'], 6)
            . $pad($row['tracking'], 16)
            . $pad($row['customer_name'], 26)
            . $pad($row['customer_phone'], 16)
            . $pad($row['courier_name'] ?? 'N/D', 18)
            . $pad(pickup_status_label($row['status']), 12)
            . substr($row['updated_at'], 0, 19);
    }

    if (!$rows) {
        $lines[] = 'Nessun pacco disponibile per i filtri selezionati.';
    }

    $pages = array_chunk($lines, 42);
    $pdfBinary = pickup_build_pdf($pages);

    if (ob_get_length()) {
        ob_end_clean();
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $pdfBinary;
    exit;
}

function pickup_build_pdf(array $pages): string
{
    if (!$pages) {
        $pages = [['Report vuoto']];
    }

    $objects = [''];
    $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[] = '';
    $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

    $kids = [];
    foreach ($pages as $pageLines) {
        $stream = "BT\n/F1 11 Tf\n14 TL\n40 780 Td\n";
        foreach (array_values($pageLines) as $index => $line) {
            $escaped = pickup_escape_pdf_text($line);
            if ($index === 0) {
                $stream .= '(' . $escaped . ") Tj\n";
            } else {
                $stream .= "T*\n(" . $escaped . ") Tj\n";
            }
        }
        $stream .= "ET\n";

        $contentObject = sprintf("<< /Length %d >>\nstream\n%sendstream", strlen($stream), $stream);
        $objects[] = $contentObject;
        $contentId = array_key_last($objects);

        $pageObject = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents %d 0 R >>', $contentId);
        $objects[] = $pageObject;
        $pageId = array_key_last($objects);
        $kids[] = $pageId . ' 0 R';
    }

    $objects[2] = sprintf('<< /Type /Pages /Count %d /Kids [%s] >>', count($kids), implode(' ', $kids));

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    $maxId = array_key_last($objects);

    for ($i = 1; $i <= $maxId; $i++) {
        if (!isset($objects[$i]) || $objects[$i] === '') {
            continue;
        }
        $offsets[$i] = strlen($pdf);
        $pdf .= $i . " 0 obj\n" . $objects[$i] . "\nendobj\n";
    }

    $xrefStart = strlen($pdf);
    $pdf .= 'xref\n0 ' . ($maxId + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= $maxId; $i++) {
        $offset = $offsets[$i] ?? 0;
        $pdf .= sprintf('%010d 00000 n ' . "\n", $offset);
    }
    $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefStart . "\n%%EOF";

    return $pdf;
}

function pickup_escape_pdf_text(string $text): string
{
    $text = str_replace(["\\", "(", ")"], ['\\\\', '\\(', '\\)'], $text);
    $text = preg_replace('/[\r\n\t]+/', ' ', $text) ?? $text;
    return $text;
}

function archive_old_packages(int $days = PICKUP_DEFAULT_ARCHIVE_DAYS): int
{
    $pdo = pickup_db();
    $stmt = $pdo->prepare('UPDATE pickup_packages SET archived_at = NOW() WHERE status = :status AND archived_at IS NULL AND updated_at < DATE_SUB(NOW(), INTERVAL :days DAY)');
    $stmt->bindValue(':status', 'ritirato', PDO::PARAM_STR);
    $stmt->bindValue(':days', $days, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->rowCount();
}

function get_recent_notifications(int $limit = 10): array
{
    $pdo = pickup_db();
    $stmt = $pdo->prepare('SELECT n.*, p.tracking FROM pickup_notifications n INNER JOIN pickup_packages p ON p.id = n.package_id ORDER BY n.created_at DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_notifications_for_package(int $packageId, int $limit = 20): array
{
    $pdo = pickup_db();
    $stmt = $pdo->prepare('SELECT * FROM pickup_notifications WHERE package_id = :package_id ORDER BY created_at DESC LIMIT :limit');
    $stmt->bindValue(':package_id', $packageId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

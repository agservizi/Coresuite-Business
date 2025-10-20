<?php
declare(strict_types=1);

use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use Throwable;

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
$limit = max(1, min($limit, 40));

try {
    $notifications = fetch_global_notifications($pdo, $limit);
    $now = new DateTimeImmutable('now');

    $payload = [
        'total' => count($notifications),
        'items' => array_map(
            static function (array $item): array {
                return [
                    'id' => $item['id'] ?? '',
                    'type' => $item['type'] ?? '',
                    'icon' => $item['icon'] ?? 'fa-bell',
                    'title' => $item['title'] ?? 'Notifica',
                    'message' => $item['message'] ?? '',
                    'meta' => $item['meta'] ?? '',
                    'timestamp' => $item['timestamp'] ?? '',
                    'timeLabel' => $item['timeLabel'] ?? '',
                    'url' => $item['url'] ?? '',
                ];
            },
            $notifications
        ),
        'refreshedAt' => $now->format(DateTimeInterface::ATOM),
    ];

    echo json_encode($payload, JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log('Notifications API failed: ' . $exception->getMessage());
    http_response_code(500);
    try {
        echo json_encode(['error' => 'Impossibile recuperare le notifiche.'], JSON_THROW_ON_ERROR);
    } catch (JsonException $jsonException) {
        echo '{"error":"Notifications unavailable"}';
    }
}

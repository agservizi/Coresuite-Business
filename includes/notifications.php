<?php
declare(strict_types=1);

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use PDO;
use PDOException;

if (!function_exists('fetch_global_notifications')) {
    /**
     * Recupera un elenco di notifiche globali dalle principali aree applicative.
     *
     * @return array<int, array<string, mixed>>
     */
    function fetch_global_notifications(PDO $pdo, int $limit = 10): array
    {
        $limit = max(1, min($limit, 40));
        $perSource = max($limit, 10);

        $items = [];
        $items = array_merge(
            $items,
            cs_notifications_from_finance($pdo, $perSource),
            cs_notifications_from_appointments($pdo, $perSource),
            cs_notifications_from_loyalty($pdo, $perSource)
        );

        usort($items, static fn(array $a, array $b): int => ($b['_sort'] ?? 0) <=> ($a['_sort'] ?? 0));

        $items = array_slice($items, 0, $limit);
        foreach ($items as &$item) {
            unset($item['_sort']);
        }
        unset($item);

        return $items;
    }
}

if (!function_exists('cs_notifications_from_finance')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function cs_notifications_from_finance(PDO $pdo, int $limit): array
    {
        $sql = 'SELECT eu.id, eu.tipo_movimento, eu.descrizione, eu.importo, eu.stato, eu.data_scadenza, eu.created_at, eu.updated_at, c.ragione_sociale, c.nome, c.cognome
            FROM entrate_uscite eu
            LEFT JOIN clienti c ON c.id = eu.cliente_id
            ORDER BY COALESCE(eu.updated_at, eu.created_at) DESC
            LIMIT :limit';

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $exception) {
            error_log('Finance notifications query failed: ' . $exception->getMessage());
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $timestamp = cs_notifications_parse_timestamp($row['updated_at'] ?? null, $row['created_at'] ?? null);
            $client = cs_notifications_client_label($row['ragione_sociale'] ?? null, $row['nome'] ?? null, $row['cognome'] ?? null);
            $state = trim((string) ($row['stato'] ?? ''));
            $movementLabel = ($row['tipo_movimento'] ?? '') === 'Uscita' ? 'Uscita' : 'Entrata';

            $messageParts = [];
            if (!empty($row['descrizione'])) {
                $messageParts[] = (string) $row['descrizione'];
            }
            if ($state !== '') {
                $messageParts[] = 'Stato: ' . $state;
            }
            $messageParts[] = 'Importo: ' . format_currency((float) ($row['importo'] ?? 0));
            if (!empty($row['data_scadenza'])) {
                $messageParts[] = 'Scadenza: ' . format_datetime((string) $row['data_scadenza'], 'd/m/Y');
            }

            $items[] = [
                'id' => 'entrate_uscite-' . (int) $row['id'],
                'type' => 'entrate_uscite',
                'icon' => $movementLabel === 'Uscita' ? 'fa-arrow-trend-down' : 'fa-arrow-trend-up',
                'title' => sprintf('%s #%d', $movementLabel, (int) $row['id']),
                'message' => implode(' - ', array_filter($messageParts, static fn($value) => $value !== '')),
                'meta' => $client !== '' ? 'Cliente: ' . $client : '',
                'url' => base_url('modules/servizi/entrate-uscite/view.php?id=' . (int) $row['id']),
                'timestamp' => $timestamp->format(DateTimeInterface::ATOM),
                'timeLabel' => format_datetime_locale($timestamp->format('Y-m-d H:i:s')),
                '_sort' => $timestamp->getTimestamp(),
            ];
        }

        return $items;
    }
}

if (!function_exists('cs_notifications_from_appointments')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function cs_notifications_from_appointments(PDO $pdo, int $limit): array
    {
        $sql = 'SELECT sa.id, sa.titolo, sa.tipo_servizio, sa.stato, sa.data_inizio, sa.data_fine, sa.created_at, sa.updated_at,
                       c.ragione_sociale, c.nome, c.cognome
                FROM servizi_appuntamenti sa
                LEFT JOIN clienti c ON c.id = sa.cliente_id
                ORDER BY COALESCE(sa.updated_at, sa.data_inizio, sa.created_at) DESC
                LIMIT :limit';

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $exception) {
            error_log('Appointments notifications query failed: ' . $exception->getMessage());
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $timestamp = cs_notifications_parse_timestamp($row['updated_at'] ?? null, $row['data_inizio'] ?? null, $row['created_at'] ?? null);
            $client = cs_notifications_client_label($row['ragione_sociale'] ?? null, $row['nome'] ?? null, $row['cognome'] ?? null);
            $status = trim((string) ($row['stato'] ?? ''));

            $messageParts = [];
            if (!empty($row['titolo'])) {
                $messageParts[] = (string) $row['titolo'];
            }
            if ($status !== '') {
                $messageParts[] = 'Stato: ' . $status;
            }
            if (!empty($row['data_inizio'])) {
                $messageParts[] = 'Inizio: ' . format_datetime_locale((string) $row['data_inizio']);
            }

            $items[] = [
                'id' => 'servizi_appuntamenti-' . (int) $row['id'],
                'type' => 'servizi_appuntamenti',
                'icon' => 'fa-calendar-check',
                'title' => sprintf('Appuntamento #%d', (int) $row['id']),
                'message' => implode(' - ', array_filter($messageParts, static fn($value) => $value !== '')),
                'meta' => $client !== '' ? 'Cliente: ' . $client : '',
                'url' => base_url('modules/servizi/ricariche/view.php?id=' . (int) $row['id']),
                'timestamp' => $timestamp->format(DateTimeInterface::ATOM),
                'timeLabel' => format_datetime_locale($timestamp->format('Y-m-d H:i:s')),
                '_sort' => $timestamp->getTimestamp(),
            ];
        }

        return $items;
    }
}

if (!function_exists('cs_notifications_from_loyalty')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function cs_notifications_from_loyalty(PDO $pdo, int $limit): array
    {
        $sql = 'SELECT fm.id, fm.tipo_movimento, fm.descrizione, fm.punti, fm.saldo_post_movimento, fm.operatore, fm.data_movimento, fm.created_at, fm.updated_at,
                       c.ragione_sociale, c.nome, c.cognome
                FROM fedelta_movimenti fm
                LEFT JOIN clienti c ON c.id = fm.cliente_id
                ORDER BY COALESCE(fm.updated_at, fm.data_movimento, fm.created_at) DESC
                LIMIT :limit';

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $exception) {
            error_log('Loyalty notifications query failed: ' . $exception->getMessage());
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $timestamp = cs_notifications_parse_timestamp($row['updated_at'] ?? null, $row['data_movimento'] ?? null, $row['created_at'] ?? null);
            $client = cs_notifications_client_label($row['ragione_sociale'] ?? null, $row['nome'] ?? null, $row['cognome'] ?? null);
            $movementType = trim((string) ($row['tipo_movimento'] ?? ''));
            $points = (int) ($row['punti'] ?? 0);
            $balance = (int) ($row['saldo_post_movimento'] ?? 0);
            $operator = trim((string) ($row['operatore'] ?? ''));

            $messageParts = [];
            if (!empty($row['descrizione'])) {
                $messageParts[] = (string) $row['descrizione'];
            }
            $messageParts[] = 'Punti: ' . ($points >= 0 ? '+' : '') . number_format($points, 0, ',', '.');
            $messageParts[] = 'Saldo: ' . number_format($balance, 0, ',', '.') . ' pt';

            $metaParts = [];
            if ($client !== '') {
                $metaParts[] = 'Cliente: ' . $client;
            }
            if ($operator !== '') {
                $metaParts[] = 'Operatore: ' . $operator;
            }

            $items[] = [
                'id' => 'fedelta_movimenti-' . (int) $row['id'],
                'type' => 'fedelta_movimenti',
                'icon' => $points >= 0 ? 'fa-star' : 'fa-star-half-stroke',
                'title' => $movementType !== '' ? sprintf('Fedeltà: %s', $movementType) : sprintf('Fedeltà #%d', (int) $row['id']),
                'message' => implode(' - ', array_filter($messageParts, static fn($value) => $value !== '')),
                'meta' => $metaParts ? implode(' | ', $metaParts) : '',
                'url' => base_url('modules/servizi/fedelta/view.php?id=' . (int) $row['id']),
                'timestamp' => $timestamp->format(DateTimeInterface::ATOM),
                'timeLabel' => format_datetime_locale($timestamp->format('Y-m-d H:i:s')),
                '_sort' => $timestamp->getTimestamp(),
            ];
        }

        return $items;
    }
}

if (!function_exists('cs_notifications_parse_timestamp')) {
    /**
     * @param string|null ...$candidates
     */
    function cs_notifications_parse_timestamp(?string ...$candidates): DateTimeImmutable
    {
        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }

            try {
                return new DateTimeImmutable($candidate);
            } catch (Exception $exception) {
                continue;
            }
        }

        return new DateTimeImmutable('now');
    }
}

if (!function_exists('cs_notifications_client_label')) {
    function cs_notifications_client_label(?string $company, ?string $firstName, ?string $lastName): string
    {
        $company = trim((string) $company);
        if ($company !== '') {
            return $company;
        }

        $first = trim((string) $firstName);
        $last = trim((string) $lastName);
        $full = trim($first . ' ' . $last);

        return $full;
    }
}

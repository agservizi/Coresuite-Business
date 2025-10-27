<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

/**
 * Servizio per la gestione dei pacchi e integrazione con il sistema pickup
 */
class PickupService {
    
    /**
     * Ottiene le statistiche del cliente
     */
    public function getCustomerStats(int $customerId): array {
        $stats = [];
        
        // Pacchi in attesa (segnalati ma non ancora arrivati)
        $stats['pending_packages'] = portal_count(
            'SELECT COUNT(*) FROM pickup_customer_reports WHERE customer_id = ? AND status = ?',
            [$customerId, 'reported']
        );
        
        // Pacchi pronti per il ritiro (arrivati)
        $stats['ready_packages'] = portal_count(
            'SELECT COUNT(*) FROM pickup_customer_reports r 
             LEFT JOIN pickup p ON r.pickup_id = p.id 
             WHERE r.customer_id = ? AND (p.status = ? OR p.status = ?)',
            [$customerId, 'consegnato', 'in_giacenza']
        );
        
        // Pacchi ritirati questo mese
        $stats['monthly_delivered'] = portal_count(
            'SELECT COUNT(*) FROM pickup_customer_reports r 
             LEFT JOIN pickup p ON r.pickup_id = p.id 
             WHERE r.customer_id = ? AND p.status = ? AND p.updated_at >= ?',
            [$customerId, 'ritirato', date('Y-m-01')]
        );
        
        // Totale pacchi
        $stats['total_packages'] = portal_count(
            'SELECT COUNT(*) FROM pickup_customer_reports WHERE customer_id = ?',
            [$customerId]
        );
        
        return $stats;
    }
    
    /**
     * Ottiene i pacchi del cliente
     */
    public function getCustomerPackages(int $customerId, array $options = []): array {
        $limit = $options['limit'] ?? 50;
        $offset = $options['offset'] ?? 0;
        $status = $options['status'] ?? null;
        
        $sql = 'SELECT r.*, p.status as pickup_status, p.courier_id, p.pickup_location_id,
                       p.delivered_at, p.created_at as pickup_created_at,
                       c.name as courier_name, l.name as location_name
                FROM pickup_customer_reports r
                LEFT JOIN pickup p ON r.pickup_id = p.id
                LEFT JOIN pickup_couriers c ON p.courier_id = c.id
                LEFT JOIN pickup_locations l ON p.pickup_location_id = l.id
                WHERE r.customer_id = ?';
        
        $params = [$customerId];
        
        if ($status) {
            $sql .= ' AND r.status = ?';
            $params[] = $status;
        }
        
        $sql .= ' ORDER BY r.created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;
        
        return portal_fetch_all($sql, $params);
    }
    
    /**
     * Ottiene un singolo pacco del cliente
     */
    public function getCustomerPackage(int $customerId, int $packageId): ?array {
        return portal_fetch_one(
            'SELECT r.*, p.status as pickup_status, p.courier_id, p.pickup_location_id,
                    p.delivered_at, p.created_at as pickup_created_at, p.tracking_number,
                    p.otp_code, p.signature_path, p.photo_path,
                    c.name as courier_name, l.name as location_name, l.address as location_address
             FROM pickup_customer_reports r
             LEFT JOIN pickup p ON r.pickup_id = p.id
             LEFT JOIN pickup_couriers c ON p.courier_id = c.id
             LEFT JOIN pickup_locations l ON p.pickup_location_id = l.id
             WHERE r.customer_id = ? AND r.id = ?',
            [$customerId, $packageId]
        );
    }
    
    /**
     * Segnala un nuovo pacco
     */
    public function reportPackage(int $customerId, array $data): array {
        $trackingCode = trim($data['tracking_code'] ?? '');
        $courierName = trim($data['courier_name'] ?? '');
        $recipientName = trim($data['recipient_name'] ?? '');
        $expectedDeliveryDate = $data['expected_delivery_date'] ?? null;
        $notes = trim($data['notes'] ?? '');
        $deliveryLocation = trim($data['delivery_location'] ?? '');
        
        if (empty($trackingCode)) {
            throw new Exception('Codice tracking richiesto');
        }
        
        if (strlen($trackingCode) < portal_config('min_tracking_length') || 
            strlen($trackingCode) > portal_config('max_tracking_length')) {
            throw new Exception('Codice tracking non valido');
        }
        
        // Verifica se il tracking è già stato segnalato dal cliente
        $existing = portal_fetch_one(
            'SELECT id FROM pickup_customer_reports WHERE customer_id = ? AND tracking_code = ?',
            [$customerId, $trackingCode]
        );
        
        if ($existing) {
            throw new Exception('Hai già segnalato un pacco con questo codice tracking');
        }
        
        $now = date('Y-m-d H:i:s');
        
        $reportData = [
            'customer_id' => $customerId,
            'tracking_code' => $trackingCode,
            'courier_name' => $courierName ?: null,
            'recipient_name' => $recipientName ?: null,
            'expected_delivery_date' => $expectedDeliveryDate ?: null,
            'delivery_location' => $deliveryLocation ?: null,
            'notes' => $notes ?: null,
            'status' => 'reported',
            'created_at' => $now,
            'updated_at' => $now
        ];
        
        $reportId = portal_insert('pickup_customer_reports', $reportData);
        
        // Log attività
        $this->logCustomerActivity($customerId, 'package_reported', 'package', $reportId, [
            'tracking_code' => $trackingCode,
            'courier_name' => $courierName
        ]);
        
        // Invia notifica di conferma
        $this->createNotification($customerId, 'system_message', 
            'Pacco segnalato', 
            "Il pacco con tracking {$trackingCode} è stato segnalato correttamente.",
            $trackingCode
        );
        
        portal_info_log('Package reported by customer', [
            'customer_id' => $customerId,
            'report_id' => $reportId,
            'tracking_code' => $trackingCode
        ]);
        
        return portal_fetch_one('SELECT * FROM pickup_customer_reports WHERE id = ?', [$reportId]);
    }
    
    /**
     * Ottiene le segnalazioni del cliente
     */
    public function getCustomerReports(int $customerId, array $options = []): array {
        $limit = $options['limit'] ?? 50;
        $offset = $options['offset'] ?? 0;
        $status = $options['status'] ?? null;
        
        $sql = 'SELECT * FROM pickup_customer_reports WHERE customer_id = ?';
        $params = [$customerId];
        
        if ($status) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }
        
        $sql .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;
        
        return portal_fetch_all($sql, $params);
    }
    
    /**
     * Ottiene le notifiche del cliente
     */
    public function getCustomerNotifications(int $customerId, array $options = []): array {
        $limit = $options['limit'] ?? 50;
        $offset = $options['offset'] ?? 0;
        $unreadOnly = $options['unread_only'] ?? false;
        
        $sql = 'SELECT * FROM pickup_customer_notifications WHERE customer_id = ?';
        $params = [$customerId];
        
        if ($unreadOnly) {
            $sql .= ' AND read_at IS NULL';
        }
        
        $sql .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;
        
        return portal_fetch_all($sql, $params);
    }
    
    /**
     * Crea una notifica per il cliente
     */
    public function createNotification(int $customerId, string $type, string $title, string $message, ?string $trackingCode = null): int {
        $notificationData = [
            'customer_id' => $customerId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'tracking_code' => $trackingCode,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return portal_insert('pickup_customer_notifications', $notificationData);
    }
    
    /**
     * Marca una notifica come letta
     */
    public function markNotificationAsRead(int $customerId, int $notificationId): bool {
        $updated = portal_update(
            'pickup_customer_notifications',
            ['read_at' => date('Y-m-d H:i:s')],
            ['id' => $notificationId, 'customer_id' => $customerId]
        );
        
        return $updated > 0;
    }
    
    /**
     * Collega una segnalazione a un pacco del sistema pickup
     */
    public function linkReportToPickup(int $reportId, int $pickupId): bool {
        $updated = portal_update(
            'pickup_customer_reports',
            ['pickup_id' => $pickupId, 'status' => 'confirmed', 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => $reportId]
        );
        
        if ($updated > 0) {
            // Ottieni i dati del report per notificare il cliente
            $report = portal_fetch_one('SELECT * FROM pickup_customer_reports WHERE id = ?', [$reportId]);
            if ($report) {
                $this->createNotification(
                    $report['customer_id'],
                    'package_arrived',
                    'Pacco arrivato!',
                    "Il tuo pacco {$report['tracking_code']} è arrivato ed è pronto per il ritiro.",
                    $report['tracking_code']
                );
            }
        }
        
        return $updated > 0;
    }
    
    /**
     * Log attività del cliente
     */
    public function logCustomerActivity(int $customerId, string $action, ?string $resourceType = null, ?int $resourceId = null, array $details = []): void {
        $logData = [
            'customer_id' => $customerId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'details' => json_encode($details),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        portal_insert('pickup_customer_activity_logs', $logData);
    }
    
    /**
     * Ottiene il badge HTML per lo stato
     */
    public function getStatusBadge(string $status): string {
        $badges = [
            'reported' => '<span class="badge bg-warning">Segnalato</span>',
            'confirmed' => '<span class="badge bg-info">Confermato</span>',
            'arrived' => '<span class="badge bg-success">Arrivato</span>',
            'cancelled' => '<span class="badge bg-secondary">Annullato</span>',
            'in_arrivo' => '<span class="badge bg-primary">In Arrivo</span>',
            'consegnato' => '<span class="badge bg-success">Consegnato</span>',
            'ritirato' => '<span class="badge bg-dark">Ritirato</span>',
            'in_giacenza' => '<span class="badge bg-warning">In Giacenza</span>',
            'in_giacenza_scaduto' => '<span class="badge bg-danger">Giacenza Scaduta</span>',
        ];
        
        return $badges[$status] ?? '<span class="badge bg-secondary">' . htmlspecialchars(ucfirst($status)) . '</span>';
    }
    
    /**
     * Ottiene l'icona per il tipo di notifica
     */
    public function getNotificationIcon(string $type): string {
        $icons = [
            'package_arrived' => 'box',
            'package_ready' => 'check-circle',
            'package_reminder' => 'clock',
            'package_expired' => 'exclamation-triangle',
            'system_message' => 'info-circle'
        ];
        
        return $icons[$type] ?? 'bell';
    }
    
    /**
     * Cerca pacchi nel sistema pickup per tracking code
     */
    public function findPickupByTracking(string $trackingCode): ?array {
        try {
            // Connessione al database del sistema pickup
            $pickup = portal_fetch_one(
                'SELECT * FROM pickup WHERE tracking_number = ? OR customer_note LIKE ?',
                [$trackingCode, '%' . $trackingCode . '%']
            );
            
            return $pickup;
        } catch (Exception $e) {
            portal_error_log('Error finding pickup by tracking: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Sincronizza le segnalazioni con il sistema pickup
     */
    public function syncReportsWithPickup(): array {
        $results = ['linked' => 0, 'errors' => 0];
        
        // Trova segnalazioni non ancora collegate
        $unlinkedReports = portal_fetch_all(
            'SELECT * FROM pickup_customer_reports WHERE pickup_id IS NULL AND status = ?',
            ['reported']
        );
        
        foreach ($unlinkedReports as $report) {
            try {
                $pickup = $this->findPickupByTracking($report['tracking_code']);
                
                if ($pickup) {
                    $this->linkReportToPickup($report['id'], $pickup['id']);
                    $results['linked']++;
                    
                    portal_info_log('Report linked to pickup', [
                        'report_id' => $report['id'],
                        'pickup_id' => $pickup['id'],
                        'tracking_code' => $report['tracking_code']
                    ]);
                }
            } catch (Exception $e) {
                $results['errors']++;
                portal_error_log('Error syncing report: ' . $e->getMessage(), [
                    'report_id' => $report['id']
                ]);
            }
        }
        
        return $results;
    }
    
    /**
     * Pulisce i dati vecchi
     */
    public function cleanup(): array {
        $results = ['notifications' => 0, 'logs' => 0, 'sessions' => 0];
        
        // Pulisci notifiche vecchie (oltre 90 giorni)
        $stmt = portal_query(
            'DELETE FROM pickup_customer_notifications WHERE created_at < ?',
            [date('Y-m-d H:i:s', strtotime('-90 days'))]
        );
        $results['notifications'] = $stmt->rowCount();
        
        // Pulisci log attività vecchi (oltre 180 giorni)
        $stmt = portal_query(
            'DELETE FROM pickup_customer_activity_logs WHERE created_at < ?',
            [date('Y-m-d H:i:s', strtotime('-180 days'))]
        );
        $results['logs'] = $stmt->rowCount();
        
        // Pulisci sessioni scadute
        $stmt = portal_query(
            'DELETE FROM pickup_customer_sessions WHERE expires_at < ?',
            [date('Y-m-d H:i:s')]
        );
        $results['sessions'] = $stmt->rowCount();
        
        return $results;
    }
}
<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/mailer.php';

/**
 * Classe per la gestione dell'autenticazione dei clienti del portale
 */
class CustomerAuth {
    
    /**
     * Registra o aggiorna un cliente
     */
    public static function registerOrUpdateCustomer(array $data): array {
        $email = trim(strtolower($data['email'] ?? ''));
        $phone = trim($data['phone'] ?? '');
        $name = trim($data['name'] ?? '');
        
        if (empty($email) && empty($phone)) {
            throw new Exception('Email o telefono richiesti');
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email non valida');
        }
        
        if (!empty($phone) && !preg_match('/^\+?[1-9]\d{1,14}$/', $phone)) {
            throw new Exception('Numero di telefono non valido');
        }
        
        $existingCustomer = null;
        
        // Cerca cliente esistente per email o telefono
        if (!empty($email)) {
            $existingCustomer = portal_fetch_one(
                'SELECT * FROM pickup_customers WHERE email = ?',
                [$email]
            );
        }
        
        if (!$existingCustomer && !empty($phone)) {
            $existingCustomer = portal_fetch_one(
                'SELECT * FROM pickup_customers WHERE phone = ?',
                [$phone]
            );
        }
        
        $now = date('Y-m-d H:i:s');
        
        if ($existingCustomer) {
            // Aggiorna cliente esistente
            $updateData = [
                'last_login_attempt' => $now,
                'updated_at' => $now
            ];
            
            if (!empty($email) && $existingCustomer['email'] !== $email) {
                $updateData['email'] = $email;
            }
            
            if (!empty($phone) && $existingCustomer['phone'] !== $phone) {
                $updateData['phone'] = $phone;
            }
            
            if (!empty($name) && $existingCustomer['name'] !== $name) {
                $updateData['name'] = $name;
            }
            
            portal_update('pickup_customers', $updateData, ['id' => $existingCustomer['id']]);
            
            $customerId = $existingCustomer['id'];
        } else {
            // Crea nuovo cliente
            $customerData = [
                'email' => $email ?: null,
                'phone' => $phone ?: null,
                'name' => $name ?: null,
                'status' => 'active',
                'last_login_attempt' => $now,
                'created_at' => $now,
                'updated_at' => $now
            ];
            
            $customerId = portal_insert('pickup_customers', $customerData);
        }
        
        return portal_fetch_one('SELECT * FROM pickup_customers WHERE id = ?', [$customerId]);
    }
    
    /**
     * Genera e invia OTP per l'autenticazione
     */
    public static function generateAndSendOtp(int $customerId, string $method = 'email'): array {
        $customer = portal_fetch_one('SELECT * FROM pickup_customers WHERE id = ?', [$customerId]);
        
        if (!$customer) {
            throw new Exception('Cliente non trovato');
        }
        
        // Controlla tentativi di login
        if (self::isCustomerLocked($customerId)) {
            throw new Exception('Account temporaneamente bloccato per troppi tentativi falliti');
        }
        
        // Genera OTP
        $otp = str_pad((string) random_int(0, 999999), portal_config('otp_length'), '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', time() + portal_config('otp_validity'));
        
        // Salva OTP nel database
        $otpData = [
            'customer_id' => $customerId,
            'otp_code' => password_hash($otp, PASSWORD_ARGON2ID),
            'delivery_method' => $method,
            'expires_at' => $expiresAt,
            'used' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        portal_insert('pickup_customer_otps', $otpData);
        
        // Invia OTP
        $sent = false;
        $destination = '';
        
        try {
            if ($method === 'email' && !empty($customer['email'])) {
                $sent = self::sendOtpByEmail($customer['email'], $customer['name'] ?? '', $otp);
                $destination = $customer['email'];
            } elseif ($method === 'sms' && !empty($customer['phone'])) {
                $sent = self::sendOtpBySms($customer['phone'], $otp);
                $destination = $customer['phone'];
            }
            
            if (!$sent) {
                throw new Exception('Impossibile inviare OTP');
            }
            
            portal_info_log('OTP generated and sent', [
                'customer_id' => $customerId,
                'method' => $method,
                'destination' => self::maskDestination($destination, $method)
            ]);
            
            return [
                'success' => true,
                'method' => $method,
                'destination' => self::maskDestination($destination, $method),
                'expires_in' => portal_config('otp_validity')
            ];
            
        } catch (Exception $e) {
            portal_error_log('Failed to send OTP: ' . $e->getMessage(), [
                'customer_id' => $customerId,
                'method' => $method
            ]);
            throw new Exception('Errore durante l\'invio dell\'OTP');
        }
    }
    
    /**
     * Verifica OTP e effettua il login
     */
    public static function verifyOtpAndLogin(int $customerId, string $otp): array {
        if (self::isCustomerLocked($customerId)) {
            throw new Exception('Account temporaneamente bloccato');
        }
        
        // Trova OTP valido non utilizzato
        $validOtp = portal_fetch_one(
            'SELECT * FROM pickup_customer_otps 
             WHERE customer_id = ? AND used = 0 AND expires_at > NOW() 
             ORDER BY created_at DESC LIMIT 1',
            [$customerId]
        );
        
        if (!$validOtp) {
            self::recordFailedAttempt($customerId);
            throw new Exception('OTP non valido o scaduto');
        }
        
        // Verifica OTP
        if (!password_verify($otp, $validOtp['otp_code'])) {
            self::recordFailedAttempt($customerId);
            throw new Exception('OTP non corretto');
        }
        
        // Marca OTP come utilizzato
        portal_update('pickup_customer_otps', 
            ['used' => 1, 'used_at' => date('Y-m-d H:i:s')],
            ['id' => $validOtp['id']]
        );
        
        // Reset tentativi falliti
        portal_update('pickup_customers',
            ['failed_login_attempts' => 0, 'last_login' => date('Y-m-d H:i:s')],
            ['id' => $customerId]
        );
        
        // Crea sessione
        $sessionData = self::createSession($customerId);
        
        portal_info_log('Customer logged in successfully', ['customer_id' => $customerId]);
        
        return $sessionData;
    }
    
    /**
     * Crea sessione per il cliente
     */
    private static function createSession(int $customerId): array {
        $customer = portal_fetch_one('SELECT * FROM pickup_customers WHERE id = ?', [$customerId]);
        
        if (!$customer) {
            throw new Exception('Cliente non trovato');
        }
        
        session_regenerate_id(true);
        
        $_SESSION['customer_authenticated'] = true;
        $_SESSION['customer_id'] = $customerId;
        $_SESSION['customer_email'] = $customer['email'];
        $_SESSION['customer_name'] = $customer['name'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        return [
            'customer_id' => $customerId,
            'email' => $customer['email'],
            'name' => $customer['name'],
            'phone' => $customer['phone']
        ];
    }
    
    /**
     * Verifica se il cliente è autenticato
     */
    public static function isAuthenticated(): bool {
        if (!isset($_SESSION['customer_authenticated']) || !$_SESSION['customer_authenticated']) {
            return false;
        }
        
        // Controlla timeout sessione
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity']) > portal_config('session_timeout')) {
            self::logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Ottiene i dati del cliente autenticato
     */
    public static function getAuthenticatedCustomer(): ?array {
        if (!self::isAuthenticated()) {
            return null;
        }
        
        $customerId = $_SESSION['customer_id'] ?? 0;
        return portal_fetch_one('SELECT * FROM pickup_customers WHERE id = ?', [$customerId]);
    }
    
    /**
     * Effettua il logout
     */
    public static function logout(): void {
        $customerId = $_SESSION['customer_id'] ?? null;
        
        session_unset();
        session_destroy();
        
        if ($customerId) {
            portal_info_log('Customer logged out', ['customer_id' => $customerId]);
        }
    }
    
    /**
     * Controlla se il cliente è bloccato per troppi tentativi
     */
    private static function isCustomerLocked(int $customerId): bool {
        $customer = portal_fetch_one(
            'SELECT failed_login_attempts, locked_until FROM pickup_customers WHERE id = ?',
            [$customerId]
        );
        
        if (!$customer) {
            return false;
        }
        
        if ($customer['locked_until'] && strtotime($customer['locked_until']) > time()) {
            return true;
        }
        
        return $customer['failed_login_attempts'] >= portal_config('max_login_attempts');
    }
    
    /**
     * Registra tentativo di login fallito
     */
    private static function recordFailedAttempt(int $customerId): void {
        $attempts = portal_fetch_value(
            'SELECT failed_login_attempts FROM pickup_customers WHERE id = ?',
            [$customerId]
        ) ?: 0;
        
        $attempts++;
        $updateData = ['failed_login_attempts' => $attempts];
        
        // Se raggiunto il limite, blocca account
        if ($attempts >= portal_config('max_login_attempts')) {
            $lockUntil = date('Y-m-d H:i:s', time() + portal_config('lockout_time'));
            $updateData['locked_until'] = $lockUntil;
        }
        
        portal_update('pickup_customers', $updateData, ['id' => $customerId]);
    }
    
    /**
     * Invia OTP via email
     */
    private static function sendOtpByEmail(string $email, string $name, string $otp): bool {
        if (!portal_config('enable_email')) {
            return false;
        }
        
        $subject = 'Codice di accesso - ' . portal_config('portal_name');
        $body = "Ciao " . ($name ?: 'Cliente') . ",\n\n";
        $body .= "Il tuo codice di accesso è: {$otp}\n\n";
        $body .= "Il codice è valido per " . (portal_config('otp_validity') / 60) . " minuti.\n\n";
        $body .= "Se non hai richiesto questo codice, ignora questa email.\n\n";
        $body .= "Grazie,\nIl team di " . portal_config('portal_name');
        
        try {
            return send_system_mail($email, $subject, $body);
        } catch (Exception $e) {
            portal_error_log('Email OTP sending failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Invia OTP via SMS
     */
    private static function sendOtpBySms(string $phone, string $otp): bool {
        if (!portal_config('enable_sms')) {
            return false;
        }
        
        $message = "Il tuo codice di accesso è: {$otp}. Valido per " . 
                  (portal_config('otp_validity') / 60) . " minuti.";
        
        // TODO: Implementare invio SMS tramite provider SMS
        // Per ora simula invio
        portal_debug_log('SMS OTP would be sent', ['phone' => $phone, 'message' => $message]);
        return true;
    }
    
    /**
     * Maschera la destinazione per privacy
     */
    private static function maskDestination(string $destination, string $method): string {
        if ($method === 'email') {
            $parts = explode('@', $destination);
            if (count($parts) === 2) {
                $localPart = $parts[0];
                $domain = $parts[1];
                $maskedLocal = substr($localPart, 0, 2) . str_repeat('*', max(0, strlen($localPart) - 4)) . substr($localPart, -2);
                return $maskedLocal . '@' . $domain;
            }
        } elseif ($method === 'sms') {
            $length = strlen($destination);
            if ($length > 6) {
                return substr($destination, 0, 3) . str_repeat('*', $length - 6) . substr($destination, -3);
            }
        }
        
        return $destination;
    }
    
    /**
     * Pulizia OTP scaduti
     */
    public static function cleanupExpiredOtps(): int {
        $deleted = portal_delete('pickup_customer_otps', []);
        
        $stmt = portal_query('DELETE FROM pickup_customer_otps WHERE expires_at < NOW()');
        $deletedCount = $stmt->rowCount();
        
        if ($deletedCount > 0) {
            portal_info_log('Cleaned up expired OTPs', ['count' => $deletedCount]);
        }
        
        return $deletedCount;
    }
}
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';

require_method('POST');

// Ottieni i dati JSON o form
$data = get_json_input();
if (empty($data)) {
    $data = $_POST;
}

// Validazione CSRF
validate_csrf_token($data);

// Validazione input
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');
$name = trim($data['name'] ?? '');

if (empty($email) && empty($phone)) {
    api_error('Email o telefono richiesti');
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    api_error('Email non valida');
}

if (!empty($phone) && !preg_match('/^\+?[1-9]\d{1,14}$/', $phone)) {
    api_error('Numero di telefono non valido');
}

try {
    // Registra o aggiorna cliente
    $customer = CustomerAuth::registerOrUpdateCustomer([
        'email' => $email,
        'phone' => $phone,
        'name' => $name
    ]);
    
    // Determina metodo di invio OTP
    $method = 'email';
    if (empty($email) && !empty($phone)) {
        $method = 'sms';
    }
    
    // Genera e invia OTP
    $otpResult = CustomerAuth::generateAndSendOtp($customer['id'], $method);
    
    // Log attività
    portal_info_log('Customer login attempt', [
        'customer_id' => $customer['id'],
        'method' => $method,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);
    
    api_success([
        'customer_id' => $customer['id'],
        'method' => $otpResult['method'],
        'destination' => $otpResult['destination'],
        'expires_in' => $otpResult['expires_in']
    ], 'Codice OTP inviato');
    
} catch (Exception $e) {
    portal_error_log('Login API error: ' . $e->getMessage(), [
        'email' => $email,
        'phone' => $phone
    ]);
    
    api_error($e->getMessage());
}
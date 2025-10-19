<?php
require_once __DIR__ . '/env.php';

function send_system_mail(string $to, string $subject, string $htmlBody): bool
{
    load_env(__DIR__ . '/../.env');

    $fromAddress = trim((string) env('MAIL_FROM_ADDRESS', 'no-reply@example.com'));
    $fromName = trim((string) env('MAIL_FROM_NAME', 'Coresuite Business'));
    $apiKey = trim((string) env('RESEND_API_KEY', ''));

    if ($apiKey !== '') {
        $resendResult = send_mail_via_resend($apiKey, $fromAddress, $fromName, $to, $subject, $htmlBody);
        if ($resendResult === true) {
            return true;
        }
    }

    return send_mail_via_php_mail($fromAddress, $fromName, $to, $subject, $htmlBody);
}

function send_mail_via_resend(string $apiKey, string $fromAddress, string $fromName, string $to, string $subject, string $htmlBody): bool
{
    if (!function_exists('curl_init')) {
        log_mail_failure('resend', $to, $subject, 'cURL non disponibile sul server.');
        return false;
    }

    $payload = [
        'from' => trim($fromName) !== '' ? sprintf('%s <%s>', $fromName, $fromAddress) : $fromAddress,
        'to' => [$to],
        'subject' => $subject,
        'html' => $htmlBody,
    ];

    if ($fromAddress !== '') {
        $payload['reply_to'] = $fromAddress;
    }

    try {
        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    } catch (JsonException $exception) {
        log_mail_failure('resend', $to, $subject, 'Serializzazione JSON fallita: ' . $exception->getMessage());
        return false;
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $jsonPayload,
        CURLOPT_TIMEOUT => 10,
    ]);

    $responseBody = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($responseBody === false || $curlError !== '') {
        log_mail_failure('resend', $to, $subject, 'Errore cURL: ' . ($curlError !== '' ? $curlError : 'risposta vuota'));
        return false;
    }

    if ($statusCode >= 200 && $statusCode < 300) {
        return true;
    }

    $errorMessage = 'Status HTTP ' . $statusCode;
    $decoded = json_decode($responseBody, true);
    if (is_array($decoded)) {
        $message = $decoded['error']['message'] ?? $decoded['message'] ?? null;
        if ($message) {
            $errorMessage .= ' - ' . $message;
        }
    }

    log_mail_failure('resend', $to, $subject, $errorMessage);
    return false;
}

function send_mail_via_php_mail(string $fromAddress, string $fromName, string $to, string $subject, string $htmlBody): bool
{
    $headers = [];
    $headers[] = 'From: ' . sprintf('"%s" <%s>', addslashes($fromName), $fromAddress);
    $headers[] = 'Reply-To: ' . $fromAddress;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $success = mail($to, $encodedSubject, $htmlBody, implode("\r\n", $headers));

    if (!$success) {
        log_mail_failure('mail', $to, $subject, 'La funzione mail() ha restituito false.');
    }

    return $success;
}

function log_mail_failure(string $channel, string $recipient, string $subject, string $message): void
{
    $logDir = __DIR__ . '/../backups';
    if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
        return;
    }

    $logMessage = sprintf(
        '[%s][%s] Mail fallita verso %s (oggetto: %s) - %s%s',
        date('c'),
        strtoupper($channel),
        $recipient,
        $subject,
        $message,
        PHP_EOL
    );

    file_put_contents($logDir . '/email.log', $logMessage, FILE_APPEND);
}

function render_mail_template(string $title, string $content): string
{
    $year = date('Y');
    return <<<HTML
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>{$title}</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f6f6f6; padding: 24px;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden;">
        <div style="background: #0b2f6b; color: #ffffff; padding: 16px 24px; border-bottom: 4px solid #12468f;">
            <h1 style="margin: 0; font-size: 20px; letter-spacing: 0.04em;">Coresuite Business</h1>
        </div>
        <div style="padding: 24px; color: #1c2534; line-height: 1.5;">
            {$content}
        </div>
        <div style="padding: 16px 24px; font-size: 12px; color: #6c7d93; background: #f1f3f5;">
            &copy; {$year} Coresuite Business. Questo è un messaggio automatico, non rispondere a questa email.
        </div>
    </div>
</body>
</html>
HTML;
}

<?php
require_once __DIR__ . '/env.php';

function send_system_mail(string $to, string $subject, string $htmlBody): bool
{
    load_env(__DIR__ . '/../.env');
    $fromAddress = env('MAIL_FROM_ADDRESS', 'no-reply@example.com');
    $fromName = env('MAIL_FROM_NAME', 'Coresuite Business');

    $headers = [];
    $headers[] = 'From: ' . sprintf('"%s" <%s>', addslashes($fromName), $fromAddress);
    $headers[] = 'Reply-To: ' . $fromAddress;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $success = mail($to, $encodedSubject, $htmlBody, implode("\r\n", $headers));

    if (!$success) {
        $logDir = __DIR__ . '/../backups';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }
        $logMessage = sprintf("[%s] Mail fallita verso %s con oggetto %s\n", date('c'), $to, $subject);
        file_put_contents($logDir . '/email.log', $logMessage, FILE_APPEND);
    }

    return $success;
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

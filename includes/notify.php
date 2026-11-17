<?php
/* ═══════════════════════════════════════
   includes/notify.php  —  SEND EMAIL + SMS

   send_email($to, $subject, $text)  → true/false
   send_sms($to_e164, $text)         → true/false

   No Composer libraries needed: email uses a small
   built-in SMTP client (works with Gmail), SMS uses
   the BulkSMS REST API with cURL.
   Anything that can't be sent is written to
   logs/otp_dev.log so you can see it while testing.
═══════════════════════════════════════ */
require_once __DIR__ . '/notify_config.php';

function notify_log(string $line): void
{
    $dir = dirname(__DIR__) . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
    @file_put_contents($dir . '/otp_dev.log', '[' . date('Y-m-d H:i:s') . "] $line\n", FILE_APPEND);
}

function email_configured(): bool
{
    return SMTP_USER !== '' && SMTP_PASS !== '';
}

function sms_configured(): bool
{
    return BULKSMS_TOKEN_ID !== '' && BULKSMS_SECRET !== '';
}

/* ─────────────────────────── EMAIL ─────────────────────────── */
/* Does this email's domain exist at all (has mail/DNS records)?
   Stops us sending to addresses like fake123@nowhere-test.com, which Gmail would accept
   and then BOUNCE back to the church inbox (the bounce even contains the code).
   Cannot tell if the *mailbox* exists (fake123@gmail.com) – that is what the 6-digit
   code is for. If this PC has no DNS/internet we do not block anybody. */
function email_domain_exists(string $email): bool
{
    $at = strrpos($email, '@');
    if ($at === false) return false;
    $domain = strtolower(trim(substr($email, $at + 1)));
    if ($domain === '' || strpos($domain, '.') === false) return false;
    if (function_exists('idn_to_ascii')) {
        $ascii = @idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if ($ascii) $domain = $ascii;
    }
    if (!function_exists('checkdnsrr')) return true;

    static $dnsWorks = null;                       // can this PC look up DNS at all?
    if ($dnsWorks === null) $dnsWorks = @checkdnsrr('gmail.com.', 'MX');
    if (!$dnsWorks) return true;                   // offline → fail open

    $d = rtrim($domain, '.') . '.';
    return @checkdnsrr($d, 'MX') || @checkdnsrr($d, 'A') || @checkdnsrr($d, 'AAAA');
}

function send_email(string $to, string $subject, string $text, ?string $replyTo = null): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    if ($replyTo !== null && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) return false;
    if (!email_configured()) {
        notify_log("EMAIL not sent to $to: SMTP credentials are not configured");
        return false;
    }
    if (!email_domain_exists($to)) {
        notify_log("EMAIL not sent to $to: that email domain does not exist");
        return false;
    }
    try {
        smtp_send($to, $subject, $text, $replyTo);
        return true;
    } catch (Throwable $e) {
        notify_log("EMAIL FAILED to $to: " . $e->getMessage());
        return false;
    }
}

function smtp_send(string $to, string $subject, string $text, ?string $replyTo = null): void
{
    $ssl    = ((int) SMTP_PORT === 465);
    $remote = ($ssl ? 'ssl://' : 'tcp://') . SMTP_HOST . ':' . SMTP_PORT;
    $sock   = @stream_socket_client($remote, $errno, $errstr, 15);
    if (!$sock) throw new RuntimeException("Cannot connect to " . SMTP_HOST . ": $errstr");
    stream_set_timeout($sock, 15);

    $read = function () use ($sock): string {
        $data = '';
        while (($line = fgets($sock, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;   // last line of reply
        }
        return $data;
    };
    $cmd = function (?string $line, array $ok) use ($sock, $read): string {
        if ($line !== null) fwrite($sock, $line . "\r\n");
        $reply = $read();
        if (!in_array((int) substr($reply, 0, 3), $ok, true)) {
            $shown = (strpos((string) $line, 'AUTH') === 0 || strlen((string) $line) > 40) ? '[hidden]' : $line;
            throw new RuntimeException("SMTP said: " . trim($reply) . " (after: $shown)");
        }
        return $reply;
    };

    $cmd(null, [220]);
    $cmd('EHLO shammah.local', [250]);
    if (!$ssl) {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('STARTTLS failed');
        }
        $cmd('EHLO shammah.local', [250]);
    }
    $cmd('AUTH LOGIN', [334]);
    $cmd(base64_encode(SMTP_USER), [334]);
    $cmd(base64_encode(str_replace(' ', '', SMTP_PASS)), [235]);
    $cmd('MAIL FROM:<' . SMTP_USER . '>', [250]);
    $cmd('RCPT TO:<' . $to . '>', [250, 251]);
    $cmd('DATA', [354]);

    $headers = [
        'From: =?UTF-8?B?' . base64_encode(SMTP_FROM_NAME) . '?= <' . SMTP_USER . '>',
        'To: <' . $to . '>',
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@shammah.local>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    if ($replyTo !== null) $headers[] = 'Reply-To: <' . $replyTo . '>';
    $body = chunk_split(base64_encode($text));
    $cmd(implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.", [250]);
    @fwrite($sock, "QUIT\r\n");
    fclose($sock);
}

/* ─────────────────────────── SMS ─────────────────────────── */
function send_sms(string $to_e164, string $text): bool
{
    if (!preg_match('/^\+[1-9]\d{7,14}$/', $to_e164)) return false;
    if (!sms_configured()) {
        notify_log("SMS (not sent – BulkSMS not set up) to $to_e164: $text");
        return false;
    }
    $payload = json_encode(['to' => $to_e164, 'body' => $text]);
    $auth    = base64_encode(BULKSMS_TOKEN_ID . ':' . BULKSMS_SECRET);

    if (function_exists('curl_init')) {
        $ch = curl_init('https://api.bulksms.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', "Authorization: Basic $auth"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
    } else {
        $ctx  = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\nAuthorization: Basic $auth\r\n",
            'content'       => $payload,
            'timeout'       => 15,
            'ignore_errors' => true,
        ]]);
        $resp = @file_get_contents('https://api.bulksms.com/v1/messages', false, $ctx);
        $code = isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m) ? (int) $m[1] : 0;
        $err  = $resp === false ? 'request failed' : '';
    }

    if ($code >= 200 && $code < 300) return true;
    notify_log("SMS FAILED to $to_e164 (HTTP $code) $err " . substr((string) $resp, 0, 300));
    return false;
}

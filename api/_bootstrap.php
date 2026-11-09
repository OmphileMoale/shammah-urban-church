<?php
/* ═══════════════════════════════════════
   api/_bootstrap.php  —  SHARED SETUP FOR THE APP API

   The Android app talks to these api/*.php files over
   HTTP and gets JSON back. They use the SAME MySQL
   database (shammah_db) as the website, so an account
   made in the app works on the website and the other
   way round.

   Every reply looks like:
     { "ok": true,  ...data }      or
     { "ok": false, "msg": "What went wrong" }

   Signed-in calls send the token from verify_code.php in
   the header  "Authorization: Bearer <token>"
   (the app also sends "X-Auth-Token: <token>", because some
   Apache setups drop the Authorization header).
═══════════════════════════════════════ */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    error_log('Shammah API error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Server error. Please try again.']);
    exit;
});

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
auth_ensure_schema($pdo);

function api_ok(array $data = []): void
{
    echo json_encode(['ok' => true] + $data);
    exit;
}

function api_fail(string $msg, int $status = 400, array $extra = []): void
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'msg' => $msg] + $extra);
    exit;
}

function api_method(string ...$allowed): string
{
    $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($m, $allowed, true)) api_fail('Method not allowed.', 405);
    return $m;
}

/** JSON body (what the app sends) or normal form fields. */
function api_input(): array
{
    static $in = null;
    if ($in !== null) return $in;
    $raw = file_get_contents('php://input');
    $json = $raw ? json_decode($raw, true) : null;
    $in = is_array($json) ? $json : $_POST;
    return $in;
}

function api_str(string $key, int $max = 500): string
{
    $v = api_input()[$key] ?? '';
    return is_scalar($v) ? mb_substr(trim((string) $v), 0, $max) : '';
}

function api_bearer_token(): string
{
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($h === '' && function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) $h = $v;
        }
    }
    if (preg_match('/^Bearer\s+(\S+)$/i', $h, $m)) return $m[1];
    return trim($_SERVER['HTTP_X_AUTH_TOKEN'] ?? '');
}

/** The signed-in member, or a 401 reply. */
function api_user(PDO $pdo): array
{
    $m = api_token_member($pdo, api_bearer_token());
    if (!$m) api_fail('Please sign in again.', 401);
    return $m;
}

function api_require_staff(array $m): void
{
    if (!in_array($m['role'], ['admin', 'pastor'], true)) api_fail('Only a pastor or admin can do that.', 403);
}

/** Reply used after a code was sent (login, register, reset). */
function api_code_sent(array $r, array $extra = []): void
{
    if (isset($r['error'])) api_fail($r['error'], 429);
    $out = ['ticket' => $r['ticket'], 'sent_to' => $r['sent_to'], 'msg' => $r['message']] + $extra;
    if (isset($r['dev_code'])) $out['dev_code'] = $r['dev_code'];
    api_ok($out);
}

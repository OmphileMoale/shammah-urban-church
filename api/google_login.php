<?php
/* POST api/google_login.php   { id_token }
   The app's real "Sign in with Google". The Google ID token is
   checked with Google; the account is found (or created) by the
   Gmail address, then a code is sent to the email AND phone.
   Roles always come from the database, never from the app. */
require __DIR__ . '/_bootstrap.php';
api_method('POST');

if (GOOGLE_WEB_CLIENT_ID === '') api_fail('Google sign-in is not set up on the server (includes/notify_config.php).', 503);
$idToken = api_str('id_token', 4096);
if ($idToken === '') api_fail('Missing Google token.');

$resp = @file_get_contents('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken), false,
    stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]));
$g = $resp ? json_decode($resp, true) : null;
if (!is_array($g) || ($g['aud'] ?? '') !== GOOGLE_WEB_CLIENT_ID || ($g['email_verified'] ?? '') !== 'true'
    || !in_array($g['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)
    || (int) ($g['exp'] ?? 0) < time()) {
    api_fail('Google sign-in could not be confirmed. Please try again.', 401);
}
$email = strtolower($g['email']);

$found = find_member_by_login($pdo, $email);
if (isset($found['error'])) {
    $first = mb_substr($g['given_name'] ?? strtok($email, '@'), 0, 60);
    $last  = mb_substr($g['family_name'] ?? '-', 0, 60);
    $pdo->prepare('INSERT INTO MEMBER (first_name, last_name, email, phone, password_hash, role, is_active, created_at)
                   VALUES (?, ?, ?, "", ?, "member", 1, NOW())')
        ->execute([$first, $last, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT)]);
    $found = find_member_by_login($pdo, $email);
}
$m = $found['member'];
if (empty($m['is_active'])) api_fail('This account has been deactivated. Please contact the church office.', 403);

api_code_sent(otp_issue($pdo, $m, 'login'), ['needs_phone' => normalize_phone((string) $m['phone']) === null]);

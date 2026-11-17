<?php
/* ═══════════════════════════════════════
   includes/auth.php  —  SHARED SIGN-IN LOGIC

   Used by BOTH the website (login.php, register.php,
   forgot_password.php) and the Android app (api/*.php),
   so one account works everywhere:

     • find a member by email OR phone number
     • lock the account for 15 min after 3 wrong passwords
     • create / resend / check 6-digit codes that are sent
       to the member's email AND phone
     • issue sign-in tokens for the app

   Requires $pdo from includes/db.php.
═══════════════════════════════════════ */
require_once __DIR__ . '/notify.php';

const OTP_MINUTES       = 10;   // a code is valid for 10 minutes
const OTP_MAX_ATTEMPTS  = 5;    // wrong codes allowed per code
const OTP_MAX_SENDS     = 5;    // resends allowed per code
const OTP_RESEND_WAIT   = 60;   // seconds between resends
const OTP_MAX_PER_HOUR  = 8;    // new codes per member per hour
const LOCK_AFTER_FAILS  = 3;
const LOCK_MINUTES      = 15;
const TOKEN_DAYS        = 30;

/* ── Create the new tables/columns the first time ───────── */
function auth_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    $hasTable = $pdo->query("SHOW TABLES LIKE 'API_TOKEN'")->fetchColumn();
    $hasCol   = $pdo->query("SHOW COLUMNS FROM MEMBER LIKE 'locked_until'")->fetchColumn();
    $hasPend  = $pdo->query("SHOW TABLES LIKE 'PENDING_REGISTRATION'")->fetchColumn();
    if ($hasTable && $hasCol && $hasPend) return;

    $sql = (string) @file_get_contents(dirname(__DIR__) . '/db_update_shared_login.sql');
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if (stripos($stmt, 'USE ') === 0) continue;
        $pdo->exec($stmt);
    }
}

/* ── Phone numbers ──────────────────────────────────────── */
/** 082 123 4567 → +27821234567 ; 0027… → +27… ; +27… unchanged ; null if invalid */
function normalize_phone(string $raw): ?string
{
    $p = preg_replace('/[\s()\-.]/', '', trim($raw));
    if ($p === '') return null;
    if (strpos($p, '+') === 0)       $e = $p;
    elseif (strpos($p, '00') === 0)  $e = '+' . substr($p, 2);
    elseif (strpos($p, '0') === 0)   $e = '+' . DEFAULT_COUNTRY_CODE . substr($p, 1);
    else                             $e = '+' . $p;
    return preg_match('/^\+[1-9]\d{7,14}$/', $e) ? $e : null;
}

function mask_email(string $email): string
{
    [$u, $d] = array_pad(explode('@', $email, 2), 2, '');
    return substr($u, 0, 2) . str_repeat('•', max(1, strlen($u) - 2)) . '@' . $d;
}

function mask_phone(string $phone): string
{
    return strlen($phone) > 6 ? substr($phone, 0, 3) . str_repeat('•', strlen($phone) - 6) . substr($phone, -3) : $phone;
}

/* ── Look up a member by email or phone ─────────────────── */
/**
 * Returns ['member' => row] or ['error' => message].
 */
function find_member_by_login(PDO $pdo, string $login): array
{
    $login = trim($login);
    $cols  = 'member_id, first_name, last_name, email, phone, password_hash, role, is_active, failed_logins, locked_until,
              TIMESTAMPDIFF(SECOND, NOW(), locked_until) AS lock_seconds';   // MySQL clock, not PHP's

    if (strpos($login, '@') !== false) {
        $st = $pdo->prepare("SELECT $cols FROM MEMBER WHERE email = ? LIMIT 1");
        $st->execute([strtolower($login)]);
        $m = $st->fetch();
        return $m ? ['member' => $m] : ['error' => 'not_found'];
    }

    $e164 = normalize_phone($login);
    if ($e164 === null) return ['error' => 'bad_login'];
    $local = '0' . substr($e164, 1 + strlen(DEFAULT_COUNTRY_CODE));   // +2782… → 082…

    $st = $pdo->prepare("
        SELECT $cols FROM MEMBER
        WHERE  REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', '') IN (?, ?)
        LIMIT  2");
    $st->execute([$e164, $local]);
    $rows = $st->fetchAll();
    if (count($rows) === 1) return ['member' => $rows[0]];
    if (count($rows) > 1)   return ['error' => 'phone_shared'];
    return ['error' => 'not_found'];
}

/* ── Lockout (stored in the database, so app + website share it) ── */
function minutes_locked(array $m): int
{
    $left = (int) ($m['lock_seconds'] ?? 0);
    return $left > 0 ? (int) ceil($left / 60) : 0;
}

/** Records a wrong password. Returns attempts left (0 = now locked). */
function record_failed_login(PDO $pdo, int $member_id): int
{
    $pdo->prepare('UPDATE MEMBER SET failed_logins = failed_logins + 1 WHERE member_id = ?')->execute([$member_id]);
    $st = $pdo->prepare('SELECT failed_logins FROM MEMBER WHERE member_id = ?');
    $st->execute([$member_id]);
    $fails = (int) $st->fetchColumn();
    if ($fails >= LOCK_AFTER_FAILS) {
        $pdo->prepare('UPDATE MEMBER SET failed_logins = 0, locked_until = NOW() + INTERVAL ' . LOCK_MINUTES . ' MINUTE WHERE member_id = ?')
            ->execute([$member_id]);
        return 0;
    }
    return LOCK_AFTER_FAILS - $fails;
}

function clear_failed_logins(PDO $pdo, int $member_id): void
{
    $pdo->prepare('UPDATE MEMBER SET failed_logins = 0, locked_until = NULL WHERE member_id = ?')->execute([$member_id]);
}

/**
 * Checks email/phone + password. Returns ['member' => row] or ['error' => message].
 * Same rules on the website and in the app.
 */
function check_password_login(PDO $pdo, string $login, string $password): array
{
    $found = find_member_by_login($pdo, $login);
    if (isset($found['error'])) {
        if ($found['error'] === 'phone_shared') {
            return ['error' => 'More than one account uses that phone number. Please sign in with your email address.'];
        }
        if ($found['error'] === 'bad_login') {
            return ['error' => 'Enter a valid email address or phone number.'];
        }
        password_verify($password, '$2y$12$prXP3CpPlnNHDAhy1xCdTuzE7ziBjpApXpGtKUpHUY6WoejdJyqKy'); // same timing as a real check
        return ['error' => 'Invalid email/phone or password.'];
    }
    $m = $found['member'];

    if (($wait = minutes_locked($m)) > 0) {
        return ['error' => "Account temporarily locked. Please wait {$wait} minute(s) before trying again."];
    }
    if (empty($m['is_active'])) {
        return ['error' => 'This account has been deactivated. Please contact the church office.'];
    }
    if (!password_verify($password, $m['password_hash'])) {
        $left = record_failed_login($pdo, (int) $m['member_id']);
        if ($left === 0) return ['error' => 'Too many failed attempts. Account locked for ' . LOCK_MINUTES . ' minutes.'];
        return ['error' => "Invalid email/phone or password. {$left} " . ($left === 1 ? 'attempt' : 'attempts') . ' remaining before lockout.'];
    }
    clear_failed_logins($pdo, (int) $m['member_id']);
    return ['member' => $m];
}

/* ── Demo accounts (admin, pastor, Thabo) ────────────────
   Their code is shown on screen, because they have no real inbox.
   Everyone else gets the code by email / SMS only.            */
function is_demo_account(array $m): bool
{
    return defined('DEMO_MODE') && DEMO_MODE
        && defined('DEMO_ACCOUNTS')
        && in_array(strtolower((string) ($m['email'] ?? '')), DEMO_ACCOUNTS, true);
}

/* ── 6-digit codes ──────────────────────────────────────── */
function otp_send_code(array $m, string $code, string $purpose): array
{
    $what = ['login' => 'sign-in', 'register' => 'account verification', 'reset' => 'password reset'][$purpose] ?? 'verification';
    $text = "Your Shammah Urban Church {$what} code is {$code}. It expires in " . OTP_MINUTES . " minutes. Never share this code with anyone.";

    $sent = [];
    if (!empty($m['email']) && send_email($m['email'], "Your Shammah code: $code", "Hi {$m['first_name']},\n\n$text\n\nIf you did not ask for this code, you can ignore this email.\n\nShammah Urban Church")) {
        $sent[] = mask_email($m['email']);
    }
    $phone = normalize_phone((string) ($m['phone'] ?? ''));
    if ($phone && send_sms($phone, $text)) {
        $sent[] = mask_phone($phone);
    }
    return $sent;
}

/**
 * Creates a code for $m and sends it by email + SMS.
 * Returns ['ticket','sent_to','message', optional 'dev_code'] or ['error'].
 */
function otp_issue(PDO $pdo, array $m, string $purpose): array
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM OTP_CODE WHERE member_id = ? AND created_at > NOW() - INTERVAL 1 HOUR');
    $st->execute([$m['member_id']]);
    if ((int) $st->fetchColumn() >= OTP_MAX_PER_HOUR) {
        return ['error' => 'Too many codes requested. Please wait an hour and try again.'];
    }

    // only the newest code of each kind stays valid
    $pdo->prepare('UPDATE OTP_CODE SET used_at = NOW() WHERE member_id = ? AND purpose = ? AND used_at IS NULL')
        ->execute([$m['member_id'], $purpose]);

    $code   = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $ticket = bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO OTP_CODE (member_id, purpose, ticket_hash, code_hash, expires_at)
                   VALUES (?, ?, ?, ?, NOW() + INTERVAL ' . OTP_MINUTES . ' MINUTE)')
        ->execute([$m['member_id'], $purpose, hash('sha256', $ticket), password_hash($code, PASSWORD_BCRYPT)]);

    $demo = is_demo_account($m);
    return otp_result($ticket, $code, $demo ? [] : otp_send_code($m, $code, $purpose), $demo, $m);
}

function otp_result(string $ticket, string $code, array $sent, bool $demo = false, ?array $m = null): array
{
    if ($demo) {
        return ['ticket' => $ticket, 'sent_to' => [], 'dev_code' => $code,
                'message' => "Demo account: your code is {$code}. (Real members receive their code by email.)"];
    }
    if ($sent) {
        return ['ticket' => $ticket, 'sent_to' => $sent,
                'message' => 'We sent a 6-digit code to ' . implode(' and ', $sent) . '.'];
    }
    /* A fake or mistyped email (its domain does not exist): say so, instead of a vague "try later" */
    if ($m && !empty($m['email']) && function_exists('email_domain_exists') && !email_domain_exists($m['email'])) {
        $dom = htmlspecialchars(substr(strrchr($m['email'], '@'), 1), ENT_QUOTES);
        return ['error' => "We could not send a code because the email domain \"{$dom}\" does not exist. Please check your email address for typos."];
    }
    if (OTP_SHOW_CODE_IF_UNSENT) {
        return ['ticket' => $ticket, 'sent_to' => [], 'dev_code' => $code,
                'message' => "TEST MODE – email/SMS are not set up yet, so your code is {$code}."];
    }
    return ['error' => 'We could not send your code right now. Please try again later or contact the church office.'];
}

function otp_row(PDO $pdo, string $ticket): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $ticket)) return null;
    $st = $pdo->prepare('
        SELECT o.*, (o.expires_at < NOW()) AS expired, TIMESTAMPDIFF(SECOND, o.last_sent, NOW()) AS since_sent,
               m.first_name, m.last_name, m.email, m.phone, m.role, m.is_active
        FROM   OTP_CODE o JOIN MEMBER m ON m.member_id = o.member_id
        WHERE  o.ticket_hash = ? LIMIT 1');
    $st->execute([hash('sha256', $ticket)]);
    return $st->fetch() ?: null;
}

/** Sends a fresh code for the same ticket. */
function otp_resend(PDO $pdo, string $ticket): array
{
    $o = otp_row($pdo, $ticket);
    if (!$o) return pending_resend($pdo, $ticket);          // a registration still waiting for its code
    if ($o['used_at'] !== null) return ['error' => 'This code request has expired. Please start again.'];
    if ((int) $o['since_sent'] < OTP_RESEND_WAIT) {
        return ['error' => 'Please wait ' . (OTP_RESEND_WAIT - (int) $o['since_sent']) . ' seconds before asking for a new code.'];
    }
    if ((int) $o['sends'] >= OTP_MAX_SENDS) return ['error' => 'Too many resends. Please start again.'];

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE OTP_CODE SET code_hash = ?, attempts = 0, sends = sends + 1, last_sent = NOW(),
                   expires_at = NOW() + INTERVAL ' . OTP_MINUTES . ' MINUTE WHERE otp_id = ?')
        ->execute([password_hash($code, PASSWORD_BCRYPT), $o['otp_id']]);

    $demo = is_demo_account($o);
    return otp_result($ticket, $code, $demo ? [] : otp_send_code($o, $code, $o['purpose']), $demo, $o);
}

/**
 * Checks a typed code. On success the code is used up and the member row is returned:
 * ['member' => row, 'purpose' => ...] or ['error' => message].
 */
function otp_verify(PDO $pdo, string $ticket, string $code, array $purposes): array
{
    $o = otp_row($pdo, $ticket);
    if (!$o && in_array('register', $purposes, true)) {
        return pending_verify($pdo, $ticket, $code);        // create the account only now
    }
    if (!$o || $o['used_at'] !== null || !in_array($o['purpose'], $purposes, true)) {
        return ['error' => 'This code is no longer valid. Please request a new one.'];
    }
    if ((int) $o['expired']) return ['error' => 'This code has expired. Tap "Resend code" to get a new one.'];
    if ((int) $o['attempts'] >= OTP_MAX_ATTEMPTS) return ['error' => 'Too many wrong codes. Please request a new one.'];
    if (empty($o['is_active'])) return ['error' => 'This account has been deactivated. Please contact the church office.'];

    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6 || !password_verify($code, $o['code_hash'])) {
        $pdo->prepare('UPDATE OTP_CODE SET attempts = attempts + 1 WHERE otp_id = ?')->execute([$o['otp_id']]);
        $left = OTP_MAX_ATTEMPTS - (int) $o['attempts'] - 1;
        return ['error' => $left > 0 ? "That code is wrong. {$left} " . ($left === 1 ? 'try' : 'tries') . ' left.'
                                     : 'Too many wrong codes. Please request a new one.'];
    }
    $pdo->prepare('UPDATE OTP_CODE SET used_at = NOW() WHERE otp_id = ?')->execute([$o['otp_id']]);

    $st = $pdo->prepare('SELECT member_id, title, first_name, last_name, email, phone, role, is_active, membership_type
                         FROM MEMBER WHERE member_id = ?');
    $st->execute([$o['member_id']]);
    return ['member' => $st->fetch(), 'purpose' => $o['purpose']];
}

/* ── PENDING REGISTRATIONS ──────────────────────────────────
   A new person is NOT saved in MEMBER when they press "Register".
   Their details wait in PENDING_REGISTRATION until they type the
   correct 6-digit code that was sent to their email / phone.
   Only then is the account created. A wrong email or a code that is
   never entered therefore never becomes a member, and unverified
   details are deleted automatically.
   The same ticket + code steps are used by the website and the app. */
const PENDING_KEEP_MINUTES = 60;   // unverified details are deleted this long after the code expires

function pending_cleanup(PDO $pdo): void
{
    $pdo->exec('DELETE FROM PENDING_REGISTRATION WHERE expires_at < NOW() - INTERVAL ' . PENDING_KEEP_MINUTES . ' MINUTE');
}

/**
 * $d = title, first_name, last_name, email, phone, dob, gender, mem_type,
 *      street, city, postal, ministries[], password_hash   (password already hashed)
 * Returns ['ticket','sent_to','message', optional 'dev_code'] or ['error'].
 */
function pending_issue(PDO $pdo, array $d): array
{
    pending_cleanup($pdo);
    $email = strtolower((string) $d['email']);
    $d['email'] = $email;

    $st = $pdo->prepare('SELECT COUNT(*) FROM PENDING_REGISTRATION WHERE email = ? AND created_at > NOW() - INTERVAL 1 HOUR');
    $st->execute([$email]);
    if ((int) $st->fetchColumn() >= OTP_MAX_PER_HOUR) {
        return ['error' => 'Too many codes requested. Please wait an hour and try again.'];
    }

    // the newest attempt wins: earlier unverified attempts for this email stop working
    $pdo->prepare('UPDATE PENDING_REGISTRATION SET used_at = NOW() WHERE email = ? AND used_at IS NULL')->execute([$email]);

    $code   = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $ticket = bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO PENDING_REGISTRATION (ticket_hash, email, phone, details, code_hash, expires_at)
                   VALUES (?, ?, ?, ?, ?, NOW() + INTERVAL ' . OTP_MINUTES . ' MINUTE)')
        ->execute([hash('sha256', $ticket), $email, (string) ($d['phone'] ?? ''),
                   json_encode($d, JSON_UNESCAPED_UNICODE), password_hash($code, PASSWORD_BCRYPT)]);

    $demo = is_demo_account($d);
    $res  = otp_result($ticket, $code, $demo ? [] : otp_send_code($d, $code, 'register'), $demo, $d);
    if (isset($res['error'])) {   // nothing could be sent, so forget the details
        $pdo->prepare('DELETE FROM PENDING_REGISTRATION WHERE ticket_hash = ?')->execute([hash('sha256', $ticket)]);
    }
    return $res;
}

function pending_row(PDO $pdo, string $ticket): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $ticket)) return null;
    $st = $pdo->prepare('
        SELECT p.*, (p.expires_at < NOW()) AS expired, TIMESTAMPDIFF(SECOND, p.last_sent, NOW()) AS since_sent
        FROM   PENDING_REGISTRATION p WHERE p.ticket_hash = ? LIMIT 1');
    $st->execute([hash('sha256', $ticket)]);
    $r = $st->fetch();
    if (!$r) return null;
    $r['data'] = json_decode((string) $r['details'], true) ?: [];
    return $r;
}

/** Sends a fresh code for a registration that is still waiting. */
function pending_resend(PDO $pdo, string $ticket): array
{
    $p = pending_row($pdo, $ticket);
    if (!$p || $p['used_at'] !== null) return ['error' => 'This code request has expired. Please start again.'];
    if ((int) $p['since_sent'] < OTP_RESEND_WAIT) {
        return ['error' => 'Please wait ' . (OTP_RESEND_WAIT - (int) $p['since_sent']) . ' seconds before asking for a new code.'];
    }
    if ((int) $p['sends'] >= OTP_MAX_SENDS) return ['error' => 'Too many resends. Please start again.'];

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE PENDING_REGISTRATION SET code_hash = ?, attempts = 0, sends = sends + 1, last_sent = NOW(),
                   expires_at = NOW() + INTERVAL ' . OTP_MINUTES . ' MINUTE WHERE pending_id = ?')
        ->execute([password_hash($code, PASSWORD_BCRYPT), $p['pending_id']]);

    $demo = is_demo_account($p['data']);
    return otp_result($ticket, $code, $demo ? [] : otp_send_code($p['data'], $code, 'register'), $demo, $p['data']);
}

/**
 * Checks the code. Only if it is correct is the member created.
 * Returns ['member' => row, 'purpose' => 'register'] or ['error' => message].
 */
function pending_verify(PDO $pdo, string $ticket, string $code): array
{
    $p = pending_row($pdo, $ticket);
    if (!$p || $p['used_at'] !== null) return ['error' => 'This code is no longer valid. Please start registering again.'];
    if ((int) $p['expired'])           return ['error' => 'This code has expired. Tap "Resend code" to get a new one.'];
    if ((int) $p['attempts'] >= OTP_MAX_ATTEMPTS) return ['error' => 'Too many wrong codes. Please request a new one.'];

    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6 || !password_verify($code, $p['code_hash'])) {
        $pdo->prepare('UPDATE PENDING_REGISTRATION SET attempts = attempts + 1 WHERE pending_id = ?')->execute([$p['pending_id']]);
        $left = OTP_MAX_ATTEMPTS - (int) $p['attempts'] - 1;
        return ['error' => $left > 0 ? "That code is wrong. {$left} " . ($left === 1 ? 'try' : 'tries') . ' left.'
                                     : 'Too many wrong codes. Please request a new one.'];
    }

    // The code is correct: the email / phone really belong to this person. Create the account now.
    $d = $p['data'];
    $chk = $pdo->prepare('SELECT 1 FROM MEMBER WHERE email = ? LIMIT 1');
    $chk->execute([$d['email'] ?? '']);
    if ($chk->fetchColumn() || (($d['phone'] ?? '') !== '' && !isset(find_member_by_login($pdo, $d['phone'])['error']))) {
        $pdo->prepare('UPDATE PENDING_REGISTRATION SET used_at = NOW(), details = ? WHERE pending_id = ?')->execute(['{}', $p['pending_id']]);
        return ['error' => 'That email address or phone number is already registered. Please log in instead.'];
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO MEMBER (title, first_name, last_name, email, phone, date_of_birth, gender,
                                           membership_type, password_hash, role, is_active, created_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "member", 1, NOW())')
            ->execute([
                ($d['title'] ?? '') !== '' ? $d['title'] : 'Mr',
                $d['first_name'], ($d['last_name'] ?? '') !== '' ? $d['last_name'] : '-',
                $d['email'], $d['phone'] ?? '',
                ($d['dob'] ?? '') !== '' ? $d['dob'] : null,
                ($d['gender'] ?? '') !== '' ? $d['gender'] : 'Other',
                ($d['mem_type'] ?? '') !== '' ? $d['mem_type'] : 'Guest',
                $d['password_hash'],
            ]);
        $new_id = (int) $pdo->lastInsertId();

        if (($d['street'] ?? '') !== '') {
            $pdo->prepare('INSERT INTO ADDRESS (member_id, street, city, postal_code) VALUES (?, ?, ?, ?)')
                ->execute([$new_id, $d['street'], $d['city'] ?? '', $d['postal'] ?? '']);
        }
        foreach (($d['ministries'] ?? []) as $ministry) {
            if ($ministry !== '') {
                $pdo->prepare('INSERT INTO VOLUNTEER (member_id, ministry_name, status) VALUES (?, ?, "interested")')
                    ->execute([$new_id, $ministry]);
            }
        }
        // done: remember that this attempt was used and wipe the stored details
        $pdo->prepare('UPDATE PENDING_REGISTRATION SET used_at = NOW(), details = ? WHERE pending_id = ?')->execute(['{}', $p['pending_id']]);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Shammah pending_verify failed: ' . $e->getMessage());
        return ['error' => 'We could not finish creating your account. Please try again or contact the church office.'];
    }

    $st = $pdo->prepare('SELECT member_id, title, first_name, last_name, email, phone, role, is_active, membership_type
                         FROM MEMBER WHERE member_id = ?');
    $st->execute([$new_id]);
    return ['member' => $st->fetch(), 'purpose' => 'register'];
}

/* ── Password rules (same everywhere) ───────────────────── */
function password_problem(string $pw): ?string
{
    if (strlen($pw) < 8)                 return 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Za-z]/', $pw))  return 'Password must include at least one letter.';
    if (!preg_match('/[0-9]/', $pw))     return 'Password must include at least one number.';
    return null;
}

/* ── App sign-in tokens ─────────────────────────────────── */
function api_token_issue(PDO $pdo, int $member_id, string $device = 'Android app'): string
{
    $token = bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO API_TOKEN (member_id, token_hash, device, expires_at)
                   VALUES (?, ?, ?, NOW() + INTERVAL ' . TOKEN_DAYS . ' DAY)')
        ->execute([$member_id, hash('sha256', $token), substr($device, 0, 80)]);
    return $token;
}

function api_token_member(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $st = $pdo->prepare('
        SELECT m.member_id, m.title, m.first_name, m.last_name, m.email, m.phone, m.role, m.is_active, m.membership_type,
               t.token_id
        FROM   API_TOKEN t JOIN MEMBER m ON m.member_id = t.member_id
        WHERE  t.token_hash = ? AND t.expires_at > NOW() AND m.is_active = 1
        LIMIT  1');
    $st->execute([hash('sha256', $token)]);
    $m = $st->fetch();
    if ($m) $pdo->prepare('UPDATE API_TOKEN SET last_used_at = NOW() WHERE token_id = ?')->execute([$m['token_id']]);
    return $m ?: null;
}

/** Member details in the shape the app expects. */
function member_public(array $m): array
{
    $role = strtolower($m['role'] ?? 'member');
    return [
        'id'         => (int) $m['member_id'],
        'first_name' => $m['first_name'],
        'last_name'  => $m['last_name'],
        'name'       => trim($m['first_name'] . ' ' . $m['last_name']),
        'email'      => $m['email'],
        'phone'      => $m['phone'],
        'role'       => $role,                                              // admin | pastor | member
        'app_role'   => in_array($role, ['admin', 'pastor'], true) ? 'Pastor' : 'Member',
    ];
}

/** Website: log the member in (after the code was correct). */
function start_web_session(array $m, bool $remember = false): string
{
    session_regenerate_id(true);
    $_SESSION['member_id']   = $m['member_id'];
    $_SESSION['member_name'] = htmlspecialchars($m['first_name'] . ' ' . $m['last_name']);
    $_SESSION['role']        = $m['role'] ?? 'member';
    unset($_SESSION['otp_ticket'], $_SESSION['otp_remember']);

    if ($remember) {
        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires'  => time() + (30 * 24 * 60 * 60),
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    return in_array($_SESSION['role'], ['admin', 'pastor'], true) ? 'admin_dashboard.php' : 'member_portal.php';
}

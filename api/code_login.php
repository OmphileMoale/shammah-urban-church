<?php
/* POST api/code_login.php   { login: email OR phone }
   Sign in without a password: a code is sent to the email AND
   phone on the account. Used by the app's "Sign in with Gmail"
   when Google sign-in isn't set up.
   Reply: { ok, ticket, sent_to, msg } → then verify_code.php */
require __DIR__ . '/_bootstrap.php';
api_method('POST');

$found = find_member_by_login($pdo, api_str('login', 120) ?: api_str('email', 120));
if (isset($found['error'])) {
    api_fail($found['error'] === 'phone_shared'
        ? 'More than one account uses that phone number. Please use your email address.'
        : 'No account found. Please create an account first.', 404);
}
$m = $found['member'];
if (empty($m['is_active'])) api_fail('This account has been deactivated. Please contact the church office.', 403);
if (($wait = minutes_locked($m)) > 0) api_fail("Account temporarily locked. Please wait {$wait} minute(s).", 423);

api_code_sent(otp_issue($pdo, $m, 'login'));

<?php
/* POST api/forgot_password.php   { login: email OR phone }
   Sends a password-reset code to the email AND phone.
   Reply: { ok, ticket, sent_to, msg } → then reset_password.php */
require __DIR__ . '/_bootstrap.php';
api_method('POST');

$found = find_member_by_login($pdo, api_str('login', 120) ?: api_str('email', 120));
if (isset($found['error'])) {
    api_fail($found['error'] === 'phone_shared'
        ? 'More than one account uses that phone number. Please use your email address.'
        : 'No account found for that email or phone number.', 404);
}
if (empty($found['member']['is_active'])) api_fail('This account has been deactivated. Please contact the church office.', 403);
api_code_sent(otp_issue($pdo, $found['member'], 'reset'));

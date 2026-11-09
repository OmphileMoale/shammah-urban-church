<?php
/* POST api/login.php   { login: email OR phone, password }
   Checks the password (3 wrong tries = 15 min lock, shared with
   the website) and sends a 6-digit code to the email AND phone.
   Reply: { ok, ticket, sent_to, msg }  → then call verify_code.php */
require __DIR__ . '/_bootstrap.php';
api_method('POST');

$login = api_str('login', 120) ?: api_str('email', 120);
$pass  = (string) (api_input()['password'] ?? '');
if ($login === '' || $pass === '') api_fail('Enter your email or phone number and your password.');

$r = check_password_login($pdo, $login, $pass);
if (isset($r['error'])) api_fail($r['error'], 401);

api_code_sent(otp_issue($pdo, $r['member'], 'login'));

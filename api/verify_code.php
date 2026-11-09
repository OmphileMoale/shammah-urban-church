<?php
/* POST api/verify_code.php   { ticket, code }
   Checks the 6-digit code. For login/register it returns a
   sign-in token the app keeps and sends with every call.
   Reply: { ok, token, user } */
require __DIR__ . '/_bootstrap.php';
api_method('POST');

$r = otp_verify($pdo, api_str('ticket', 64), api_str('code', 10), ['login', 'register']);
if (isset($r['error'])) api_fail($r['error'], 401);

$token = api_token_issue($pdo, (int) $r['member']['member_id'], api_str('device', 80) ?: 'Android app');
api_ok(['token' => $token, 'user' => member_public($r['member']), 'msg' => 'Welcome, ' . $r['member']['first_name'] . '!']);

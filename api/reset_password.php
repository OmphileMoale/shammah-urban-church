<?php
/* POST api/reset_password.php   { ticket, code, new_password }
   Sets a new password (works on the website too) and signs the app in. */
require __DIR__ . '/_bootstrap.php';
api_method('POST');

$new = (string) (api_input()['new_password'] ?? '');
if ($p = password_problem($new)) api_fail($p);

$r = otp_verify($pdo, api_str('ticket', 64), api_str('code', 10), ['reset']);
if (isset($r['error'])) api_fail($r['error'], 401);

$id = (int) $r['member']['member_id'];
$pdo->prepare('UPDATE MEMBER SET password_hash = ? WHERE member_id = ?')->execute([password_hash($new, PASSWORD_BCRYPT), $id]);
clear_failed_logins($pdo, $id);
$pdo->prepare('DELETE FROM API_TOKEN WHERE member_id = ?')->execute([$id]);   // sign out other phones

$token = api_token_issue($pdo, $id, api_str('device', 80) ?: 'Android app');
api_ok(['token' => $token, 'user' => member_public($r['member']), 'msg' => 'Password changed. You are signed in.']);

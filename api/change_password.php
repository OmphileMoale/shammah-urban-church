<?php
/* POST api/change_password.php   { old_password, new_password } */
require __DIR__ . '/_bootstrap.php';
api_method('POST');
$m = api_user($pdo);

$old = (string) (api_input()['old_password'] ?? '');
$new = (string) (api_input()['new_password'] ?? '');
if ($p = password_problem($new)) api_fail($p);

$r = check_password_login($pdo, $m['email'], $old);
if (isset($r['error'])) api_fail(str_replace('Invalid email/phone or password', 'Current password is incorrect', $r['error']), 401);

$pdo->prepare('UPDATE MEMBER SET password_hash = ? WHERE member_id = ?')->execute([password_hash($new, PASSWORD_BCRYPT), $m['member_id']]);
api_ok(['msg' => 'Password changed. Use the new password on the app and the website.']);

<?php
/* POST api/prayer_status.php   { id, status: approved | rejected }   (pastor/admin only) */
require __DIR__ . '/_bootstrap.php';
api_method('POST');
$m = api_user($pdo);
api_require_staff($m);

$status = api_str('status', 20);
if (!in_array($status, ['approved', 'rejected'], true)) api_fail('Status must be approved or rejected.');
$st = $pdo->prepare('UPDATE PRAYER_REQUEST SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE request_id = ?');
$st->execute([$status, $m['member_id'], (int) (api_input()['id'] ?? 0)]);
if ($st->rowCount() === 0) api_fail('Prayer request not found.', 404);
api_ok(['msg' => 'Prayer request ' . $status . '.']);

<?php
/* GET  api/prayers.php                  → approved public prayers (the Prayer Wall)
   GET  api/prayers.php?status=pending   → waiting for approval (pastor/admin only)
   POST api/prayers.php  { message, category?, anonymous? }  → new request (pending)
   Same PRAYER_REQUEST table the website's dashboard approves. */
require __DIR__ . '/_bootstrap.php';
$method = api_method('GET', 'POST');
$m = api_user($pdo);

if ($method === 'POST') {
    $msg = api_str('message', 1000);
    if (mb_strlen($msg) < 5) api_fail('Please write your prayer request.');
    $anon = !empty(api_input()['anonymous']);
    $name = $anon ? 'Anonymous' : $m['first_name'] . ' ' . mb_substr($m['last_name'], 0, 1) . '.';
    $pdo->prepare('INSERT INTO PRAYER_REQUEST (member_id, display_name, category, message, is_public, status, created_at)
                   VALUES (?, ?, ?, ?, 1, "pending", NOW())')
        ->execute([$m['member_id'], $name, api_str('category', 40) ?: 'General', $msg]);
    api_ok(['msg' => 'Prayer submitted. It will appear on the Prayer Wall once a pastor approves it.']);
}

$pending = ($_GET['status'] ?? '') === 'pending';
if ($pending) api_require_staff($m);

$st = $pdo->prepare('SELECT request_id, display_name, category, message, status, prayer_count, created_at
                     FROM PRAYER_REQUEST WHERE status = ?' . ($pending ? '' : ' AND is_public = 1') . '
                     ORDER BY created_at DESC LIMIT 100');
$st->execute([$pending ? 'pending' : 'approved']);
api_ok(['prayers' => array_map(fn($p) => [
    'id'       => (int) $p['request_id'],
    'name'     => $p['display_name'],
    'category' => $p['category'],
    'text'     => $p['message'],
    'status'   => $p['status'],
    'praying'  => (int) $p['prayer_count'],
    'date'     => $p['created_at'],
], $st->fetchAll())]);

<?php
/* GET api/members.php   → member directory (pastor/admin only) */
require __DIR__ . '/_bootstrap.php';
api_method('GET');
$m = api_user($pdo);
api_require_staff($m);

$rows = $pdo->query('SELECT member_id, first_name, last_name, email, phone, role, is_active, membership_type
                     FROM MEMBER ORDER BY first_name, last_name')->fetchAll();
api_ok(['members' => array_map(fn($r) => member_public($r) + [
    'active'          => (bool) $r['is_active'],
    'membership_type' => $r['membership_type'],
], $rows)]);

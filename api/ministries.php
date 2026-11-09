<?php
/* GET api/ministries.php   → church groups / ministries (same MINISTRY table as the website) */
require __DIR__ . '/_bootstrap.php';
api_method('GET');
api_user($pdo);

$rows = $pdo->query('SELECT ministry_id, name, category, leader, schedule, desc_short
                     FROM MINISTRY WHERE is_active = 1 ORDER BY name')->fetchAll();
api_ok(['ministries' => array_map(fn($r) => [
    'id'       => (int) $r['ministry_id'],
    'name'     => $r['name'],
    'category' => $r['category'],
    'leader'   => $r['leader'],
    'schedule' => $r['schedule'],
    'about'    => $r['desc_short'],
], $rows)]);

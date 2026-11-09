<?php
/* GET  api/donations.php            → my giving history + total
   GET  api/donations.php?all=1      → every donation (pastor/admin only)
   POST api/donations.php  { amount, category, method }  → records a gift
   Same DONATION table the website's portal and dashboard use. */
require __DIR__ . '/_bootstrap.php';
$method = api_method('GET', 'POST');
$m = api_user($pdo);

$types   = ['Tithe', 'Offering', 'Building Fund', 'Special Seed', 'Missions', 'Thanksgiving'];
$methods = ['Cash', 'Card', 'EFT'];

if ($method === 'POST') {
    $amount = round((float) (api_input()['amount'] ?? 0), 2);
    $type   = api_str('category', 40);
    $pay    = api_str('method', 20);
    if ($amount < 1 || $amount > 1000000) api_fail('Enter an amount between R1 and R1,000,000.');
    if (!in_array($type, $types, true)) $type = 'Offering';
    if (!in_array($pay, $methods, true)) $pay = 'Cash';
    $ref = 'APP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

    $pdo->prepare('INSERT INTO DONATION (member_id, amount, donation_type, payment_method, reference, donation_date)
                   VALUES (?, ?, ?, ?, ?, CURDATE())')->execute([$m['member_id'], $amount, $type, $pay, $ref]);
    api_ok(['msg' => 'Thank you! Your gift of R' . number_format($amount, 2) . " was recorded (ref $ref).", 'reference' => $ref]);
}

$all = !empty($_GET['all']);
if ($all) api_require_staff($m);

$sql = 'SELECT d.donation_id, d.amount, d.donation_type, d.payment_method, d.reference, d.donation_date,
               CONCAT(m.first_name, " ", m.last_name) AS donor
        FROM DONATION d JOIN MEMBER m ON m.member_id = d.member_id'
     . ($all ? '' : ' WHERE d.member_id = ?') . ' ORDER BY d.donation_date DESC, d.donation_id DESC LIMIT 200';
$st = $pdo->prepare($sql);
$st->execute($all ? [] : [$m['member_id']]);

$rows = array_map(fn($d) => [
    'id'       => (int) $d['donation_id'],
    'donor'    => $d['donor'],
    'amount'   => (float) $d['amount'],
    'category' => $d['donation_type'],
    'method'   => $d['payment_method'],
    'reference'=> $d['reference'],
    'date'     => $d['donation_date'],
], $st->fetchAll());

$tot = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM DONATION' . ($all ? '' : ' WHERE member_id = ?'));
$tot->execute($all ? [] : [$m['member_id']]);
$week = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM DONATION WHERE donation_date >= CURDATE() - INTERVAL 7 DAY' . ($all ? '' : ' AND member_id = ?'));
$week->execute($all ? [] : [$m['member_id']]);

api_ok(['donations' => $rows, 'total' => (float) $tot->fetchColumn(), 'last_7_days' => (float) $week->fetchColumn()]);

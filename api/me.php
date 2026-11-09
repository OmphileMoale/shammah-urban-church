<?php
/* GET  api/me.php                     → { ok, user }
   POST api/me.php  { name, phone }     → updates the profile (website sees it too) */
require __DIR__ . '/_bootstrap.php';
$method = api_method('GET', 'POST');
$m = api_user($pdo);

if ($method === 'POST') {
    $parts = preg_split('/\s+/', api_str('name', 120), 2);
    $first = $parts[0] ?? '';
    $last  = $parts[1] ?? '-';
    $phone = normalize_phone(api_str('phone', 30));
    if (mb_strlen($first) < 2 || preg_match('/\d/', $first . $last)) api_fail('Please enter a valid name.');
    if ($phone === null) api_fail('Enter a valid phone number, e.g. 0821234567.');

    $other = find_member_by_login($pdo, $phone);
    if (isset($other['member']) && (int) $other['member']['member_id'] !== (int) $m['member_id']) {
        api_fail('This phone number is already linked to another account.', 409);
    }
    $pdo->prepare('UPDATE MEMBER SET first_name = ?, last_name = ?, phone = ? WHERE member_id = ?')
        ->execute([$first, $last, $phone, $m['member_id']]);
    $m = array_merge($m, ['first_name' => $first, 'last_name' => $last, 'phone' => $phone]);
}
api_ok(['user' => member_public($m)]);

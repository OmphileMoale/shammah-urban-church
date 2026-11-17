<?php
/* POST api/register.php
   { name | first_name + last_name, email, phone, password }
   Sends a 6-digit code to the email AND phone. The account is only created in MEMBER
   (same table as the website) after verify_code.php gets the correct code.
   Reply: { ok, ticket, sent_to, msg }  → then call verify_code.php */
require __DIR__ . '/_bootstrap.php';
api_method('POST');

$first = api_str('first_name', 60);
$last  = api_str('last_name', 60);
if ($first === '' && ($full = api_str('name', 120)) !== '') {
    $parts = preg_split('/\s+/', $full, 2);
    $first = $parts[0];
    $last  = $parts[1] ?? '';
}
$email = strtolower(api_str('email', 120));
$phone = normalize_phone(api_str('phone', 30));
$pass  = (string) (api_input()['password'] ?? '');

if (mb_strlen($first) < 2 || preg_match('/\d/', $first . $last)) api_fail('Please enter your full name.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) api_fail('Please enter a valid email address.');
if ($phone === null) api_fail('Enter a valid phone number, e.g. 0821234567 or +27821234567.');
if ($p = password_problem($pass)) api_fail($p);

$st = $pdo->prepare('SELECT 1 FROM MEMBER WHERE email = ? LIMIT 1');
$st->execute([$email]);
if ($st->fetchColumn()) api_fail('An account with this email already exists. Please sign in instead.', 409);
if (!isset(find_member_by_login($pdo, $phone)['error'])) {
    api_fail('This phone number is already linked to another account.', 409);
}

/* The account is NOT created yet. The details wait in PENDING_REGISTRATION and the member is
   only saved after verify_code.php receives the correct 6-digit code. */
api_code_sent(pending_issue($pdo, [
    'title' => '', 'first_name' => $first, 'last_name' => $last !== '' ? $last : '-',
    'email' => $email, 'phone' => $phone, 'dob' => '', 'gender' => '', 'mem_type' => '',
    'street' => '', 'city' => '', 'postal' => '', 'ministries' => [],
    'password_hash' => password_hash($pass, PASSWORD_BCRYPT),
]));

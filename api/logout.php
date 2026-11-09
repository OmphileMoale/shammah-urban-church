<?php
/* POST api/logout.php   → deletes this phone's sign-in token */
require __DIR__ . '/_bootstrap.php';
api_method('POST');
$token = api_bearer_token();
if ($token !== '') $pdo->prepare('DELETE FROM API_TOKEN WHERE token_hash = ?')->execute([hash('sha256', $token)]);
api_ok(['msg' => 'Signed out.']);

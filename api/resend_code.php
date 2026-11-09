<?php
/* POST api/resend_code.php   { ticket }   → sends a new code for the same ticket */
require __DIR__ . '/_bootstrap.php';
api_method('POST');
api_code_sent(otp_resend($pdo, api_str('ticket', 64)));

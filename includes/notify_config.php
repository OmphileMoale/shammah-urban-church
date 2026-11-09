<?php
/* ═══════════════════════════════════════
   includes/notify_config.php  —  EMAIL + SMS SETTINGS

   Contact form messages are delivered to CONTACT_EMAIL.
   SMTP credentials are read from Apache environment variables.
   Other notifications use the same SMTP account.

   Do NOT commit real passwords to Azure Repos —
   keep a copy with blanks in the repo.
═══════════════════════════════════════ */

/* ── 1. EMAIL (Gmail SMTP — free) ─────────────────────────
   a) Turn on 2-Step Verification on the Gmail account
   b) Google Account > Security > App passwords > create one
      (16 letters, e.g. "abcd efgh ijkl mnop")
   c) Open http://localhost/shammah-urban-church/setup_email.php on this PC,
      paste the 16 letters and click Save (or set SHAMMAH_SMTP_PASS in Apache). */
define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_PORT',      465);                 // 465 = SSL, 587 = STARTTLS
define('CONTACT_EMAIL',  'babatundesaliu19@gmail.com');
define('SMTP_USER',      getenv('SHAMMAH_SMTP_USER') ?: CONTACT_EMAIL);
// Read from Apache's environment (SetEnv SHAMMAH_SMTP_PASS) or, if that isn't set,
// from includes/smtp_secret.php, which setup_email.php writes. Neither is in the repo.
define('SMTP_PASS',      getenv('SHAMMAH_SMTP_PASS')
                         ?: (is_file(__DIR__ . '/smtp_secret.php') ? (string) (include __DIR__ . '/smtp_secret.php') : ''));
define('SMTP_FROM_NAME', 'Shammah Urban Church');

/* ── 2. SMS (BulkSMS.com — South African, pay per SMS) ────
   a) Sign up at https://www.bulksms.com and buy a few credits
   b) Settings > API Tokens > Create Token
   c) Copy the Token ID and Token Secret below              */
define('SMS_PROVIDER',     'bulksms');
define('BULKSMS_TOKEN_ID', '');
define('BULKSMS_SECRET',   '');

/* ── 3. GOOGLE SIGN-IN (app's "Sign in with Google" button) ──
   Same "Web client ID" as GOOGLE_WEB_CLIENT_ID in the app.
   Leave blank if you don't use Google sign-in.             */
define('GOOGLE_WEB_CLIENT_ID', '');

/* ── 4. TEST MODE ─────────────────────────────────────────
   true  = if NEITHER email nor SMS could be sent, the code is
           shown on screen so you can still demo the login.
   false = never show the code (use this for the real church). */
define('OTP_SHOW_CODE_IF_UNSENT', true);

define('DEFAULT_COUNTRY_CODE', '27');

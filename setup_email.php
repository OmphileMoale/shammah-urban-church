<?php
/* ═══════════════════════════════════════
   setup_email.php  —  SAVE THE GMAIL APP PASSWORD

   Paste the 16-letter Gmail App Password, click Save.
   It is stored in includes/smtp_secret.php (kept out of
   Azure Repos by .gitignore), then a test email is sent
   to the church inbox to prove Gmail accepts it.

   Only works when opened on the PC running XAMPP
   (http://localhost/...), never from another device.
═══════════════════════════════════════ */
session_start();

$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('This page only works on the PC running XAMPP: open http://localhost/shammah-urban-church/setup_email.php');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$secretFile = __DIR__ . '/includes/smtp_secret.php';
$result = null;   // ['ok' => bool, 'msg' => string]

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf']);
    $pass    = strtolower(preg_replace('/\s+/', '', $_POST['app_password'] ?? ''));

    if (!$csrf_ok) {
        $result = ['ok' => false, 'msg' => 'Security token mismatch. Refresh the page and try again.'];
    } elseif (!preg_match('/^[a-z]{16}$/', $pass)) {
        $result = ['ok' => false, 'msg' => 'That is not a Gmail App Password. It should be 16 letters, like "abcd efgh ijkl mnop".'];
    } elseif (@file_put_contents($secretFile, "<?php\n// Gmail App Password written by setup_email.php. Do not commit.\nreturn '" . $pass . "';\n") === false) {
        $result = ['ok' => false, 'msg' => 'Could not save the password: Windows blocked writing to the includes folder.'];
    } else {
        require_once __DIR__ . '/includes/notify.php';   // reads the password just saved
        try {
            smtp_send(CONTACT_EMAIL, 'Shammah website: email is working',
                "Hi,\n\nThis test email confirms the Shammah Urban Church website can send email through Gmail.\n" .
                "Sign-in codes and contact form messages will now be delivered.\n\nShammah Urban Church website");
            $result = ['ok' => true, 'msg' => 'Saved. Gmail accepted the password and a test email was sent to ' . CONTACT_EMAIL . '. Check your inbox (and Spam).'];
        } catch (Throwable $e) {
            @unlink($secretFile);    // don't keep a password Gmail rejects
            $why = $e->getMessage();
            $hint = (stripos($why, '535') !== false || stripos($why, 'Username and Password') !== false)
                ? 'Gmail rejected the password. Create a new App Password and paste it again.'
                : 'The PC could not reach Gmail. Check the internet connection and try again.';
            $result = ['ok' => false, 'msg' => $hint . ' (Details: ' . htmlspecialchars($why) . ')'];
        }
    }
}

$configured = is_file($secretFile) || getenv('SHAMMAH_SMTP_PASS');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Email setup | Shammah Urban Church</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body { background: var(--ivory); font-family: var(--font-b); margin: 0; }
    .box { max-width: 480px; margin: 60px auto; background: #fff; border-radius: var(--r-lg);
           box-shadow: var(--shadow); padding: 32px; }
    h1 { font-family: var(--font-d); color: var(--navy); margin: 0 0 8px; font-size: 1.6rem; }
    p, li { color: var(--text); line-height: 1.55; font-size: .95rem; }
    label { display: block; font-weight: 600; color: var(--navy); margin: 18px 0 6px; font-size: .85rem; }
    input[type=password], input[type=text] { width: 100%; box-sizing: border-box; padding: 12px 14px;
           border: 1.5px solid var(--border-md); border-radius: var(--r); font-size: 1.05rem; letter-spacing: .08em; }
    button { margin-top: 16px; width: 100%; padding: 13px; border: 0; border-radius: var(--r);
             background: var(--navy); color: #fff; font-weight: 600; font-size: 1rem; cursor: pointer; }
    .msg { padding: 12px 14px; border-radius: var(--r); margin: 16px 0 0; font-size: .92rem; }
    .ok  { background: #EEF6E4; color: #3B5E12; }
    .err { background: #FBEAEA; color: var(--red); }
    .status { font-size: .85rem; opacity: .8; }
    .show { font-weight: 400; font-size: .85rem; margin-top: 8px; display: flex; gap: 6px; align-items: center; }
  </style>
</head>
<body>
  <main class="box">
    <h1>Email setup</h1>
    <p>Sends sign-in codes and contact form messages from <strong>babatundesaliu19@gmail.com</strong>.</p>
    <ol>
      <li>Open <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">myaccount.google.com/apppasswords</a> and create an App Password.</li>
      <li>Copy the 16 letters Google shows and paste them below.</li>
    </ol>

    <?php if ($result): ?>
      <div class="msg <?= $result['ok'] ? 'ok' : 'err' ?>" role="alert"><?= $result['msg'] ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <label for="app_password">Gmail App Password</label>
      <input type="password" id="app_password" name="app_password" placeholder="abcd efgh ijkl mnop" required>
      <label class="show"><input type="checkbox" onclick="app_password.type = this.checked ? 'text' : 'password'"> Show letters</label>
      <button type="submit">Save and send test email</button>
    </form>

    <p class="status">Status: <?= $configured ? '✅ an App Password is saved' : 'not set up yet (codes are shown on screen in test mode)' ?></p>
    <p class="status"><a href="index.php">← Back to the website</a></p>
  </main>
</body>
</html>

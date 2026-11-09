<?php
/* ═══════════════════════════════════════
   forgot_password.php  –  RESET PASSWORD BY CODE

   1. Member types their email OR phone number
   2. A 6-digit code is sent to their email AND phone
   3. They type the code + a new password (js/otp.js)
   4. Password is updated in MEMBER — the new password
      works on the website AND in the Shammah app.
═══════════════════════════════════════ */
session_start();
require_once 'includes/data.php';
require_once 'includes/db.php';

$is_logged_in = isset($_SESSION['member_id']);
$user_name    = $is_logged_in ? htmlspecialchars($_SESSION['member_name'] ?? 'Member') : '';
$user_role    = $is_logged_in ? ($_SESSION['role'] ?? 'member') : '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    require_once 'includes/auth.php';
    auth_ensure_schema($pdo);

    $csrf_ok = isset($_POST['csrf'], $_SESSION['csrf_token'])
               && hash_equals($_SESSION['csrf_token'], $_POST['csrf']);
    if (!$csrf_ok) {
        echo json_encode(['ok' => false, 'msg' => 'Security token mismatch. Please refresh the page.']);
        exit;
    }

    $action = $_POST['action'] ?? 'send';

    if ($action === 'send') {
        $found = find_member_by_login($pdo, $_POST['login'] ?? '');
        if (isset($found['error']) || empty($found['member']['is_active'])) {
            $msg = ($found['error'] ?? '') === 'phone_shared'
                ? 'More than one account uses that phone number. Please use your email address.'
                : 'No active account was found for that email or phone number.';
            echo json_encode(['ok' => false, 'msg' => $msg]);
            exit;
        }
        $sent = otp_issue($pdo, $found['member'], 'reset');
        if (isset($sent['error'])) {
            echo json_encode(['ok' => false, 'msg' => $sent['error']]);
            exit;
        }
        $_SESSION['reset_ticket'] = $sent['ticket'];
        echo json_encode(['ok' => true, 'msg' => $sent['message'], 'dev_code' => $sent['dev_code'] ?? null]);
        exit;
    }

    $ticket = $_SESSION['reset_ticket'] ?? '';
    if ($ticket === '') {
        echo json_encode(['ok' => false, 'msg' => 'Your reset request expired. Please start again.']);
        exit;
    }

    if ($action === 'resend') {
        $r = otp_resend($pdo, $ticket);
        echo json_encode(isset($r['error'])
            ? ['ok' => false, 'msg' => $r['error']]
            : ['ok' => true, 'msg' => $r['message'], 'dev_code' => $r['dev_code'] ?? null]);
        exit;
    }

    if ($action === 'verify') {
        $new = $_POST['new_password'] ?? '';
        if ($problem = password_problem($new)) {
            echo json_encode(['ok' => false, 'msg' => $problem]);
            exit;
        }
        if ($new !== ($_POST['confirm_password'] ?? '')) {
            echo json_encode(['ok' => false, 'msg' => 'The two passwords do not match.']);
            exit;
        }
        $r = otp_verify($pdo, $ticket, $_POST['code'] ?? '', ['reset']);
        if (isset($r['error'])) {
            echo json_encode(['ok' => false, 'msg' => $r['error']]);
            exit;
        }
        $id = (int) $r['member']['member_id'];
        $pdo->prepare('UPDATE MEMBER SET password_hash = ? WHERE member_id = ?')
            ->execute([password_hash($new, PASSWORD_BCRYPT), $id]);
        clear_failed_logins($pdo, $id);
        $pdo->prepare('DELETE FROM API_TOKEN WHERE member_id = ?')->execute([$id]);  // sign the app out everywhere
        unset($_SESSION['reset_ticket']);

        $dest = start_web_session($r['member']);
        echo json_encode(['ok' => true, 'msg' => 'Password changed. You are now signed in.', 'dest' => $dest]);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Unknown request.']);
    exit;
}

$page_title = 'Reset Password | Shammah Urban Church';
$page_desc  = 'Reset your Shammah Urban Church account password with a code sent to your email and phone.';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>
<link rel="stylesheet" href="css/login.css">
<link rel="stylesheet" href="css/otp.css">

<main id="main-content">
  <section class="login-section" aria-label="Reset password">
    <div class="login-left" aria-hidden="true">
      <div class="login-left-bg"></div>
      <div class="login-left-inner">
        <div class="login-brand-logo">SUC</div>
        <h2 class="login-brand-name">Shammah Urban<br><em>Church</em></h2>
        <p class="login-brand-tagline">"Experience the Touch of His Presence"</p>
        <div class="login-divider"></div>
        <blockquote class="login-scripture">
          <p>"Create in me a clean heart, O God, and renew a right spirit within me."</p>
          <cite>Psalm 51:10</cite>
        </blockquote>
      </div>
    </div>

    <div class="login-right">
      <div class="login-form-wrap">
        <div class="login-mobile-logo" aria-hidden="true">SUC</div>
        <div class="login-form-eyebrow">Forgot Password</div>
        <h1 class="login-form-title">Reset your <em>Password</em></h1>
        <p class="login-form-sub">
          Enter the email or phone number on your account. We'll send a 6-digit code to your email and phone.
        </p>

        <form id="resetForm" novalidate aria-label="Reset password form">
          <div class="lf-group">
            <label class="lf-label" for="rp-login">
              Email or Phone Number <span class="lf-req" aria-hidden="true">*</span>
            </label>
            <div class="lf-input-wrap">
              <span class="lf-icon" aria-hidden="true"><i class="ti ti-user"></i></span>
              <input class="lf-input" type="text" id="rp-login" name="login"
                     placeholder="thabo@email.com or 071 234 5678"
                     autocomplete="username" required aria-required="true">
            </div>
          </div>

          <div class="lf-attempt-warn" id="rp-warn" role="alert" aria-live="polite"></div>

          <button class="lf-submit" type="submit" id="rp-submit">
            <i class="ti ti-send" aria-hidden="true"></i>
            <span>Send Code</span>
          </button>

          <div class="lf-divider" aria-hidden="true"><span>or</span></div>
          <a href="login.php" class="lf-register-btn">
            <i class="ti ti-arrow-left" aria-hidden="true"></i>
            Back to Sign In
          </a>
        </form>
      </div>
    </div>
  </section>
</main>

<script>
  const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
</script>
<script src="js/otp.js"></script>
<script>
(() => {
  const form = document.getElementById('resetForm');
  const warn = document.getElementById('rp-warn');
  const btn  = document.getElementById('rp-submit');

  const showWarn = (msg) => { warn.textContent = msg; warn.classList.add('visible'); };

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    warn.classList.remove('visible');
    const login = document.getElementById('rp-login').value.trim();
    if (login.length < 5) { showWarn('Please enter your email address or phone number.'); return; }

    btn.disabled = true;
    try {
      const fd = new FormData();
      fd.append('csrf', CSRF_TOKEN);
      fd.append('action', 'send');
      fd.append('login', login);
      const data = await (await fetch('forgot_password.php', { method: 'POST', body: fd })).json();
      if (!data.ok) { showWarn(data.msg); return; }

      OtpBox.open({
        url:     'forgot_password.php',
        title:   'Reset your password',
        csrf:    CSRF_TOKEN,
        message: data.msg,
        devCode: data.dev_code,
        extraHtml: `
          <label class="otp-label" for="otp-new">New password</label>
          <input class="otp-extra" type="password" id="otp-new" autocomplete="new-password"
                 placeholder="8+ characters, a letter and a number">
          <label class="otp-label" for="otp-confirm">Confirm new password</label>
          <input class="otp-extra" type="password" id="otp-confirm" autocomplete="new-password">`,
        extraFields: (box) => ({
          new_password:     box.querySelector('#otp-new').value,
          confirm_password: box.querySelector('#otp-confirm').value,
        }),
        onSuccess: (res) => {
          if (typeof showToast === 'function') showToast(res.msg, 'success');
          setTimeout(() => { window.location.href = res.dest ?? 'member_portal.php'; }, 1000);
        },
      });
    } catch (err) {
      showWarn('Network error. Please check your connection and try again.');
    } finally {
      btn.disabled = false;
    }
  });
})();
</script>

<?php require_once 'includes/footer.php'; ?>

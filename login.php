<?php
/* ═══════════════════════════════════════
   login.php  –  MEMBER LOGIN
   
   HOW THIS FILE WORKS:
   1. session_start()    – reads the PHP session
   2. require_once       – loads shared files
   3. REDIRECT           – if already logged in,
                           send to the portal
   4. FORM HANDLER       – processes POST, verifies
                           bcrypt password, sets
                           session, returns JSON
   5. HTML               – login page body
═══════════════════════════════════════ */
session_start();
require_once 'includes/data.php';
require_once 'includes/db.php';

/* ── REDIRECT IF ALREADY LOGGED IN ──── */
$is_logged_in = isset($_SESSION['member_id']);
$user_name    = $is_logged_in ? htmlspecialchars($_SESSION['member_name'] ?? 'Member') : '';
$user_role    = $is_logged_in ? ($_SESSION['role'] ?? 'member') : '';

if ($is_logged_in) {
    $dest = ($user_role === 'admin' || $user_role === 'pastor')
            ? 'admin_dashboard.php'
            : 'member_portal.php';
    header("Location: {$dest}");
    exit;
}

/* ── GENERATE CSRF TOKEN ──────────────── */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ═══════════════════════════════════════
   FORM HANDLER — POST request (JSON reply)

   Signing in is two steps, the same as in the app:
     1. action=login   – email OR phone + password.
                         password_verify() checks the bcrypt hash.
                         3 wrong passwords = 15 min lock
                         (login_locked_ – stored in MEMBER.locked_until,
                          so the lock also applies in the app).
                         A 6-digit code is then sent to the
                         member's email AND phone.
     2. action=verify  – the code is checked and the session starts.
        action=resend  – sends a new code.

   The code ticket stays in the PHP session; the browser
   never sees it. The plain password is never logged or stored.
═══════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    require_once 'includes/auth.php';
    auth_ensure_schema($pdo);

    /* ── CSRF CHECK ──────────────────── */
    $csrf_ok = isset($_POST['csrf'], $_SESSION['csrf_token'])
               && hash_equals($_SESSION['csrf_token'], $_POST['csrf']);
    if (!$csrf_ok) {
        echo json_encode(['ok' => false, 'msg' => 'Security token mismatch. Please refresh the page.']);
        exit;
    }

    $action = $_POST['action'] ?? 'login';

    /* ── STEP 2: CHECK THE CODE ──────── */
    if ($action === 'verify' || $action === 'resend') {
        $ticket = $_SESSION['otp_ticket'] ?? '';
        if ($ticket === '') {
            echo json_encode(['ok' => false, 'msg' => 'Your sign-in expired. Please enter your password again.']);
            exit;
        }
        if ($action === 'resend') {
            $r = otp_resend($pdo, $ticket);
            echo json_encode(isset($r['error'])
                ? ['ok' => false, 'msg' => $r['error']]
                : ['ok' => true, 'msg' => $r['message'], 'dev_code' => $r['dev_code'] ?? null]);
            exit;
        }
        $r = otp_verify($pdo, $ticket, $_POST['code'] ?? '', ['login', 'register']);
        if (isset($r['error'])) {
            echo json_encode(['ok' => false, 'msg' => $r['error']]);
            exit;
        }
        $dest = start_web_session($r['member'], !empty($_SESSION['otp_remember']));
        echo json_encode([
            'ok'   => true,
            'msg'  => ($r['purpose'] === 'register' ? 'Welcome to Shammah, ' : 'Welcome back, ')
                      . htmlspecialchars($r['member']['first_name']) . '! Redirecting…',
            'dest' => $dest,
        ]);
        exit;
    }

    /* ── STEP 1: EMAIL/PHONE + PASSWORD ─ */
    $login    = trim($_POST['login'] ?? $_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || strlen($password) < 1) {
        echo json_encode(['ok' => false, 'msg' => 'Please enter your email or phone number and your password.']);
        exit;
    }

    $r = check_password_login($pdo, $login, $password);   // password_verify() + login_locked_ rules
    if (isset($r['error'])) {
        echo json_encode(['ok' => false, 'msg' => $r['error']]);
        exit;
    }

    $sent = otp_issue($pdo, $r['member'], 'login');
    if (isset($sent['error'])) {
        echo json_encode(['ok' => false, 'msg' => $sent['error']]);
        exit;
    }
    $_SESSION['otp_ticket']   = $sent['ticket'];
    $_SESSION['otp_remember'] = isset($_POST['remember']);

    echo json_encode([
        'ok'       => true,
        'step'     => 'code',
        'msg'      => $sent['message'],
        'dev_code' => $sent['dev_code'] ?? null,
    ]);
    exit;
}

/* ── PAGE META ──────────────────────── */
$page_title = 'Login | Shammah Urban Church';
$page_desc  = 'Log in to your Shammah Urban Church member account — access sermons, RSVP to events, and manage your membership.';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>
<link rel="stylesheet" href="css/login.css">
<link rel="stylesheet" href="css/otp.css">

<main id="main-content">

  <!-- ═══════════════════════════════════
       SPLIT-SCREEN LOGIN LAYOUT
       Left panel: branding + role info
       Right panel: the actual login form
  ════════════════════════════════════ -->
  <section class="login-section" aria-label="Member login">

    <!-- ── LEFT PANEL — BRANDING ── -->
    <div class="login-left" aria-hidden="true">
      <div class="login-left-bg"></div>
      <div class="login-left-inner">

        <!-- Church logo -->
        <div class="login-brand-logo">SUC</div>
        <h2 class="login-brand-name">Shammah Urban<br><em>Church</em></h2>
        <p class="login-brand-tagline">"Experience the Touch of His Presence"</p>

        <div class="login-divider"></div>

        <!-- Scripture -->
        <blockquote class="login-scripture">
          <p>"For I know the plans I have for you, declares the Lord — plans to prosper you."</p>
          <cite>Jeremiah 29:11</cite>
        </blockquote>

        <div class="login-divider"></div>

        <!-- Role access panel -->
        <div class="login-roles">
          <div class="login-roles-title">Portal Access</div>
          <div class="login-role-grid">
            <div class="lr-item"><span class="lr-icon">👑</span><span>Admin</span></div>
            <div class="lr-item"><span class="lr-icon">🙏</span><span>Pastor</span></div>
            <div class="lr-item"><span class="lr-icon">👤</span><span>Member</span></div>
            <div class="lr-item"><span class="lr-icon">🛠</span><span>Staff</span></div>
            <div class="lr-item"><span class="lr-icon">🤝</span><span>Volunteer</span></div>
            <div class="lr-item"><span class="lr-icon">✝️</span><span>Visitor</span></div>
          </div>
        </div>

        <!-- Decorative cross pattern -->
        <div class="login-deco" aria-hidden="true"></div>
      </div>
    </div>

    <!-- ── RIGHT PANEL — FORM ── -->
    <div class="login-right">
      <div class="login-form-wrap">

        <!-- Mobile-only logo (hidden on desktop) -->
        <div class="login-mobile-logo" aria-hidden="true">SUC</div>

        <div class="login-form-eyebrow">Welcome Back</div>
        <h1 class="login-form-title">Sign in to your <em>Account</em></h1>
        <p class="login-form-sub">
          Access your member portal, sermons, events and giving history.
        </p>

        <!-- ── THE LOGIN FORM ── -->
        <!--
          id="loginForm" used by login.js.
          novalidate = our custom JS validation runs instead
          of browser default bubbles.
        -->
        <form id="loginForm" novalidate aria-label="Member login form">

          <!-- CSRF token — verified server-side -->
          <input type="hidden" name="csrf"
                 value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

          <!-- Email field -->
          <div class="lf-group">
            <label class="lf-label" for="lf-email">
              Email or Phone Number <span class="lf-req" aria-hidden="true">*</span>
            </label>
            <div class="lf-input-wrap">
              <span class="lf-icon" aria-hidden="true">
                <i class="ti ti-user"></i>
              </span>
              <input class="lf-input" type="text" id="lf-email" name="login"
                     placeholder="thabo@email.com or 071 234 5678"
                     autocomplete="username"
                     required
                     aria-required="true"
                     aria-describedby="lf-email-err">
            </div>
            <span class="lf-error" id="lf-email-err" role="alert"></span>
          </div>

          <!-- Password field -->
          <div class="lf-group">
            <div class="lf-label-row">
              <label class="lf-label" for="lf-password">
                Password <span class="lf-req" aria-hidden="true">*</span>
              </label>
              <!-- Forgot password link -->
              <a href="forgot_password.php" class="lf-forgot"
                 tabindex="0" aria-label="Forgot your password?">
                Forgot password?
              </a>
            </div>
            <div class="lf-input-wrap">
              <span class="lf-icon" aria-hidden="true">
                <i class="ti ti-lock"></i>
              </span>
              <input class="lf-input lf-input--pw" type="password" id="lf-password" name="password"
                     placeholder="Your password"
                     autocomplete="current-password"
                     required
                     aria-required="true"
                     aria-describedby="lf-password-err">
              <!--
                Show/hide toggle — login.js toggles
                type="password" ↔ type="text".
              -->
              <button type="button" class="lf-pw-toggle"
                      id="lf-pw-toggle"
                      aria-label="Show password"
                      aria-pressed="false">
                <i class="ti ti-eye" aria-hidden="true"></i>
              </button>
            </div>
            <span class="lf-error" id="lf-password-err" role="alert"></span>
          </div>

          <!-- Remember me checkbox -->
          <div class="lf-remember-row">
            <label class="lf-remember-label" for="lf-remember">
              <input type="checkbox" id="lf-remember" name="remember">
              <span class="lf-checkbox-box" aria-hidden="true"></span>
              Keep me signed in for 30 days
            </label>
          </div>

          <!-- Attempt warning — shown by JS after failed logins -->
          <div class="lf-attempt-warn" id="lf-attempt-warn" role="alert" aria-live="polite"></div>

          <!-- Submit button -->
          <button class="lf-submit" type="submit" id="lf-submit-btn">
            <i class="ti ti-login" aria-hidden="true"></i>
            <span id="lf-btn-label">Sign In</span>
          </button>

          <!-- Divider -->
          <div class="lf-divider" aria-hidden="true">
            <span>or</span>
          </div>

          <!-- Register link -->
          <a href="register.php" class="lf-register-btn">
            <i class="ti ti-user-plus" aria-hidden="true"></i>
            Create a New Account
          </a>

        </form>

        <!-- Security note -->
        <p class="lf-security-note">
          <i class="ti ti-shield-lock" aria-hidden="true"></i>
          Your login is protected by bcrypt encryption, CSRF tokens and a
          6-digit code sent to your email and phone. Accounts lock after 3 failed attempts.
          The same account works in the Shammah app.
        </p>

      </div>
    </div>

  </section>

</main>

<!-- PHP → JS bridge: pass CSRF token to login.js -->
<script>
  const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
</script>
<script src="js/otp.js"></script>
<script src="js/login.js"></script>

<?php require_once 'includes/footer.php'; ?>

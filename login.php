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

/* ── TRACK LOGIN ATTEMPTS (brute-force protection) ──
   After 3 failed attempts the account is locked
   for 15 minutes. Stored in the session per email.
──────────────────────────────────────────────────── */

/* ═══════════════════════════════════════
   FORM HANDLER — POST request
   Returns JSON so JS can show a toast
   without a full page reload.
   
   SECURITY:
   – CSRF token verified first (timing-safe)
   – password_verify() checks bcrypt hash
   – Brute-force counter stored in session
   – Plain password NEVER logged or stored
═══════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    /* ── CSRF CHECK ──────────────────── */
    $csrf_ok = isset($_POST['csrf'], $_SESSION['csrf_token'])
               && hash_equals($_SESSION['csrf_token'], $_POST['csrf']);
    if (!$csrf_ok) {
        echo json_encode(['ok' => false, 'msg' => 'Security token mismatch. Please refresh the page.']);
        exit;
    }

    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $remember = isset($_POST['remember']);

    /* ── BASIC VALIDATION ────────────── */
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 1) {
        echo json_encode(['ok' => false, 'msg' => 'Please enter a valid email address and password.']);
        exit;
    }

    /* ── BRUTE-FORCE COUNTER ─────────── */
    $attempt_key = 'login_attempts_' . md5($email);
    $lock_key    = 'login_locked_'   . md5($email);

    if (!empty($_SESSION[$lock_key]) && $_SESSION[$lock_key] > time()) {
        $wait = ceil(($_SESSION[$lock_key] - time()) / 60);
        echo json_encode(['ok' => false, 'msg' => "Account temporarily locked. Please wait {$wait} minute(s) before trying again."]);
        exit;
    }

    /* ── VERIFY CREDENTIALS ─────────── */
    $stmt = $pdo->prepare('
        SELECT member_id, first_name, last_name,
               password_hash, role, is_active
        FROM   MEMBER
        WHERE  email = ?
        LIMIT  1
    ');
    $stmt->execute([$email]);
    $member = $stmt->fetch();

    $valid = $member
             && !empty($member['is_active'])
             && password_verify($password, $member['password_hash']);

    if (!$valid) {
        /* Increment failure counter */
        $_SESSION[$attempt_key] = ($_SESSION[$attempt_key] ?? 0) + 1;
        $remaining = max(0, 3 - $_SESSION[$attempt_key]);

        if ($_SESSION[$attempt_key] >= 3) {
            $_SESSION[$lock_key] = time() + (15 * 60); // lock 15 min
            unset($_SESSION[$attempt_key]);
            echo json_encode(['ok' => false, 'msg' => 'Too many failed attempts. Account locked for 15 minutes.']);
        } else {
            $plural = $remaining === 1 ? 'attempt' : 'attempts';
            echo json_encode(['ok' => false, 'msg' => "Invalid email or password. {$remaining} {$plural} remaining before lockout."]);
        }
        exit;
    }

    /* ── SUCCESS — SET SESSION ─────── */
    unset($_SESSION[$attempt_key], $_SESSION[$lock_key]);
    session_regenerate_id(true); // prevent session fixation

    $_SESSION['member_id']   = $member['member_id'];
    $_SESSION['member_name'] = htmlspecialchars($member['first_name'] . ' ' . $member['last_name']);
    $_SESSION['role']        = $member['role'] ?? 'member';

    /* Remember me — extend cookie lifetime to 30 days */
    if ($remember) {
        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires'  => time() + (30 * 24 * 60 * 60),
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    $dest = ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'pastor')
            ? 'admin_dashboard.php'
            : 'member_portal.php';

    echo json_encode([
        'ok'   => true,
        'msg'  => 'Welcome back, ' . htmlspecialchars($member['first_name']) . '! Redirecting…',
        'dest' => $dest,
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
              Email Address <span class="lf-req" aria-hidden="true">*</span>
            </label>
            <div class="lf-input-wrap">
              <span class="lf-icon" aria-hidden="true">
                <i class="ti ti-mail"></i>
              </span>
              <input class="lf-input" type="email" id="lf-email" name="email"
                     placeholder="thabo@email.com"
                     autocomplete="email"
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
          Your login is protected by bcrypt encryption and CSRF tokens.
          Accounts lock after 3 failed attempts.
        </p>

      </div>
    </div>

  </section>

</main>

<!-- PHP → JS bridge: pass CSRF token to login.js -->
<script>
  const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
</script>
<script src="js/login.js"></script>

<?php require_once 'includes/footer.php'; ?>

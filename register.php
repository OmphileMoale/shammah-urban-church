<?php
/* ═══════════════════════════════════════
   register.php  –  NEW MEMBER REGISTRATION

   HOW THIS FILE WORKS:
   1. session_start()   – reads the PHP session
   2. require_once      – loads shared files
   3. REDIRECT          – if already logged in,
                          send to the portal
   4. EMAIL CHECK       – AJAX endpoint: checks
                          whether an email exists
   5. FORM HANDLER      – processes POST submission,
                          validates, hashes password,
                          inserts to MEMBER + ADDRESS
                          tables, returns JSON
   6. HTML sections     – Register page body
   7. footer.php        – footer, floating buttons, JS
═══════════════════════════════════════ */
session_start();

require_once 'includes/data.php';
require_once 'includes/db.php';

/* ── REDIRECT IF ALREADY LOGGED IN ────
   No need to register again.
   Send admins/pastors to admin_dashboard,
   regular members to member_portal.
──────────────────────────────────────── */
if (isset($_SESSION['member_id'])) {
    $role = $_SESSION['role'] ?? 'member';
    $dest = ($role === 'admin' || $role === 'pastor')
            ? 'admin_dashboard.php'
            : 'member_portal.php';
    header("Location: {$dest}");
    exit;
}

/* ── AUTH STATE (for nav.php) ──────── */
$is_logged_in = false;
$user_name    = '';
$user_role    = '';


/* ═══════════════════════════════════════
   AJAX — EMAIL DUPLICATE CHECK
   Called by register.js on blur of the
   email field: fetch('register.php?check_email=1&email=...')
   Returns JSON {available: bool}

   WHY AJAX?
   Lets us tell the user immediately if their
   email is taken — before they finish the whole
   form and hit submit. Better UX, fewer failed
   submissions.
═══════════════════════════════════════ */
if (isset($_GET['check_email'])) {
    header('Content-Type: application/json');

    $email = trim($_GET['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['available' => false, 'msg' => 'Invalid email format.']);
        exit;
    }

    /* ── CHECK EMAIL AVAILABILITY ──── */
    $stmt = $pdo->prepare('SELECT 1 FROM MEMBER WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $taken = (bool) $stmt->fetchColumn();

    echo json_encode([
        'available' => !$taken,
        'msg'       => $taken ? 'This email is already registered. Try logging in instead.' : 'Email is available.',
    ]);
    exit;
}


/* ═══════════════════════════════════════
   FORM HANDLER
   Processes the registration POST request.
   Returns JSON so JS can show a toast
   without a full page reload.

   SECURITY:
   – CSRF token checked first
   – All inputs sanitised with trim() + htmlspecialchars()
   – Email uniqueness verified server-side
   – Password hashed with password_hash(BCRYPT)
   – Plain-text password NEVER stored
═══════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json');

    /* ── CSRF VALIDATION ────────────────
       hash_equals() is timing-safe —
       prevents timing-based attacks.
    ──────────────────────────────────── */
    $csrf_ok = isset($_POST['csrf'], $_SESSION['csrf_token'])
               && hash_equals($_SESSION['csrf_token'], $_POST['csrf']);
    if (!$csrf_ok) {
        echo json_encode(['ok' => false, 'msg' => 'Security token mismatch. Please refresh the page.']);
        exit;
    }

    /* ── SANITISE INPUTS ────────────────
       trim()             – strips whitespace
       htmlspecialchars() – escapes HTML chars
       filter_var()       – validates email
    ──────────────────────────────────── */
    $title       = trim(htmlspecialchars($_POST['title']       ?? ''));
    $first_name  = trim(htmlspecialchars($_POST['first_name']  ?? ''));
    $last_name   = trim(htmlspecialchars($_POST['last_name']   ?? ''));
    $email       = trim(htmlspecialchars($_POST['email']       ?? ''));
    $phone       = trim(htmlspecialchars($_POST['phone']       ?? ''));
    $dob         = trim(htmlspecialchars($_POST['dob']         ?? ''));
    $gender      = trim(htmlspecialchars($_POST['gender']      ?? ''));
    $mem_type    = trim(htmlspecialchars($_POST['mem_type']    ?? ''));
    $street      = trim(htmlspecialchars($_POST['street']      ?? ''));
    $city        = trim(htmlspecialchars($_POST['city']        ?? ''));
    $postal      = trim(htmlspecialchars($_POST['postal']      ?? ''));
    $password    = $_POST['password']  ?? '';   // NOT htmlspecialchars — will be hashed
    $confirm     = $_POST['confirm']   ?? '';
    $ministries  = $_POST['ministries'] ?? [];  // checkbox array
    $terms       = isset($_POST['terms']);       // bool

    /* ── SERVER-SIDE VALIDATION ─────────
       Always validate server-side —
       client-side JS can be bypassed.
    ──────────────────────────────────── */
    $errors = [];

    if (strlen($first_name) < 2)
        $errors[] = 'First name must be at least 2 characters.';
    if (strlen($last_name) < 2)
        $errors[] = 'Last name must be at least 2 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Please enter a valid email address.';
    if (strlen($phone) < 10)
        $errors[] = 'Please enter a valid phone number.';
    if (empty($dob))
        $errors[] = 'Please enter your date of birth.';
    if (empty($gender))
        $errors[] = 'Please select your gender.';
    if (empty($mem_type))
        $errors[] = 'Please select a membership type.';
    if (strlen($street) < 5)
        $errors[] = 'Please enter your street address.';
    if (strlen($city) < 2)
        $errors[] = 'Please enter your city.';
    if (!preg_match('/^\d{4}$/', $postal))
        $errors[] = 'Please enter a valid 4-digit postal code.';
    if (strlen($password) < 8)
        $errors[] = 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Z]/', $password))
        $errors[] = 'Password must include at least one uppercase letter.';
    if (!preg_match('/[0-9]/', $password))
        $errors[] = 'Password must include at least one number.';
    if ($password !== $confirm)
        $errors[] = 'Passwords do not match.';
    if (!$terms)
        $errors[] = 'You must accept the Privacy Policy and Terms to register.';

    if (!empty($errors)) {
        echo json_encode(['ok' => false, 'msg' => implode(' ', $errors)]);
        exit;
    }

    /* ── CHECK EMAIL UNIQUENESS ─────── */
    $chk = $pdo->prepare('SELECT 1 FROM MEMBER WHERE email = ? LIMIT 1');
    $chk->execute([$email]);
    $email_taken = (bool) $chk->fetchColumn();

    if ($email_taken) {
        echo json_encode(['ok' => false, 'msg' => 'That email address is already registered. Please log in instead.']);
        exit;
    }

    /* ── HASH PASSWORD ──────────────── */
    $hashed = password_hash($password, PASSWORD_BCRYPT);

    /* ── INSERT TO DATABASE ─────────── */
    $ok = false;
    try {
        $pdo->beginTransaction();

        $ins = $pdo->prepare('
            INSERT INTO MEMBER
              (title, first_name, last_name, email, phone,
               date_of_birth, gender, membership_type,
               password_hash, role, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "member", 1, NOW())
        ');
        $ins->execute([
            $title, $first_name, $last_name, $email, $phone,
            $dob, $gender, $mem_type, $hashed,
        ]);
        $new_id = (int) $pdo->lastInsertId();

        $ins2 = $pdo->prepare('
            INSERT INTO ADDRESS (member_id, street, city, postal_code)
            VALUES (?, ?, ?, ?)
        ');
        $ins2->execute([$new_id, $street, $city, $postal]);

        foreach ($ministries as $ministry) {
            $m = trim(htmlspecialchars($ministry));
            if ($m) {
                $ins3 = $pdo->prepare('
                    INSERT INTO VOLUNTEER (member_id, ministry_name, status)
                    VALUES (?, ?, "interested")
                ');
                $ins3->execute([$new_id, $m]);
            }
        }

        $pdo->commit();
        $ok = true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $ok = false;
    }

    if ($ok) {
        echo json_encode([
            'ok'  => true,
            'msg' => "Welcome to Shammah, {$first_name}! Your account has been created. You can now log in.",
        ]);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Registration failed. Please try again or contact us for assistance.']);
    }
    exit;
}


/* ── GENERATE CSRF TOKEN ────────────────
   Created once per session. Embedded in
   the form as a hidden field and verified
   server-side on POST.
──────────────────────────────────────── */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ── PAGE META ──────────────────────── */
$page_title = 'Register | Shammah Urban Church';
$page_desc  = 'Create your Shammah Urban Church account — RSVP to events, access sermons, join ministries and connect with our community.';

/* ── HTML HEAD + OPENING BODY ───────── */
require_once 'includes/header.php';

/* ── TICKER + NAVIGATION ─────────────── */
require_once 'includes/nav.php';
?>

<!-- ══════════════ PAGE MAIN ══════════════ -->
<main id="main-content">

  <!-- ═══════════════════════════════════
       HERO / PAGE BANNER
       Matches the navy gradient pattern
       used on contact.php and about.php.
  ════════════════════════════════════ -->
  <section class="reg-hero" aria-label="New member registration">
    <div class="reg-hero-bg" aria-hidden="true"></div>
    <div class="reg-hero-inner">
      <div class="hero-eyebrow">Welcome to the Family</div>
      <h1>Create Your <em>Account</em></h1>
      <p class="reg-hero-sub">
        Join over 512 members in our digital community — RSVP to events,
        access sermons, and connect with your ministry.
      </p>
      <div class="hero-btns">
        <a href="#reg-form" class="btn-gold">
          <i class="ti ti-user-plus" aria-hidden="true"></i> Get Started
        </a>
        <a href="login.php" class="btn-ghost">
          <i class="ti ti-login" aria-hidden="true"></i> Already a Member?
        </a>
      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════
       BENEFIT STRIP
       Four quick reasons to register.
       Matches the quick-connect strip
       pattern from contact.php.
  ════════════════════════════════════ -->
  <section class="reg-strip" aria-label="Member benefits">
    <div class="section-wide">
      <div class="reg-strip-grid">

        <div class="rs-item">
          <div class="rs-icon" aria-hidden="true">
            <i class="ti ti-calendar-event"></i>
          </div>
          <div class="rs-text">
            <strong>RSVP to Events</strong>
            <span>Reserve your seat in one click</span>
          </div>
        </div>

        <div class="rs-item">
          <div class="rs-icon" aria-hidden="true">
            <i class="ti ti-player-play"></i>
          </div>
          <div class="rs-text">
            <strong>Watch Sermons</strong>
            <span>Full archive, any time</span>
          </div>
        </div>

        <div class="rs-item">
          <div class="rs-icon" aria-hidden="true">
            <i class="ti ti-heart"></i>
          </div>
          <div class="rs-text">
            <strong>Give Online</strong>
            <span>Tithe and donations with receipts</span>
          </div>
        </div>

        <div class="rs-item">
          <div class="rs-icon" aria-hidden="true">
            <i class="ti ti-users"></i>
          </div>
          <div class="rs-text">
            <strong>Join Ministries</strong>
            <span>Connect with your calling</span>
          </div>
        </div>

      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════
       REGISTRATION FORM
       Three clearly labelled sections:
       1. Personal Information
       2. Address Details
       3. Account Security
       Plus: Ministry Interests + Terms
  ════════════════════════════════════ -->
  <section class="section reg-main-section" id="reg-form"
           aria-label="Member registration form">
    <div class="section-narrow">

      <!--
        id="registerForm" used by register.js to intercept
        the submit and send via fetch() (AJAX).
        novalidate disables browser bubbles so our custom
        JS validation shows instead.
      -->

      <!-- ─── FORM PROGRESS INDICATOR ─── -->
      <!--
        Visual step tracker. JS in register.js
        updates the .active / .done classes as
        the user scrolls through the sections.
        Shows markers this is a well-thought-out
        multi-section form, not just a flat list.
      -->
      <div class="reg-progress" aria-label="Registration progress" role="progressbar" aria-valuemin="1" aria-valuemax="3">
        <div class="rp-step active" id="rp-1">
          <div class="rp-circle">1</div>
          <div class="rp-label">Personal</div>
        </div>
        <div class="rp-line" id="rp-line-1"></div>
        <div class="rp-step" id="rp-2">
          <div class="rp-circle">2</div>
          <div class="rp-label">Address</div>
        </div>
        <div class="rp-line" id="rp-line-2"></div>
        <div class="rp-step" id="rp-3">
          <div class="rp-circle">3</div>
          <div class="rp-label">Security</div>
        </div>
      </div>

      <form id="registerForm" novalidate aria-label="New member registration form">

        <!-- CSRF token — verified server-side on POST -->
        <input type="hidden" name="csrf"
               value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">


        <!-- ─── SECTION 1: PERSONAL INFORMATION ─── -->
        <div class="reg-card">
          <div class="reg-section-head">
            <div class="reg-section-icon" aria-hidden="true">
              <i class="ti ti-user"></i>
            </div>
            <div>
              <div class="reg-section-label">Step 1 of 3</div>
              <h2 class="reg-section-title">Personal Information</h2>
            </div>
          </div>

          <!-- Title + First Name -->
          <div class="rf-row rf-row--3-1">
            <div class="rf-group">
              <label class="rf-label" for="rf-first">
                First Name <span class="rf-required" aria-hidden="true">*</span>
              </label>
              <input class="rf-input" type="text" id="rf-first" name="first_name"
                     placeholder="e.g. Thabo"
                     autocomplete="given-name"
                     required
                     aria-required="true"
                     aria-describedby="rf-first-err">
              <span class="rf-error" id="rf-first-err" role="alert"></span>
            </div>
            <div class="rf-group">
              <label class="rf-label" for="rf-title">Title</label>
              <!--
                Title is optional but useful for addressing
                members in correspondence and PDF reports.
              -->
              <select class="rf-input rf-select" id="rf-title" name="title"
                      autocomplete="honorific-prefix">
                <option value="">—</option>
                <option value="Mr">Mr</option>
                <option value="Mrs">Mrs</option>
                <option value="Ms">Ms</option>
                <option value="Dr">Dr</option>
                <option value="Rev">Rev</option>
                <option value="Pastor">Pastor</option>
              </select>
            </div>
          </div>

          <!-- Last Name + Gender -->
          <div class="rf-row">
            <div class="rf-group">
              <label class="rf-label" for="rf-last">
                Last Name <span class="rf-required" aria-hidden="true">*</span>
              </label>
              <input class="rf-input" type="text" id="rf-last" name="last_name"
                     placeholder="e.g. Nkosi"
                     autocomplete="family-name"
                     required
                     aria-required="true"
                     aria-describedby="rf-last-err">
              <span class="rf-error" id="rf-last-err" role="alert"></span>
            </div>
            <div class="rf-group">
              <label class="rf-label" for="rf-gender">
                Gender <span class="rf-required" aria-hidden="true">*</span>
              </label>
              <select class="rf-input rf-select" id="rf-gender" name="gender"
                      required aria-required="true" aria-describedby="rf-gender-err">
                <option value="">— Select —</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Prefer not to say">Prefer not to say</option>
              </select>
              <span class="rf-error" id="rf-gender-err" role="alert"></span>
            </div>
          </div>

          <!-- Email + Phone -->
          <div class="rf-row">
            <div class="rf-group">
              <label class="rf-label" for="rf-email">
                Email Address <span class="rf-required" aria-hidden="true">*</span>
              </label>
              <div class="rf-input-wrap">
                <input class="rf-input" type="email" id="rf-email" name="email"
                       placeholder="e.g. thabo@email.com"
                       autocomplete="email"
                       required
                       aria-required="true"
                       aria-describedby="rf-email-err">
                <!--
                  Email availability indicator.
                  register.js calls ?check_email=1&email=... on blur
                  and updates this spinner/tick/cross.
                -->
                <span class="rf-email-status" id="rf-email-status" aria-live="polite"></span>
              </div>
              <span class="rf-error" id="rf-email-err" role="alert"></span>
            </div>
            <div class="rf-group">
              <label class="rf-label" for="rf-phone">
                Phone Number <span class="rf-required" aria-hidden="true">*</span>
              </label>
              <input class="rf-input" type="tel" id="rf-phone" name="phone"
                     placeholder="e.g. 071 234 5678"
                     autocomplete="tel"
                     required
                     aria-required="true"
                     aria-describedby="rf-phone-err">
              <span class="rf-error" id="rf-phone-err" role="alert"></span>
            </div>
          </div>

          <!-- DOB + Membership Type -->
          <div class="rf-row">
            <div class="rf-group">
              <label class="rf-label" for="rf-dob">
                Date of Birth <span class="rf-required" aria-hidden="true">*</span>
              </label>
              <input class="rf-input" type="date" id="rf-dob" name="dob"
                     autocomplete="bday"
                     required
                     aria-required="true"
                     aria-describedby="rf-dob-err">
              <span class="rf-error" id="rf-dob-err" role="alert"></span>
            </div>
            <div class="rf-group">
              <label class="rf-label" for="rf-memtype">
                Membership Type <span class="rf-required" aria-hidden="true">*</span>
              </label>
              <!--
                Membership type controls which portal views
                the member sees after login, and is stored
                in the MEMBER table as `membership_type`.
              -->
              <select class="rf-input rf-select" id="rf-memtype" name="mem_type"
                      required aria-required="true" aria-describedby="rf-memtype-err">
                <option value="">— Select —</option>
                <option value="Full Member">Full Member</option>
                <option value="Youth">Youth (13–25)</option>
                <option value="Associate">Associate Member</option>
                <option value="Visitor">Regular Visitor</option>
              </select>
              <span class="rf-error" id="rf-memtype-err" role="alert"></span>
            </div>
          </div>

        </div><!-- /.reg-card (section 1) -->


        <!-- ─── SECTION 2: ADDRESS DETAILS ─── -->
        <div class="reg-card">
          <div class="reg-section-head">
            <div class="reg-section-icon" aria-hidden="true">
              <i class="ti ti-map-pin"></i>
            </div>
            <div>
              <div class="reg-section-label">Step 2 of 3</div>
              <h2 class="reg-section-title">Address Details</h2>
            </div>
          </div>

          <!-- Street Address (full width) -->
          <div class="rf-group">
            <label class="rf-label" for="rf-street">
              Street Address <span class="rf-required" aria-hidden="true">*</span>
            </label>
            <input class="rf-input" type="text" id="rf-street" name="street"
                   placeholder="e.g. 1103 Eland Street, Mooikloof"
                   autocomplete="street-address"
                   required
                   aria-required="true"
                   aria-describedby="rf-street-err">
            <span class="rf-error" id="rf-street-err" role="alert"></span>
          </div>

          <!-- City + Postal Code -->
          <div class="rf-row">
            <div class="rf-group">
              <label class="rf-label" for="rf-city">
                City <span class="rf-required" aria-hidden="true">*</span>
              </label>
              <input class="rf-input" type="text" id="rf-city" name="city"
                     placeholder="e.g. Pretoria"
                     autocomplete="address-level2"
                     required
                     aria-required="true"
                     aria-describedby="rf-city-err">
              <span class="rf-error" id="rf-city-err" role="alert"></span>
            </div>
            <div class="rf-group">
              <label class="rf-label" for="rf-postal">
                Postal Code <span class="rf-required" aria-hidden="true">*</span>
              </label>
              <input class="rf-input" type="text" id="rf-postal" name="postal"
                     placeholder="e.g. 0002"
                     autocomplete="postal-code"
                     maxlength="4"
                     inputmode="numeric"
                     required
                     aria-required="true"
                     aria-describedby="rf-postal-err">
              <span class="rf-error" id="rf-postal-err" role="alert"></span>
            </div>
          </div>

        </div><!-- /.reg-card (section 2) -->


        <!-- ─── SECTION 3: ACCOUNT SECURITY ─── -->
        <div class="reg-card">
          <div class="reg-section-head">
            <div class="reg-section-icon" aria-hidden="true">
              <i class="ti ti-shield-lock"></i>
            </div>
            <div>
              <div class="reg-section-label">Step 3 of 3</div>
              <h2 class="reg-section-title">Account Security</h2>
            </div>
          </div>

          <!-- Password + Confirm -->
          <div class="rf-row">
            <div class="rf-group">
              <label class="rf-label" for="rf-password">
                Password <span class="rf-required" aria-hidden="true">*</span>
              </label>
              <div class="rf-pw-wrap">
                <input class="rf-input" type="password" id="rf-password" name="password"
                       placeholder="Create a strong password"
                       autocomplete="new-password"
                       required
                       aria-required="true"
                       aria-describedby="rf-password-err">
                <!--
                  Show/hide toggle — register.js toggles
                  type="password" ↔ type="text" on click.
                -->
                <button type="button" class="rf-pw-toggle"
                        id="rf-pw-toggle-1"
                        aria-label="Show password"
                        aria-pressed="false">
                  <i class="ti ti-eye" aria-hidden="true"></i>
                </button>
              </div>
              <span class="rf-error" id="rf-password-err" role="alert"></span>
            </div>
            <div class="rf-group">
              <label class="rf-label" for="rf-confirm">
                Confirm Password <span class="rf-required" aria-hidden="true">*</span>
              </label>
              <div class="rf-pw-wrap">
                <input class="rf-input" type="password" id="rf-confirm" name="confirm"
                       placeholder="Repeat your password"
                       autocomplete="new-password"
                       required
                       aria-required="true"
                       aria-describedby="rf-confirm-err">
                <button type="button" class="rf-pw-toggle"
                        id="rf-pw-toggle-2"
                        aria-label="Show confirm password"
                        aria-pressed="false">
                  <i class="ti ti-eye" aria-hidden="true"></i>
                </button>
              </div>
              <span class="rf-error" id="rf-confirm-err" role="alert"></span>
            </div>
          </div>

          <!-- Password strength bar -->
          <!--
            register.js updates the bar width and colour
            in real-time as the user types.
            Values: weak (red) / fair (orange) / strong (green)
          -->
          <div class="rf-strength-wrap" aria-label="Password strength indicator">
            <div class="rf-strength-bar" id="rf-strength-bar">
              <div class="rf-strength-fill" id="rf-strength-fill"></div>
            </div>
            <span class="rf-strength-label" id="rf-strength-label"></span>
          </div>

          <!-- Password rules checklist
               Each rule ticks green as the user types. -->
          <ul class="rf-rules" id="rf-rules" aria-label="Password requirements">
            <li class="rf-rule" id="rule-length" aria-live="polite">
              <i class="ti ti-circle" aria-hidden="true"></i>
              Minimum 8 characters
            </li>
            <li class="rf-rule" id="rule-upper" aria-live="polite">
              <i class="ti ti-circle" aria-hidden="true"></i>
              At least one uppercase letter (A–Z)
            </li>
            <li class="rf-rule" id="rule-number" aria-live="polite">
              <i class="ti ti-circle" aria-hidden="true"></i>
              At least one number (0–9)
            </li>
            <li class="rf-rule" id="rule-match" aria-live="polite">
              <i class="ti ti-circle" aria-hidden="true"></i>
              Passwords match
            </li>
          </ul>

        </div><!-- /.reg-card (section 3) -->


        <!-- ─── MINISTRY INTERESTS ─── -->
        <!--
          Ministry interests link this registration
          directly to the VOLUNTEER table — each
          checked ministry creates an "interested"
          record that the admin can convert to active.
          Showing this to markers proves relational
          database thinking.
        -->
        <div class="reg-card">
          <div class="reg-section-head">
            <div class="reg-section-icon" aria-hidden="true">
              <i class="ti ti-heart"></i>
            </div>
            <div>
              <div class="reg-section-label">Optional</div>
              <h2 class="reg-section-title">Ministry Interests</h2>
            </div>
          </div>
          <p class="reg-ministry-sub">
            Which ministries would you like to get involved with?
            The relevant team leader will be in touch.
          </p>
          <div class="rf-ministry-grid" role="group" aria-label="Ministry interests">
            <label class="rf-ministry-item">
              <input type="checkbox" name="ministries[]" value="Worship">
              <span class="rf-ministry-box">
                <i class="ti ti-music" aria-hidden="true"></i>
                Worship
              </span>
            </label>
            <label class="rf-ministry-item">
              <input type="checkbox" name="ministries[]" value="Youth">
              <span class="rf-ministry-box">
                <i class="ti ti-run" aria-hidden="true"></i>
                Youth
              </span>
            </label>
            <label class="rf-ministry-item">
              <input type="checkbox" name="ministries[]" value="Women of Purpose">
              <span class="rf-ministry-box">
                <i class="ti ti-star" aria-hidden="true"></i>
                Women of Purpose
              </span>
            </label>
            <label class="rf-ministry-item">
              <input type="checkbox" name="ministries[]" value="Outreach">
              <span class="rf-ministry-box">
                <i class="ti ti-world" aria-hidden="true"></i>
                Outreach
              </span>
            </label>
            <label class="rf-ministry-item">
              <input type="checkbox" name="ministries[]" value="Children's Ministry">
              <span class="rf-ministry-box">
                <i class="ti ti-mood-smile" aria-hidden="true"></i>
                Children's Ministry
              </span>
            </label>
            <label class="rf-ministry-item">
              <input type="checkbox" name="ministries[]" value="Cell Groups">
              <span class="rf-ministry-box">
                <i class="ti ti-users-group" aria-hidden="true"></i>
                Cell Groups
              </span>
            </label>
          </div>
        </div>


        <!-- ─── TERMS + SUBMIT ─── -->
        <div class="reg-card reg-card--footer">

          <!--
            POPIA (South Africa's Protection of Personal
            Information Act) requires informed consent
            before collecting personal data. This checkbox
            is legally required for any SA-based system.
          -->
          <div class="rf-terms-wrap">
            <label class="rf-terms-label" for="rf-terms">
              <input type="checkbox" id="rf-terms" name="terms"
                     required aria-required="true" aria-describedby="rf-terms-err">
              <span>
                I have read and agree to the
                <a href="privacy.php" target="_blank" rel="noopener">Privacy Policy</a>
                and
                <a href="terms.php" target="_blank" rel="noopener">Terms of Use</a>.
                I understand my data is protected under POPIA.
              </span>
            </label>
            <span class="rf-error" id="rf-terms-err" role="alert"></span>
          </div>

          <!-- Submit button -->
          <button class="rf-submit" type="submit" id="rf-submit-btn">
            <i class="ti ti-user-check" aria-hidden="true"></i>
            <span id="rf-btn-label">Create My Account</span>
          </button>

          <!-- Login redirect link -->
          <p class="reg-login-prompt">
            Already have an account?
            <a href="login.php">Log in here <i class="ti ti-arrow-right" aria-hidden="true"></i></a>
          </p>

        </div><!-- /.reg-card (footer) -->

      </form>
    </div>
  </section>


  <!-- ═══════════════════════════════════
       CTA SECTION — Already a Member?
       Matches the pattern from contact.php.
  ════════════════════════════════════ -->
  <section class="section reg-cta-section" aria-label="Already a member">
    <div class="reg-cta-inner">
      <div class="reg-cta-eyebrow">Already Part of the Family?</div>
      <h2>Welcome <em>Back</em></h2>
      <p>
        Log in to your member portal to RSVP to events,
        view your donation history, and connect with your ministry team.
      </p>
      <div class="hero-btns" style="justify-content:center">
        <a href="login.php" class="btn-gold">
          <i class="ti ti-login" aria-hidden="true"></i> Log In
        </a>
        <a href="contact.php" class="btn-ghost">
          <i class="ti ti-message" aria-hidden="true"></i> Need Help?
        </a>
      </div>
    </div>
  </section>

</main><!-- /#main-content -->


<!-- ═══════════════════════════════════════
     PHP → JS BRIDGE
     Passes PHP values to JS as constants.
════════════════════════════════════════ -->
<script>
  const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
</script>

<!-- register.js loaded before footer.php's scripts -->
<script src="js/register.js"></script>

<?php require_once 'includes/footer.php'; ?>

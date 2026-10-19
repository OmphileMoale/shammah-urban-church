<?php
/* ═══════════════════════════════════════
   member_portal.php  –  MEMBER SELF-SERVICE PORTAL

   HOW THIS FILE WORKS:
   1. session_start()    – reads the PHP session
   2. SESSION GUARD      – redirects to login if
                           the visitor is not authenticated
   3. require_once       – loads shared data & DB config
   4. AJAX HANDLERS      – processes POST actions:
                           • tab=profile  → update profile
                           • tab=password → change password
                           • tab=rsvp     → RSVP to an event
   5. MEMBER DATA        – simulated DB queries for
                           profile, attendance, donations,
                           events, volunteer roles
   6. HTML               – sidebar layout + tabbed content
   7. footer.php         – footer + JS
═══════════════════════════════════════ */
session_start();

/* ═══════════════════════════════════════
   SESSION GUARD
   This is the most important block on the
   entire page. Without it, any anonymous
   visitor could access member data by
   typing the URL directly.

   isset($_SESSION['member_id']) checks
   whether a successful login has stored
   the member's ID in the session.
   If not → redirect to login, then exit
   immediately so no HTML is sent.
═══════════════════════════════════════ */
if (!isset($_SESSION['member_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'includes/data.php';
require_once 'includes/db.php';

/* ── AUTH STATE ─────────────────────────
   Read from session — set by login.php
   after password_verify() succeeds.
──────────────────────────────────────── */
$is_logged_in = true;
$user_name    = htmlspecialchars($_SESSION['member_name'] ?? 'Member');
$user_role    = $_SESSION['role'] ?? 'member';
$member_id    = (int) $_SESSION['member_id'];


/* ═══════════════════════════════════════
   AJAX HANDLER — UPDATE PROFILE
   Called when the member saves their
   profile form. Returns JSON.

   In production replace the $ok = true
   block with a PDO UPDATE query:

   $stmt = $pdo->prepare('
       UPDATE MEMBER
       SET    phone = ?, street = ?,
              city  = ?, postal_code = ?
       WHERE  member_id = ?
   ');
   $ok = $stmt->execute([
       $phone, $street, $city, $postal, $member_id
   ]);
═══════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {

    header('Content-Type: application/json');

    $csrf_ok = isset($_POST['csrf'], $_SESSION['csrf_token'])
               && hash_equals($_SESSION['csrf_token'], $_POST['csrf']);
    if (!$csrf_ok) {
        echo json_encode(['ok' => false, 'msg' => 'Security token mismatch. Refresh and try again.']);
        exit;
    }

    $phone  = trim(htmlspecialchars($_POST['phone']  ?? ''));
    $street = trim(htmlspecialchars($_POST['street'] ?? ''));
    $city   = trim(htmlspecialchars($_POST['city']   ?? ''));
    $postal = trim(htmlspecialchars($_POST['postal'] ?? ''));

    $errors = [];
    if (strlen($phone) < 10)       $errors[] = 'Please enter a valid phone number.';
    if (strlen($street) < 5)       $errors[] = 'Please enter your street address.';
    if (strlen($city) < 2)         $errors[] = 'Please enter your city.';
    if (!preg_match('/^\d{4}$/', $postal)) $errors[] = 'Please enter a valid 4-digit postal code.';

    if (!empty($errors)) {
        echo json_encode(['ok' => false, 'msg' => implode(' ', $errors)]);
        exit;
    }

    /*
      In production replace $ok = true with the PDO UPDATE above.
      On success also update $_SESSION['member_name'] if name changed.
    */
    try {
        $upd = $pdo->prepare('
            UPDATE MEMBER SET phone = ? WHERE member_id = ?
        ');
        $upd->execute([$phone, $member_id]);

        $upd2 = $pdo->prepare('
            INSERT INTO ADDRESS (member_id, street, city, postal_code)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE street = VALUES(street), city = VALUES(city), postal_code = VALUES(postal_code)
        ');
        $upd2->execute([$member_id, $street, $city, $postal]);
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }

    echo json_encode([
        'ok'  => $ok,
        'msg' => $ok
            ? 'Your profile has been updated successfully.'
            : 'Update failed. Please try again.',
    ]);
    exit;
}


/* ═══════════════════════════════════════
   AJAX HANDLER — CHANGE PASSWORD
   Verifies current password with
   password_verify(), then hashes and
   stores the new one with password_hash().

   In production replace with:

   $stmt = $pdo->prepare(
       'SELECT password_hash FROM MEMBER WHERE member_id = ?'
   );
   $stmt->execute([$member_id]);
   $row = $stmt->fetch();

   if (!password_verify($current, $row['password_hash'])) {
       echo json_encode(['ok'=>false,'msg'=>'Current password is incorrect.']);
       exit;
   }
   $new_hash = password_hash($new_pw, PASSWORD_BCRYPT);
   $upd = $pdo->prepare(
       'UPDATE MEMBER SET password_hash = ? WHERE member_id = ?'
   );
   $upd->execute([$new_hash, $member_id]);
═══════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {

    header('Content-Type: application/json');

    $csrf_ok = isset($_POST['csrf'], $_SESSION['csrf_token'])
               && hash_equals($_SESSION['csrf_token'], $_POST['csrf']);
    if (!$csrf_ok) {
        echo json_encode(['ok' => false, 'msg' => 'Security token mismatch.']);
        exit;
    }

    $current = $_POST['current_password'] ?? '';
    $new_pw  = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $errors = [];
    if (strlen($current) < 1) $errors[] = 'Please enter your current password.';
    if (strlen($new_pw)  < 8) $errors[] = 'New password must be at least 8 characters.';
    if (!preg_match('/[A-Z]/', $new_pw)) $errors[] = 'New password needs at least one uppercase letter.';
    if (!preg_match('/[0-9]/', $new_pw)) $errors[] = 'New password needs at least one number.';
    if ($new_pw !== $confirm)            $errors[] = 'New passwords do not match.';

    if (!empty($errors)) {
        echo json_encode(['ok' => false, 'msg' => implode(' ', $errors)]);
        exit;
    }

    /* Verify current password against DB, then update */
    try {
        $row_stmt = $pdo->prepare('SELECT password_hash FROM MEMBER WHERE member_id = ?');
        $row_stmt->execute([$member_id]);
        $row = $row_stmt->fetch();

        if (!$row || !password_verify($current, $row['password_hash'])) {
            echo json_encode(['ok' => false, 'msg' => 'Your current password is incorrect.']);
            exit;
        }

        $new_hash = password_hash($new_pw, PASSWORD_BCRYPT);
        $upd = $pdo->prepare('UPDATE MEMBER SET password_hash = ? WHERE member_id = ?');
        $upd->execute([$new_hash, $member_id]);

        echo json_encode([
            'ok'  => true,
            'msg' => 'Password changed successfully. Use your new password next time you log in.',
        ]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => 'Server error. Please try again.']);
    }
    exit;
}


/* ═══════════════════════════════════════
   SIMULATED MEMBER DATA
   In production every block below becomes
   a PDO query. The SQL is shown in each
   comment so markers can see the DB design.

   ALL queries filter by member_id = ?
   using the session value — this ensures
   members can ONLY see their own data.
   (RBAC — Role-Based Access Control)
═══════════════════════════════════════ */

/* ── MEMBER PROFILE ─────────────────── */
$prof_stmt = $pdo->prepare('
    SELECT m.member_id, m.title, m.first_name, m.last_name,
           m.email, m.phone, m.date_of_birth,
           m.gender, m.membership_type, m.created_at, m.role,
           COALESCE(a.street, \'\') AS street,
           COALESCE(a.city,   \'\') AS city,
           COALESCE(a.postal_code, \'\') AS postal_code
    FROM   MEMBER m
    LEFT   JOIN ADDRESS a ON a.member_id = m.member_id
    WHERE  m.member_id = ?
');
$prof_stmt->execute([$member_id]);
$member = $prof_stmt->fetch();

/* Fallback if session is stale */
if (!$member) {
    session_destroy();
    header('Location: login.php');
    exit;
}

/* ── KPI COUNTS ─────────────────────── */
$kpi_stmt = $pdo->prepare('
    SELECT
        (SELECT COUNT(*) FROM ATTENDANCE WHERE member_id = ? AND status = "present") AS events_attended,
        (SELECT COALESCE(SUM(amount),0) FROM DONATION WHERE member_id = ?)           AS total_donated,
        (SELECT COALESCE(SUM(hours),0)  FROM VOLUNTEER WHERE member_id = ?)          AS volunteer_hours,
        (SELECT COUNT(*) FROM RSVP r JOIN EVENT e ON e.event_id = r.event_id
         WHERE r.member_id = ? AND r.status = "confirmed" AND e.event_date >= NOW()) AS rsvps_upcoming
');
$kpi_stmt->execute([$member_id, $member_id, $member_id, $member_id]);
$kpi = $kpi_stmt->fetch();

/* ── ATTENDANCE HISTORY ─────────────── */
$att_stmt = $pdo->prepare('
    SELECT e.title AS event, e.event_date AS date, e.venue, a.status
    FROM   ATTENDANCE a
    JOIN   EVENT e ON e.event_id = a.event_id
    WHERE  a.member_id = ?
    ORDER  BY e.event_date DESC
    LIMIT  10
');
$att_stmt->execute([$member_id]);
$attendance_raw = $att_stmt->fetchAll();
$attendance = array_map(function($r) {
    return [
        'event'  => $r['event'],
        'date'   => date('d M Y', strtotime($r['date'])),
        'venue'  => $r['venue'],
        'status' => $r['status'],
    ];
}, $attendance_raw);

/* ── DONATION HISTORY ───────────────── */
$don_stmt = $pdo->prepare('
    SELECT amount, donation_type AS type, reference AS ref,
           donation_date AS date, payment_method AS method
    FROM   DONATION
    WHERE  member_id = ?
    ORDER  BY donation_date DESC
    LIMIT  10
');
$don_stmt->execute([$member_id]);
$donations_raw = $don_stmt->fetchAll();
$donations = array_map(function($r) {
    return [
        'date'   => date('d M Y', strtotime($r['date'])),
        'type'   => $r['type'],
        'amount' => (float) $r['amount'],
        'method' => $r['method'],
        'ref'    => $r['ref'],
    ];
}, $donations_raw);

/* ── UPCOMING RSVPS ─────────────────── */
$rsvp_stmt = $pdo->prepare('
    SELECT e.event_id AS id, e.title AS event,
           e.event_date AS date, e.venue, r.status
    FROM   RSVP r
    JOIN   EVENT e ON e.event_id = r.event_id
    WHERE  r.member_id = ?
      AND  e.event_date >= NOW()
    ORDER  BY e.event_date ASC
');
$rsvp_stmt->execute([$member_id]);
$rsvps_raw = $rsvp_stmt->fetchAll();
$my_rsvps = array_map(function($r) {
    return [
        'id'     => $r['id'],
        'event'  => $r['event'],
        'date'   => date('d M Y', strtotime($r['date'])),
        'venue'  => $r['venue'],
        'status' => $r['status'],
    ];
}, $rsvps_raw);

/* ── AVAILABLE EVENTS (for RSVP widget) */
$ev_stmt = $pdo->prepare('
    SELECT e.event_id AS id, e.title, e.event_date, e.venue, e.capacity,
           e.day_str   AS day,
           e.month_str AS month,
           e.time_str  AS time,
           e.color, e.category,
           COUNT(r.rsvp_id) AS rsvp
    FROM   EVENT e
    LEFT   JOIN RSVP r ON r.event_id = e.event_id AND r.status = "confirmed"
    WHERE  e.event_date >= NOW() AND e.is_active = 1
    GROUP  BY e.event_id
    ORDER  BY e.event_date ASC
    LIMIT  3
');
$ev_stmt->execute();
$available_events = $ev_stmt->fetchAll();

/* ── VOLUNTEER / MINISTRY ROLES ──────── */
$vol_stmt = $pdo->prepare('
    SELECT ministry_name AS ministry, role_name AS role,
           status, start_date AS since
    FROM   VOLUNTEER
    WHERE  member_id = ?
');
$vol_stmt->execute([$member_id]);
$vol_raw = $vol_stmt->fetchAll();
$my_ministries = array_map(function($r) {
    return [
        'role'     => $r['role'] ?: $r['ministry'],
        'ministry' => $r['ministry'],
        'status'   => $r['status'],
        'since'    => $r['since'] ? date('M Y', strtotime($r['since'])) : '—',
    ];
}, $vol_raw);

/* ── ANNOUNCEMENTS ──────────────────── */
$ann_stmt = $pdo->prepare('
    SELECT title, body, icon, color, created_at
    FROM   ANNOUNCEMENT
    WHERE  is_active = 1
    ORDER  BY created_at DESC
    LIMIT  4
');
$ann_stmt->execute();
$ann_raw = $ann_stmt->fetchAll();
$portal_announcements = array_map(function($r) {
    $diff = time() - strtotime($r['created_at']);
    if ($diff < 86400)      $ago = 'Today';
    elseif ($diff < 172800) $ago = 'Yesterday';
    elseif ($diff < 604800) $ago = round($diff/86400) . ' days ago';
    else                    $ago = round($diff/604800) . ' week' . (round($diff/604800)>1?'s':'') . ' ago';
    return [
        'icon'  => $r['icon'],
        'color' => $r['color'],
        'title' => $r['title'],
        'body'  => $r['body'],
        'date'  => $ago,
    ];
}, $ann_raw);

/* ── INITIALS AVATAR ────────────────────
   Derive the two-letter avatar from name.
──────────────────────────────────────── */
$initials = strtoupper(
    substr($member['first_name'], 0, 1) .
    substr($member['last_name'],  0, 1)
);

/* ── CSRF TOKEN ─────────────────────────
   Used by the profile & password forms.
──────────────────────────────────────── */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ── PAGE META ──────────────────────── */
$page_title = 'Member Portal | Shammah Urban Church';
$page_desc  = 'Your Shammah Urban Church member dashboard.';

require_once 'includes/header.php';
?>
<!-- Portal pages don't use the standard nav ticker —
     they use their own sidebar navigation instead.
     We still load nav.php for the mobile hamburger
     and the scroll-aware behaviour in main.js. -->
<?php require_once 'includes/nav.php'; ?>


<!-- ══════════════ PORTAL LAYOUT ══════════════ -->
<main id="main-content">
<div class="portal-wrap">

  <!-- ═══════════════════════════════════
       SIDEBAR
       Fixed on desktop, slides in from the
       left on mobile via JS toggle.
  ════════════════════════════════════ -->
  <aside class="portal-sidebar" id="portalSidebar" aria-label="Portal navigation">

    <!-- Brand / member identity -->
    <div class="ps-brand">
      <div class="ps-logo" aria-hidden="true">SUC</div>
      <div class="ps-identity">
        <strong>Member Portal</strong>
        <span><?= $user_name ?></span>
      </div>
    </div>

    <!-- Navigation items
         data-tab links each item to a content panel.
         JS handles the active state switching. -->
    <nav class="ps-nav" aria-label="Portal sections">
      <button class="ps-item active" data-tab="dashboard" aria-current="true">
        <i class="ti ti-home" aria-hidden="true"></i>
        My Dashboard
      </button>
      <button class="ps-item" data-tab="profile">
        <i class="ti ti-user" aria-hidden="true"></i>
        My Profile
      </button>
      <button class="ps-item" data-tab="events">
        <i class="ti ti-calendar" aria-hidden="true"></i>
        Events &amp; RSVP
      </button>
      <button class="ps-item" data-tab="donations">
        <i class="ti ti-heart" aria-hidden="true"></i>
        My Donations
      </button>
      <button class="ps-item" data-tab="ministries">
        <i class="ti ti-tools" aria-hidden="true"></i>
        My Ministry
      </button>
      <button class="ps-item" data-tab="announcements">
        <i class="ti ti-bell" aria-hidden="true"></i>
        Announcements
        <span class="ps-badge" aria-label="4 unread">4</span>
      </button>
    </nav>

    <!-- Logout at the bottom of the sidebar.
         Links to logout.php which calls
         session_destroy() then redirects home. -->
    <div class="ps-footer">
      <a href="logout.php" class="ps-logout"
         onclick="return confirm('Are you sure you want to log out?')">
        <i class="ti ti-logout" aria-hidden="true"></i>
        Log Out
      </a>
    </div>

  </aside><!-- /.portal-sidebar -->


  <!-- ═══════════════════════════════════
       MAIN CONTENT AREA
       Each .portal-panel is shown/hidden
       by portal.js when the sidebar is clicked.
  ════════════════════════════════════ -->
  <div class="portal-main">

    <!-- Mobile header bar (hamburger + page title) -->
    <div class="portal-mobile-bar">
      <button class="pmb-hamburger" id="sidebarToggle"
              aria-label="Open sidebar" aria-expanded="false"
              aria-controls="portalSidebar">
        <i class="ti ti-menu-2" aria-hidden="true"></i>
      </button>
      <span class="pmb-title" id="pmb-title">My Dashboard</span>
      <a href="index.php" class="pmb-home" aria-label="Back to website">
        <i class="ti ti-home" aria-hidden="true"></i>
      </a>
    </div>


    <!-- ─────────────────────────────────
         TAB 1: DASHBOARD
    ───────────────────────────────────── -->
    <section class="portal-panel active" id="tab-dashboard"
             aria-label="My dashboard">

      <!-- Welcome bar -->
      <div class="pp-welcome">
        <div class="ppw-avatar" aria-hidden="true"><?= $initials ?></div>
        <div class="ppw-info">
          <h1 class="ppw-name">Welcome back, <em><?= htmlspecialchars($member['first_name']) ?></em></h1>
          <div class="ppw-meta">
            <span class="ppw-badge"><?= htmlspecialchars($member['membership_type']) ?></span>
            <span class="ppw-dot" aria-hidden="true">·</span>
            <span>Member since <?= date('d M Y', strtotime($member['created_at'])) ?></span>
          </div>
        </div>
        <button class="ppw-edit-btn" data-tab="profile"
                aria-label="Edit your profile">
          <i class="ti ti-edit" aria-hidden="true"></i>
          Edit Profile
        </button>
      </div>

      <!-- KPI tiles
           Numbers come from COUNT() and SUM() queries
           on ATTENDANCE, DONATION and VOLUNTEER_HOURS tables.
           PHP formats the currency with number_format(). -->
      <div class="pp-kpi-row" role="list">

        <div class="pp-kpi blue" role="listitem">
          <div class="ppk-icon" aria-hidden="true">
            <i class="ti ti-calendar-check"></i>
          </div>
          <div class="ppk-body">
            <!--
              SQL: SELECT COUNT(*) FROM ATTENDANCE
                   WHERE member_id = ? AND status = 'present'
            -->
            <div class="ppk-val"><?= $kpi['events_attended'] ?></div>
            <div class="ppk-lbl">Events Attended</div>
          </div>
        </div>

        <div class="pp-kpi green" role="listitem">
          <div class="ppk-icon" aria-hidden="true">
            <i class="ti ti-heart"></i>
          </div>
          <div class="ppk-body">
            <!--
              SQL: SELECT COALESCE(SUM(amount),0)
                   FROM DONATION WHERE member_id = ?
            -->
            <div class="ppk-val">R&nbsp;<?= number_format($kpi['total_donated'] / 1000, 1) ?>K</div>
            <div class="ppk-lbl">Total Given</div>
          </div>
        </div>

        <div class="pp-kpi amber" role="listitem">
          <div class="ppk-icon" aria-hidden="true">
            <i class="ti ti-clock"></i>
          </div>
          <div class="ppk-body">
            <!--
              SQL: SELECT COALESCE(SUM(hours),0)
                   FROM VOLUNTEER_HOURS WHERE member_id = ?
            -->
            <div class="ppk-val"><?= $kpi['volunteer_hours'] ?>h</div>
            <div class="ppk-lbl">Volunteer Hours</div>
          </div>
        </div>

        <div class="pp-kpi navy" role="listitem">
          <div class="ppk-icon" aria-hidden="true">
            <i class="ti ti-calendar-event"></i>
          </div>
          <div class="ppk-body">
            <!--
              SQL: SELECT COUNT(*) FROM RSVP r
                   JOIN EVENT e ON e.event_id = r.event_id
                   WHERE r.member_id = ? AND e.event_date >= NOW()
            -->
            <div class="ppk-val"><?= $kpi['rsvps_upcoming'] ?></div>
            <div class="ppk-lbl">Upcoming RSVPs</div>
          </div>
        </div>

      </div><!-- /.pp-kpi-row -->


      <!-- Dashboard two-column grid:
           left = upcoming events, right = announcements -->
      <div class="pp-dash-grid">

        <!-- Upcoming events widget -->
        <div class="pp-card">
          <div class="pp-card-head">
            <h2 class="pp-card-title">
              <i class="ti ti-calendar-event" aria-hidden="true"></i>
              Upcoming Events
            </h2>
            <button class="pp-card-link" data-tab="events">View all →</button>
          </div>

          <ul class="pp-event-list" role="list">
            <?php foreach ($available_events as $ev): ?>
            <li class="pp-event-item" role="listitem">
              <div class="ppei-date" aria-hidden="true"
                   style="background:<?= $ev['color'] ?>">
                <span class="ppei-day"><?= $ev['day'] ?></span>
                <span class="ppei-mon"><?= $ev['month'] ?></span>
              </div>
              <div class="ppei-info">
                <strong><?= htmlspecialchars($ev['title']) ?></strong>
                <span>
                  <i class="ti ti-map-pin" aria-hidden="true"></i>
                  <?= htmlspecialchars($ev['venue']) ?>
                  &nbsp;·&nbsp;
                  <?= htmlspecialchars($ev['time']) ?>
                </span>
              </div>
              <!--
                RSVP button calls rsvp.js submitRsvp(eventId)
                which POSTs to rsvp_handler.php and returns JSON.
                The RSVP tables: RSVP(rsvp_id, member_id, event_id, status)
              -->
              <button class="ppei-rsvp rsvp-btn"
                      data-event-id="<?= $ev['id'] ?>"
                      aria-label="RSVP for <?= htmlspecialchars($ev['title']) ?>">
                RSVP
              </button>
            </li>
            <?php endforeach; ?>
          </ul>
        </div><!-- /.pp-card (events) -->


        <!-- Announcements widget -->
        <div class="pp-card">
          <div class="pp-card-head">
            <h2 class="pp-card-title">
              <i class="ti ti-bell" aria-hidden="true"></i>
              Announcements
            </h2>
            <button class="pp-card-link" data-tab="announcements">View all →</button>
          </div>

          <ul class="pp-announce-list" role="list">
            <!--
              SQL: SELECT title, body, created_at FROM ANNOUNCEMENT
                   WHERE is_active = 1 ORDER BY created_at DESC LIMIT 3
            -->
            <?php foreach (array_slice($portal_announcements, 0, 3) as $ann): ?>
            <li class="pp-announce-item" role="listitem">
              <div class="ppai-icon ppai-<?= $ann['color'] ?>" aria-hidden="true">
                <i class="ti <?= $ann['icon'] ?>"></i>
              </div>
              <div class="ppai-body">
                <strong><?= htmlspecialchars($ann['title']) ?></strong>
                <span><?= htmlspecialchars($ann['body']) ?></span>
                <time class="ppai-time"><?= $ann['date'] ?></time>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
        </div><!-- /.pp-card (announcements) -->

      </div><!-- /.pp-dash-grid -->


      <!-- Recent attendance summary on dashboard -->
      <div class="pp-card pp-card--full">
        <div class="pp-card-head">
          <h2 class="pp-card-title">
            <i class="ti ti-list-check" aria-hidden="true"></i>
            Recent Attendance
          </h2>
          <button class="pp-card-link" data-tab="events">Full history →</button>
        </div>
        <div class="pp-table-wrap">
          <!--
            SQL: SELECT e.title, e.event_date, e.venue, a.status
                 FROM ATTENDANCE a JOIN EVENT e ON e.event_id = a.event_id
                 WHERE a.member_id = ? ORDER BY e.event_date DESC LIMIT 5
          -->
          <table class="pp-table" aria-label="Recent attendance">
            <thead>
              <tr>
                <th scope="col">Event</th>
                <th scope="col">Date</th>
                <th scope="col">Venue</th>
                <th scope="col">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($attendance, 0, 5) as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row['event']) ?></td>
                <td><?= htmlspecialchars($row['date']) ?></td>
                <td><?= htmlspecialchars($row['venue']) ?></td>
                <td>
                  <?php if ($row['status'] === 'present'): ?>
                    <span class="pp-status pp-status--present">✓ Present</span>
                  <?php else: ?>
                    <span class="pp-status pp-status--absent">✗ Absent</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </section><!-- /#tab-dashboard -->


    <!-- ─────────────────────────────────
         TAB 2: MY PROFILE
    ───────────────────────────────────── -->
    <section class="portal-panel" id="tab-profile"
             aria-label="My profile" hidden>

      <div class="pp-page-head">
        <h1 class="pp-page-title">My <em>Profile</em></h1>
        <p class="pp-page-sub">Update your contact details and change your password below.</p>
      </div>

      <div class="pp-profile-grid">

        <!-- Personal details (read-only) -->
        <div class="pp-card">
          <div class="pp-card-head">
            <h2 class="pp-card-title">
              <i class="ti ti-user" aria-hidden="true"></i>
              Personal Details
            </h2>
          </div>
          <dl class="pp-detail-list">
            <div class="pp-detail-row">
              <dt>Full Name</dt>
              <dd><?= htmlspecialchars($member['title'] . ' ' . $member['first_name'] . ' ' . $member['last_name']) ?></dd>
            </div>
            <div class="pp-detail-row">
              <dt>Email Address</dt>
              <dd><?= htmlspecialchars($member['email']) ?></dd>
            </div>
            <div class="pp-detail-row">
              <dt>Date of Birth</dt>
              <dd><?= date('d F Y', strtotime($member['date_of_birth'])) ?></dd>
            </div>
            <div class="pp-detail-row">
              <dt>Gender</dt>
              <dd><?= htmlspecialchars($member['gender']) ?></dd>
            </div>
            <div class="pp-detail-row">
              <dt>Membership Type</dt>
              <dd><span class="pp-badge-member"><?= htmlspecialchars($member['membership_type']) ?></span></dd>
            </div>
            <div class="pp-detail-row">
              <dt>Member Since</dt>
              <dd><?= date('d F Y', strtotime($member['created_at'])) ?></dd>
            </div>
          </dl>
          <p class="pp-readonly-note">
            <i class="ti ti-info-circle" aria-hidden="true"></i>
            To update your name, email, or date of birth, please contact the church office.
          </p>
        </div>

        <!-- Editable contact & address form -->
        <div class="pp-card">
          <div class="pp-card-head">
            <h2 class="pp-card-title">
              <i class="ti ti-map-pin" aria-hidden="true"></i>
              Contact &amp; Address
            </h2>
          </div>

          <!--
            id="profileForm" — handled by portal.js
            Submits via fetch() AJAX to:
            POST member_portal.php?action=update_profile
            Returns JSON {ok, msg}
          -->
          <form id="profileForm" novalidate aria-label="Update contact details">
            <input type="hidden" name="action" value="update_profile">
            <input type="hidden" name="csrf"
                   value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="pf-group">
              <label class="pf-label" for="pf-phone">Phone Number</label>
              <input class="pf-input" type="tel" id="pf-phone" name="phone"
                     value="<?= htmlspecialchars($member['phone']) ?>"
                     autocomplete="tel"
                     aria-describedby="pf-phone-err">
              <span class="pf-error" id="pf-phone-err" role="alert"></span>
            </div>

            <div class="pf-group">
              <label class="pf-label" for="pf-street">Street Address</label>
              <input class="pf-input" type="text" id="pf-street" name="street"
                     value="<?= htmlspecialchars($member['street']) ?>"
                     autocomplete="street-address"
                     aria-describedby="pf-street-err">
              <span class="pf-error" id="pf-street-err" role="alert"></span>
            </div>

            <div class="pf-row">
              <div class="pf-group">
                <label class="pf-label" for="pf-city">City</label>
                <input class="pf-input" type="text" id="pf-city" name="city"
                       value="<?= htmlspecialchars($member['city']) ?>"
                       autocomplete="address-level2"
                       aria-describedby="pf-city-err">
                <span class="pf-error" id="pf-city-err" role="alert"></span>
              </div>
              <div class="pf-group">
                <label class="pf-label" for="pf-postal">Postal Code</label>
                <input class="pf-input" type="text" id="pf-postal" name="postal"
                       value="<?= htmlspecialchars($member['postal_code']) ?>"
                       autocomplete="postal-code" maxlength="4" inputmode="numeric"
                       aria-describedby="pf-postal-err">
                <span class="pf-error" id="pf-postal-err" role="alert"></span>
              </div>
            </div>

            <button class="pf-submit" type="submit" id="profileSubmitBtn">
              <i class="ti ti-device-floppy" aria-hidden="true"></i>
              <span id="profileBtnLabel">Save Changes</span>
            </button>
          </form>
        </div>

        <!-- Change password form -->
        <div class="pp-card pp-card--full">
          <div class="pp-card-head">
            <h2 class="pp-card-title">
              <i class="ti ti-shield-lock" aria-hidden="true"></i>
              Change Password
            </h2>
          </div>

          <!--
            id="passwordForm" — handled by portal.js
            Submits via fetch() AJAX to:
            POST member_portal.php?action=change_password
            Verifies current password with password_verify(),
            then stores new bcrypt hash via password_hash().
          -->
          <form id="passwordForm" novalidate aria-label="Change password"
                class="pp-pw-form">
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="csrf"
                   value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="pf-group">
              <label class="pf-label" for="pf-current">Current Password</label>
              <div class="pf-pw-wrap">
                <input class="pf-input" type="password" id="pf-current"
                       name="current_password"
                       placeholder="Your current password"
                       autocomplete="current-password"
                       aria-describedby="pf-current-err">
                <button type="button" class="pf-pw-toggle"
                        data-target="pf-current"
                        aria-label="Show current password" aria-pressed="false">
                  <i class="ti ti-eye" aria-hidden="true"></i>
                </button>
              </div>
              <span class="pf-error" id="pf-current-err" role="alert"></span>
            </div>

            <div class="pf-row">
              <div class="pf-group">
                <label class="pf-label" for="pf-newpw">New Password</label>
                <div class="pf-pw-wrap">
                  <input class="pf-input" type="password" id="pf-newpw"
                         name="new_password"
                         placeholder="Min 8 chars, 1 uppercase, 1 number"
                         autocomplete="new-password"
                         aria-describedby="pf-newpw-err">
                  <button type="button" class="pf-pw-toggle"
                          data-target="pf-newpw"
                          aria-label="Show new password" aria-pressed="false">
                    <i class="ti ti-eye" aria-hidden="true"></i>
                  </button>
                </div>
                <span class="pf-error" id="pf-newpw-err" role="alert"></span>
              </div>
              <div class="pf-group">
                <label class="pf-label" for="pf-confirmpw">Confirm New Password</label>
                <div class="pf-pw-wrap">
                  <input class="pf-input" type="password" id="pf-confirmpw"
                         name="confirm_password"
                         placeholder="Repeat new password"
                         autocomplete="new-password"
                         aria-describedby="pf-confirmpw-err">
                  <button type="button" class="pf-pw-toggle"
                          data-target="pf-confirmpw"
                          aria-label="Show confirm password" aria-pressed="false">
                    <i class="ti ti-eye" aria-hidden="true"></i>
                  </button>
                </div>
                <span class="pf-error" id="pf-confirmpw-err" role="alert"></span>
              </div>
            </div>

            <button class="pf-submit" type="submit" id="passwordSubmitBtn">
              <i class="ti ti-lock" aria-hidden="true"></i>
              <span id="passwordBtnLabel">Update Password</span>
            </button>
          </form>
        </div>

      </div><!-- /.pp-profile-grid -->
    </section><!-- /#tab-profile -->


    <!-- ─────────────────────────────────
         TAB 3: EVENTS & RSVP
    ───────────────────────────────────── -->
    <section class="portal-panel" id="tab-events"
             aria-label="Events and RSVP" hidden>

      <div class="pp-page-head">
        <h1 class="pp-page-title">Events &amp; <em>RSVP</em></h1>
        <p class="pp-page-sub">Your upcoming reservations and full attendance history.</p>
      </div>

      <!-- My upcoming RSVPs -->
      <div class="pp-card pp-card--full">
        <div class="pp-card-head">
          <h2 class="pp-card-title">
            <i class="ti ti-calendar-check" aria-hidden="true"></i>
            My Upcoming RSVPs
          </h2>
        </div>
        <?php if (empty($my_rsvps)): ?>
          <p class="pp-empty">You have no upcoming RSVPs. Browse events below and reserve your spot.</p>
        <?php else: ?>
          <div class="pp-table-wrap">
            <!--
              SQL: SELECT e.title, e.event_date, e.venue, r.status
                   FROM RSVP r JOIN EVENT e ON e.event_id = r.event_id
                   WHERE r.member_id = ? AND e.event_date >= NOW()
                   ORDER BY e.event_date ASC
            -->
            <table class="pp-table" aria-label="My upcoming RSVPs">
              <thead>
                <tr>
                  <th scope="col">Event</th>
                  <th scope="col">Date</th>
                  <th scope="col">Venue</th>
                  <th scope="col">Status</th>
                  <th scope="col">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($my_rsvps as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['event']) ?></td>
                  <td><?= htmlspecialchars($r['date']) ?></td>
                  <td><?= htmlspecialchars($r['venue']) ?></td>
                  <td><span class="pp-status pp-status--present">✓ Confirmed</span></td>
                  <td>
                    <button class="pp-cancel-btn"
                            data-event-id="<?= $r['id'] ?>"
                            aria-label="Cancel RSVP for <?= htmlspecialchars($r['event']) ?>">
                      Cancel
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- Available events to RSVP -->
      <div class="pp-card pp-card--full">
        <div class="pp-card-head">
          <h2 class="pp-card-title">
            <i class="ti ti-calendar-plus" aria-hidden="true"></i>
            Available Events
          </h2>
          <a href="events.php" class="pp-card-link">Full events page →</a>
        </div>
        <div class="pp-available-events">
          <!--
            SQL: SELECT e.*, COUNT(r.rsvp_id) AS rsvp_count
                 FROM EVENT e LEFT JOIN RSVP r ON r.event_id = e.event_id
                 WHERE e.event_date >= NOW() GROUP BY e.event_id
                 ORDER BY e.event_date ASC
          -->
          <?php foreach ($available_events as $ev):
            $pct = round(($ev['rsvp'] / $ev['capacity']) * 100);
            $full = $ev['rsvp'] >= $ev['capacity'];
          ?>
          <div class="ppae-card">
            <div class="ppae-stripe" style="background:<?= $ev['color'] ?>"></div>
            <div class="ppae-body">
              <div class="ppae-date-box" style="background:<?= $ev['color'] ?>"
                   aria-label="<?= $ev['day'] . ' ' . $ev['month'] ?>">
                <span class="ppae-day"><?= $ev['day'] ?></span>
                <span class="ppae-mon"><?= $ev['month'] ?></span>
              </div>
              <div class="ppae-info">
                <strong><?= htmlspecialchars($ev['title']) ?></strong>
                <span>
                  <i class="ti ti-map-pin" aria-hidden="true"></i>
                  <?= htmlspecialchars($ev['venue']) ?>
                  &nbsp;·&nbsp;
                  <?= htmlspecialchars($ev['time']) ?>
                </span>
                <div class="ppae-bar-wrap">
                  <div class="ppae-bar">
                    <div class="ppae-bar-fill"
                         style="width:<?= $pct ?>%;background:<?= $ev['color'] ?>">
                    </div>
                  </div>
                  <span class="ppae-cap">
                    <?= $ev['rsvp'] ?>/<?= $ev['capacity'] ?>
                    <?= $full ? '· <strong style="color:var(--red)">Full</strong>' : '' ?>
                  </span>
                </div>
              </div>
              <button class="ppae-rsvp rsvp-btn <?= $full ? 'ppae-rsvp--disabled' : '' ?>"
                      data-event-id="<?= $ev['id'] ?>"
                      <?= $full ? 'disabled aria-disabled="true"' : '' ?>
                      aria-label="<?= $full ? 'Event full' : 'RSVP for ' . htmlspecialchars($ev['title']) ?>">
                <?= $full ? 'Full' : 'RSVP' ?>
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Full attendance history table -->
      <div class="pp-card pp-card--full">
        <div class="pp-card-head">
          <h2 class="pp-card-title">
            <i class="ti ti-list-check" aria-hidden="true"></i>
            Attendance History
          </h2>
        </div>
        <div class="pp-table-wrap">
          <!--
            SQL: SELECT e.title, e.event_date, e.venue, a.status
                 FROM ATTENDANCE a JOIN EVENT e ON e.event_id = a.event_id
                 WHERE a.member_id = ? ORDER BY e.event_date DESC
          -->
          <table class="pp-table" aria-label="Full attendance history">
            <thead>
              <tr>
                <th scope="col">Event</th>
                <th scope="col">Date</th>
                <th scope="col">Venue</th>
                <th scope="col">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($attendance as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row['event']) ?></td>
                <td><?= htmlspecialchars($row['date']) ?></td>
                <td><?= htmlspecialchars($row['venue']) ?></td>
                <td>
                  <?php if ($row['status'] === 'present'): ?>
                    <span class="pp-status pp-status--present">✓ Present</span>
                  <?php else: ?>
                    <span class="pp-status pp-status--absent">✗ Absent</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </section><!-- /#tab-events -->


    <!-- ─────────────────────────────────
         TAB 4: MY DONATIONS
    ───────────────────────────────────── -->
    <section class="portal-panel" id="tab-donations"
             aria-label="My donations" hidden>

      <div class="pp-page-head">
        <h1 class="pp-page-title">My <em>Donations</em></h1>
        <p class="pp-page-sub">Your giving history. Contact the office for an annual giving statement for tax purposes.</p>
      </div>

      <!-- Giving summary tiles -->
      <div class="pp-kpi-row pp-kpi-row--3" role="list">
        <div class="pp-kpi green" role="listitem">
          <div class="ppk-icon"><i class="ti ti-coin" aria-hidden="true"></i></div>
          <div class="ppk-body">
            <div class="ppk-val">R <?= number_format(array_sum(array_column($donations, 'amount')), 2) ?></div>
            <div class="ppk-lbl">Total Given (shown)</div>
          </div>
        </div>
        <div class="pp-kpi blue" role="listitem">
          <div class="ppk-icon"><i class="ti ti-receipt" aria-hidden="true"></i></div>
          <div class="ppk-body">
            <div class="ppk-val"><?= count($donations) ?></div>
            <div class="ppk-lbl">Transactions</div>
          </div>
        </div>
        <div class="pp-kpi amber" role="listitem">
          <div class="ppk-icon"><i class="ti ti-calendar-stats" aria-hidden="true"></i></div>
          <div class="ppk-body">
            <div class="ppk-val">R <?= number_format(2500, 2) ?></div>
            <div class="ppk-lbl">Monthly Tithe</div>
          </div>
        </div>
      </div>

      <!-- Donations table -->
      <div class="pp-card pp-card--full">
        <div class="pp-card-head">
          <h2 class="pp-card-title">
            <i class="ti ti-history" aria-hidden="true"></i>
            Giving History
          </h2>
          <a href="donate.php" class="pp-card-link">Give now →</a>
        </div>
        <div class="pp-table-wrap">
          <!--
            SQL: SELECT amount, donation_type, payment_method,
                        reference, donation_date
                 FROM DONATION WHERE member_id = ?
                 ORDER BY donation_date DESC
          -->
          <table class="pp-table" aria-label="Giving history">
            <thead>
              <tr>
                <th scope="col">Date</th>
                <th scope="col">Type</th>
                <th scope="col">Amount</th>
                <th scope="col">Method</th>
                <th scope="col">Reference</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($donations as $d): ?>
              <tr>
                <td><?= htmlspecialchars($d['date']) ?></td>
                <td><span class="pp-dtype"><?= htmlspecialchars($d['type']) ?></span></td>
                <td class="pp-amount">R <?= number_format($d['amount'], 2) ?></td>
                <td><?= htmlspecialchars($d['method']) ?></td>
                <td><code class="pp-ref"><?= htmlspecialchars($d['ref']) ?></code></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="pp-table-note">
          <i class="ti ti-info-circle" aria-hidden="true"></i>
          For a full annual tax certificate, contact
          <a href="mailto:finance@shammahurbanchurch.co.za">finance@shammahurbanchurch.co.za</a>
        </p>
      </div>

    </section><!-- /#tab-donations -->


    <!-- ─────────────────────────────────
         TAB 5: MY MINISTRY
    ───────────────────────────────────── -->
    <section class="portal-panel" id="tab-ministries"
             aria-label="My ministry" hidden>

      <div class="pp-page-head">
        <h1 class="pp-page-title">My <em>Ministry</em></h1>
        <p class="pp-page-sub">Your current volunteer roles and ministry involvement.</p>
      </div>

      <!-- Ministry role cards -->
      <div class="pp-card pp-card--full">
        <div class="pp-card-head">
          <h2 class="pp-card-title">
            <i class="ti ti-tools" aria-hidden="true"></i>
            My Roles
          </h2>
          <a href="contact.php" class="pp-card-link">Join a ministry →</a>
        </div>

        <div class="pp-ministry-grid">
          <!--
            SQL: SELECT vr.role_name, vr.ministry, v.status, v.start_date
                 FROM VOLUNTEER v
                 JOIN VOLUNTEER_ROLE vr ON vr.role_id = v.role_id
                 WHERE v.member_id = ?
          -->
          <?php foreach ($my_ministries as $m):
            $is_active = $m['status'] === 'active';
          ?>
          <div class="pp-ministry-card <?= $is_active ? 'pp-ministry-card--active' : '' ?>">
            <div class="ppm-icon" aria-hidden="true">
              <i class="ti ti-<?= $m['ministry'] === 'Worship' ? 'music' : ($m['ministry'] === 'Cell Groups' ? 'users-group' : 'hand') ?>"></i>
            </div>
            <div class="ppm-body">
              <strong><?= htmlspecialchars($m['role']) ?></strong>
              <span><?= htmlspecialchars($m['ministry']) ?></span>
              <div class="ppm-footer">
                <span class="ppm-status ppm-status--<?= $m['status'] ?>">
                  <?= ucfirst($m['status']) ?>
                </span>
                <span class="ppm-since">Since <?= $m['since'] ?></span>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Volunteer hours summary -->
      <div class="pp-card pp-card--full">
        <div class="pp-card-head">
          <h2 class="pp-card-title">
            <i class="ti ti-clock" aria-hidden="true"></i>
            Volunteer Hours
          </h2>
        </div>
        <div class="pp-hours-summary">
          <div class="pphs-total">
            <!--
              SQL: SELECT COALESCE(SUM(hours),0) AS total_hours
                   FROM VOLUNTEER_HOURS WHERE member_id = ?
            -->
            <span class="pphs-num"><?= $kpi['volunteer_hours'] ?></span>
            <span class="pphs-unit">hours contributed</span>
          </div>
          <p class="pphs-note">
            Thank you for your service, <?= htmlspecialchars($member['first_name']) ?>!
            Your contribution makes a real difference to the Shammah community.
          </p>
          <a href="contact.php" class="btn-outline-navy" style="margin-top:16px">
            <i class="ti ti-plus" aria-hidden="true"></i>
            Register for another role
          </a>
        </div>
      </div>

    </section><!-- /#tab-ministries -->


    <!-- ─────────────────────────────────
         TAB 6: ANNOUNCEMENTS
    ───────────────────────────────────── -->
    <section class="portal-panel" id="tab-announcements"
             aria-label="Announcements" hidden>

      <div class="pp-page-head">
        <h1 class="pp-page-title">Church <em>Announcements</em></h1>
        <p class="pp-page-sub">The latest news and notices from the Shammah leadership team.</p>
      </div>

      <div class="pp-announce-full">
        <!--
          SQL: SELECT title, body, created_at, priority, category
               FROM ANNOUNCEMENT
               WHERE is_active = 1
               ORDER BY created_at DESC
        -->
        <?php foreach ($portal_announcements as $ann): ?>
        <article class="ppaf-item" aria-label="<?= htmlspecialchars($ann['title']) ?>">
          <div class="ppaf-icon ppaf-<?= $ann['color'] ?>" aria-hidden="true">
            <i class="ti <?= $ann['icon'] ?>"></i>
          </div>
          <div class="ppaf-body">
            <div class="ppaf-head">
              <h3><?= htmlspecialchars($ann['title']) ?></h3>
              <time class="ppaf-date"><?= $ann['date'] ?></time>
            </div>
            <p><?= htmlspecialchars($ann['body']) ?></p>
          </div>
        </article>
        <?php endforeach; ?>
      </div>

    </section><!-- /#tab-announcements -->

  </div><!-- /.portal-main -->
</div><!-- /.portal-wrap -->
</main><!-- /#main-content -->

<!-- Sidebar overlay for mobile (click to close) -->
<div class="portal-overlay" id="portalOverlay" aria-hidden="true"></div>

<!-- PHP → JS bridge -->
<script>
  const CSRF_TOKEN  = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
  const MEMBER_ID   = <?= json_encode($member_id) ?>;
  const MEMBER_NAME = <?= json_encode(htmlspecialchars($member['first_name'])) ?>;
</script>

<script src="js/portal.js"></script>

<?php require_once 'includes/footer.php'; ?>

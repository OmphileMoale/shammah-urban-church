<?php
/* ═══════════════════════════════════════
   volunteers.php  –  VOLUNTEER PAGE

   HOW THIS FILE WORKS:
   1. session_start()     – reads PHP session (auth state)
   2. require data.php    – loads $announcements (ticker)
   3. $volunteer_roles    – all available ministry roles
   4. $my_schedule        – logged-in user's upcoming shifts
   5. $hours_log          – user's past logged hours
   6. $testimonials       – volunteer quotes for the testimonial section
   7. Hero + stats        – banner with impact numbers
   8. Breadcrumb          – Home > Volunteers
   9. Category filter     – pill buttons to filter roles by ministry
   10. Tabs               – Available Roles / My Schedule / Log Hours / My Hours Summary
   11. Register modal     – popup form for joining a role
   12. Testimonials       – 3 volunteer stories
   13. CTA section        – bottom call-to-action

   DATABASE NOTE:
   $volunteer_roles simulates a VOLUNTEER_ROLE table.
   $my_schedule simulates a VOLUNTEER_SHIFT table WHERE member_id = $_SESSION['member_id'].
   $hours_log simulates a VOLUNTEER_HOURS table.
   When you add a real DB, replace these arrays with PDO queries.
═══════════════════════════════════════ */
session_start();
require_once 'includes/data.php';

/* ── AUTH STATE ─────────────────────── */
$is_logged_in = isset($_SESSION['member_id']);
$user_name    = $is_logged_in ? htmlspecialchars($_SESSION['member_name'] ?? 'Member') : '';
$user_role    = $is_logged_in ? ($_SESSION['role'] ?? 'member') : '';

/* ── PAGE META ──────────────────────── */
$page_title = 'Volunteers | Shammah Urban Church';
$page_desc  = 'Serve with purpose at Shammah Urban Church. Browse open ministry roles, manage your schedule, and log volunteer hours — all in one place.';

/* ══════════════════════════════════════
   VOLUNTEER ROLES DATA ARRAY
   Each entry = one row in VOLUNTEER_ROLE table.
   FIELDS:
   id          – unique ID (DB primary key later)
   title       – role name shown on card
   category    – ministry category (for filter pill)
   cat_key     – lowercase slug for JS filtering
   icon        – Tabler icon class
   color       – accent stripe colour
   desc        – what the volunteer does (2 sentences)
   schedule    – when they serve
   spots       – null = unlimited / int = spots remaining
   active      – current volunteer count
   requirements– short list of what's needed
   registered  – simulate if the logged-in user is already in this role
══════════════════════════════════════ */
$volunteer_roles = [
  [
    'id'           => 1,
    'title'        => 'Usher & Welcome Team',
    'category'     => 'Hospitality',
    'cat_key'      => 'hospitality',
    'icon'         => 'ti-door-enter',
    'color'        => '#C9A84C',
    'desc'         => 'Welcome members and guests at the door, manage seating, and distribute service programs. You are the first face people see — make it a warm one.',
    'schedule'     => 'Sunday 8:30 AM & 10:30 AM',
    'spots'        => null,
    'active'       => 18,
    'requirements' => ['Friendly & approachable', 'Arrive 30 min early', 'Neat dress code'],
    'registered'   => false,
  ],
  [
    'id'           => 2,
    'title'        => 'Worship Team Vocalist',
    'category'     => 'Worship',
    'cat_key'      => 'worship',
    'icon'         => 'ti-microphone-2',
    'color'        => '#3D0C2F',
    'desc'         => 'Lead the congregation in praise and worship during Sunday services and special events. Rehearsals are held every Thursday evening.',
    'schedule'     => 'Thu 6:30 PM (rehearsal) · Sun 8:00 AM',
    'spots'        => 3,
    'active'       => 12,
    'requirements' => ['Vocal ability', 'Committed to rehearsals', 'Heart for worship'],
    'registered'   => true,
  ],
  [
    'id'           => 3,
    'title'        => "Children's Ministry Helper",
    'category'     => 'Children',
    'cat_key'      => 'children',
    'icon'         => 'ti-baby-carriage',
    'color'        => '#639922',
    'desc'         => 'Supervise and teach Sunday school classes for children aged 3–12. You will lead Bible activities, crafts, and songs in a safe, nurturing environment.',
    'schedule'     => 'Sunday 9:00 AM – 12:00 PM',
    'spots'        => 2,
    'active'       => 14,
    'requirements' => ['Police clearance required', 'Patient & energetic', 'Training provided'],
    'registered'   => false,
  ],
  [
    'id'           => 4,
    'title'        => 'Sound Engineer',
    'category'     => 'Technical',
    'cat_key'      => 'technical',
    'icon'         => 'ti-adjustments-horizontal',
    'color'        => '#378ADD',
    'desc'         => 'Operate the mixing desk and audio equipment during services. You will balance microphones, instruments, and in-ear monitors to create a great listening experience.',
    'schedule'     => 'Sunday 7:45 AM – 1:00 PM',
    'spots'        => null,
    'active'       => 4,
    'requirements' => ['Basic audio knowledge', 'Punctual & reliable', 'On-the-job training available'],
    'registered'   => false,
  ],
  [
    'id'           => 5,
    'title'        => 'Community Outreach Helper',
    'category'     => 'Outreach',
    'cat_key'      => 'outreach',
    'icon'         => 'ti-world-heart',
    'color'        => '#C0392B',
    'desc'         => 'Join our monthly outreach drives in Sunnyside and surrounding areas — distributing food parcels, clothing, and prayer to families in need.',
    'schedule'     => '1st Saturday of each month, 8:00 AM',
    'spots'        => null,
    'active'       => 22,
    'requirements' => ['Servant heart', 'No prior experience needed', 'Transport provided'],
    'registered'   => false,
  ],
  [
    'id'           => 6,
    'title'        => 'Live Stream & Media Operator',
    'category'     => 'Media',
    'cat_key'      => 'media',
    'icon'         => 'ti-video',
    'color'        => '#8E44AD',
    'desc'         => 'Run the live stream cameras, OBS software, and social media posts during Sunday services. You will help thousands watch online from across South Africa.',
    'schedule'     => 'Sunday 8:00 AM – 1:00 PM',
    'spots'        => 2,
    'active'       => 5,
    'requirements' => ['Basic computer skills', 'OBS experience a bonus', 'Training provided'],
    'registered'   => false,
  ],
  [
    'id'           => 7,
    'title'        => 'Parking & Traffic Marshaller',
    'category'     => 'Hospitality',
    'cat_key'      => 'hospitality',
    'icon'         => 'ti-car',
    'color'        => '#C9A84C',
    'desc'         => 'Direct vehicles into parking bays before and after services. You help ensure safety and keep traffic flowing smoothly on our church grounds.',
    'schedule'     => 'Sunday 8:15 AM & 10:15 AM',
    'spots'        => null,
    'active'       => 8,
    'requirements' => ['Reflective vest provided', 'Physically able', 'Outdoor environment'],
    'registered'   => false,
  ],
  [
    'id'           => 8,
    'title'        => 'Prayer Team Intercessor',
    'category'     => 'Worship',
    'cat_key'      => 'worship',
    'icon'         => 'ti-heart-handshake',
    'color'        => '#3D0C2F',
    'desc'         => 'Pray with congregation members after services and intercede during our monthly prayer nights. You will be trained to pray confidently and sensitively with others.',
    'schedule'     => 'After Sunday services · Monthly prayer nights',
    'spots'        => null,
    'active'       => 9,
    'requirements' => ['Mature faith', 'Confidentiality required', 'Prayer training provided'],
    'registered'   => false,
  ],
];

/* ── MY SCHEDULE DATA ─────────────────
   Simulates VOLUNTEER_SHIFT table for the logged-in member.
   In a real DB: SELECT * FROM VOLUNTEER_SHIFT WHERE member_id = ? AND date >= NOW()
──────────────────────────────────────── */
$my_schedule = [
  [
    'id'       => 1,
    'role'     => 'Worship Team Vocalist',
    'category' => 'Worship',
    'day'      => '01',
    'month'    => 'Jun',
    'year'     => '2025',
    'time'     => 'Sun 8:00 AM',
    'venue'    => 'Main Sanctuary',
    'status'   => 'confirmed',
  ],
  [
    'id'       => 2,
    'role'     => 'Worship Team Vocalist',
    'category' => 'Worship',
    'day'      => '05',
    'month'    => 'Jun',
    'year'     => '2025',
    'time'     => 'Thu 6:30 PM',
    'venue'    => 'Rehearsal Room B',
    'status'   => 'confirmed',
  ],
  [
    'id'       => 3,
    'role'     => 'Worship Team Vocalist',
    'category' => 'Worship',
    'day'      => '08',
    'month'    => 'Jun',
    'year'     => '2025',
    'time'     => 'Sun 8:00 AM',
    'venue'    => 'Main Sanctuary',
    'status'   => 'pending',
  ],
];

/* ── HOURS LOG DATA ───────────────────
   Simulates VOLUNTEER_HOURS table.
   In a real DB: SELECT * FROM VOLUNTEER_HOURS WHERE member_id = ? ORDER BY log_date DESC
──────────────────────────────────────── */
$hours_log = [
  ['date' => '25 May 2025', 'role' => 'Worship Team Vocalist', 'hours' => 3.5, 'notes' => 'Sunday morning service + sound check'],
  ['date' => '22 May 2025', 'role' => 'Worship Team Vocalist', 'hours' => 2.0, 'notes' => 'Thursday rehearsal'],
  ['date' => '18 May 2025', 'role' => 'Worship Team Vocalist', 'hours' => 3.5, 'notes' => 'Sunday morning service'],
  ['date' => '11 May 2025', 'role' => 'Worship Team Vocalist', 'hours' => 3.5, 'notes' => 'Sunday morning + evening service'],
  ['date' => '04 May 2025', 'role' => 'Worship Team Vocalist', 'hours' => 2.0, 'notes' => 'Thursday rehearsal'],
  ['date' => '01 May 2025', 'role' => 'Worship Team Vocalist', 'hours' => 4.0, 'notes' => 'Special Workers Day service'],
  ['date' => '27 Apr 2025', 'role' => 'Worship Team Vocalist', 'hours' => 3.5, 'notes' => 'Sunday morning service'],
  ['date' => '20 Apr 2025', 'role' => 'Worship Team Vocalist', 'hours' => 4.5, 'notes' => 'Easter Sunday — both services'],
  ['date' => '13 Apr 2025', 'role' => 'Worship Team Vocalist', 'hours' => 3.0, 'notes' => 'Palm Sunday service'],
  ['date' => '06 Apr 2025', 'role' => 'Worship Team Vocalist', 'hours' => 2.0, 'notes' => 'Rehearsal — new song arrangements'],
];
$total_my_hours  = array_sum(array_column($hours_log, 'hours'));
$hours_goal      = 50; /* annual target */
$hours_pct       = min(100, round(($total_my_hours / $hours_goal) * 100));

/* ── TESTIMONIALS ─────────────────────
   3 volunteer quote cards in the section below the tabs.
──────────────────────────────────────── */
$testimonials = [
  [
    'quote'    => 'Serving on the worship team completely changed my life. I came to church looking for community and found my calling instead.',
    'name'     => 'Zanele Dlamini',
    'role'     => 'Worship Team Vocalist · 2 years',
    'initials' => 'ZD',
    'color'    => '#3D0C2F',
  ],
  [
    'quote'    => 'Every Saturday on outreach reminds me why we are here. Seeing families receive food and prayer — that is the church being the church.',
    'name'     => 'Thabo Nkosi',
    'role'     => 'Community Outreach · 3 years',
    'initials' => 'TN',
    'color'    => '#C0392B',
  ],
  [
    'quote'    => 'I was nervous to volunteer but the team made me feel so welcome from day one. Now I count the days until Sunday so I can be there for the kids.',
    'name'     => 'Priya Govender',
    'role'     => "Children's Ministry · 1 year",
    'initials' => 'PG',
    'color'    => '#639922',
  ],
];

/* ── PAGE STATS ─────────────────────── */
$total_volunteers  = 87;
$total_hours_all   = 1240;
$total_ministries  = 6;
$total_roles       = count($volunteer_roles);
$categories        = ['Worship', 'Hospitality', 'Children', 'Technical', 'Outreach', 'Media'];

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- ══════════════ PAGE MAIN ══════════════ -->
<main id="main-content">

  <!-- ═══════════════════════════════════
       HERO SECTION
  ════════════════════════════════════ -->
  <section class="vol-hero" aria-label="Volunteer at Shammah Urban Church">
    <div class="vol-hero-pattern" aria-hidden="true"></div>
    <div class="vol-hero-inner">
      <div class="hero-eyebrow">
        <i class="ti ti-heart-handshake" aria-hidden="true"></i>
        Serve with Purpose
      </div>
      <h1>Volunteer <em>Ministries</em></h1>
      <p class="vol-hero-sub">
        Your time and talent can transform lives. Browse open roles, manage your
        schedule, and track your impact — all in one place.
      </p>
      <div class="vol-hero-stats" aria-label="Volunteer statistics">
        <div class="v-stat">
          <strong><?= $total_volunteers ?></strong>
          <span>Active Volunteers</span>
        </div>
        <div class="v-stat-divider" aria-hidden="true"></div>
        <div class="v-stat">
          <strong><?= number_format($total_hours_all) ?>+</strong>
          <span>Hours Served</span>
        </div>
        <div class="v-stat-divider" aria-hidden="true"></div>
        <div class="v-stat">
          <strong><?= $total_ministries ?></strong>
          <span>Ministry Areas</span>
        </div>
        <div class="v-stat-divider" aria-hidden="true"></div>
        <div class="v-stat">
          <strong><?= $total_roles ?></strong>
          <span>Open Roles</span>
        </div>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════════════════
       BREADCRUMB
  ════════════════════════════════════ -->
  <nav class="breadcrumb-bar" aria-label="Breadcrumb">
    <ol class="breadcrumb">
      <li><a href="index.php"><i class="ti ti-home" aria-hidden="true"></i> Home</a></li>
      <li aria-current="page">Volunteers</li>
    </ol>
  </nav>

  <!-- ═══════════════════════════════════
       TABS BAR
       Sticky below nav so it stays visible
       while scrolling through roles.
  ════════════════════════════════════ -->
  <div class="vol-tabs-wrap">
    <div class="vol-tabs" role="tablist" aria-label="Volunteer page sections">
      <button class="v-tab active"
              data-tab="roles"
              role="tab"
              aria-selected="true"
              aria-controls="tab-roles">
        <i class="ti ti-layout-grid" aria-hidden="true"></i>
        Available Roles
      </button>
      <button class="v-tab"
              data-tab="schedule"
              role="tab"
              aria-selected="false"
              aria-controls="tab-schedule">
        <i class="ti ti-calendar-event" aria-hidden="true"></i>
        My Schedule
        <?php if ($is_logged_in): ?>
          <span class="tab-badge"><?= count($my_schedule) ?></span>
        <?php endif; ?>
      </button>
      <button class="v-tab"
              data-tab="log"
              role="tab"
              aria-selected="false"
              aria-controls="tab-log">
        <i class="ti ti-clock-check" aria-hidden="true"></i>
        Log Hours
      </button>
      <button class="v-tab"
              data-tab="summary"
              role="tab"
              aria-selected="false"
              aria-controls="tab-summary">
        <i class="ti ti-chart-bar" aria-hidden="true"></i>
        My Hours Summary
      </button>
    </div>
  </div>

  <!-- ═══════════════════════════════════
       TAB PANELS
  ════════════════════════════════════ -->
  <div class="section">
    <div class="section-wide">

      <!-- ══════════════════════════════
           TAB 1 — AVAILABLE ROLES
      ═══════════════════════════════ -->
      <div class="tab-panel active" id="tab-roles" role="tabpanel">

        <!-- ── CATEGORY FILTER PILLS ─ -->
        <div class="cat-filter" role="group" aria-label="Filter roles by ministry category">
          <button class="cat-pill active" data-cat="all" aria-pressed="true">
            All Roles <span class="pill-count"><?= $total_roles ?></span>
          </button>
          <?php
          /*
           * Count how many roles belong to each category
           * so we can show the number on each pill.
           */
          foreach ($categories as $cat):
            $cnt = count(array_filter($volunteer_roles, fn($r) => $r['category'] === $cat));
            if ($cnt === 0) continue;
            $slug = strtolower($cat);
          ?>
          <button class="cat-pill" data-cat="<?= $slug ?>" aria-pressed="false">
            <?= htmlspecialchars($cat) ?> <span class="pill-count"><?= $cnt ?></span>
          </button>
          <?php endforeach; ?>
        </div>

        <!-- ── ROLES GRID ─────────────
             JS hides/shows cards based on
             the active category pill.
        ──────────────────────────────── -->
        <div class="roles-grid" id="rolesGrid">
          <?php foreach ($volunteer_roles as $role): ?>
          <article class="role-card"
                   data-cat="<?= htmlspecialchars($role['cat_key']) ?>"
                   aria-label="<?= htmlspecialchars($role['title']) ?> volunteer role">

            <!-- Top accent stripe -->
            <div class="role-stripe" style="background:<?= $role['color'] ?>" aria-hidden="true"></div>

            <div class="role-body">

              <!-- Icon + category tag -->
              <div class="role-head">
                <div class="role-icon" style="color:<?= $role['color'] ?>" aria-hidden="true">
                  <i class="ti <?= $role['icon'] ?>"></i>
                </div>
                <div>
                  <span class="role-cat-tag"><?= htmlspecialchars($role['category']) ?></span>
                  <?php if ($role['registered']): ?>
                    <span class="registered-badge">✓ Registered</span>
                  <?php elseif (!is_null($role['spots']) && $role['spots'] <= 3): ?>
                    <span class="urgent-badge">
                      <?= $role['spots'] ?> spot<?= $role['spots'] !== 1 ? 's' : '' ?> left
                    </span>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Title -->
              <h3 class="role-title"><?= htmlspecialchars($role['title']) ?></h3>

              <!-- Description -->
              <p class="role-desc"><?= htmlspecialchars($role['desc']) ?></p>

              <!-- Schedule -->
              <div class="role-schedule">
                <i class="ti ti-clock" aria-hidden="true"></i>
                <?= htmlspecialchars($role['schedule']) ?>
              </div>

              <!-- Requirements pills -->
              <div class="role-reqs" aria-label="Requirements">
                <?php foreach ($role['requirements'] as $req): ?>
                  <span class="req-tag"><?= htmlspecialchars($req) ?></span>
                <?php endforeach; ?>
              </div>

              <!-- Footer: active count + action button -->
              <div class="role-footer">
                <span class="role-active-count">
                  <i class="ti ti-users" aria-hidden="true"></i>
                  <?= $role['active'] ?> active volunteer<?= $role['active'] !== 1 ? 's' : '' ?>
                </span>

                <?php if ($role['registered']): ?>
                  <button class="btn-registered" disabled aria-label="You are already registered for this role">
                    <i class="ti ti-check" aria-hidden="true"></i>
                    Registered
                  </button>
                <?php else: ?>
                  <!--
                    data-role-id and data-role-title are read by JS
                    to pre-fill the registration modal.
                  -->
                  <button class="btn-register open-modal"
                          data-role-id="<?= $role['id'] ?>"
                          data-role-title="<?= htmlspecialchars($role['title']) ?>"
                          aria-haspopup="dialog"
                          aria-label="Register for <?= htmlspecialchars($role['title']) ?>">
                    <i class="ti ti-plus" aria-hidden="true"></i>
                    Register
                  </button>
                <?php endif; ?>
              </div>

            </div><!-- /role-body -->
          </article>
          <?php endforeach; ?>
        </div><!-- /roles-grid -->

        <!-- No results (shown by JS when filter returns 0) -->
        <div class="no-roles" id="noRoles" aria-live="polite">
          <i class="ti ti-search-off" aria-hidden="true"></i>
          <p>No roles found in this category. Try selecting a different filter.</p>
        </div>

      </div><!-- /tab-roles -->


      <!-- ══════════════════════════════
           TAB 2 — MY SCHEDULE
      ═══════════════════════════════ -->
      <div class="tab-panel" id="tab-schedule" role="tabpanel">

        <?php if (!$is_logged_in): ?>
          <!-- Not logged in: show prompt -->
          <div class="login-prompt" role="alert">
            <i class="ti ti-lock" aria-hidden="true"></i>
            <h3>Please log in to view your schedule</h3>
            <p>Your upcoming volunteer shifts and confirmed rosters will appear here once you sign in.</p>
            <a href="login.php" class="btn-gold">
              <i class="ti ti-login" aria-hidden="true"></i>
              Log In
            </a>
          </div>

        <?php else: ?>
          <!-- Logged in: show schedule -->
          <div class="section-tag">Upcoming Shifts</div>
          <h2 class="section-title" style="margin-bottom:6px">
            My <em>Schedule</em>
          </h2>
          <p class="section-sub" style="margin-bottom:28px">
            Your confirmed and pending volunteer shifts for the coming weeks.
          </p>

          <?php if (empty($my_schedule)): ?>
            <div class="empty-state">
              <i class="ti ti-calendar-off" aria-hidden="true"></i>
              <p>You have no upcoming shifts. Register for a role above to get started.</p>
            </div>
          <?php else: ?>
            <div class="schedule-list" role="list">
              <?php foreach ($my_schedule as $shift): ?>
              <div class="shift-card" role="listitem">
                <!-- Date badge -->
                <div class="shift-date" aria-label="Date: <?= $shift['day'] ?> <?= $shift['month'] ?>">
                  <span class="shift-day"><?= $shift['day'] ?></span>
                  <span class="shift-month"><?= $shift['month'] ?></span>
                </div>

                <!-- Shift info -->
                <div class="shift-info">
                  <div class="shift-role"><?= htmlspecialchars($shift['role']) ?></div>
                  <div class="shift-meta">
                    <span><i class="ti ti-clock" aria-hidden="true"></i> <?= htmlspecialchars($shift['time']) ?></span>
                    <span><i class="ti ti-map-pin" aria-hidden="true"></i> <?= htmlspecialchars($shift['venue']) ?></span>
                  </div>
                </div>

                <!-- Status + cancel -->
                <div class="shift-right">
                  <span class="shift-status <?= $shift['status'] ?>">
                    <?= $shift['status'] === 'confirmed' ? '✓ Confirmed' : '⏳ Pending' ?>
                  </span>
                  <button class="btn-cancel-shift"
                          data-shift-id="<?= $shift['id'] ?>"
                          aria-label="Cancel shift for <?= htmlspecialchars($shift['role']) ?> on <?= $shift['day'] ?> <?= $shift['month'] ?>">
                    <i class="ti ti-x" aria-hidden="true"></i>
                    Can't make it
                  </button>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>

      </div><!-- /tab-schedule -->


      <!-- ══════════════════════════════
           TAB 3 — LOG HOURS
      ═══════════════════════════════ -->
      <div class="tab-panel" id="tab-log" role="tabpanel">

        <?php if (!$is_logged_in): ?>
          <div class="login-prompt" role="alert">
            <i class="ti ti-lock" aria-hidden="true"></i>
            <h3>Please log in to log your hours</h3>
            <p>Track your volunteer contributions by signing in first.</p>
            <a href="login.php" class="btn-gold">
              <i class="ti ti-login" aria-hidden="true"></i>
              Log In
            </a>
          </div>

        <?php else: ?>
          <div class="log-layout">

            <!-- Log form -->
            <div class="log-form-wrap">
              <div class="section-tag">Record Your Service</div>
              <h2 class="section-title" style="margin-bottom:6px">Log <em>Hours</em></h2>
              <p class="section-sub" style="margin-bottom:24px">
                Every hour you serve counts. Submit your hours after each shift.
              </p>

              <!--
                No <form> tag used — JS intercepts the button click.
                This avoids a full page reload and shows a toast instead.
              -->
              <div class="log-form" id="logHoursForm" role="form" aria-label="Log volunteer hours">

                <!-- Date -->
                <div class="form-group">
                  <label for="logDate" class="form-label">
                    <i class="ti ti-calendar" aria-hidden="true"></i>
                    Date of Service <span class="req-star" aria-hidden="true">*</span>
                  </label>
                  <input type="date"
                         id="logDate"
                         class="form-input"
                         max="<?= date('Y-m-d') ?>"
                         required
                         aria-required="true">
                  <span class="field-error" id="logDateErr" role="alert"></span>
                </div>

                <!-- Role -->
                <div class="form-group">
                  <label for="logRole" class="form-label">
                    <i class="ti ti-briefcase" aria-hidden="true"></i>
                    Ministry Role <span class="req-star" aria-hidden="true">*</span>
                  </label>
                  <select id="logRole" class="form-select" required aria-required="true">
                    <option value="">— Select your role —</option>
                    <?php foreach ($volunteer_roles as $r): ?>
                      <?php if ($r['registered']): ?>
                        <option value="<?= htmlspecialchars($r['title']) ?>" selected>
                          <?= htmlspecialchars($r['title']) ?>
                        </option>
                      <?php else: ?>
                        <option value="<?= htmlspecialchars($r['title']) ?>">
                          <?= htmlspecialchars($r['title']) ?>
                        </option>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </select>
                  <span class="field-error" id="logRoleErr" role="alert"></span>
                </div>

                <!-- Hours -->
                <div class="form-group">
                  <label for="logHours" class="form-label">
                    <i class="ti ti-clock" aria-hidden="true"></i>
                    Hours Served <span class="req-star" aria-hidden="true">*</span>
                  </label>
                  <input type="number"
                         id="logHours"
                         class="form-input"
                         min="0.5"
                         max="24"
                         step="0.5"
                         placeholder="e.g. 3.5"
                         required
                         aria-required="true">
                  <span class="field-error" id="logHoursErr" role="alert"></span>
                </div>

                <!-- Notes -->
                <div class="form-group">
                  <label for="logNotes" class="form-label">
                    <i class="ti ti-notes" aria-hidden="true"></i>
                    Notes <span class="form-optional">(optional)</span>
                  </label>
                  <textarea id="logNotes"
                            class="form-textarea"
                            rows="3"
                            placeholder="What did you do during this shift? (e.g. Sunday service, afternoon rehearsal)"
                            aria-label="Notes about this volunteer shift"></textarea>
                </div>

                <button class="btn-submit-hours" id="submitHours" type="button">
                  <i class="ti ti-clock-plus" aria-hidden="true"></i>
                  Submit Hours
                </button>

              </div><!-- /log-form -->
            </div><!-- /log-form-wrap -->

            <!-- Recent logs sidebar -->
            <div class="recent-logs">
              <h4 class="recent-logs-title">
                <i class="ti ti-history" aria-hidden="true"></i>
                Recent Entries
              </h4>
              <div class="logs-list" role="list">
                <?php foreach (array_slice($hours_log, 0, 5) as $entry): ?>
                <div class="log-entry" role="listitem">
                  <div class="log-entry-date"><?= htmlspecialchars($entry['date']) ?></div>
                  <div class="log-entry-role"><?= htmlspecialchars($entry['role']) ?></div>
                  <div class="log-entry-hours">
                    <i class="ti ti-clock" aria-hidden="true"></i>
                    <?= $entry['hours'] ?> hr<?= $entry['hours'] !== 1.0 ? 's' : '' ?>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>

          </div><!-- /log-layout -->
        <?php endif; ?>

      </div><!-- /tab-log -->


      <!-- ══════════════════════════════
           TAB 4 — MY HOURS SUMMARY
      ═══════════════════════════════ -->
      <div class="tab-panel" id="tab-summary" role="tabpanel">

        <?php if (!$is_logged_in): ?>
          <div class="login-prompt" role="alert">
            <i class="ti ti-lock" aria-hidden="true"></i>
            <h3>Please log in to view your summary</h3>
            <p>Your total hours, progress, and monthly breakdown will appear here once you sign in.</p>
            <a href="login.php" class="btn-gold">
              <i class="ti ti-login" aria-hidden="true"></i>
              Log In
            </a>
          </div>

        <?php else: ?>
          <div class="section-tag">Your Contribution</div>
          <h2 class="section-title" style="margin-bottom:6px">Hours <em>Summary</em></h2>
          <p class="section-sub" style="margin-bottom:28px">
            A snapshot of your service this year. Keep going — every hour matters.
          </p>

          <!-- KPI cards -->
          <div class="summary-kpis" role="list" aria-label="Volunteer hour statistics">
            <div class="kpi-card" role="listitem">
              <div class="kpi-icon gold" aria-hidden="true">
                <i class="ti ti-clock"></i>
              </div>
              <div class="kpi-val"><?= $total_my_hours ?></div>
              <div class="kpi-label">Total Hours This Year</div>
            </div>
            <div class="kpi-card" role="listitem">
              <div class="kpi-icon navy" aria-hidden="true">
                <i class="ti ti-calendar-stats"></i>
              </div>
              <div class="kpi-val"><?= count($hours_log) ?></div>
              <div class="kpi-label">Shifts Completed</div>
            </div>
            <div class="kpi-card" role="listitem">
              <div class="kpi-icon green" aria-hidden="true">
                <i class="ti ti-target"></i>
              </div>
              <div class="kpi-val"><?= $hours_goal - $total_my_hours ?></div>
              <div class="kpi-label">Hours Until Annual Goal</div>
            </div>
          </div>

          <!-- Annual goal progress bar -->
          <div class="progress-section" aria-label="Annual hours progress">
            <div class="progress-label">
              <span>Annual Goal Progress</span>
              <span class="progress-nums">
                <?= $total_my_hours ?> / <?= $hours_goal ?> hours
                (<?= $hours_pct ?>%)
              </span>
            </div>
            <div class="progress-track" role="progressbar"
                 aria-valuenow="<?= $total_my_hours ?>"
                 aria-valuemin="0"
                 aria-valuemax="<?= $hours_goal ?>"
                 aria-label="<?= $hours_pct ?>% of annual goal reached">
              <!--
                CSS custom property --pct used by JS to
                animate the bar width on tab open.
              -->
              <div class="progress-fill" id="progressFill" style="--pct:0%"></div>
            </div>
            <p class="progress-message">
              <?php if ($hours_pct >= 100): ?>
                🎉 You've reached your annual goal — amazing work!
              <?php elseif ($hours_pct >= 75): ?>
                💪 Almost there — only <?= $hours_goal - $total_my_hours ?> hours to go!
              <?php elseif ($hours_pct >= 50): ?>
                👏 Great progress — you're more than halfway to your goal!
              <?php else: ?>
                🙏 Every shift brings you closer — keep serving faithfully.
              <?php endif; ?>
            </p>
          </div>

          <!-- Detailed log table -->
          <div class="hours-table-wrap">
            <h4 class="table-heading">
              <i class="ti ti-table" aria-hidden="true"></i>
              All Logged Hours
            </h4>
            <div class="table-scroll">
              <table class="hours-table" aria-label="All volunteer hours logged">
                <thead>
                  <tr>
                    <th scope="col">Date</th>
                    <th scope="col">Ministry Role</th>
                    <th scope="col">Hours</th>
                    <th scope="col">Notes</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($hours_log as $entry): ?>
                  <tr>
                    <td>
                      <span class="table-date"><?= htmlspecialchars($entry['date']) ?></span>
                    </td>
                    <td><?= htmlspecialchars($entry['role']) ?></td>
                    <td>
                      <span class="hours-badge">
                        <?= $entry['hours'] ?> hr<?= $entry['hours'] !== 1.0 ? 's' : '' ?>
                      </span>
                    </td>
                    <td class="notes-cell"><?= htmlspecialchars($entry['notes']) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="2"><strong>Total</strong></td>
                    <td>
                      <span class="hours-badge gold">
                        <?= $total_my_hours ?> hrs
                      </span>
                    </td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

        <?php endif; ?>

      </div><!-- /tab-summary -->

    </div><!-- /section-wide -->
  </div><!-- /section -->


  <!-- ═══════════════════════════════════
       TESTIMONIALS SECTION
  ════════════════════════════════════ -->
  <section class="testimonials-section section" aria-label="Volunteer testimonials">
    <div class="section-narrow" style="text-align:center">
      <div class="section-tag">Real Stories</div>
      <h2 class="section-title">Voices from Our <em>Volunteers</em></h2>
      <p class="section-sub" style="margin:0 auto 36px">
        Hear what serving at Shammah Urban Church means to the people who do it every week.
      </p>
    </div>
    <div class="section-wide">
      <div class="testimonials-grid">
        <?php foreach ($testimonials as $t): ?>
        <blockquote class="testimonial-card">
          <!-- Quote mark decoration -->
          <div class="quote-mark" aria-hidden="true">"</div>
          <p class="testimonial-text"><?= htmlspecialchars($t['quote']) ?></p>
          <footer class="testimonial-author">
            <div class="t-avatar"
                 style="background:<?= $t['color'] ?>"
                 aria-hidden="true">
              <?= htmlspecialchars($t['initials']) ?>
            </div>
            <div>
              <div class="t-name"><?= htmlspecialchars($t['name']) ?></div>
              <div class="t-role"><?= htmlspecialchars($t['role']) ?></div>
            </div>
          </footer>
        </blockquote>
        <?php endforeach; ?>
      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════
       CTA SECTION
  ════════════════════════════════════ -->
  <section class="vol-cta section" aria-label="Get involved call to action">
    <div class="section-narrow" style="text-align:center">
      <div class="section-tag">Get Involved</div>
      <h2 class="section-title">Have Questions About <em>Volunteering?</em></h2>
      <p class="section-sub" style="margin:0 auto 28px">
        Not sure which role is right for you? Our volunteer coordinator would love to
        help you find your perfect place to serve.
      </p>
      <div class="cta-btns">
        <a href="contact.php" class="btn-gold">
          <i class="ti ti-message-circle" aria-hidden="true"></i>
          Contact the Volunteer Coordinator
        </a>
        <a href="events.php" class="btn-outline-navy">
          <i class="ti ti-calendar-event" aria-hidden="true"></i>
          Browse Upcoming Events
        </a>
      </div>
    </div>
  </section>

</main><!-- /main -->


<!-- ═══════════════════════════════════
     VOLUNTEER REGISTRATION MODAL
     Opened by JS when "Register" is clicked.
     Closed by clicking overlay or × button.
════════════════════════════════════ -->
<div class="modal-overlay" id="registerModal" role="dialog"
     aria-modal="true"
     aria-label="Volunteer registration form"
     aria-hidden="true">
  <div class="modal-box">

    <!-- Modal header -->
    <div class="modal-header">
      <div>
        <div class="modal-tag">Ministry Registration</div>
        <h3 class="modal-title" id="modalRoleTitle">Register for a Role</h3>
      </div>
      <button class="modal-close" id="closeModal"
              aria-label="Close registration form">
        <i class="ti ti-x" aria-hidden="true"></i>
      </button>
    </div>

    <!-- Modal body -->
    <div class="modal-body">

      <!-- Applicant name -->
      <div class="form-group">
        <label for="regName" class="form-label">
          <i class="ti ti-user" aria-hidden="true"></i>
          Full Name <span class="req-star" aria-hidden="true">*</span>
        </label>
        <input type="text"
               id="regName"
               class="form-input"
               placeholder="Your full name"
               value="<?= $user_name ?>"
               required
               aria-required="true">
        <span class="field-error" id="regNameErr" role="alert"></span>
      </div>

      <!-- Email -->
      <div class="form-group">
        <label for="regEmail" class="form-label">
          <i class="ti ti-mail" aria-hidden="true"></i>
          Email Address <span class="req-star" aria-hidden="true">*</span>
        </label>
        <input type="email"
               id="regEmail"
               class="form-input"
               placeholder="your@email.com"
               required
               aria-required="true">
        <span class="field-error" id="regEmailErr" role="alert"></span>
      </div>

      <!-- Phone -->
      <div class="form-group">
        <label for="regPhone" class="form-label">
          <i class="ti ti-phone" aria-hidden="true"></i>
          Phone Number
        </label>
        <input type="tel"
               id="regPhone"
               class="form-input"
               placeholder="e.g. 071 234 5678">
      </div>

      <!-- Availability checkboxes -->
      <div class="form-group">
        <span class="form-label">
          <i class="ti ti-calendar" aria-hidden="true"></i>
          Availability <span class="req-star" aria-hidden="true">*</span>
        </span>
        <div class="avail-grid" role="group" aria-label="Select your availability">
          <label class="avail-check">
            <input type="checkbox" value="sunday-am"> Sunday AM (9:00)
          </label>
          <label class="avail-check">
            <input type="checkbox" value="sunday-pm"> Sunday PM (11:00)
          </label>
          <label class="avail-check">
            <input type="checkbox" value="thursday"> Thursday Evening
          </label>
          <label class="avail-check">
            <input type="checkbox" value="saturday"> Saturday Mornings
          </label>
        </div>
        <span class="field-error" id="regAvailErr" role="alert"></span>
      </div>

      <!-- Special skills -->
      <div class="form-group">
        <label for="regSkills" class="form-label">
          <i class="ti ti-star" aria-hidden="true"></i>
          Relevant Skills or Experience <span class="form-optional">(optional)</span>
        </label>
        <textarea id="regSkills"
                  class="form-textarea"
                  rows="2"
                  placeholder="e.g. 3 years leading worship, trained in audio engineering..."></textarea>
      </div>

      <!-- Hidden: role being registered -->
      <input type="hidden" id="regRoleId">

    </div><!-- /modal-body -->

    <!-- Modal footer -->
    <div class="modal-footer">
      <button class="btn-modal-cancel" id="cancelModal">Cancel</button>
      <button class="btn-modal-submit" id="submitRegister" type="button">
        <i class="ti ti-send" aria-hidden="true"></i>
        Submit Registration
      </button>
    </div>

  </div><!-- /modal-box -->
</div><!-- /registerModal -->


<?php require_once 'includes/footer.php'; ?>

<?php
/* ═══════════════════════════════════════
   ministries.php  –  MINISTRIES PAGE

   HOW THIS FILE WORKS:
   1. session_start()   – reads the PHP session
   2. require_once      – loads shared data + includes
   3. AUTH STATE        – three distinct views:
                          public  → Join buttons say "Login to Join"
                          member  → can join/leave any ministry
                          admin   → same as member + sees admin badge
   4. $ministries_full  – 8 ministries with all fields
   5. $joined_ids       – set of MinistryIDs this member has joined
                          (simulated with session; swap for real DB later)
   6. SECTIONS:
      - Hero banner
      - KPI stats band (total members, most active, etc.)
      - Category filter pills + search bar
      - Ministry cards grid (all 8)
      - Cell Groups finder section
      - Scripture strip + Quick Links
   
   DB NOTE:
   In production replace the $ministries_full array with:
     SELECT m.*, COUNT(mm.MemberID) AS member_count
     FROM MINISTRY m
     LEFT JOIN MINISTRY_MEMBER mm ON m.MinistryID = mm.MinistryID
     WHERE mm.IsActive = 1 OR mm.MemberID IS NULL
     GROUP BY m.MinistryID
   Replace $joined_ids with:
     SELECT MinistryID FROM MINISTRY_MEMBER
     WHERE MemberID = :member_id AND IsActive = 1
═══════════════════════════════════════ */
session_start();
require_once 'includes/data.php';

/* ── AUTH STATE ─────────────────────────
   Three different states drive what the
   visitor sees on this page.
──────────────────────────────────────── */
$is_logged_in = isset($_SESSION['member_id']);
$user_name    = $is_logged_in ? htmlspecialchars($_SESSION['member_name'] ?? 'Member') : '';
$user_role    = $is_logged_in ? ($_SESSION['role'] ?? 'member') : '';
$member_id    = $is_logged_in ? ($_SESSION['member_id'] ?? 0) : 0;

/* ── CSRF TOKEN ─────────────────────────
   Security token for the Join/Leave fetch
   calls. Generated once per session.
──────────────────────────────────────── */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ── MINISTRY DATA ──────────────────────
   All 8 ministries. Each entry matches the
   columns your MINISTRY table will have:
   
   id           – MinistryID (PK)
   name         – ministry display name
   category     – filter key (must match pill data-cat values)
   icon         – Tabler icon name (no emoji)
   color        – accent hex (matches your brand palette)
   leader       – leader display name (from MEMBER.FirstName + LastName)
   schedule     – when the ministry meets
   desc_short   – one-line shown on the card
   desc_full    – shown in the expanded detail panel
   member_count – COUNT(*) from MINISTRY_MEMBER in real DB
   next_event   – next upcoming event title for this ministry
   next_date    – next event date
   testimonial  – a real member quote
──────────────────────────────────────── */
$ministries_full = [
  [
    'id'           => 1,
    'name'         => 'Worship Ministry',
    'category'     => 'worship',
    'icon'         => 'ti-music',
    'color'        => '#3D0C2F',
    'leader'       => 'David Khumalo',
    'schedule'     => 'Sundays 8:00 AM (rehearsal) · Wednesdays 6:00 PM',
    'desc_short'   => 'Choir, live band, and audio/visual team leading Sunday worship.',
    'desc_full'    => 'Our Worship Ministry leads the congregation in heartfelt praise every Sunday. We have a full choir, a live band, and a dedicated audio/visual team. Rehearsals are held every Wednesday evening and Sunday morning before service. We welcome singers, instrumentalists, and sound technicians of all skill levels. No audition required — only a willing heart.',
    'member_count' => 42,
    'next_event'   => 'Sunday Morning Service',
    'next_date'    => '15 Jun',
    'testimonial'  => '"Being in Worship Ministry has completely transformed my faith. I wake up excited for Sundays." — Zanele D.',
  ],
  [
    'id'           => 2,
    'name'         => 'Youth Ministry',
    'category'     => 'youth',
    'icon'         => 'ti-users',
    'color'        => '#639922',
    'leader'       => 'Elder Nkosi',
    'schedule'     => 'Fridays 6:00 PM · Youth Centre',
    'desc_short'   => 'Empowering ages 13–25 through weekly nights, camps, and mentorship.',
    'desc_full'    => 'Shammah Youth is a vibrant community for young people aged 13–25. Every Friday night we gather for worship, relevant teaching, group discussions, and games. We run an annual youth camp, leadership development workshops, and a peer mentorship programme. Our goal is to help young people build a genuine faith that lasts beyond their teenage years.',
    'member_count' => 87,
    'next_event'   => 'Youth Camp 2025',
    'next_date'    => '20 Jun',
    'testimonial'  => '"Youth Ministry gave me a community I actually belong to. These are my people." — Kagiso M.',
  ],
  [
    'id'           => 3,
    'name'         => 'Women of Purpose',
    'category'     => 'women',
    'icon'         => 'ti-heart',
    'color'        => '#C9A84C',
    'leader'       => 'Grace Mokoena',
    'schedule'     => 'Last Saturday of every month · 10:00 AM · Conference Room B',
    'desc_short'   => 'Monthly gatherings for women pursuing spiritual depth and community.',
    'desc_full'    => 'Women of Purpose is a safe, uplifting space for women of all ages and backgrounds. We meet monthly for themed sessions covering faith, personal growth, relationships, and practical life skills. Each gathering includes a message, breakaway discussion groups, and time for prayer. We also run a mentorship pairing programme connecting younger and older women in the congregation.',
    'member_count' => 65,
    'next_event'   => 'Women of Purpose Meeting',
    'next_date'    => '25 Jun',
    'testimonial'  => '"I joined Women of Purpose in a dark season. This community carried me through it." — Thandi O.',
  ],
  [
    'id'           => 4,
    'name'         => 'Community Outreach',
    'category'     => 'outreach',
    'icon'         => 'ti-world',
    'color'        => '#378ADD',
    'leader'       => 'Samuel Osei',
    'schedule'     => 'First Saturday of every month · 8:00 AM',
    'desc_short'   => 'Monthly food drives, school supplies, and neighbourhood visits.',
    'desc_full'    => 'Our Outreach Ministry takes the love of Christ beyond the church walls into Sunnyside and surrounding Pretoria communities. Every first Saturday we run food drives, distribute school supplies to underprivileged children, and visit elderly members in care homes. We partner with local NGOs and coordinate with the City of Tshwane on community development initiatives. No special skills needed — just a servant heart.',
    'member_count' => 53,
    'next_event'   => 'Community Prayer Night',
    'next_date'    => '18 Jun',
    'testimonial'  => '"Outreach showed me what real Christianity looks like — it gets its hands dirty." — Lerato B.',
  ],
  [
    'id'           => 5,
    'name'         => "Children's Ministry",
    'category'     => 'children',
    'icon'         => 'ti-school',
    'color'        => '#C0392B',
    'leader'       => 'Zanele Dlamini',
    'schedule'     => 'Sundays 9:00 AM & 11:00 AM · Children\'s Hall',
    'desc_short'   => 'Fun, safe, Bible-based programmes for children ages 4–12.',
    'desc_full'    => 'Our Children\'s Ministry runs parallel to Sunday services so parents can worship freely knowing their children are safe and engaged. We use age-appropriate Bible lessons, crafts, songs, and games to help children build a genuine foundation of faith. We also run a special quarterly Kids\' Fun Day and holiday programmes during school breaks. All volunteers are screened and trained in child safety.',
    'member_count' => 38,
    'next_event'   => "Children's Ministry Fun Day",
    'next_date'    => '28 Jun',
    'testimonial'  => '"My daughter literally runs to Sunday school. She loves it more than I do." — Parent, Thabo N.',
  ],
  [
    'id'           => 6,
    'name'         => 'Cell Groups',
    'category'     => 'community',
    'icon'         => 'ti-home-2',
    'color'        => '#5C1A47',
    'leader'       => 'Various Leaders',
    'schedule'     => 'Various days per group · Pretoria suburbs',
    'desc_short'   => '12 active cell groups meeting weekly across Pretoria.',
    'desc_full'    => 'Cell Groups are the heartbeat of Shammah — small communities of 8–15 people meeting weekly in homes across Pretoria. Each group shares a meal, studies a Bible passage, and prays for one another. Groups are organised by suburb so you can find one close to home. If there\'s no group in your area yet, we\'ll help you start one. Our 12 current groups cover Sunnyside, Arcadia, Hatfield, Centurion, and more.',
    'member_count' => 144,
    'next_event'   => 'Wednesday Bible Study',
    'next_date'    => '18 Jun',
    'testimonial'  => '"Cell Group is where church actually happens for me. Sunday is great, but Wednesday is where I\'m known." — Sipho K.',
  ],
  [
    'id'           => 7,
    'name'         => 'Hospitality & Ushers',
    'category'     => 'hospitality',
    'icon'         => 'ti-hand-finger',
    'color'        => '#0F6E56',
    'leader'       => 'Nomsa Sithole',
    'schedule'     => 'Sundays 8:30 AM · Main Entrance',
    'desc_short'   => 'Welcoming every visitor with warmth, dignity, and direction.',
    'desc_full'    => 'Our Hospitality and Ushering team is the first face visitors see at Shammah. We welcome, seat, and assist members and guests during all Sunday services and special events. Our team also manages the coffee station, coordinates the offering, and ensures accessibility for elderly and differently-abled members. Hospitality ministry training is provided every quarter, covering service excellence, conflict de-escalation, and first-aid basics.',
    'member_count' => 28,
    'next_event'   => 'Sunday Morning Service',
    'next_date'    => '15 Jun',
    'testimonial'  => '"I joined as an usher four years ago. Now I can\'t imagine Sunday without it — it sets the tone for my whole week." — Nomsa S.',
  ],
  [
    'id'           => 8,
    'name'         => 'Prayer Ministry',
    'category'     => 'prayer',
    'icon'         => 'ti-hand-stop',
    'color'        => '#533AB7',
    'leader'       => 'Pastor Sithole',
    'schedule'     => 'Wednesdays 5:30 AM (Prayer Watch) · Sundays after service',
    'desc_short'   => 'Intercession, prayer chains, and ministry to individuals after services.',
    'desc_full'    => 'The Prayer Ministry is the engine room of Shammah Urban Church. Our early-morning Wednesday Prayer Watch begins at 5:30 AM, interceding for the nation, the congregation, and individual requests submitted through our prayer wall. After every Sunday service, our prayer team remains at the front to minister personally to anyone who needs prayer. We also coordinate a 24-hour prayer chain for urgent needs — members sign up for one-hour slots.',
    'member_count' => 31,
    'next_event'   => 'Community Prayer Night',
    'next_date'    => '18 Jun',
    'testimonial'  => '"Prayer Ministry taught me that prayer is not a last resort. It is the first response." — Miriam P.',
  ],
];

/* ── CELL GROUPS ────────────────────────
   Shown in the "Find Your Cell Group"
   section when the user scrolls past the
   ministry cards.
   In production: SELECT * FROM SMALL_GROUP
   WHERE IsActive = 1 ORDER BY Suburb ASC
──────────────────────────────────────── */
$cell_groups = [
  ['name' => 'Sunnyside Growth Group',   'suburb' => 'Sunnyside',    'day' => 'Tuesdays',    'time' => '7:00 PM', 'leader' => 'Thabo N.',   'spots' => 3],
  ['name' => 'Arcadia Family Circle',    'suburb' => 'Arcadia',      'day' => 'Wednesdays',  'time' => '7:00 PM', 'leader' => 'Grace M.',   'spots' => 5],
  ['name' => 'Hatfield Young Adults',    'suburb' => 'Hatfield',     'day' => 'Thursdays',   'time' => '6:30 PM', 'leader' => 'Kagiso M.',  'spots' => 2],
  ['name' => 'Centurion Connect',        'suburb' => 'Centurion',    'day' => 'Tuesdays',    'time' => '7:30 PM', 'leader' => 'Samuel O.',  'spots' => 6],
  ['name' => 'Menlyn Life Group',        'suburb' => 'Menlyn',       'day' => 'Thursdays',   'time' => '7:00 PM', 'leader' => 'David K.',   'spots' => 4],
  ['name' => 'Pretoria North Outpost',   'suburb' => 'Pretoria North','day' => 'Wednesdays', 'time' => '6:00 PM', 'leader' => 'Nomsa S.',   'spots' => 0],
  ['name' => 'Moot Road Fellowship',     'suburb' => 'Moot',         'day' => 'Mondays',     'time' => '7:00 PM', 'leader' => 'Elder Nkosi','spots' => 7],
  ['name' => 'Brooklyn Bible Circle',    'suburb' => 'Brooklyn',     'day' => 'Fridays',     'time' => '6:30 PM', 'leader' => 'Zanele D.',  'spots' => 1],
];

/* ── JOINED MINISTRY IDs ────────────────
   Simulated with session data. In production
   replace with a DB query:
   SELECT MinistryID FROM MINISTRY_MEMBER
   WHERE MemberID = :id AND IsActive = 1
──────────────────────────────────────── */
$joined_ids = $is_logged_in
    ? ($_SESSION['joined_ministries'] ?? [])
    : [];

/* ── KPI STATS ──────────────────────────
   Computed from $ministries_full.
   In production these come from DB aggregates.
──────────────────────────────────────── */
$total_members     = array_sum(array_column($ministries_full, 'member_count'));
$total_ministries  = count($ministries_full);
$total_groups      = count($cell_groups);
$most_active       = array_reduce($ministries_full, function($carry, $m) {
    return (!$carry || $m['member_count'] > $carry['member_count']) ? $m : $carry;
});

/* ── PAGE META ──────────────────────────  */
$page_title = 'Ministries | Shammah Urban Church';
$page_desc  = 'Discover all 8 active ministries at Shammah Urban Church in Pretoria — join a ministry, find your cell group, and serve your community.';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- ══════════════ PAGE MAIN ══════════════ -->
<main id="main-content">

  <!-- ═══════════════════════════════════
       HERO BANNER
       Consistent gradient + diagonal pattern
       used on about.php and events.php.
  ════════════════════════════════════ -->
  <section class="min-hero" aria-label="Ministries at Shammah Urban Church">
    <div class="min-hero-pattern" aria-hidden="true"></div>
    <div class="min-hero-inner">
      <div class="hero-eyebrow">Find Your Place to Belong</div>
      <h1>
        Our <em>Ministries</em>
      </h1>
      <p class="min-hero-sub">
        <?= $total_ministries ?> active ministries serving our congregation and Pretoria community.
        There is a place for everyone — come and be part of something greater than yourself.
      </p>

      <!-- Hero KPI strip -->
      <div class="min-hero-stats" aria-label="Ministry statistics">
        <div class="hero-stat">
          <strong><?= $total_members ?>+</strong>
          <span>Ministry Members</span>
        </div>
        <div class="hero-stat-divider" aria-hidden="true"></div>
        <div class="hero-stat">
          <strong><?= $total_ministries ?></strong>
          <span>Active Ministries</span>
        </div>
        <div class="hero-stat-divider" aria-hidden="true"></div>
        <div class="hero-stat">
          <strong><?= $total_groups ?></strong>
          <span>Cell Groups</span>
        </div>
      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════
       KPI BAND
       Four summary cards between the hero
       and the ministry grid. Each pulls from
       computed PHP values above.
       In production: real DB aggregates.
  ════════════════════════════════════ -->
  <section class="min-kpi-band" aria-label="Ministry summary statistics">
    <div class="min-kpi-grid">

      <!-- KPI 1: Total active members across all ministries -->
      <div class="min-kpi-card">
        <i class="ti ti-users-group" aria-hidden="true"></i>
        <div class="min-kpi-body">
          <span class="min-kpi-val"><?= number_format($total_members) ?></span>
          <span class="min-kpi-lbl">Members Serving</span>
        </div>
      </div>

      <!-- KPI 2: Most active ministry by member count -->
      <div class="min-kpi-card">
        <i class="ti ti-trophy" aria-hidden="true"></i>
        <div class="min-kpi-body">
          <span class="min-kpi-val"><?= htmlspecialchars($most_active['name']) ?></span>
          <span class="min-kpi-lbl">Most Active Ministry</span>
        </div>
      </div>

      <!-- KPI 3: Cell groups running -->
      <div class="min-kpi-card">
        <i class="ti ti-map-pin" aria-hidden="true"></i>
        <div class="min-kpi-body">
          <span class="min-kpi-val"><?= $total_groups ?> locations</span>
          <span class="min-kpi-lbl">Cell Groups in Pretoria</span>
        </div>
      </div>

      <!-- KPI 4: How many the logged-in member has joined -->
      <div class="min-kpi-card">
        <i class="ti ti-heart" aria-hidden="true"></i>
        <div class="min-kpi-body">
          <?php if ($is_logged_in): ?>
            <span class="min-kpi-val"><?= count($joined_ids) ?></span>
            <span class="min-kpi-lbl">Your Ministries Joined</span>
          <?php else: ?>
            <span class="min-kpi-val min-kpi-val--small">
              <a href="login.php" style="color:var(--gold);text-decoration:underline;font-size:14px;">Login</a> to track your ministries
            </span>
            <span class="min-kpi-lbl">Your Ministries</span>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </section>


  <!-- ═══════════════════════════════════
       FILTER + SEARCH BAR
       Same sticky pattern as events.php.
       JS reads data-cat on pills and
       data-category on each ministry card.
  ════════════════════════════════════ -->
  <div class="min-controls" role="search" aria-label="Filter and search ministries">
    <div class="min-controls-inner">

      <!-- Category filter pills -->
      <div class="filter-pills" role="list" aria-label="Filter by category">
        <button class="filter-pill active" data-cat="all"         role="listitem" aria-pressed="true">All Ministries</button>
        <button class="filter-pill"        data-cat="worship"     role="listitem" aria-pressed="false">
          <i class="ti ti-music" aria-hidden="true"></i> Worship
        </button>
        <button class="filter-pill"        data-cat="youth"       role="listitem" aria-pressed="false">
          <i class="ti ti-users" aria-hidden="true"></i> Youth
        </button>
        <button class="filter-pill"        data-cat="women"       role="listitem" aria-pressed="false">
          <i class="ti ti-heart" aria-hidden="true"></i> Women
        </button>
        <button class="filter-pill"        data-cat="children"    role="listitem" aria-pressed="false">
          <i class="ti ti-school" aria-hidden="true"></i> Children
        </button>
        <button class="filter-pill"        data-cat="outreach"    role="listitem" aria-pressed="false">
          <i class="ti ti-world" aria-hidden="true"></i> Outreach
        </button>
        <button class="filter-pill"        data-cat="community"   role="listitem" aria-pressed="false">
          <i class="ti ti-home-2" aria-hidden="true"></i> Cell Groups
        </button>
        <button class="filter-pill"        data-cat="hospitality" role="listitem" aria-pressed="false">
          <i class="ti ti-hand-finger" aria-hidden="true"></i> Hospitality
        </button>
        <button class="filter-pill"        data-cat="prayer"      role="listitem" aria-pressed="false">
          <i class="ti ti-hand-stop" aria-hidden="true"></i> Prayer
        </button>
      </div>

      <!-- Live search input -->
      <div class="events-search">
        <i class="ti ti-search" aria-hidden="true"></i>
        <input type="search"
               id="ministrySearch"
               placeholder="Search ministries…"
               aria-label="Search ministries by name or leader"
               autocomplete="off">
      </div>

    </div>
  </div>


  <!-- ═══════════════════════════════════
       MINISTRY CARDS GRID
       All 8 ministries rendered from the
       $ministries_full array above.
       
       CARD ANATOMY:
       - Coloured accent bar (top)
       - Icon + ministry name header
       - Description + schedule row
       - Leader row + member count
       - Next event strip
       - Accordion detail panel (hidden by default)
       - Join / Joined / Login-to-Join button
  ════════════════════════════════════ -->
  <section class="min-section" id="ministries-grid">
    <div class="section-wide">

      <div class="section-label-row">
        <div class="section-tag">Active Ministries</div>
        <h2 class="section-title">Find Your <em>Place to Serve</em></h2>
        <p class="section-sub">Each ministry has a role for you — whether you lead, support, or simply show up.</p>
      </div>

      <div class="min-grid" role="list" aria-label="Ministry cards">

        <?php foreach ($ministries_full as $m):
          // Has this logged-in member already joined this ministry?
          $is_joined = in_array($m['id'], $joined_ids);

          // Spots available: spots = max_capacity - member_count
          // We use a simple heuristic here; replace with DB column in production
          $is_almost_full = $m['member_count'] >= 70;

          // Safe strings for data-* attributes
          $safe_name   = htmlspecialchars($m['name'],      ENT_QUOTES);
          $safe_leader = htmlspecialchars($m['leader'],    ENT_QUOTES);
          $safe_desc   = htmlspecialchars($m['desc_full'], ENT_QUOTES);
          $safe_sched  = htmlspecialchars($m['schedule'],  ENT_QUOTES);
          $safe_test   = htmlspecialchars($m['testimonial'], ENT_QUOTES);
        ?>

        <article class="min-card <?= $is_joined ? 'min-card--joined' : '' ?>"
                 role="listitem"
                 aria-label="<?= $safe_name ?> ministry"
                 data-category="<?= htmlspecialchars($m['category']) ?>"
                 data-name="<?= $safe_name ?>"
                 data-leader="<?= $safe_leader ?>">

          <!-- Top accent stripe (colour from $m['color']) -->
          <div class="min-card-stripe" style="background:<?= $m['color'] ?>;" aria-hidden="true"></div>

          <!-- Card header: icon + name -->
          <div class="min-card-header">
            <div class="min-card-icon" style="background:<?= $m['color'] ?>1a; color:<?= $m['color'] ?>;"
                 aria-hidden="true">
              <i class="ti <?= $m['icon'] ?>"></i>
            </div>
            <div class="min-card-title-group">
              <h3><?= htmlspecialchars($m['name']) ?></h3>
              <?php if ($is_joined): ?>
                <!-- Badge shown when the member has joined this ministry -->
                <span class="min-joined-badge">
                  <i class="ti ti-check" aria-hidden="true"></i> Joined
                </span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Short description -->
          <p class="min-card-desc"><?= htmlspecialchars($m['desc_short']) ?></p>

          <!-- Schedule row -->
          <div class="min-card-meta">
            <i class="ti ti-clock" aria-hidden="true"></i>
            <span><?= htmlspecialchars($m['schedule']) ?></span>
          </div>

          <!-- Leader + member count row -->
          <div class="min-card-meta-row">
            <div class="min-card-meta">
              <i class="ti ti-user" aria-hidden="true"></i>
              <span>Leader: <strong><?= htmlspecialchars($m['leader']) ?></strong></span>
            </div>
            <div class="min-member-count" aria-label="<?= $m['member_count'] ?> members">
              <i class="ti ti-users" aria-hidden="true"></i>
              <strong><?= $m['member_count'] ?></strong>
              <span>members</span>
            </div>
          </div>

          <!-- Next event strip -->
          <div class="min-next-event">
            <i class="ti ti-calendar-event" aria-hidden="true"></i>
            <span>Next: <strong><?= htmlspecialchars($m['next_event']) ?></strong> · <?= $m['next_date'] ?></span>
            <a href="events.php" class="min-rsvp-link" aria-label="RSVP for <?= $safe_name ?> next event">RSVP</a>
          </div>

          <!-- EXPANDABLE DETAIL PANEL
               Toggled by the "Learn More" button below.
               JS adds/removes the class 'expanded' on .min-card-detail.
               The CSS uses max-height transition to animate the reveal.
          -->
          <div class="min-card-detail" id="detail-<?= $m['id'] ?>" aria-hidden="true">
            <div class="min-card-detail-inner">

              <!-- Full description -->
              <p class="min-detail-desc"><?= htmlspecialchars($m['desc_full']) ?></p>

              <!-- Member testimonial quote -->
              <blockquote class="min-testimonial">
                <i class="ti ti-quote" aria-hidden="true"></i>
                <?= htmlspecialchars($m['testimonial']) ?>
              </blockquote>

            </div>
          </div>

          <!-- Action row: Learn More + Join/Leave -->
          <div class="min-card-actions">

            <!-- Learn More toggle button -->
            <button class="min-learn-btn js-toggle-detail"
                    data-target="detail-<?= $m['id'] ?>"
                    aria-expanded="false"
                    aria-controls="detail-<?= $m['id'] ?>"
                    aria-label="Learn more about <?= $safe_name ?>">
              Learn More
              <i class="ti ti-chevron-down" aria-hidden="true"></i>
            </button>

            <!-- Join / Leave / Login-to-Join button
                 THREE STATES:
                 1. Not logged in     → "Login to Join"  (link to login.php)
                 2. Logged in, not joined → "Join Ministry" (fetch POST)
                 3. Logged in, joined → "Joined ✓"       (fetch DELETE)
            -->
            <?php if (!$is_logged_in): ?>
              <!-- STATE 1: Public visitor -->
              <a href="login.php?redirect=ministries.php"
                 class="min-join-btn min-join-btn--login"
                 aria-label="Login to join <?= $safe_name ?>">
                <i class="ti ti-login" aria-hidden="true"></i>
                Login to Join
              </a>

            <?php elseif ($is_joined): ?>
              <!-- STATE 3: Member already joined -->
              <button class="min-join-btn min-join-btn--joined js-leave-ministry"
                      data-id="<?= $m['id'] ?>"
                      data-name="<?= $safe_name ?>"
                      aria-label="Leave <?= $safe_name ?>">
                <i class="ti ti-check" aria-hidden="true"></i>
                Joined
              </button>

            <?php else: ?>
              <!-- STATE 2: Member not yet joined -->
              <button class="min-join-btn js-join-ministry"
                      data-id="<?= $m['id'] ?>"
                      data-name="<?= $safe_name ?>"
                      aria-label="Join <?= $safe_name ?>">
                <i class="ti ti-plus" aria-hidden="true"></i>
                Join Ministry
              </button>

            <?php endif; ?>

          </div><!-- /.min-card-actions -->
        </article>

        <?php endforeach; ?>

      </div><!-- /.min-grid -->

      <!-- Empty state (shown by JS when search returns no results) -->
      <div id="ministriesEmpty" class="min-empty" style="display:none;" aria-live="polite">
        <i class="ti ti-search-off" aria-hidden="true"></i>
        <p>No ministries match your search. Try a different category or keyword.</p>
      </div>

    </div>
  </section>


  <!-- ═══════════════════════════════════
       CELL GROUPS FINDER
       Only shown when the visitor clicks
       "Cell Groups" filter pill OR scrolls
       to this section directly via the
       Cell Groups ministry card.
       
       In production: SELECT * FROM SMALL_GROUP
       JOIN MEMBER ON SMALL_GROUP.LeaderMemberID = MEMBER.MemberID
       WHERE SMALL_GROUP.IsActive = 1
       ORDER BY Suburb ASC
  ════════════════════════════════════ -->
  <section class="min-section min-section--rose" id="cell-groups" aria-label="Cell groups finder">
    <div class="section-wide">

      <div class="section-label-row">
        <div class="section-tag">Small Groups</div>
        <h2 class="section-title">Find Your <em>Cell Group</em></h2>
        <p class="section-sub">
          <?= count($cell_groups) ?> groups meeting weekly across Pretoria.
          Find one near you — or start a new one with our support.
        </p>
      </div>

      <!-- Cell group table -->
      <div class="min-groups-table-wrap">
        <table class="min-groups-table" aria-label="Cell groups in Pretoria">
          <thead>
            <tr>
              <th scope="col">Group Name</th>
              <th scope="col">Suburb</th>
              <th scope="col">Meets</th>
              <th scope="col">Time</th>
              <th scope="col">Leader</th>
              <th scope="col">Spots</th>
              <th scope="col">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($cell_groups as $g):
              $full = $g['spots'] <= 0;
            ?>
            <tr>
              <td class="group-name"><?= htmlspecialchars($g['name']) ?></td>
              <td>
                <span class="group-suburb">
                  <i class="ti ti-map-pin" aria-hidden="true"></i>
                  <?= htmlspecialchars($g['suburb']) ?>
                </span>
              </td>
              <td><?= htmlspecialchars($g['day']) ?></td>
              <td><?= htmlspecialchars($g['time']) ?></td>
              <td><?= htmlspecialchars($g['leader']) ?></td>
              <td>
                <?php if ($full): ?>
                  <span class="spots-full">Full</span>
                <?php elseif ($g['spots'] <= 2): ?>
                  <span class="spots-urgent"><?= $g['spots'] ?> spot<?= $g['spots'] === 1 ? '' : 's' ?></span>
                <?php else: ?>
                  <span class="spots-ok"><?= $g['spots'] ?> spots</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($full): ?>
                  <button class="min-group-btn min-group-btn--full" disabled>Full</button>
                <?php elseif (!$is_logged_in): ?>
                  <a href="login.php?redirect=ministries.php#cell-groups"
                     class="min-group-btn">Join</a>
                <?php else: ?>
                  <button class="min-group-btn js-join-group"
                          data-group="<?= htmlspecialchars($g['name'], ENT_QUOTES) ?>"
                          aria-label="Join <?= htmlspecialchars($g['name'], ENT_QUOTES) ?>">
                    Join
                  </button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- CTA for starting a new group -->
      <div class="min-groups-cta">
        <div class="min-groups-cta-text">
          <i class="ti ti-plus" aria-hidden="true"></i>
          <div>
            <strong>No group near you?</strong>
            <span>We'll help you start one. Contact our Cell Groups coordinator.</span>
          </div>
        </div>
        <a href="contact.php" class="btn-gold">
          <i class="ti ti-mail" aria-hidden="true"></i> Get in Touch
        </a>
      </div>

    </div>
  </section>


  <!-- ═══════════════════════════════════
       SCRIPTURE SECTION
       Consistent on all pages.
  ════════════════════════════════════ -->
  <section class="verse-section" aria-label="Scripture of the day">
    <div class="verse-inner">
      <div class="verse-day-tag">✦ Daily Scripture</div>
      <p class="verse-text"><?= htmlspecialchars($verse['text']) ?></p>
      <div class="verse-ref">— <?= htmlspecialchars($verse['ref']) ?></div>
    </div>
  </section>


  <!-- ═══════════════════════════════════
       QUICK LINKS STRIP
       Same component as index.php and events.php.
  ════════════════════════════════════ -->
  <nav class="quicklinks" role="navigation" aria-label="Quick actions">
    <div class="quicklinks-grid">
      <a class="ql-item" href="donate.php" aria-label="Give online">
        <div class="ql-icon" aria-hidden="true">💛</div>
        <div class="ql-text">
          <strong>Give Online</strong>
          <span>Tithes, offerings &amp; building fund</span>
        </div>
      </a>
      <a class="ql-item" href="prayer.php" aria-label="Submit a prayer request">
        <div class="ql-icon" aria-hidden="true">🙏</div>
        <div class="ql-text">
          <strong>Prayer Request</strong>
          <span>Submit &amp; join the prayer wall</span>
        </div>
      </a>
      <a class="ql-item" href="volunteers.php" aria-label="Volunteer in a ministry role">
        <div class="ql-icon" aria-hidden="true">🤝</div>
        <div class="ql-text">
          <strong>Volunteer</strong>
          <span>Serve in a specific ministry role</span>
        </div>
      </a>
      <a class="ql-item" href="contact.php" aria-label="Contact the church">
        <div class="ql-icon" aria-hidden="true">📍</div>
        <div class="ql-text">
          <strong>Plan Your Visit</strong>
          <span>Directions &amp; contact details</span>
        </div>
      </a>
    </div>
  </nav>

</main><!-- /#main-content -->


<!-- ═══════════════════════════════════════
     JAVASCRIPT BRIDGE: PHP → JS
     IS_LOGGED_IN and CSRF_TOKEN are needed
     by ministries.js for the join/leave fetch calls.
════════════════════════════════════════ -->
<script>
  const IS_LOGGED_IN = <?= json_encode($is_logged_in) ?>;
  const CSRF_TOKEN   = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
</script>

<?php require_once 'includes/footer.php'; ?>

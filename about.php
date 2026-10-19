<?php
/* ═══════════════════════════════════════
   about.php  –  ABOUT US PAGE

   HOW THIS FILE WORKS:
   1. session_start()   – reads the PHP session
                          (checks if user is logged in)
   2. require_once      – pulls in shared files:
                          data.php    → $stats, $ticker_str etc.
                          header.php  → <head>, CSS links
                          nav.php     → ticker + navigation bar
   3. HTML sections     – About Us page body
   4. footer.php        – footer, floating buttons, JS files
═══════════════════════════════════════ */
session_start();

/* ── LOAD SHARED DATA ───────────────────
   data.php gives us $stats, $ticker_str,
   $verse and $announcements — the same
   data used on the homepage.
──────────────────────────────────────── */
require_once 'includes/data.php';

/* ── AUTH STATE ─────────────────────────
   Same pattern as index.php — check the
   session so nav.php can show Login or
   the user's name.
──────────────────────────────────────── */
$is_logged_in = isset($_SESSION['member_id']);
$user_name    = $is_logged_in ? htmlspecialchars($_SESSION['member_name'] ?? 'Member') : '';
$user_role    = $is_logged_in ? ($_SESSION['role'] ?? 'member') : '';

/* ── PAGE META ──────────────────────────
   Used by header.php for <title> and
   <meta description>.
──────────────────────────────────────── */
$page_title = 'About Us | Shammah Urban Church';
$page_desc  = 'Learn about Shammah Urban Church — our story, mission, vision, leadership team and core values since 2009.';

/* ── LEADERSHIP DATA ────────────────────
   In a real system this would be:
   SELECT * FROM MEMBER WHERE role IN ('Pastor','Elder')
   For now we use a PHP array to simulate the DB.
──────────────────────────────────────── */
$leaders = [
  [
    'initials' => 'PS',
    'name'     => 'Pastor Sithole',
    'title'    => 'Senior Pastor',
    'desc'     => 'Leading Shammah Urban Church since founding in 2009 with a vision for transformational ministry in Pretoria.',
    'color'    => '#3D0C2F',
  ],
  [
    'initials' => 'GM',
    'name'     => 'Grace Mokoena',
    'title'    => 'Women\'s Ministry Lead',
    'desc'     => 'Heads the Women of Purpose ministry and coordinates all women\'s programmes and outreach events.',
    'color'    => '#C9A84C',
  ],
  [
    'initials' => 'TN',
    'name'     => 'Elder Nkosi',
    'title'    => 'Youth & Outreach Elder',
    'desc'     => 'Passionate about youth development and leads all community outreach programmes in Sunnyside.',
    'color'    => '#5C1A47',
  ],
];

/* ── CORE VALUES DATA ───────────────────
   Four value cards shown as a grid.
   icon  – emoji used instead of image placeholder
   title – value name
   desc  – short tagline
──────────────────────────────────────── */
$values = [
  ['icon' => '✝️',  'title' => 'Faith',     'desc' => 'Rooted in Scripture and prayer'],
  ['icon' => '🤝',  'title' => 'Community', 'desc' => 'Building genuine family bonds'],
  ['icon' => '🙌',  'title' => 'Service',   'desc' => 'Hands-on outreach in Pretoria'],
  ['icon' => '🌱',  'title' => 'Growth',    'desc' => 'Discipleship & spiritual depth'],
];

/* ── HISTORY TIMELINE DATA ──────────────
   Each milestone: year, heading, detail.
   In a real system: SELECT * FROM HISTORY ORDER BY year ASC
──────────────────────────────────────── */
$timeline = [
  [
    'year'  => '2009',
    'title' => 'Church Founded',
    'desc'  => 'Pastor Sithole launches Shammah Urban Church from a small prayer group of 12 families in Sunnyside, Pretoria.',
    'dot'   => '#C9A84C',
  ],
  [
    'year'  => '2013',
    'title' => 'New Sanctuary Opened',
    'desc'  => 'Congregation moves into the current 500-seat sanctuary at 1103 Eland Street, Mooikloof as membership surpasses 200.',
    'dot'   => '#C9A84C',
  ],
  [
    'year'  => '2018',
    'title' => 'Youth & Outreach Ministry Launched',
    'desc'  => 'Elder Nkosi establishes the Youth Centre and weekly community outreach programme in Sunnyside Park.',
    'dot'   => '#C9A84C',
  ],
  [
    'year'  => '2020',
    'title' => 'Online Ministry Begins',
    'desc'  => 'During lockdown, SUC launches live-streamed services and digital giving — reaching over 1 000 viewers online.',
    'dot'   => '#C9A84C',
  ],
  [
    'year'  => '2025',
    'title' => 'Digital Management System (CMES) Launched',
    'desc'  => 'A full member portal, donation system, and event management platform goes live — serving 500+ members.',
    'dot'   => '#3D0C2F',
  ],
];

/* ── HTML HEAD + OPENING BODY ───────── */
require_once 'includes/header.php';

/* ── TICKER + NAVIGATION ─────────────── */
require_once 'includes/nav.php';
?>

<!-- ══════════════ PAGE MAIN ══════════════ -->
<main id="main-content">

  <!-- ═══════════════════════════════════
       HERO / PAGE BANNER
       Matches the homepage hero style —
       same navy gradient, same font sizes.
  ════════════════════════════════════ -->
  <section class="about-hero" aria-label="About Shammah Urban Church">
    <div class="about-hero-bg"  aria-hidden="true"></div>
    <div class="about-hero-inner">
      <div class="hero-eyebrow">Our Story</div>
      <h1>About <em>Shammah Urban Church</em></h1>
      <p class="about-hero-sub">
        "Shammah" means "The Lord is There" — a name that defines our purpose and
        presence in Pretoria since 2009.
      </p>
      <div class="hero-btns">
        <a href="contact.php" class="btn-gold">
          <i class="ti ti-map-pin" aria-hidden="true"></i> Plan Your Visit
        </a>
        <a href="#leadership" class="btn-ghost">
          <i class="ti ti-users" aria-hidden="true"></i> Meet Our Team
        </a>
      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════
       STATS BAND
       Reuses the same animated counter
       from the homepage — same classes,
       same data-target attributes.
       JS in main.js drives the count-up.
  ════════════════════════════════════ -->
  <section class="stats-band" aria-label="Church statistics">
    <div class="stats-grid">
      <?php
      // Same four stats as index.php — pulled from data.php
      $stat_items = [
        ['val' => $stats['members'],    'suf' => '+', 'lbl' => 'Active Members'],
        ['val' => $stats['ministries'], 'suf' => '',  'lbl' => 'Ministries'],
        ['val' => $stats['groups'],     'suf' => '',  'lbl' => 'Cell Groups'],
        ['val' => $stats['years'],      'suf' => '',  'lbl' => 'Years of Ministry'],
      ];
      foreach ($stat_items as $s): ?>
      <div class="stat-item">
        <span class="stat-num"
              data-target="<?= $s['val'] ?>"
              data-suffix="<?= $s['suf'] ?>">
          0<?= $s['suf'] ?>
        </span>
        <div class="stat-lbl"><?= $s['lbl'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>


  <!-- ═══════════════════════════════════
       MISSION & VISION
  ════════════════════════════════════ -->
  <section class="section about-mv-section" aria-label="Mission and Vision">
    <div class="section-wide about-mv-grid">

      <!-- Left: image placeholder + address -->
      <div class="about-img-block" aria-label="Church building photo">
        <div class="about-img-placeholder">
          <div class="about-img-inner">
            <i class="ti ti-building-church" aria-hidden="true"
               style="font-size:48px;color:var(--gold);margin-bottom:12px"></i>
            <p style="color:var(--gold);font-size:12px;letter-spacing:1px;
                      text-transform:uppercase;font-weight:600">Church Building Photo</p>
            <p style="color:rgba(255,255,255,.5);font-size:11px;margin-top:6px">
              1103 Eland St, Mooikloof
            </p>
          </div>
        </div>
        <!--
          In production, replace the placeholder above with:
          <img src="assets/images/church-building.jpg"
               alt="Shammah Urban Church building at 1103 Eland Street, Mooikloof"
               loading="lazy">
          WHERE photo = SELECT photo FROM SETTINGS WHERE key='building_photo'
        -->
      </div>

      <!-- Right: mission text -->
      <div class="about-mission-text">
        <div class="section-tag">Our Mission</div>
        <h2 class="section-title">Why We <em>Exist</em></h2>
        <p style="color:var(--text-2);line-height:1.8;margin-bottom:20px">
          To reach the lost, equip the saints, and transform communities through the
          power of the Gospel. We are a multigenerational, multiracial congregation
          committed to authentic worship and relevant ministry in Pretoria.
        </p>
        <blockquote class="about-quote">
          "Experience the Touch of His Presence" — our founding vision since 2009
        </blockquote>
      </div>
    </div>

    <!-- Vision banner below -->
    <div class="section-wide" style="margin-top:24px">
      <div class="about-vision-banner">
        <div class="about-vision-tag">Our Vision</div>
        <p>
          A Spirit-filled urban church transforming Pretoria one family at a time —
          through discipleship, outreach, and the uncompromising truth of God's Word.
        </p>
      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════
       CORE VALUES
       Four cards — each with an emoji icon,
       title and tagline.
  ════════════════════════════════════ -->
  <section class="section" aria-label="Our Core Values"
           style="background:var(--rose);padding-top:56px;padding-bottom:56px">
    <div class="section-wide">
      <div class="section-tag" style="justify-content:center">What We Stand For</div>
      <h2 class="section-title" style="text-align:center">
        Our Core <em>Values</em>
      </h2>
      <p class="section-sub" style="text-align:center;margin:0 auto 40px">
        Four pillars that guide everything we do as a church community.
      </p>

      <div class="about-values-grid" role="list">
        <?php foreach ($values as $v): ?>
        <div class="about-value-card" role="listitem">
          <div class="about-value-icon" aria-hidden="true"><?= $v['icon'] ?></div>
          <h3><?= htmlspecialchars($v['title']) ?></h3>
          <p><?= htmlspecialchars($v['desc']) ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════
       HISTORY TIMELINE
       Each milestone rendered as a
       vertical timeline entry.
  ════════════════════════════════════ -->
  <section class="section" id="history" aria-label="Our History">
    <div class="section-wide">
      <div class="section-tag">Since 2009</div>
      <h2 class="section-title">Our <em>History</em></h2>
      <p class="section-sub">From a small prayer group to a congregation of 500+.</p>

      <div class="about-timeline" role="list">
        <?php foreach ($timeline as $i => $t): ?>
        <div class="about-tl-item <?= $i % 2 === 0 ? 'tl-left' : 'tl-right' ?>"
             role="listitem">
          <div class="about-tl-year"><?= htmlspecialchars($t['year']) ?></div>
          <div class="about-tl-dot"
               style="background:<?= htmlspecialchars($t['dot']) ?>"
               aria-hidden="true"></div>
          <div class="about-tl-content">
            <h4><?= htmlspecialchars($t['title']) ?></h4>
            <p><?= htmlspecialchars($t['desc']) ?></p>
          </div>
        </div>
        <?php endforeach; ?>
        <!-- Timeline vertical line (decorative) -->
        <div class="about-tl-line" aria-hidden="true"></div>
      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════
       LEADERSHIP TEAM
       Cards pulled from $leaders array.
       In production: SELECT * FROM MEMBER
         WHERE role IN ('Pastor','Elder')
  ════════════════════════════════════ -->
  <section class="section" id="leadership" aria-label="Meet Our Leadership"
           style="background:var(--rose);padding-top:56px;padding-bottom:56px">
    <div class="section-wide">
      <div class="section-tag" style="justify-content:center">The People Behind the Vision</div>
      <h2 class="section-title" style="text-align:center">
        Meet Our <em>Leadership</em>
      </h2>
      <p class="section-sub" style="text-align:center;margin:0 auto 40px">
        Passionate leaders committed to serving God and the Pretoria community.
      </p>

      <div class="about-leaders-grid" role="list">
        <?php foreach ($leaders as $l): ?>
        <article class="about-leader-card" role="listitem"
                 aria-label="<?= htmlspecialchars($l['name']) ?>, <?= htmlspecialchars($l['title']) ?>">
          <!--
            Avatar circle with initials.
            In production replace with:
            <img src="assets/images/leaders/<?= strtolower(str_replace(' ','_',$l['name'])) ?>.jpg"
                 alt="Photo of <?= htmlspecialchars($l['name']) ?>">
            WHERE photo = SELECT photo FROM MEMBER WHERE name='...'
          -->
          <div class="about-leader-avatar"
               style="background:<?= htmlspecialchars($l['color']) ?>"
               aria-hidden="true">
            <?= htmlspecialchars($l['initials']) ?>
          </div>
          <h3><?= htmlspecialchars($l['name']) ?></h3>
          <div class="about-leader-title"><?= htmlspecialchars($l['title']) ?></div>
          <p><?= htmlspecialchars($l['desc']) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════
       SERVICE TIMES
       Same data as homepage service section
       but styled as a darker band to
       add visual variety.
  ════════════════════════════════════ -->
  <section class="section about-services-section" aria-label="Service Times">
    <div class="section-wide">
      <div class="section-tag" style="justify-content:center">Join Us</div>
      <h2 class="section-title" style="text-align:center;color:#fff">
        Service <em>Times</em>
      </h2>

      <div class="about-services-grid">
        <div class="about-service-card">
          <div class="about-svc-icon" aria-hidden="true">🌅</div>
          <h3>Sunday Services</h3>
          <div class="about-svc-time">9:00 AM &amp; 11:00 AM</div>
          <p>Main Sanctuary</p>
        </div>
        <div class="about-service-card">
          <div class="about-svc-icon" aria-hidden="true">📖</div>
          <h3>Wednesday Bible Study</h3>
          <div class="about-svc-time">6:30 PM</div>
          <p>Fellowship Hall</p>
        </div>
        <div class="about-service-card">
          <div class="about-svc-icon" aria-hidden="true">🌙</div>
          <h3>Youth Friday</h3>
          <div class="about-svc-time">6:00 PM</div>
          <p>Youth Centre</p>
        </div>
      </div>

      <!-- What to expect grid -->
      <div class="about-expect-grid" style="margin-top:32px">
        <h3 style="color:#fff;font-size:18px;font-weight:700;
                   margin-bottom:20px;grid-column:1/-1">
          What to Expect on Sunday
        </h3>
        <?php
        // What-to-expect items — could also come from a DB SETTINGS table
        $expect = [
          ['icon' => '⏱️', 'label' => 'Duration',     'detail' => 'Approx. 90 minutes'],
          ['icon' => '👗', 'label' => 'Dress Code',   'detail' => 'Smart casual welcome'],
          ['icon' => '🅿️', 'label' => 'Parking',      'detail' => 'Free on Eland St'],
          ['icon' => '👶', 'label' => 'Children',     'detail' => 'Kids church ages 3–12'],
          ['icon' => '📺', 'label' => 'Online',       'detail' => 'Streamed on YouTube'],
          ['icon' => '♿', 'label' => 'Accessibility', 'detail' => 'Wheelchair accessible'],
        ];
        foreach ($expect as $e): ?>
        <div class="about-expect-item">
          <span class="about-expect-icon" aria-hidden="true"><?= $e['icon'] ?></span>
          <div>
            <strong><?= htmlspecialchars($e['label']) ?></strong>
            <span><?= htmlspecialchars($e['detail']) ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════
       SCRIPTURE / VERSE BAND
       Same verse component as homepage —
       reinforces brand consistency.
  ════════════════════════════════════ -->
  <section class="verse-section" aria-label="Scripture of the day">
    <div class="verse-inner">
      <div class="verse-day-tag">✦ Daily Scripture</div>
      <p class="verse-text"><?= htmlspecialchars($verse['text']) ?></p>
      <div class="verse-ref">— <?= htmlspecialchars($verse['ref']) ?></div>
    </div>
  </section>


  <!-- ═══════════════════════════════════
       CTA — WE'D LOVE TO MEET YOU
       Conversion section to turn page
       visitors into Sunday attendees.
  ════════════════════════════════════ -->
  <section class="section about-cta-section" aria-label="New here? Join us">
    <div class="about-cta-inner">
      <div class="about-cta-eyebrow">New Here?</div>
      <h2>We'd Love to <em>Meet You</em></h2>
      <p>
        Join us this Sunday — your first visit is completely pressure-free.
        No membership required, just bring yourself.
      </p>
      <div class="hero-btns" style="justify-content:center">
        <a href="register.php" class="btn-gold">
          <i class="ti ti-user-plus" aria-hidden="true"></i> Register Online
        </a>
        <a href="contact.php" class="btn-ghost">
          <i class="ti ti-mail" aria-hidden="true"></i> Contact Us
        </a>
      </div>
    </div>
  </section>

</main><!-- /#main-content -->


<!-- ═══════════════════════════════════════
     JAVASCRIPT BRIDGE: PHP → JS
     Same pattern as index.php — pass the
     login state and CSRF token to JS.
════════════════════════════════════════ -->
<script>
  const IS_LOGGED_IN = <?= json_encode($is_logged_in) ?>;
  const CSRF_TOKEN   = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
</script>

<?php
/* ── FOOTER + CLOSING TAGS ──────────────
   Identical footer to index.php — same
   quick links, service times, social icons.
──────────────────────────────────────── */
require_once 'includes/footer.php';
?>

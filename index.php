<?php
/* ═══════════════════════════════════════
   index.php  –  HOME PAGE
   
   HOW THIS FILE WORKS:
   1. session_start()   – enables PHP sessions
                          (needed to know if user is logged in)
   2. require_once      – pulls in our separated files:
                          data.php    → all the $events, $stats etc
                          header.php  → <head>, CSS links
                          nav.php     → ticker + navigation
   3. HTML sections     – the page body (hero, stats, etc.)
   4. footer.php        – footer, JS files, </body></html>
   
   WHAT IS session_start()?
   PHP sessions store user data on the SERVER
   (not in the browser). After login.php runs,
   it saves the user's ID and name in $_SESSION.
   session_start() reads that data back out on
   every subsequent page load.
═══════════════════════════════════════ */
session_start();

/* ── LOAD ALL DATA ──────────────────────
   require_once means: load this file, but
   only once even if called multiple times.
   After this line, $stats, $events, $ministries,
   $verse, and $ticker_str are all available.
──────────────────────────────────────── */
require_once 'includes/data.php';

/* ── AUTH STATE ─────────────────────────
   Read the PHP session to check if the
   visitor is logged in.
   
   isset() returns true if the variable exists
   and is not NULL.
──────────────────────────────────────── */
$is_logged_in = isset($_SESSION['member_id']);
$user_name    = $is_logged_in ? htmlspecialchars($_SESSION['member_name'] ?? 'Member') : '';
$user_role    = $is_logged_in ? ($_SESSION['role'] ?? 'member') : '';

/* ── PAGE META ──────────────────────────
   Set variables that header.php will use
   for <title> and <meta description>.
──────────────────────────────────────── */
$page_title = 'Shammah Urban Church | Home';
$page_desc  = 'Experience the Touch of His Presence. Join us Sundays in Pretoria, South Africa.';

/* ── HTML HEAD + OPENING BODY ───────── */
require_once 'includes/header.php';

/* ── TICKER + NAVIGATION ─────────────── */
require_once 'includes/nav.php';
?>

<!-- ══════════════ PAGE MAIN ══════════════ -->
<main id="main-content">

  <!-- ═══════════════════════════════════
       HERO SECTION
       The full-screen landing area.
  ════════════════════════════════════ -->
  <section class="hero" aria-label="Welcome to Shammah Urban Church">
    <!-- Background decorations (purely visual) -->
    <div class="hero-bg"  aria-hidden="true"></div>
    <div class="hero-orb" aria-hidden="true"></div>

    <div class="hero-inner">
      <div class="hero-eyebrow">Welcome to Pretoria's Church Family</div>

      <h1>
        Shammah Urban Church
        <em>Experience His Presence</em>
      </h1>

      <p class="hero-sub">
        A multigenerational, multicultural congregation committed to authentic worship,
        relevant ministry, and transforming communities across Pretoria.
      </p>

      <div class="hero-btns">
        <a href="events.php" class="btn-gold">
          <i class="ti ti-calendar" aria-hidden="true"></i> Join Us This Sunday
        </a>
        <a href="sermons.php" class="btn-ghost">
          <i class="ti ti-player-play" aria-hidden="true"></i> Watch Online
        </a>
      </div>

      <!-- Live service indicator -->
      <div class="hero-service" aria-label="Next service information">
        <div class="service-dot" aria-hidden="true"></div>
        <p>Next service: <strong>Sunday 9:00 AM &amp; 11:00 AM</strong> · Main Sanctuary</p>
      </div>
    </div>

    <!-- Animated scroll indicator -->
    <div class="hero-scroll" aria-hidden="true">
      <div class="scroll-mouse"></div>
      <span>Scroll</span>
    </div>
  </section>


  <!-- ═══════════════════════════════════
       STATS BAND
       Four animated counters.
       
       HOW THE COUNTER WORKS:
       data-target="512" stores the final number.
       JS (main.js) reads this and animates
       the displayed number from 0 → 512.
  ════════════════════════════════════ -->
  <section class="stats-band" aria-label="Church statistics">
    <div class="stats-grid">
      <?php
      // Build the four stat items from $stats (defined in data.php)
      $stat_items = [
        ['val' => $stats['members'],    'suf' => '+', 'lbl' => 'Active Members'],
        ['val' => $stats['ministries'], 'suf' => '',  'lbl' => 'Ministries'],
        ['val' => $stats['groups'],     'suf' => '',  'lbl' => 'Cell Groups'],
        ['val' => $stats['years'],      'suf' => '',  'lbl' => 'Years of Ministry'],
      ];

      // foreach loops through the array.
      // Each iteration, $s = one item.
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
       VERSE / SCRIPTURE
  ════════════════════════════════════ -->
  <section class="verse-section" aria-label="Scripture of the day">
    <div class="verse-inner">
      <div class="verse-day-tag">✦ Daily Scripture</div>
      <!--
        htmlspecialchars() converts characters like < > & " '
        into safe HTML entities so they can't break the page
        or be used for XSS (cross-site scripting) attacks.
        Always use it when printing user-supplied or database data.
      -->
      <p class="verse-text"><?= htmlspecialchars($verse['text']) ?></p>
      <div class="verse-ref">— <?= htmlspecialchars($verse['ref']) ?></div>
    </div>
  </section>


  <!-- ═══════════════════════════════════
       UPCOMING EVENTS
  ════════════════════════════════════ -->
  <section class="section events-section" id="events">
    <div class="section-wide">
      <div class="section-tag">Upcoming Events</div>
      <div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:12px">
        <div>
          <h2 class="section-title">Don't Miss What's <em>Happening</em></h2>
          <p class="section-sub">Register your attendance — capacity is managed in real time.</p>
        </div>
        <a href="events.php" class="btn-outline-navy" style="white-space:nowrap;align-self:flex-end">
          All Events <i class="ti ti-arrow-right" aria-hidden="true"></i>
        </a>
      </div>

      <div class="events-grid" role="list">
        <?php foreach ($events as $ev):
          // Calculate percentage of capacity filled (0–100)
          $pct    = round(($ev['rsvp'] / $ev['capacity']) * 100);
          // Calculate how many spots are left
          $spots  = $ev['capacity'] - $ev['rsvp'];
          // Flag as urgent (warning colour) when ≥85% full
          $urgent = $pct >= 85;
        ?>
        <article class="event-card" role="listitem"
                 aria-label="<?= htmlspecialchars($ev['title']) ?>">
          <!-- Coloured stripe at top – colour comes from $ev['color'] in data.php -->
          <div class="event-stripe" style="background:<?= $ev['color'] ?>"></div>

          <div class="event-body">
            <div class="event-header">
              <!-- Date box -->
              <div class="event-date-box"
                   aria-label="Date: <?= $ev['day'] ?> <?= $ev['month'] ?>">
                <div class="event-date-day"><?= $ev['day'] ?></div>
                <div class="event-date-mon"><?= $ev['month'] ?></div>
              </div>

              <!-- Title & meta -->
              <div class="event-info">
                <div class="event-title"><?= htmlspecialchars($ev['title']) ?></div>
                <div class="event-meta">
                  <i class="ti ti-clock" aria-hidden="true"></i><?= $ev['time'] ?>
                </div>
                <div class="event-meta">
                  <i class="ti ti-map-pin" aria-hidden="true"></i><?= htmlspecialchars($ev['venue']) ?>
                </div>
              </div>
            </div>

            <!-- Capacity progress bar -->
            <div class="event-cap-row">
              <span class="event-cap-label">Capacity</span>
              <span class="<?= $urgent ? 'cap-warning' : 'cap-ok' ?>">
                <?= $urgent ? "⚠ Only {$spots} spots left!" : "{$spots} spots remaining" ?>
              </span>
            </div>
            <!--
              role="progressbar" + aria-valuenow makes this accessible.
              The width is set as an inline style: style="width:74%"
              The background colour matches the event's stripe colour.
            -->
            <div class="event-prog"
                 role="progressbar"
                 aria-valuenow="<?= $pct ?>"
                 aria-valuemin="0"
                 aria-valuemax="100"
                 aria-label="<?= $pct ?>% capacity filled">
              <div class="event-prog-fill"
                   style="width:<?= $pct ?>%; background:<?= $ev['color'] ?>">
              </div>
            </div>

            <!-- RSVP button + detail link -->
            <div>
              <!--
                onclick calls handleRSVP() in rsvp.js.
                addslashes() escapes single quotes in the title so
                the JS string doesn't break if the title contains one.
              -->
              <button class="rsvp-btn"
                      onclick="handleRSVP(<?= $ev['id'] ?>, '<?= addslashes($ev['title']) ?>')"
                      aria-label="RSVP for <?= htmlspecialchars($ev['title']) ?>">
                <i class="ti ti-calendar-plus" aria-hidden="true"></i> RSVP Now
              </button>
              <a href="events.php?id=<?= $ev['id'] ?>" class="detail-link">
                Details <i class="ti ti-arrow-right" aria-hidden="true"></i>
              </a>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div><!-- /.events-grid -->
    </div><!-- /.section-wide -->
  </section>


  <!-- ═══════════════════════════════════
       SERVICE TIMES
  ════════════════════════════════════ -->
  <section class="section services-section">
    <div class="section-narrow" style="text-align:center;margin-bottom:0">
      <div class="section-tag" style="justify-content:center">Service Times</div>
      <h2 class="section-title">Come & Worship <em>With Us</em></h2>
      <p class="section-sub" style="margin:0 auto">All are welcome — wherever you are on your faith journey.</p>
    </div>
    <div class="section-wide">
      <div class="services-grid">
        <div class="service-card">
          <div class="service-icon" aria-hidden="true">🌅</div>
          <h3>Sunday Morning</h3>
          <div class="service-time">9:00 AM</div>
          <p>Main Sanctuary · All ages welcome<br>Second service at 11:00 AM</p>
        </div>
        <div class="service-card">
          <div class="service-icon" aria-hidden="true">📖</div>
          <h3>Wednesday Bible Study</h3>
          <div class="service-time">6:30 PM</div>
          <p>Fellowship Hall · Deeper Word<br>Open to all members &amp; guests</p>
        </div>
        <div class="service-card">
          <div class="service-icon" aria-hidden="true">🌙</div>
          <h3>Friday Youth Night</h3>
          <div class="service-time">6:00 PM</div>
          <p>Youth Centre · Ages 13–25<br>Worship, fellowship &amp; games</p>
        </div>
      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════
       MINISTRIES
  ════════════════════════════════════ -->
  <section class="section ministries-section">
    <div class="section-wide">
      <div class="section-tag">Our Ministries</div>
      <div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:12px;margin-bottom:0">
        <div>
          <h2 class="section-title">Find Your <em>Place to Belong</em></h2>
          <p class="section-sub">8 active ministries — there is a place for everyone here.</p>
        </div>
      </div>

      <div class="ministries-grid" role="list">
        <?php foreach ($ministries as $m): ?>
        <div class="ministry-card" role="listitem">
          <div class="ministry-emoji" aria-hidden="true"><?= $m['icon'] ?></div>
          <div class="ministry-text">
            <h4><?= htmlspecialchars($m['name']) ?></h4>
            <p><?= htmlspecialchars($m['desc']) ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="ministries-cta">
        <a href="ministries.php" class="btn-outline-navy">
          View All Ministries <i class="ti ti-arrow-right" aria-hidden="true"></i>
        </a>
      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════
       QUICK LINKS STRIP
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
      <a class="ql-item" href="volunteers/" aria-label="Volunteer in a ministry">
        <div class="ql-icon" aria-hidden="true">🤝</div>
        <div class="ql-text">
          <strong>Volunteer</strong>
          <span>Serve in a ministry role</span>
        </div>
      </a>
      <a class="ql-item" href="register.php" aria-label="Register as a new member">
        <div class="ql-icon" aria-hidden="true">📋</div>
        <div class="ql-text">
          <strong>Become a Member</strong>
          <span>Register to join our community</span>
        </div>
      </a>
    </div>
  </nav>

</main><!-- /#main-content -->


<!-- ═══════════════════════════════════════
     JAVASCRIPT BRIDGE: PHP → JS
     
     We need two values from PHP available in
     JavaScript: whether the user is logged in,
     and the CSRF token (for the RSVP fetch call).
     
     We print them as JS const declarations
     so rsvp.js can read them as normal variables.
     
     json_encode() converts PHP values to safe
     JavaScript values:
       true  → true
       false → false
       'My Name' → "My Name"
════════════════════════════════════════ -->
<script>
  // Is the current visitor logged in?
  const IS_LOGGED_IN = <?= json_encode($is_logged_in) ?>;

  // Security token from the PHP session (prevents CSRF attacks on the RSVP endpoint)
  const CSRF_TOKEN   = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
</script>

<?php
/* ── FOOTER + CLOSING TAGS ──────────────
   footer.php outputs the <footer>, the
   WhatsApp and back-to-top buttons, loads
   JS files, and closes </body></html>.
──────────────────────────────────────── */
require_once 'includes/footer.php';
?>

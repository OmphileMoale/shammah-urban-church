<?php
/* ═══════════════════════════════════════
   events.php  –  EVENTS PAGE

   HOW THIS FILE WORKS:
   1. session_start()   – reads the PHP session
   2. require_once      – loads shared data + includes
   3. UPCOMING EVENTS   – filtered grid with RSVP
   4. EVENT DETAIL      – expanded view via JS
   5. PAST EVENTS       – archived / greyed cards
   6. SIDEBAR           – service times + quick links
═══════════════════════════════════════ */
session_start();
require_once 'includes/data.php';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));   // needed by the RSVP button

/* Real numbers: events, capacity and RSVPs now come from the database */
$events      = shammah_live_events($events);
$past_events = shammah_live_past_events($past_events);

/* ── AUTH STATE ─────────────────────── */
$is_logged_in = isset($_SESSION['member_id']);
$user_name    = $is_logged_in ? htmlspecialchars($_SESSION['member_name'] ?? 'Member') : '';
$user_role    = $is_logged_in ? ($_SESSION['role'] ?? 'member') : '';

/* ── PAGE META ──────────────────────── */
$page_title = 'Events | Shammah Urban Church';
$page_desc  = 'Browse all upcoming events at Shammah Urban Church — RSVP, get details, and stay connected with our congregation in Pretoria.';

/* ── SUMMARY COUNTS (for the hero stats) ── */
$total_upcoming = count($events);
$total_rsvps    = shammah_rsvps_this_month(array_sum(array_column($events, 'rsvp')));
$categories     = array_unique(array_column($events, 'category'));

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- ══════════════ PAGE MAIN ══════════════ -->
<main id="main-content">

  <!-- ═══════════════════════════════════
       HERO BANNER
       Same gradient and diagonal pattern
       as about.php — consistent brand feel.
  ════════════════════════════════════ -->
  <section class="events-hero" aria-label="Events at Shammah Urban Church">
    <div class="events-hero-pattern" aria-hidden="true"></div>
    <div class="events-hero-inner">
      <div class="hero-eyebrow">What's On</div>
      <h1>
        Church <em>Events</em>
      </h1>
      <p class="events-hero-sub">
        Browse upcoming events, RSVP your spot, and stay connected with
        everything happening at Shammah Urban Church in Pretoria.
      </p>

      <!-- Live hero stats strip -->
      <div class="events-hero-stats" aria-label="Quick event statistics">
        <div class="hero-stat">
          <strong><?= $total_upcoming ?></strong>
          <span>Upcoming Events</span>
        </div>
        <div class="hero-stat-divider" aria-hidden="true"></div>
        <div class="hero-stat">
          <strong><?= (int) $total_rsvps ?></strong>
          <span>RSVPs This Month</span>
        </div>
        <div class="hero-stat-divider" aria-hidden="true"></div>
        <div class="hero-stat">
          <strong><?= count($categories) ?></strong>
          <span>Categories</span>
        </div>
      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════
       STICKY FILTER + SEARCH BAR
       Sticks below the nav so it's always
       accessible while browsing cards.
  ════════════════════════════════════ -->
  <div class="events-controls" role="search" aria-label="Filter and search events">
    <div class="events-controls-inner">

      <!-- Category filter pills -->
      <!--
        data-cat on each pill is read by events.js
        to match against data-category on each card.
        'all' is the default active state.
      -->
      <div class="filter-pills" role="list" aria-label="Filter by category">
        <button class="filter-pill active" data-cat="all"      role="listitem" aria-pressed="true">All Events</button>
        <button class="filter-pill"        data-cat="worship"  role="listitem" aria-pressed="false">🙏 Worship</button>
        <button class="filter-pill"        data-cat="youth"    role="listitem" aria-pressed="false">👦 Youth</button>
        <button class="filter-pill"        data-cat="outreach" role="listitem" aria-pressed="false">🌍 Outreach</button>
        <button class="filter-pill"        data-cat="women"    role="listitem" aria-pressed="false">👩 Women</button>
        <button class="filter-pill"        data-cat="children" role="listitem" aria-pressed="false">👶 Children</button>
      </div>

      <!-- Keyword search -->
      <div class="events-search">
        <i class="ti ti-search" aria-hidden="true"></i>
        <input type="search"
               id="eventSearch"
               placeholder="Search events…"
               aria-label="Search events by title or venue"
               autocomplete="off">
      </div>

    </div>
  </div>


  <!-- ═══════════════════════════════════
       MAIN LAYOUT: events grid + sidebar
  ════════════════════════════════════ -->
  <div class="events-layout">

    <!-- ── LEFT COLUMN: event cards ── -->
    <div class="events-main">

      <!-- ═══════════════════════════════
           EVENT DETAIL PANEL
           Hidden by default (display:none).
           JS populates and shows it when
           the user clicks "Details" on a card.
      ════════════════════════════════ -->
      <div id="eventDetailPanel" style="display:none;"
           class="event-detail-panel"
           role="region"
           aria-label="Event details">

        <!-- Coloured accent bar (bg set by JS) -->
        <div id="detailAccent" style="height:6px; background:var(--navy);"></div>

        <div class="detail-panel-hero">
          <!-- Back button -->
          <button class="back-btn js-hide-detail" aria-label="Back to events list">
            <i class="ti ti-arrow-left" aria-hidden="true"></i> Back to Events
          </button>

          <!-- Category tag -->
          <div id="detailCat" class="event-cat-tag cat-worship" style="margin-bottom:10px;">Worship</div>

          <!-- Title — JS fills this in -->
          <h2 id="detailTitle">Event Title</h2>
        </div>

        <div class="detail-panel-body">
          <!-- Four info cards: date, time, venue, capacity -->
          <div class="detail-info-grid">
            <div class="detail-info-card">
              <i class="ti ti-calendar-event" aria-hidden="true"></i>
              <div>
                <strong>Date</strong>
                <span id="detailDate">—</span>
              </div>
            </div>
            <div class="detail-info-card">
              <i class="ti ti-clock" aria-hidden="true"></i>
              <div>
                <strong>Time</strong>
                <span id="detailTime">—</span>
              </div>
            </div>
            <div class="detail-info-card">
              <i class="ti ti-map-pin" aria-hidden="true"></i>
              <div>
                <strong>Venue</strong>
                <span id="detailVenue">—</span>
              </div>
            </div>
            <div class="detail-info-card">
              <i class="ti ti-users" aria-hidden="true"></i>
              <div>
                <strong>Availability</strong>
                <span id="detailCap">—</span>
              </div>
            </div>
          </div>

          <!-- Full event description -->
          <div class="detail-desc-heading">About This Event</div>
          <p class="detail-desc" id="detailDesc">—</p>

          <!-- RSVP section inside detail panel -->
          <div class="detail-rsvp-section">
            <div class="detail-rsvp-left">
              <strong>Ready to join us?</strong>
              <span>Secure your spot — capacity is managed in real time.</span>
            </div>
            <button id="detailRsvpBtn" class="rsvp-btn-full"
                    aria-label="RSVP for this event">
              <i class="ti ti-calendar-plus" aria-hidden="true"></i> RSVP Now
            </button>
          </div>
        </div>
      </div>
      <!-- /#eventDetailPanel -->


      <!-- ═══════════════════════════════
           UPCOMING EVENTS GRID
      ════════════════════════════════ -->
      <div class="events-section-label">
        <h2>Upcoming Events</h2>
        <span class="count-badge"><?= $total_upcoming ?></span>
        <div class="line" aria-hidden="true"></div>
      </div>

      <div class="events-grid-full" role="list" aria-label="Upcoming events">

        <?php foreach ($events as $ev):
          $pct    = round(($ev['rsvp'] / $ev['capacity']) * 100);
          $spots  = $ev['capacity'] - $ev['rsvp'];
          $urgent = $pct >= 85;
          $full   = $spots <= 0;

          // Safe HTML for data attributes (prevents XSS)
          $safe_title = htmlspecialchars($ev['title'],   ENT_QUOTES);
          $safe_venue = htmlspecialchars($ev['venue'],   ENT_QUOTES);
          $safe_desc  = htmlspecialchars($ev['desc'],    ENT_QUOTES);
          $safe_cat   = htmlspecialchars($ev['category'],ENT_QUOTES);
          $safe_date  = htmlspecialchars($ev['day'] . ' ' . $ev['month'] . ' ' . $ev['year'], ENT_QUOTES);

          // Capacity text
          if ($full)        $cap_text = '🔴 Fully Booked';
          elseif ($urgent)  $cap_text = "⚠ Only {$spots} spots left!";
          else              $cap_text = "{$spots} spots remaining";

          $cap_class = $full ? 'cap-full' : ($urgent ? 'cap-warning' : 'cap-ok');
        ?>

        <article class="event-card-full"
                 role="listitem"
                 aria-label="<?= $safe_title ?>"
                 data-category="<?= htmlspecialchars($ev['cat_key']) ?>"
                 data-title="<?= $safe_title ?>"
                 data-venue="<?= $safe_venue ?>">

          <!-- Left accent stripe — colour from $ev['color'] -->
          <div class="event-card-accent" style="background:<?= $ev['color'] ?>;" aria-hidden="true"></div>

          <div class="event-card-body">

            <!-- TOP ROW: date box, title, meta -->
            <div class="event-card-top">
              <!-- Date box -->
              <div class="event-date-box"
                   aria-label="Date: <?= $ev['day'] ?> <?= $ev['month'] ?> <?= $ev['year'] ?>">
                <div class="event-date-day"><?= $ev['day'] ?></div>
                <div class="event-date-mon"><?= $ev['month'] ?></div>
                <div class="event-date-yr"><?= $ev['year'] ?></div>
              </div>

              <!-- Title + meta -->
              <div class="event-card-info">
                <!-- Category badge -->
                <span class="event-cat-tag <?= $ev['cat_cls'] ?>"><?= htmlspecialchars($ev['category']) ?></span>

                <h3><?= htmlspecialchars($ev['title']) ?></h3>

                <div class="event-meta-row">
                  <span class="event-meta-item">
                    <i class="ti ti-clock" aria-hidden="true"></i>
                    <?= htmlspecialchars($ev['time']) ?>
                  </span>
                  <span class="event-meta-item">
                    <i class="ti ti-map-pin" aria-hidden="true"></i>
                    <?= htmlspecialchars($ev['venue']) ?>
                  </span>
                </div>
              </div>
            </div>

            <!-- COUNTDOWN TIMER
                 data-countdown holds the event datetime.
                 events.js updates the textContent every second.
            -->
            <?php if (!$full): ?>
            <div class="event-countdown" aria-live="polite">
              <i class="ti ti-hourglass" aria-hidden="true"></i>
              Starts in&nbsp;
              <strong data-countdown="<?= htmlspecialchars($ev['datetime']) ?>">
                calculating…
              </strong>
            </div>
            <?php endif; ?>

            <!-- CAPACITY BAR -->
            <div class="event-cap-section">
              <div class="event-cap-row">
                <span class="event-cap-label">
                  <?= $ev['rsvp'] ?> / <?= $ev['capacity'] ?> RSVPs
                </span>
                <span class="<?= $cap_class ?>" aria-live="polite">
                  <?= $cap_text ?>
                </span>
              </div>
              <!--
                role="progressbar" + aria-valuenow makes this accessible.
                Width set as an inline style: width:74%
                Colour matches the event's accent stripe.
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
            </div>

            <!-- ACTION ROW: RSVP + Details + Share -->
            <div class="event-card-actions">

              <?php if ($full): ?>
                <button class="rsvp-btn-full full" disabled aria-label="Event fully booked">
                  <i class="ti ti-lock" aria-hidden="true"></i> Fully Booked
                </button>
              <?php else: ?>
                <!--
                  onclick calls handleRSVP() in rsvp.js.
                  addslashes() escapes single quotes so the JS string is safe.
                -->
                <button class="rsvp-btn-full"
                        onclick="handleRSVP(<?= $ev['id'] ?>, '<?= addslashes($ev['title']) ?>')"
                        aria-label="RSVP for <?= $safe_title ?>">
                  <i class="ti ti-calendar-plus" aria-hidden="true"></i> RSVP Now
                </button>
              <?php endif; ?>

              <!--
                "Details" button — data-* attributes carry all the event
                information so events.js can populate the detail panel
                without a page reload.
              -->
              <button class="event-detail-link js-show-detail"
                      data-eventid="<?= $ev['id'] ?>"
                      data-title="<?= $safe_title ?>"
                      data-date="<?= $safe_date ?>"
                      data-time="<?= htmlspecialchars($ev['time'], ENT_QUOTES) ?>"
                      data-venue="<?= $safe_venue ?>"
                      data-desc="<?= $safe_desc ?>"
                      data-cat="<?= $safe_cat ?>"
                      data-catclass="<?= htmlspecialchars($ev['cat_cls'], ENT_QUOTES) ?>"
                      data-color="<?= htmlspecialchars($ev['color'], ENT_QUOTES) ?>"
                      data-spots="<?= $full ? 'Fully Booked' : "{$spots} spots remaining" ?>"
                      data-full="<?= $full ? '1' : '0' ?>"
                      aria-label="View details for <?= $safe_title ?>">
                Details <i class="ti ti-arrow-right" aria-hidden="true"></i>
              </button>

              <!-- WhatsApp share button (South African context) -->
              <button class="event-share-btn"
                      data-title="<?= $safe_title ?>"
                      aria-label="Share <?= $safe_title ?> on WhatsApp">
                <i class="ti ti-brand-whatsapp" aria-hidden="true"></i> Share
              </button>

            </div><!-- /.event-card-actions -->
          </div><!-- /.event-card-body -->
        </article>

        <?php endforeach; ?>

        <!-- Empty state (shown by JS when no cards match filter/search) -->
        <div id="eventsEmpty" class="events-empty" style="display:none;" aria-live="polite">
          <i class="ti ti-calendar-off" aria-hidden="true"></i>
          <p>No events match your filter or search. Try a different category.</p>
        </div>

      </div><!-- /.events-grid-full -->


      <!-- ═══════════════════════════════
           PAST EVENTS
           Shown greyed out — demonstrates
           database awareness & separating
           current from historical records.
      ════════════════════════════════ -->
      <div class="events-section-label" style="margin-top: 8px;">
        <h2>Past Events</h2>
        <span class="count-badge" style="background:var(--text-3);"><?= count($past_events) ?></span>
        <div class="line" aria-hidden="true"></div>
      </div>

      <div class="past-events-grid" role="list" aria-label="Past events archive">
        <?php foreach ($past_events as $pe): ?>
        <div class="event-card-past" role="listitem"
             aria-label="<?= htmlspecialchars($pe['title']) ?> — <?= $pe['day'] ?> <?= $pe['month'] ?>">
          <!-- Greyed date box -->
          <div class="past-date-box" aria-hidden="true">
            <div class="day"><?= $pe['day'] ?></div>
            <div class="mon"><?= $pe['month'] ?></div>
          </div>
          <!-- Info -->
          <div class="past-card-info">
            <h4><?= htmlspecialchars($pe['title']) ?></h4>
            <p>
              <i class="ti ti-map-pin" aria-hidden="true" style="font-size:11px;"></i>
              <?= htmlspecialchars($pe['venue']) ?>
            </p>
            <p style="margin-top:3px;font-size:11px;color:var(--text-3);">
              <?= $pe['rsvp'] ?> attended of <?= $pe['capacity'] ?> capacity
            </p>
            <span class="past-ended-badge">Event Ended</span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

    </div><!-- /.events-main -->


    <!-- ── RIGHT COLUMN: sidebar ── -->
    <aside class="events-sidebar" aria-label="Events sidebar">

      <!-- Widget 1: Service Times -->
      <div class="sidebar-widget">
        <div class="widget-header">
          <i class="ti ti-clock" aria-hidden="true"></i>
          <h3>Service Times</h3>
        </div>
        <div class="widget-body">
          <div class="sidebar-service-item">
            <div class="sidebar-svc-icon" aria-hidden="true">🌅</div>
            <div class="sidebar-svc-info">
              <strong>Sunday Morning</strong>
              <span>9:00 AM &amp; 11:00 AM · Main Sanctuary</span>
            </div>
          </div>
          <div class="sidebar-service-item">
            <div class="sidebar-svc-icon" aria-hidden="true">📖</div>
            <div class="sidebar-svc-info">
              <strong>Wednesday Bible Study</strong>
              <span>6:30 PM · Fellowship Hall</span>
            </div>
          </div>
          <div class="sidebar-service-item">
            <div class="sidebar-svc-icon" aria-hidden="true">🌙</div>
            <div class="sidebar-svc-info">
              <strong>Friday Youth Night</strong>
              <span>6:00 PM · Youth Centre</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Widget 2: Quick RSVPs — all upcoming events listed -->
      <div class="sidebar-widget">
        <div class="widget-header">
          <i class="ti ti-calendar-check" aria-hidden="true"></i>
          <h3>Quick RSVP</h3>
        </div>
        <div class="widget-body">
          <?php foreach ($events as $ev):
            $sp = $ev['capacity'] - $ev['rsvp'];
            $fl = $sp <= 0;
          ?>
          <div class="sidebar-upcoming-item">
            <div class="sidebar-date-pill" aria-label="<?= $ev['day'] ?> <?= $ev['month'] ?>">
              <?= $ev['day'] ?><br><?= strtoupper(substr($ev['month'],0,3)) ?>
            </div>
            <div class="sidebar-upcoming-info" style="flex:1;min-width:0;">
              <strong><?= htmlspecialchars($ev['title']) ?></strong>
              <span><?= $fl ? 'Fully Booked' : "{$sp} spots left" ?></span>
            </div>
            <?php if (!$fl): ?>
            <button class="rsvp-btn"
                    onclick="handleRSVP(<?= $ev['id'] ?>, '<?= addslashes($ev['title']) ?>')"
                    aria-label="RSVP <?= htmlspecialchars($ev['title'], ENT_QUOTES) ?>"
                    style="font-size:10px;padding:5px 10px;flex-shrink:0;">
              RSVP
            </button>
            <?php else: ?>
            <span style="font-size:10px;color:var(--red);font-weight:700;flex-shrink:0;">Full</span>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Widget 3: CTA — Plan Your Visit -->
      <div class="sidebar-widget">
        <div class="widget-header">
          <i class="ti ti-map-pin" aria-hidden="true"></i>
          <h3>Plan Your Visit</h3>
        </div>
        <div class="widget-body" style="text-align:center;">
          <div style="font-size:28px;margin-bottom:10px;" aria-hidden="true">⛪</div>
          <p style="font-size:12.5px;color:var(--text-2);line-height:1.7;margin-bottom:16px;">
            1103 Eland Street, Mooikloof<br>
            Pretoria East, 0059 · Free parking available
          </p>
          <a href="contact.php" class="btn-gold" style="width:100%;justify-content:center;display:inline-flex;">
            <i class="ti ti-directions" aria-hidden="true"></i> Get Directions
          </a>
          <a href="https://wa.me/27123456789?text=Hi%2C+I%27d+like+to+know+more+about+visiting+Shammah+Urban+Church"
             class="btn-outline-navy"
             style="width:100%;justify-content:center;display:inline-flex;margin-top:10px;"
             target="_blank" rel="noopener">
            <i class="ti ti-brand-whatsapp" aria-hidden="true"></i> Chat on WhatsApp
          </a>
        </div>
      </div>

    </aside><!-- /.events-sidebar -->

  </div><!-- /.events-layout -->


  <!-- ═══════════════════════════════════
       SCRIPTURE SECTION
       Consistent with index.php and about.php.
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
       Same component as the home page —
       drives visitors to other actions.
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
      <a class="ql-item" href="register.php" aria-label="Register as a new member">
        <div class="ql-icon" aria-hidden="true">📋</div>
        <div class="ql-text">
          <strong>Become a Member</strong>
          <span>Register to join our community</span>
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
     by rsvp.js and events.js.
════════════════════════════════════════ -->
<script>
  const IS_LOGGED_IN = <?= json_encode($is_logged_in) ?>;
  const CSRF_TOKEN   = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
</script>

<?php require_once 'includes/footer.php'; ?>
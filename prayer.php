<?php
/* ═══════════════════════════════════════
   prayer.php  –  PRAYER WALL PAGE

   HOW THIS FILE WORKS:
   1. session_start()       – reads PHP session (auth state)
   2. require data.php      – loads $announcements (ticker) + $verse
   3. $prayer_requests      – all public prayer submissions
   4. $categories           – prayer categories with colours
   5. $prayer_stats         – site-wide impact numbers
   6. Hero + scripture      – banner with verse and live stats
   7. Breadcrumb            – Home > Prayer
   8. Two-column layout     – Submit form (left) + Prayer Wall (right)
   9. Category filter pills – filter wall cards by category
   10. Prayer wall cards    – colour-coded, with Praying button + Answered toggle
   11. Daily verse band     – Scripture card from data.php
   12. CTA section          – bottom call-to-action

   DATABASE NOTES:
   $prayer_requests simulates a PRAYER_REQUEST table.
   Fields: id, name, category, message, is_anonymous,
           prayer_count, is_answered, time_ago.
   When you add a real DB, replace the array with:
     $stmt = $pdo->query(
       "SELECT * FROM PRAYER_REQUEST
        WHERE is_public = 1
        ORDER BY created_at DESC"
     );
     $prayer_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

   The "Praying" button would POST to a small endpoint:
     POST /api/pray.php  { prayer_id: X }
     UPDATE PRAYER_REQUEST SET prayer_count = prayer_count + 1 WHERE id = X

   The "Answered" toggle would POST to:
     POST /api/answered.php { prayer_id: X }
     UPDATE PRAYER_REQUEST SET is_answered = 1 WHERE id = X
═══════════════════════════════════════ */
session_start();
require_once 'includes/data.php';

/* ── AUTH STATE ─────────────────────── */
$is_logged_in = isset($_SESSION['member_id']);
$user_name    = $is_logged_in ? htmlspecialchars($_SESSION['member_name'] ?? 'Member') : '';
$user_role    = $is_logged_in ? ($_SESSION['role'] ?? 'member') : '';

/* ── PAGE META ──────────────────────── */
$page_title = 'Prayer Wall | Shammah Urban Church';
$page_desc  = 'Submit a prayer request and intercede for the Shammah Urban Church community. Browse the prayer wall, pray for others, and celebrate answered prayers.';

/* ══════════════════════════════════════
   PRAYER CATEGORIES
   Each category has a colour for the card's
   left accent border and the filter pills.
══════════════════════════════════════ */
$categories = [
  'Health & Healing'  => ['color' => '#C0392B', 'icon' => 'ti-heart-pulse',    'emoji' => '❤️'],
  'Family'            => ['color' => '#C9A84C', 'icon' => 'ti-users',           'emoji' => '👨‍👩‍👧'],
  'Financial'         => ['color' => '#639922', 'icon' => 'ti-coin',            'emoji' => '💛'],
  'Spiritual Growth'  => ['color' => '#378ADD', 'icon' => 'ti-book',            'emoji' => '📖'],
  'Relationships'     => ['color' => '#8E44AD', 'icon' => 'ti-heart-handshake', 'emoji' => '🤝'],
  'Thanksgiving'      => ['color' => '#27AE60', 'icon' => 'ti-confetti',        'emoji' => '🎉'],
  'Protection'        => ['color' => '#3D0C2F', 'icon' => 'ti-shield',          'emoji' => '🛡️'],
  'Guidance'          => ['color' => '#E67E22', 'icon' => 'ti-compass',         'emoji' => '🧭'],
];

/* ══════════════════════════════════════
   PRAYER REQUESTS DATA ARRAY
   Each entry = one row in PRAYER_REQUEST table.
   FIELDS:
   id            – unique ID (DB primary key later)
   name          – display name (or "Anonymous")
   is_anonymous  – true = name was hidden by submitter
   category      – matches a key in $categories above
   message       – the prayer request text
   time_ago      – human-readable time since submission
   prayer_count  – how many people have clicked "Praying"
   is_answered   – true = submitter marked this as answered
   member_prayed – simulate if THIS logged-in user already prayed
══════════════════════════════════════ */
$prayer_requests = [
  [
    'id'            => 1,
    'name'          => 'Anonymous',
    'is_anonymous'  => true,
    'category'      => 'Health & Healing',
    'message'       => 'Please pray for my mother\'s recovery from surgery. She needs strength and healing from God. The doctors say it will be a long road but we trust the Lord.',
    'time_ago'      => '2 hours ago',
    'prayer_count'  => 24,
    'is_answered'   => false,
    'member_prayed' => false,
  ],
  [
    'id'            => 2,
    'name'          => 'Zanele D.',
    'is_anonymous'  => false,
    'category'      => 'Thanksgiving',
    'message'       => 'God provided my family with a new home after months of prayer! After being on the waiting list for almost a year, we received the keys this week. Thank you Shammah family for standing with us!',
    'time_ago'      => 'Yesterday',
    'prayer_count'  => 47,
    'is_answered'   => true,
    'member_prayed' => true,
  ],
  [
    'id'            => 3,
    'name'          => 'Thabo N.',
    'is_anonymous'  => false,
    'category'      => 'Spiritual Growth',
    'message'       => 'Pray for my consistency in morning devotions and that I would grow deeper in my walk with God. I want to develop a stronger prayer life and really understand the Word.',
    'time_ago'      => '2 days ago',
    'prayer_count'  => 18,
    'is_answered'   => false,
    'member_prayed' => false,
  ],
  [
    'id'            => 4,
    'name'          => 'Anonymous',
    'is_anonymous'  => true,
    'category'      => 'Financial',
    'message'       => 'I am facing a very difficult financial season. Please pray for wisdom in managing what I have and for God to open doors of provision. I believe in His faithfulness.',
    'time_ago'      => '3 days ago',
    'prayer_count'  => 31,
    'is_answered'   => false,
    'member_prayed' => false,
  ],
  [
    'id'            => 5,
    'name'          => 'Priya G.',
    'is_anonymous'  => false,
    'category'      => 'Family',
    'message'       => 'Please pray for my teenage son who has been struggling with peer pressure and making poor choices. I pray that God will protect him and draw his heart back to the Lord.',
    'time_ago'      => '4 days ago',
    'prayer_count'  => 22,
    'is_answered'   => false,
    'member_prayed' => false,
  ],
  [
    'id'            => 6,
    'name'          => 'Elder Nkosi',
    'is_anonymous'  => false,
    'category'      => 'Protection',
    'message'       => 'Lift up our nation in prayer. South Africa needs divine intervention — protection from violence, wisdom for our leaders, and a revival of faith across every community.',
    'time_ago'      => '5 days ago',
    'prayer_count'  => 89,
    'is_answered'   => false,
    'member_prayed' => true,
  ],
  [
    'id'            => 7,
    'name'          => 'Nomsa K.',
    'is_anonymous'  => false,
    'category'      => 'Guidance',
    'message'       => 'I am at a crossroads — deciding between two job opportunities. One pays more but the other aligns with my calling. Please pray for clear direction from the Holy Spirit.',
    'time_ago'      => '1 week ago',
    'prayer_count'  => 15,
    'is_answered'   => false,
    'member_prayed' => false,
  ],
  [
    'id'            => 8,
    'name'          => 'Anonymous',
    'is_anonymous'  => true,
    'category'      => 'Relationships',
    'message'       => 'My marriage has been under a lot of strain this year. Please pray for healing, restoration, and renewed love in our home. We are committed but we need God\'s help.',
    'time_ago'      => '1 week ago',
    'prayer_count'  => 37,
    'is_answered'   => false,
    'member_prayed' => false,
  ],
  [
    'id'            => 9,
    'name'          => 'Sifiso M.',
    'is_anonymous'  => false,
    'category'      => 'Thanksgiving',
    'message'       => 'After three years of studying, I finally graduated last weekend! God is faithful. Thank you to everyone who prayed for me through the late nights and exam anxiety.',
    'time_ago'      => '2 weeks ago',
    'prayer_count'  => 54,
    'is_answered'   => true,
    'member_prayed' => false,
  ],
  [
    'id'            => 10,
    'name'          => 'Anonymous',
    'is_anonymous'  => true,
    'category'      => 'Health & Healing',
    'message'       => 'I was recently diagnosed with a chronic illness. I am scared but I choose to trust God. Please pray for peace, strength, and complete healing according to His will.',
    'time_ago'      => '2 weeks ago',
    'prayer_count'  => 43,
    'is_answered'   => false,
    'member_prayed' => false,
  ],
];

/* ── PAGE STATS ─────────────────────── */
$total_requests   = 142;
$total_prayers    = 1840;
$total_answered   = 38;
$wall_count       = count($prayer_requests);

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- ══════════════ PAGE MAIN ══════════════ -->
<main id="main-content">

  <!-- ═══════════════════════════════════
       HERO SECTION
  ════════════════════════════════════ -->
  <section class="prayer-hero" aria-label="Prayer Wall at Shammah Urban Church">
    <div class="prayer-hero-pattern" aria-hidden="true"></div>
    <div class="prayer-hero-inner">
      <div class="hero-eyebrow">
        <i class="ti ti-hand-stop" aria-hidden="true"></i>
        Intercession &amp; Community
      </div>
      <h1>Prayer <em>Wall</em></h1>
      <p class="prayer-hero-verse">
        <i class="ti ti-quote" aria-hidden="true"></i>
        "Carry each other's burdens, and in this way you will fulfill the law of Christ."
        <span>— Galatians 6:2</span>
      </p>
      <!-- Live impact stats -->
      <div class="prayer-hero-stats" aria-label="Prayer community statistics">
        <div class="p-stat">
          <strong><?= $total_requests ?>+</strong>
          <span>Prayers Submitted</span>
        </div>
        <div class="p-stat-divider" aria-hidden="true"></div>
        <div class="p-stat">
          <strong><?= number_format($total_prayers) ?>+</strong>
          <span>Intercessions Made</span>
        </div>
        <div class="p-stat-divider" aria-hidden="true"></div>
        <div class="p-stat">
          <strong><?= $total_answered ?></strong>
          <span>Answered Prayers</span>
        </div>
        <div class="p-stat-divider" aria-hidden="true"></div>
        <div class="p-stat">
          <strong><?= count($categories) ?></strong>
          <span>Prayer Categories</span>
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
      <li aria-current="page">Prayer Wall</li>
    </ol>
  </nav>

  <!-- ═══════════════════════════════════
       MAIN CONTENT — TWO COLUMN LAYOUT
       Left:  Submit a prayer request form
       Right: Community prayer wall
  ════════════════════════════════════ -->
  <div class="section">
    <div class="section-wide">
      <div class="prayer-layout">

        <!-- ══════════════════════════════
             LEFT — SUBMIT FORM
        ═══════════════════════════════ -->
        <aside class="prayer-form-col" aria-label="Submit a prayer request">

          <div class="prayer-form-card">
            <div class="form-card-header">
              <i class="ti ti-send" aria-hidden="true"></i>
              <div>
                <div class="section-tag" style="margin-bottom:4px">Share Your Heart</div>
                <h2 class="form-card-title">Submit a Prayer Request</h2>
              </div>
            </div>

            <div id="prayerForm" role="form" aria-label="Prayer request submission form">

              <!-- Name -->
              <div class="form-group">
                <label for="reqName" class="form-label">
                  <i class="ti ti-user" aria-hidden="true"></i>
                  Your Name
                </label>
                <input type="text"
                       id="reqName"
                       class="form-input"
                       placeholder="Your name (or leave blank for anonymous)"
                       value="<?= $user_name ?>">
              </div>

              <!-- Anonymous toggle -->
              <label class="anon-toggle" for="reqAnon">
                <input type="checkbox" id="reqAnon" <?= !$is_logged_in ? 'checked' : '' ?>>
                <span class="anon-box" aria-hidden="true"></span>
                <span class="anon-label">Post anonymously — your name will not appear on the wall</span>
              </label>

              <!-- Category -->
              <div class="form-group">
                <label for="reqCategory" class="form-label">
                  <i class="ti ti-tag" aria-hidden="true"></i>
                  Category <span class="req-star" aria-hidden="true">*</span>
                </label>
                <select id="reqCategory" class="form-select" required aria-required="true">
                  <option value="">— Select a category —</option>
                  <?php foreach ($categories as $cat => $meta): ?>
                    <option value="<?= htmlspecialchars($cat) ?>">
                      <?= $meta['emoji'] ?> <?= htmlspecialchars($cat) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <span class="field-error" id="reqCategoryErr" role="alert"></span>
              </div>

              <!-- Prayer request textarea -->
              <div class="form-group">
                <label for="reqMessage" class="form-label">
                  <i class="ti ti-message-circle" aria-hidden="true"></i>
                  Prayer Request <span class="req-star" aria-hidden="true">*</span>
                </label>
                <textarea id="reqMessage"
                          class="form-textarea"
                          rows="5"
                          maxlength="500"
                          placeholder="Share what's on your heart. This is a safe space — write as openly as you feel comfortable."
                          required
                          aria-required="true"
                          aria-describedby="charCount"></textarea>
                <!--
                  Character counter — updated live by prayer.js.
                  Shows remaining characters so submitter doesn't
                  hit the 500-char limit unexpectedly.
                -->
                <div class="char-counter" id="charCount" aria-live="polite">
                  <span id="charUsed">0</span> / 500 characters
                </div>
                <span class="field-error" id="reqMessageErr" role="alert"></span>
              </div>

              <!-- Public wall toggle -->
              <label class="public-toggle" for="reqPublic">
                <input type="checkbox" id="reqPublic" checked>
                <span class="public-box" aria-hidden="true"></span>
                <div class="public-label">
                  <strong>Share on the Prayer Wall</strong>
                  <small>Allow the community to see and pray for this request</small>
                </div>
              </label>

              <button class="btn-submit-prayer" id="submitPrayer" type="button">
                <i class="ti ti-hand-stop" aria-hidden="true"></i>
                Submit Prayer Request
              </button>

              <!-- Privacy note -->
              <p class="privacy-note">
                <i class="ti ti-lock-square" aria-hidden="true"></i>
                All requests are treated with care and confidentiality.
                Private requests go only to our pastoral team.
              </p>
            </div>
          </div>

          <!-- Pastoral contact nudge -->
          <div class="pastoral-card">
            <div class="pastoral-icon" aria-hidden="true">
              <i class="ti ti-user-heart"></i>
            </div>
            <div>
              <h4>Need to speak to a pastor?</h4>
              <p>Some burdens need more than prayer — they need a conversation. Our pastoral team is available for confidential one-on-one sessions.</p>
              <a href="contact.php" class="btn-pastoral">
                <i class="ti ti-message-circle" aria-hidden="true"></i>
                Contact a Pastor
              </a>
            </div>
          </div>

        </aside>


        <!-- ══════════════════════════════
             RIGHT — PRAYER WALL
        ═══════════════════════════════ -->
        <section class="prayer-wall-col" aria-label="Community prayer wall">

          <!-- Wall header -->
          <div class="wall-header">
            <div>
              <div class="section-tag" style="margin-bottom:4px">Community Intercession</div>
              <h2 class="wall-title">
                Prayer <em>Wall</em>
                <span class="wall-count" aria-label="<?= $wall_count ?> prayer requests"><?= $wall_count ?></span>
              </h2>
            </div>
            <!-- Sort dropdown -->
            <div class="wall-sort">
              <label for="wallSort" class="sr-only">Sort prayers by</label>
              <select id="wallSort" class="sort-select" aria-label="Sort prayer requests">
                <option value="recent">Most Recent</option>
                <option value="prayed">Most Prayed</option>
                <option value="answered">Answered First</option>
              </select>
            </div>
          </div>

          <!-- Category filter pills -->
          <div class="wall-filter" role="group" aria-label="Filter prayer requests by category">
            <button class="wall-pill active" data-cat="all" aria-pressed="true">
              All <span class="wall-pill-count"><?= $wall_count ?></span>
            </button>
            <?php foreach ($categories as $cat => $meta):
              $cnt = count(array_filter($prayer_requests, fn($p) => $p['category'] === $cat));
              if ($cnt === 0) continue;
              $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $cat));
            ?>
            <button class="wall-pill" data-cat="<?= $slug ?>" aria-pressed="false"
                    style="--cat-color:<?= $meta['color'] ?>">
              <?= $meta['emoji'] ?> <?= htmlspecialchars($cat) ?>
              <span class="wall-pill-count"><?= $cnt ?></span>
            </button>
            <?php endforeach; ?>
          </div>

          <!-- Prayer cards -->
          <div class="prayer-cards" id="prayerCards">
            <?php foreach ($prayer_requests as $req):
              $cat  = $req['category'];
              $meta = $categories[$cat] ?? ['color' => '#3D0C2F', 'icon' => 'ti-heart', 'emoji' => '🙏'];
              $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $cat));
            ?>
            <!--
              data-cat    – used by JS category filter
              data-count  – used by JS sort (most prayed)
              data-answered – used by JS sort (answered first)
            -->
            <article class="prayer-card <?= $req['is_answered'] ? 'is-answered' : '' ?>"
                     data-cat="<?= $slug ?>"
                     data-count="<?= $req['prayer_count'] ?>"
                     data-answered="<?= $req['is_answered'] ? '1' : '0' ?>"
                     aria-label="Prayer request: <?= htmlspecialchars($cat) ?><?= $req['is_answered'] ? ' — Answered' : '' ?>">

              <!-- Left accent border colour from category -->
              <div class="card-accent" style="background:<?= $meta['color'] ?>" aria-hidden="true"></div>

              <div class="card-content">

                <!-- Card top: category tag + timestamp -->
                <div class="card-top">
                  <span class="card-cat-tag" style="color:<?= $meta['color'] ?>;background:<?= $meta['color'] ?>1a">
                    <i class="ti <?= $meta['icon'] ?>" aria-hidden="true"></i>
                    <?= htmlspecialchars($cat) ?>
                  </span>
                  <span class="card-time">
                    <i class="ti ti-clock" aria-hidden="true"></i>
                    <?= htmlspecialchars($req['time_ago']) ?>
                  </span>
                </div>

                <!-- Answered banner -->
                <?php if ($req['is_answered']): ?>
                <div class="answered-banner" role="status">
                  <i class="ti ti-circle-check-filled" aria-hidden="true"></i>
                  Prayer Answered! 🎉
                </div>
                <?php endif; ?>

                <!-- Message -->
                <p class="card-message"><?= htmlspecialchars($req['message']) ?></p>

                <!-- Card bottom: name + praying button -->
                <div class="card-bottom">
                  <span class="card-name">
                    <i class="ti ti-user-circle" aria-hidden="true"></i>
                    — <?= htmlspecialchars($req['name']) ?>
                    <?php if ($req['is_anonymous']): ?>
                      <span class="anon-tag" aria-label="Anonymous submission">anon</span>
                    <?php endif; ?>
                  </span>

                  <div class="card-actions">
                    <!--
                      Praying button:
                      - data-id links to the prayer request
                      - data-count is the starting count (for JS increment)
                      - If member already prayed, .prayed class is added
                      - If not logged in, JS shows a tooltip
                    -->
                    <button class="btn-praying <?= $req['member_prayed'] ? 'prayed' : '' ?>"
                            data-id="<?= $req['id'] ?>"
                            data-count="<?= $req['prayer_count'] ?>"
                            data-logged-in="<?= $is_logged_in ? '1' : '0' ?>"
                            aria-label="<?= $req['member_prayed'] ? 'You are praying for this request' : 'Pray for this request' ?>"
                            aria-pressed="<?= $req['member_prayed'] ? 'true' : 'false' ?>">
                      <i class="ti ti-hand-stop" aria-hidden="true"></i>
                      <span class="praying-label"><?= $req['member_prayed'] ? 'Praying' : 'Pray' ?></span>
                      <span class="praying-count" aria-label="<?= $req['prayer_count'] ?> people praying">
                        <?= $req['prayer_count'] ?>
                      </span>
                    </button>

                    <?php if (!$req['is_answered']): ?>
                    <!--
                      Answered toggle:
                      Only the original submitter would see this in a real app
                      (check member_id). Here we show it for all logged-in users
                      for demonstration purposes.
                    -->
                    <?php if ($is_logged_in): ?>
                    <button class="btn-answered"
                            data-id="<?= $req['id'] ?>"
                            aria-label="Mark this prayer as answered">
                      <i class="ti ti-circle-check" aria-hidden="true"></i>
                      Answered?
                    </button>
                    <?php endif; ?>
                    <?php endif; ?>
                  </div>
                </div>

              </div><!-- /card-content -->
            </article>
            <?php endforeach; ?>
          </div><!-- /prayer-cards -->

          <!-- No results state -->
          <div class="no-prayers" id="noPrayers" aria-live="polite">
            <i class="ti ti-search-off" aria-hidden="true"></i>
            <p>No prayer requests in this category yet.</p>
          </div>

          <!-- Guest prompt (shown when guest clicks Praying) -->
          <div class="guest-toast" id="guestToast" role="alert" aria-live="polite">
            <i class="ti ti-lock" aria-hidden="true"></i>
            <span>Please <a href="login.php">log in</a> to mark yourself as praying</span>
          </div>

        </section><!-- /prayer-wall-col -->

      </div><!-- /prayer-layout -->
    </div><!-- /section-wide -->
  </div><!-- /section -->


  <!-- ═══════════════════════════════════
       DAILY VERSE SECTION
       Pulls from $verse in data.php —
       the same verse shown on the home page.
  ════════════════════════════════════ -->
  <section class="verse-section section" aria-label="Daily scripture">
    <div class="section-narrow" style="text-align:center">
      <div class="section-tag">Today's Word</div>
      <h2 class="section-title">A <em>Scripture</em> to Carry With You</h2>
      <blockquote class="verse-card">
        <div class="verse-deco" aria-hidden="true">"</div>
        <p class="verse-text"><?= nl2br(htmlspecialchars(trim($verse['text']))) ?></p>
        <footer class="verse-ref">
          <span>— <?= htmlspecialchars($verse['ref']) ?></span>
        </footer>
      </blockquote>
      <p class="section-sub" style="margin:0 auto;margin-top:16px">
        Our pastoral team prays through the church prayer wall every Monday morning.
        Your request will be lifted before God.
      </p>
    </div>
  </section>


  <!-- ═══════════════════════════════════
       CTA SECTION
  ════════════════════════════════════ -->
  <section class="prayer-cta section" aria-label="Join the prayer community">
    <div class="section-narrow" style="text-align:center">
      <div class="section-tag">Get Connected</div>
      <h2 class="section-title">Want to Pray <em>Together?</em></h2>
      <p class="section-sub" style="margin:0 auto 28px">
        Join our monthly prayer nights and become part of our intercession team.
        There is power in corporate prayer.
      </p>
      <div class="cta-btns">
        <a href="events.php" class="btn-gold">
          <i class="ti ti-calendar-event" aria-hidden="true"></i>
          View Prayer Night Events
        </a>
        <a href="volunteers.php" class="btn-outline-navy-light">
          <i class="ti ti-heart-handshake" aria-hidden="true"></i>
          Join the Prayer Team
        </a>
      </div>
    </div>
  </section>

</main>

<?php require_once 'includes/footer.php'; ?>

<?php
/* ═══════════════════════════════════════
   sermons.php  –  SERMONS ARCHIVE PAGE

   HOW THIS FILE WORKS:
   1. session_start()    – reads PHP session (for auth state)
   2. require data.php   – loads $announcements (ticker)
   3. $sermons array     – all sermon data (replaces DB later)
   4. $series_list       – unique series for tiles + filter
   5. $speakers_list     – unique speakers for filter + cards
   6. Hero + stats       – banner with totals
   7. Breadcrumb         – Home > Sermons
   8. Live banner        – shown when is_live = 1 (DB flag)
   9. Tabs               – All / By Series / By Speaker
   10. All tab           – search bar, featured card, grid, pagination
   11. By Series tab     – series tiles that pre-filter All tab
   12. By Speaker tab    – speaker cards that pre-filter All tab

   DATABASE NOTE:
   The $sermons array below simulates a SERMON table.
   When you add a real DB, replace it with:
     $stmt = $pdo->query("SELECT * FROM SERMON ORDER BY date DESC");
     $sermons = $stmt->fetchAll(PDO::FETCH_ASSOC);
═══════════════════════════════════════ */
session_start();
require_once 'includes/data.php';
require_once 'includes/db.php';   // $pdo – sermons now come from the SERMON table

/* ── AUTH STATE ─────────────────────── */
$is_logged_in = isset($_SESSION['member_id']);
$user_name    = $is_logged_in ? htmlspecialchars($_SESSION['member_name'] ?? 'Member') : '';
$user_role    = $is_logged_in ? ($_SESSION['role'] ?? 'member') : '';

/* ── PAGE META ──────────────────────── */
$page_title = 'Sermons | Shammah Urban Church';
$page_desc  = 'Watch, listen or read sermons from Shammah Urban Church in Pretoria. Browse our full archive by series, speaker, or topic.';

/* ══════════════════════════════════════
   SERMON DATA ARRAY
   Each entry = one row in the SERMON table.
   FIELDS:
   id          – unique sermon ID (DB primary key later)
   title       – sermon title shown on card
   speaker     – preacher name
   series      – sermon series name
   scripture   – Bible reference (e.g. John 3:16)
   date        – date string shown to users
   year        – used by the Year filter dropdown
   duration    – e.g. "52 min"
   desc        – short description (1–2 sentences)
   video_url   – YouTube or internal video link
   notes_url   – PDF notes file (null = not ready yet)
   is_live     – 1 = currently streaming, 0 = recorded
   popular     – true = show 🔥 badge
══════════════════════════════════════ */
$sermons_fallback = [   // only used if the SERMON table is empty
  [
    'id'        => 1,
    'title'     => 'Walking in Faith',
    'speaker'   => 'Pastor Sithole',
    'series'    => 'Foundations of Faith',
    'scripture' => 'Hebrews 11:1-6',
    'date'      => '18 May 2025',
    'year'      => '2025',
    'duration'  => '52 min',
    'desc'      => 'A powerful message on trusting God through seasons of uncertainty, drawn from the faith hall of fame in Hebrews 11.',
    'video_url' => '',
    'notes_url' => null,
    'is_live'   => 0,
    'popular'   => true,
  ],
  [
    'id'        => 2,
    'title'     => "God's Grace Renewed",
    'speaker'   => 'Pastor Sithole',
    'series'    => 'Foundations of Faith',
    'scripture' => 'Lamentations 3:22-23',
    'date'      => '11 May 2025',
    'year'      => '2025',
    'duration'  => '48 min',
    'desc'      => 'Every morning brings a fresh measure of God\'s mercy. This message unpacks the inexhaustible grace available to every believer.',
    'video_url' => '',
    'notes_url' => null,
    'is_live'   => 0,
    'popular'   => false,
  ],
  [
    'id'        => 3,
    'title'     => 'Praying Without Ceasing',
    'speaker'   => 'Elder Nkosi',
    'series'    => 'Prayer Life',
    'scripture' => '1 Thessalonians 5:17',
    'date'      => '4 May 2025',
    'year'      => '2025',
    'duration'  => '35 min',
    'desc'      => 'What does it really mean to pray continually? Elder Nkosi unpacks the lifestyle of prayer and its transforming power.',
    'video_url' => '',
    'notes_url' => null,
    'is_live'   => 0,
    'popular'   => true,
  ],
  [
    'id'        => 4,
    'title'     => 'The Power of Community',
    'speaker'   => 'Pastor Sithole',
    'series'    => 'Church Family',
    'scripture' => 'Acts 2:42-47',
    'date'      => '27 Apr 2025',
    'year'      => '2025',
    'duration'  => '55 min',
    'desc'      => 'The early church thrived because they were devoted to one another. This message calls us back to authentic community.',
    'video_url' => '',
    'notes_url' => null,
    'is_live'   => 0,
    'popular'   => false,
  ],
  [
    'id'        => 5,
    'title'     => 'Hope in the Desert',
    'speaker'   => 'Grace Mokoena',
    'series'    => 'Women of Purpose',
    'scripture' => 'Isaiah 40:31',
    'date'      => '20 Apr 2025',
    'year'      => '2025',
    'duration'  => '42 min',
    'desc'      => 'A moving message on finding strength and renewed hope in the most difficult seasons of life.',
    'video_url' => '',
    'notes_url' => null,
    'is_live'   => 0,
    'popular'   => false,
  ],
  [
    'id'        => 6,
    'title'     => 'Warfare in the Spirit',
    'speaker'   => 'Elder Nkosi',
    'series'    => 'Prayer Life',
    'scripture' => 'Ephesians 6:10-18',
    'date'      => '13 Apr 2025',
    'year'      => '2025',
    'duration'  => '47 min',
    'desc'      => 'Unpacking the full armour of God and how to stand firm against spiritual opposition in everyday life.',
    'video_url' => '',
    'notes_url' => null,
    'is_live'   => 0,
    'popular'   => false,
  ],
  [
    'id'        => 7,
    'title'     => 'Raising Godly Children',
    'speaker'   => 'Grace Mokoena',
    'series'    => 'Women of Purpose',
    'scripture' => 'Proverbs 22:6',
    'date'      => '6 Apr 2025',
    'year'      => '2025',
    'duration'  => '38 min',
    'desc'      => 'Practical biblical wisdom for mothers and parents on how to nurture faith in the next generation.',
    'video_url' => '',
    'notes_url' => null,
    'is_live'   => 0,
    'popular'   => false,
  ],
  [
    'id'        => 8,
    'title'     => 'The Restored Church',
    'speaker'   => 'Pastor Sithole',
    'series'    => 'Church Family',
    'scripture' => 'Nehemiah 2:17-18',
    'date'      => '30 Mar 2025',
    'year'      => '2025',
    'duration'  => '50 min',
    'desc'      => 'Like Nehemiah rebuilding Jerusalem\'s walls, God calls His people to rebuild broken things with prayer and purpose.',
    'video_url' => '',
    'notes_url' => null,
    'is_live'   => 0,
    'popular'   => true,
  ],
];


/* ══════════════════════════════════════
   WATCH / LISTEN / NOTES LINKS
   ──────────────────────────────────────
   Set your church's YouTube channel here. It is used whenever a
   sermon has no direct video link yet: the Watch button then opens
   a search for that sermon's title INSIDE your channel, so people
   can still find the video.
══════════════════════════════════════ */
/* TEMPORARY: until the church has its own YouTube channel, Watch/Listen send people to
   Hillsong Africa (a South African church channel) so they can find church content.
   WHEN YOU CREATE YOUR OWN CHANNEL:
     1. put its URL in $YT_CHANNEL
     2. set $YT_SEARCH_IN_CHANNEL = true  (Watch then searches your channel for each sermon title)
     3. better still, paste each sermon's video link into SERMON.video_url (plays in the pop-up) */
$YT_CHANNEL           = 'https://www.youtube.com/channel/UCygJwTvQL4N7zU1ch9a23hg';   // Hillsong Africa
$YT_SEARCH_IN_CHANNEL = false;

function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES); }

/* Only allow http(s) links, or a relative file path (e.g. notes/x.pdf). Blocks javascript: etc. */
function safe_url(?string $u): string {
  $u = trim((string) $u);
  if ($u === '') return '';
  if (preg_match('~^https?://~i', $u)) return $u;
  if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $u) || str_starts_with($u, '//')) return '';
  return $u;
}

/* Pull the 11-character YouTube video ID out of watch / youtu.be / live / shorts / embed links */
function yt_id(string $u): ?string {
  return preg_match('~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^#]*&)?v=|embed/|live/|shorts/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $u, $m)
         ? $m[1] : null;
}

/* Work out what the Watch / Listen buttons should do for one sermon */
function resolve_media(array $s, string $channel, bool $searchInChannel): array {
  $vu    = safe_url($s['video_url'] ?? '');
  // db_setup.sql seeded a made-up channel (@shammahurbanchurch) that does not exist – treat it as 'no link yet'
  if (stripos($vu, '@shammahurbanchurch') !== false) { $vu = ''; }
  $id    = $vu ? yt_id($vu) : null;
  $embed = '';
  if ($id) {
    // A real video → plays inside the page
    $embed = 'https://www.youtube-nocookie.com/embed/' . $id . '?autoplay=1&rel=0';
    $href  = 'https://www.youtube.com/watch?v=' . $id;
  } else {
    $base = $vu ?: $channel;
    // Channel / playlist link → search the channel for this sermon's title
    $isChannel = preg_match('~youtube\.com/(@|channel/|c/|user/)~i', $base) && strpos($base, '/search') === false;
    $href = !$isChannel ? $base
          : rtrim($base, '/') . ($searchInChannel ? '/search?query=' . rawurlencode($s['title']) : '/videos');
  }
  $s['embed']     = $embed;
  $s['watch_href'] = $href;
  $s['audio_url'] = safe_url($s['audio_url'] ?? '');
  $s['notes_url'] = safe_url($s['notes_url'] ?? '');
  return $s;
}

/* ── LOAD SERMONS FROM THE DB (newest first) ── */
$sermons = [];
foreach ($pdo->query('SELECT * FROM SERMON ORDER BY preached_on DESC, sermon_id DESC')->fetchAll() as $r) {
  $ts = strtotime($r['preached_on']);
  $sermons[] = [
    'id'        => (int) $r['sermon_id'],
    'title'     => $r['title'],
    'speaker'   => $r['speaker'],
    'series'    => $r['series'] !== '' ? $r['series'] : 'General',
    'scripture' => $r['scripture'] ?? '',            // optional column – hidden when empty
    'date'      => date('j M Y', $ts),
    'year'      => date('Y', $ts),
    'duration'  => (int) $r['duration_min'] . ' min',
    'desc'      => $r['description'],
    'video_url' => $r['video_url'],
    'audio_url' => $r['audio_url'],
    'notes_url' => $r['notes_url'],
    'is_live'   => 0,
    'popular'   => false,
  ];
}
if (!$sermons) { $sermons = $sermons_fallback; }
$sermons = array_map(fn($s) => resolve_media($s, $YT_CHANNEL, $YT_SEARCH_IN_CHANNEL), $sermons);

/* ── Action buttons (Watch / Listen / Notes / Share) – used by the featured card AND every grid card ── */
function sermon_actions(array $s, bool $featured): void {
  $c = $featured
     ? ['watch' => 'btn-watch', 'listen' => 'btn-listen', 'notes' => 'btn-notes', 'share' => 'btn-share']
     : ['watch' => 'c-btn watch', 'listen' => 'c-btn listen', 'notes' => 'c-btn notes', 'share' => 'c-btn listen'];
  $t = h($s['title']);
  ?>
  <!-- WATCH: plays in a pop-up if we have the video, otherwise opens YouTube -->
  <a id="watch-<?= $s['id'] ?>" href="<?= h($s['watch_href']) ?>" target="_blank" rel="noopener noreferrer"
     class="<?= $c['watch'] ?> js-watch" data-embed="<?= h($s['embed']) ?>" data-title="<?= $t ?>"
     aria-label="Watch <?= $t ?> on YouTube">
    <i class="ti ti-player-play-filled" aria-hidden="true"></i> Watch
  </a>

  <!-- LISTEN: real audio file if we have one, otherwise the video's audio on YouTube -->
  <?php if ($s['audio_url']): ?>
    <button type="button" class="<?= $c['listen'] ?> listen-btn" aria-label="Listen to <?= $t ?>">
      <i class="ti ti-headphones" aria-hidden="true"></i> Listen
    </button>
  <?php else: ?>
    <a href="<?= h($s['watch_href']) ?>" target="_blank" rel="noopener noreferrer"
       class="<?= $c['listen'] ?> js-watch" data-embed="<?= h($s['embed']) ?>" data-title="<?= $t ?>" data-audio="1"
       aria-label="Listen to <?= $t ?> on YouTube">
      <i class="ti ti-headphones" aria-hidden="true"></i> Listen
    </a>
  <?php endif; ?>

  <!-- NOTES: the church's own file if uploaded, otherwise a printable notes sheet -->
  <a href="<?= h($s['notes_url'] ?: 'sermon_notes.php?id=' . $s['id']) ?>" target="_blank" rel="noopener noreferrer"
     class="<?= $c['notes'] ?>" aria-label="Open sermon notes for <?= $t ?>">
    <i class="ti ti-file-text" aria-hidden="true"></i> Notes
  </a>

  <button type="button" class="<?= $c['share'] ?> share-btn" data-title="<?= $t ?>"
          aria-label="Share this sermon"<?= $featured ? '' : ' style="margin-left:auto"' ?>>
    <i class="ti ti-share" aria-hidden="true"></i>
  </button>
  <?php if (!$featured && !empty($s['popular'])): ?>
    <span class="popular-badge" aria-label="Popular sermon">🔥 Popular</span>
  <?php endif; ?>
  <?php
}

/* Inline audio player (only rendered when the sermon has an audio file) */
function sermon_audio(array $s): void {
  if (!$s['audio_url']) return; ?>
  <div class="audio-player" aria-label="Audio player">
    <span class="audio-title"><?= h($s['title']) ?></span>
    <audio controls preload="none" src="<?= h($s['audio_url']) ?>">Your browser does not support audio playback.</audio>
  </div>
  <?php
}

/* ── DERIVED DATA FOR FILTERS ───────────
   We extract unique series and speakers
   from the $sermons array so the dropdowns
   and tiles always reflect real data.
   In a real DB this would be:
     SELECT DISTINCT series FROM SERMON
──────────────────────────────────────── */
$series_names  = array_unique(array_column($sermons, 'series'));
$speaker_names = array_unique(array_column($sermons, 'speaker'));
$years         = array_unique(array_column($sermons, 'year'));
rsort($years); // newest year first

/* ── SERIES METADATA ─────────────────── */
/*
   Each series has an icon, accent colour,
   and description for the "By Series" tiles.
   Add entries here when you add new series.
*/
$series_meta = [
  'Foundations of Faith' => [
    'icon'  => '⛪',
    'color' => '#3D0C2F',
    'desc'  => 'Core messages on faith, trust, and walking with God.',
  ],
  'Prayer Life' => [
    'icon'  => '🙏',
    'color' => '#378ADD',
    'desc'  => 'Deep dives into a life of prayer and intercession.',
  ],
  'Church Family' => [
    'icon'  => '👨‍👩‍👧‍👦',
    'color' => '#639922',
    'desc'  => 'Building community, belonging, and the body of Christ.',
  ],
  'Women of Purpose' => [
    'icon'  => '👩‍💼',
    'color' => '#C9A84C',
    'desc'  => 'Empowering messages for women of faith and influence.',
  ],
];

/* ── SPEAKER METADATA ───────────────── */
$speaker_meta = [
  'Pastor Sithole' => [
    'role'    => 'Senior Pastor',
    'initials'=> 'PS',
    'count'   => 0, // calculated below
  ],
  'Elder Nkosi' => [
    'role'    => 'Elder & Bible Teacher',
    'initials'=> 'EN',
    'count'   => 0,
  ],
  'Grace Mokoena' => [
    'role'    => 'Women\'s Ministry Leader',
    'initials'=> 'GM',
    'count'   => 0,
  ],
];
/* Count sermons per speaker */
foreach ($sermons as $s) {
  if (isset($speaker_meta[$s['speaker']])) {
    $speaker_meta[$s['speaker']]['count']++;
  }
}

/* ── PAGE STATS ─────────────────────── */
$total_sermons = count($sermons);
$total_series  = count($series_names);
$total_speakers = count($speaker_names);

/* ── IS LIVE? ─────────────────────────
   In a real DB: SELECT * FROM SERMON WHERE is_live = 1 LIMIT 1
   Here we just check the array.
──────────────────────────────────────── */
$live_sermon = null;
foreach ($sermons as $s) {
  if ($s['is_live']) { $live_sermon = $s; break; }
}

/* ── FEATURED SERMON (latest) ─────────
   First item in the array = most recent.
──────────────────────────────────────── */
$featured = $sermons[0];

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- ══════════════ PAGE MAIN ══════════════ -->
<main id="main-content">

  <!-- ═══════════════════════════════════
       HERO BANNER
  ════════════════════════════════════ -->
  <section class="sermons-hero" aria-label="Sermons at Shammah Urban Church">
    <div class="sermons-hero-pattern" aria-hidden="true"></div>
    <div class="sermons-hero-inner">
      <div class="hero-eyebrow">Word &amp; Worship</div>
      <h1>Sermon <em>Archive</em></h1>
      <p class="sermons-hero-sub">
        Watch, listen or read past and live sermons from Shammah Urban Church.
        Grow in your faith anytime, anywhere.
      </p>
      <!-- Three quick stats in the hero -->
      <div class="sermons-hero-stats" aria-label="Sermon library statistics">
        <div class="hero-stat">
          <strong><?= $total_sermons ?>+</strong>
          <span>Sermons</span>
        </div>
        <div class="hero-stat-divider" aria-hidden="true"></div>
        <div class="hero-stat">
          <strong><?= $total_series ?></strong>
          <span>Series</span>
        </div>
        <div class="hero-stat-divider" aria-hidden="true"></div>
        <div class="hero-stat">
          <strong><?= $total_speakers ?></strong>
          <span>Speakers</span>
        </div>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════════════════
       BREADCRUMB — Home > Sermons
       Helps users know where they are in
       the site and navigate back easily.
  ════════════════════════════════════ -->
  <nav class="breadcrumb-bar" aria-label="Breadcrumb">
    <ol class="breadcrumb">
      <li><a href="index.php"><i class="ti ti-home" aria-hidden="true"></i> Home</a></li>
      <li aria-current="page">Sermons</li>
    </ol>
  </nav>

  <!-- ═══════════════════════════════════
       LIVE STREAM BANNER
       This block is only shown when a
       sermon is currently streaming live
       (is_live = 1 in the SERMON table).
       When no sermon is live, PHP outputs
       nothing here — the banner is invisible.
  ════════════════════════════════════ -->
  <?php if ($live_sermon): ?>
  <div class="live-banner" role="status" aria-live="polite">
    <div class="live-banner-left">
      <span class="live-dot" aria-hidden="true"></span>
      <span class="live-text">
        🔴 Live Now — <?= htmlspecialchars($live_sermon['title']) ?>
        <span>with <?= htmlspecialchars($live_sermon['speaker']) ?></span>
      </span>
    </div>
    <a href="<?= htmlspecialchars($live_sermon['video_url']) ?>"
       class="live-join-btn"
       target="_blank"
       rel="noopener noreferrer">
      <i class="ti ti-player-play-filled" aria-hidden="true"></i>
      Join Live Stream
    </a>
  </div>
  <?php endif; ?>

  <!-- ═══════════════════════════════════
       TABS BAR
       Three tabs: All Sermons / By Series
       / By Speaker. Sticky below the nav
       so it stays visible while scrolling.
  ════════════════════════════════════ -->
  <div class="sermons-tabs-wrap">
    <div class="sermons-tabs" role="tablist" aria-label="Sermon browse options">
      <!--
        data-tab="all" links this button to <div id="tab-all">.
        JS in sermons.js toggles the active class.
        aria-selected / role="tab" = screen-reader accessibility.
      -->
      <button class="s-tab active"
              data-tab="all"
              role="tab"
              aria-selected="true"
              aria-controls="tab-all">
        <i class="ti ti-microphone" aria-hidden="true"></i>
        All Sermons
      </button>
      <button class="s-tab"
              data-tab="series"
              role="tab"
              aria-selected="false"
              aria-controls="tab-series">
        <i class="ti ti-bookmark" aria-hidden="true"></i>
        By Series
      </button>
      <button class="s-tab"
              data-tab="speaker"
              role="tab"
              aria-selected="false"
              aria-controls="tab-speaker">
        <i class="ti ti-users" aria-hidden="true"></i>
        By Speaker
      </button>
    </div>
  </div>

  <!-- ═══════════════════════════════════
       MAIN CONTENT WRAPPER
  ════════════════════════════════════ -->
  <div class="section">
    <div class="section-wide">

      <!-- ══════════════════════════════
           TAB 1 — ALL SERMONS
           Contains: search/filter bar,
           featured card, sermon grid,
           and pagination.
      ═══════════════════════════════ -->
      <div class="tab-panel active" id="tab-all" role="tabpanel" aria-labelledby="tab-all-btn">

        <!-- ── SEARCH & FILTER BAR ─── -->
        <div class="filter-bar" role="search" aria-label="Search and filter sermons">
          <!-- Search input -->
          <div class="search-wrap">
            <i class="ti ti-search" aria-hidden="true"></i>
            <input type="search"
                   id="sermonSearch"
                   placeholder="Search by title or speaker…"
                   aria-label="Search sermons by title or speaker"
                   autocomplete="off">
          </div>

          <!-- Series dropdown filter -->
          <select id="seriesFilter" class="filter-select" aria-label="Filter by series">
            <option value="">All Series</option>
            <?php foreach ($series_names as $sname): ?>
              <option value="<?= htmlspecialchars($sname) ?>">
                <?= htmlspecialchars($sname) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <!-- Year dropdown filter -->
          <select id="yearFilter" class="filter-select" aria-label="Filter by year">
            <option value="">All Years</option>
            <?php foreach ($years as $yr): ?>
              <option value="<?= htmlspecialchars($yr) ?>"><?= $yr ?></option>
            <?php endforeach; ?>
          </select>

          <!-- Speaker dropdown filter -->
          <select id="speakerFilter" class="filter-select" aria-label="Filter by speaker">
            <option value="">All Speakers</option>
            <?php foreach ($speaker_names as $spkr): ?>
              <option value="<?= htmlspecialchars($spkr) ?>">
                <?= htmlspecialchars($spkr) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <!-- Clear filters link (shown by JS when a filter is active) -->
          <button class="filter-clear" id="clearFilters" aria-label="Clear all filters">
            <i class="ti ti-x" aria-hidden="true"></i> Clear
          </button>
        </div>

        <!-- Results count (updated by JS) -->
        <p class="results-count" id="resultsCount" aria-live="polite"></p>

        <!-- ── FEATURED / LATEST SERMON ─
             The most recent sermon gets a
             larger hero card at the top.
        ──────────────────────────────── -->
        <article class="featured-sermon" aria-label="Latest sermon: <?= htmlspecialchars($featured['title']) ?>">
          <!-- Left: thumbnail / play area -->
          <div class="featured-thumb">
            <!-- DB NOTE: replace this div with an <img> or YouTube embed
                 when real video thumbnails are available:
                 <img src="<?= htmlspecialchars($featured['thumb_url'] ?? '') ?>"
                      alt="Thumbnail for <?= htmlspecialchars($featured['title']) ?>"> -->
            <span class="sermon-badge latest" aria-label="Latest sermon">● LATEST</span>
            <div class="play-circle" role="button" tabindex="0" aria-label="Watch sermon" data-watch-for="watch-<?= $featured['id'] ?>">
              <i class="ti ti-player-play-filled" aria-hidden="true"></i>
            </div>
            <span class="thumb-label">Video Thumbnail</span>
          </div>

          <!-- Right: sermon info -->
          <div class="featured-info">
            <div class="featured-series">
              <i class="ti ti-bookmark" aria-hidden="true"></i>
              <?= htmlspecialchars($featured['series']) ?>
            </div>
            <h2 class="featured-title"><?= htmlspecialchars($featured['title']) ?></h2>

            <!-- Scripture reference — key differentiator for a church site -->
            <?php if ($featured['scripture']): ?>
            <div class="featured-scripture">
              <i class="ti ti-bible" aria-hidden="true"></i>
              <?= htmlspecialchars($featured['scripture']) ?>
            </div>
            <?php endif; ?>

            <!-- Speaker · Date · Duration -->
            <div class="featured-meta">
              <span>
                <i class="ti ti-user" aria-hidden="true"></i>
                <?= htmlspecialchars($featured['speaker']) ?>
              </span>
              <span>
                <i class="ti ti-calendar" aria-hidden="true"></i>
                <?= htmlspecialchars($featured['date']) ?>
              </span>
              <span>
                <i class="ti ti-clock" aria-hidden="true"></i>
                <?= htmlspecialchars($featured['duration']) ?>
              </span>
            </div>

            <p class="featured-desc"><?= htmlspecialchars($featured['desc']) ?></p>

            <div class="sermon-actions">
              <?php sermon_actions($featured, true); ?>
            </div>

            <?php sermon_audio($featured); ?>
          </div>
        </article>

        <!-- ── SERMON GRID ─────────────
             2-column grid of all sermons
             (excluding the featured one).
             JS handles show/hide on filter.
        ──────────────────────────────── -->
        <div class="sermons-grid" id="sermonsGrid">
          <?php
          /*
           * We skip index 0 (the featured sermon shown above)
           * and loop through the rest.
           * Each card has data-* attributes for JS filtering.
           */
          foreach ($sermons as $index => $sermon):
            if ($index === 0) continue; // skip featured
          ?>
          <article class="sermon-card"
                   data-title="<?= htmlspecialchars(strtolower($sermon['title'])) ?>"
                   data-speaker="<?= htmlspecialchars($sermon['speaker']) ?>"
                   data-series="<?= htmlspecialchars($sermon['series']) ?>"
                   data-year="<?= htmlspecialchars($sermon['year']) ?>"
                   aria-label="Sermon: <?= htmlspecialchars($sermon['title']) ?>">

            <!-- Left: play icon thumbnail -->
            <div class="card-thumb" role="button" tabindex="0" aria-label="Play sermon" data-watch-for="watch-<?= $sermon['id'] ?>">
              <i class="ti ti-player-play-filled" aria-hidden="true"></i>
            </div>

            <!-- Right: text info -->
            <div class="card-body">
              <!-- Series name in gold -->
              <div class="card-series">
                <?= htmlspecialchars($sermon['series']) ?>
              </div>

              <!-- Title -->
              <h3 class="card-title"><?= htmlspecialchars($sermon['title']) ?></h3>

              <!-- Scripture reference -->
              <?php if ($sermon['scripture']): ?>
              <div class="card-scripture">
                <i class="ti ti-bible" aria-hidden="true"></i>
                <?= htmlspecialchars($sermon['scripture']) ?>
              </div>
              <?php endif; ?>

              <!-- Speaker / Date / Duration -->
              <div class="card-meta">
                <span>
                  <i class="ti ti-user" aria-hidden="true"></i>
                  <?= htmlspecialchars($sermon['speaker']) ?>
                </span>
                <span>
                  <i class="ti ti-calendar" aria-hidden="true"></i>
                  <?= htmlspecialchars($sermon['date']) ?>
                </span>
                <span>
                  <i class="ti ti-clock" aria-hidden="true"></i>
                  <?= htmlspecialchars($sermon['duration']) ?>
                </span>
              </div>

              <div class="card-actions">
                <?php sermon_actions($sermon, false); ?>
              </div>

              <?php sermon_audio($sermon); ?>
            </div>
          </article>
          <?php endforeach; ?>
        </div>

        <!-- No results message (shown by JS when filters return 0 cards) -->
        <div class="no-results" id="noResults" aria-live="polite">
          <i class="ti ti-search-off" aria-hidden="true"></i>
          <p>No sermons match your search. Try a different keyword or clear the filters.</p>
        </div>

        <!-- ── PAGINATION ──────────────
             JS renders the ← 1 2 3 → buttons
             inside #paginationBtns.
             "Showing X–Y of Z" text goes in
             #paginationInfo.
        ──────────────────────────────── -->
        <div class="pagination-wrap">
          <span class="pagination-info" id="paginationInfo" aria-live="polite"></span>
          <div class="pagination" id="paginationBtns" role="navigation" aria-label="Sermon page navigation"></div>
        </div>

      </div><!-- /tab-all -->


      <!-- ══════════════════════════════
           TAB 2 — BY SERIES
           Grid of series tiles.
           Clicking a tile switches to the
           All tab and pre-filters by series.
      ═══════════════════════════════ -->
      <div class="tab-panel" id="tab-series" role="tabpanel" aria-labelledby="tab-series-btn">

        <div class="section-tag">Browse by Series</div>
        <h2 class="section-title" style="margin-bottom:8px">
          Sermon <em>Series</em>
        </h2>
        <p class="section-sub" style="margin-bottom:32px">
          Each series dives deep into a specific theme or book of the Bible.
          Click any series to browse its messages.
        </p>

        <div class="series-grid">
          <?php
          /*
           * Loop through each unique series name.
           * $series_meta holds the icon, colour, and description.
           * $sermon_count counts how many sermons belong to the series.
           */
          foreach ($series_names as $sname):
            $meta  = $series_meta[$sname] ?? ['icon' => '📖', 'color' => '#3D0C2F', 'desc' => ''];
            $count = count(array_filter($sermons, fn($s) => $s['series'] === $sname));
          ?>
          <div class="series-tile"
               data-series="<?= htmlspecialchars($sname) ?>"
               role="button"
               tabindex="0"
               aria-label="Browse <?= htmlspecialchars($sname) ?> series — <?= $count ?> sermons">
            <!-- Coloured stripe across the top of the tile -->
            <div class="series-stripe" style="background:<?= $meta['color'] ?>"></div>
            <div class="series-tile-body">
              <div class="series-icon" aria-hidden="true"><?= $meta['icon'] ?></div>
              <div class="series-name"><?= htmlspecialchars($sname) ?></div>
              <div class="series-count"><?= $count ?> sermon<?= $count !== 1 ? 's' : '' ?></div>
              <p class="series-desc"><?= htmlspecialchars($meta['desc']) ?></p>
              <div class="series-cta">
                Browse series
                <i class="ti ti-arrow-right" aria-hidden="true"></i>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

      </div><!-- /tab-series -->


      <!-- ══════════════════════════════
           TAB 3 — BY SPEAKER
           Cards for each preacher.
           Clicking a card switches to All
           tab and filters by that speaker.
      ═══════════════════════════════ -->
      <div class="tab-panel" id="tab-speaker" role="tabpanel" aria-labelledby="tab-speaker-btn">

        <div class="section-tag">Our Preachers</div>
        <h2 class="section-title" style="margin-bottom:8px">
          Browse by <em>Speaker</em>
        </h2>
        <p class="section-sub" style="margin-bottom:32px">
          Explore messages from each of our pastors, elders, and ministry leaders.
        </p>

        <div class="speakers-grid">
          <?php
          /*
           * Loop through each unique speaker.
           * $speaker_meta holds role, initials, and sermon count.
           */
          foreach ($speaker_names as $spkr):
            $sm = $speaker_meta[$spkr] ?? ['role' => 'Speaker', 'initials' => strtoupper(substr($spkr,0,2)), 'count' => 0];
          ?>
          <div class="speaker-card"
               data-speaker="<?= htmlspecialchars($spkr) ?>"
               role="button"
               tabindex="0"
               aria-label="Browse sermons by <?= htmlspecialchars($spkr) ?>">
            <!-- Avatar circle with initials -->
            <div class="speaker-avatar" aria-hidden="true">
              <?= htmlspecialchars($sm['initials']) ?>
            </div>
            <div class="speaker-name"><?= htmlspecialchars($spkr) ?></div>
            <div class="speaker-role"><?= htmlspecialchars($sm['role']) ?></div>
            <div class="speaker-count">
              <?= $sm['count'] ?> sermon<?= $sm['count'] !== 1 ? 's' : '' ?>
            </div>
            <div class="speaker-browse">
              View sermons
              <i class="ti ti-arrow-right" aria-hidden="true"></i>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

      </div><!-- /tab-speaker -->

    </div><!-- /section-wide -->
  </div><!-- /section -->

</main>

<!-- ═══════════════════════════════════
     VIDEO POP-UP  (opened by Watch / Listen)
     The iframe is emptied on close so playback stops.
════════════════════════════════════ -->
<div class="video-modal" id="videoModal" role="dialog" aria-modal="true" aria-labelledby="videoModalTitle" hidden>
  <div class="video-modal-box">
    <div class="video-modal-head">
      <h3 id="videoModalTitle"></h3>
      <button type="button" class="video-modal-close" id="videoModalClose" aria-label="Close video">
        <i class="ti ti-x" aria-hidden="true"></i>
      </button>
    </div>
    <div class="video-modal-frame">
      <iframe id="videoModalFrame" title="Sermon video" src="" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
    </div>
    <a id="videoModalYT" class="video-modal-yt" href="#" target="_blank" rel="noopener noreferrer">
      <i class="ti ti-brand-youtube" aria-hidden="true"></i> Open on YouTube
    </a>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
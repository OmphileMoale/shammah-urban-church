<?php
/* ═══════════════════════════════════════
   gallery.php  –  PHOTO GALLERY PAGE

   Displays a masonry grid of church
   photos organised by category.
   Includes a lightbox for full-view and
   filter tabs to browse by ministry/event.
═══════════════════════════════════════ */
session_start();

require_once 'includes/data.php';

$is_logged_in = isset($_SESSION['member_id']);
$user_name    = $is_logged_in ? htmlspecialchars($_SESSION['member_name'] ?? 'Member') : '';
$user_role    = $is_logged_in ? ($_SESSION['role'] ?? 'member') : '';

$page_title = 'Gallery | Shammah Urban Church';
$page_desc  = 'Browse photos from worship services, youth events, outreach programmes and community life at Shammah Urban Church in Pretoria East.';

/* ── GALLERY ITEMS ───────────────────────
   Each item has:
   - title, desc: shown in the lightbox
   - category: used for filter tabs (slug)
   - cat_label: display name for badge
   - badge_class: CSS modifier for badge colour
   - hue / accent: used to generate the SVG illustration
   - height: controls masonry card height variety
──────────────────────────────────────── */
$gallery_items = [

  /* WORSHIP */
  [
    'title'       => 'Sunday Morning Worship',
    'desc'        => 'Our congregation gathered in the main sanctuary for the 9:00 AM Sunday service, lifting voices in praise.',
    'category'    => 'worship',
    'cat_label'   => 'Worship',
    'badge_class' => 'gi-badge-worship',
    'bg'          => '#3D0C2F',
    'accent'      => '#C9A84C',
    'icon'        => 'music',
    'height'      => 260,
  ],
  [
    'title'       => 'Praise & Worship Band',
    'desc'        => 'The worship team leading the congregation through contemporary and traditional hymns.',
    'category'    => 'worship',
    'cat_label'   => 'Worship',
    'badge_class' => 'gi-badge-worship',
    'bg'          => '#5C1A47',
    'accent'      => '#F0D98A',
    'icon'        => 'microphone',
    'height'      => 320,
  ],
  [
    'title'       => 'Candlelight Christmas Service',
    'desc'        => 'A special candlelit service celebrating the birth of Christ with the full congregation.',
    'category'    => 'worship',
    'cat_label'   => 'Worship',
    'badge_class' => 'gi-badge-worship',
    'bg'          => '#280820',
    'accent'      => '#FFD700',
    'icon'        => 'flame',
    'height'      => 240,
  ],

  /* YOUTH */
  [
    'title'       => 'Youth Friday Night',
    'desc'        => 'Young people connecting, worshipping and growing together every Friday at 6:00 PM.',
    'category'    => 'youth',
    'cat_label'   => 'Youth',
    'badge_class' => 'gi-badge-youth',
    'bg'          => '#1a3a6e',
    'accent'      => '#64b5f6',
    'icon'        => 'bolt',
    'height'      => 300,
  ],
  [
    'title'       => 'Youth Camp 2024',
    'desc'        => 'An unforgettable weekend camp where our youth deepened their faith and formed lasting friendships.',
    'category'    => 'youth',
    'cat_label'   => 'Youth',
    'badge_class' => 'gi-badge-youth',
    'bg'          => '#0d47a1',
    'accent'      => '#90caf9',
    'icon'        => 'tent',
    'height'      => 260,
  ],
  [
    'title'       => 'Youth Praise Night',
    'desc'        => 'A high-energy evening of contemporary worship led entirely by our youth ministry team.',
    'category'    => 'youth',
    'cat_label'   => 'Youth',
    'badge_class' => 'gi-badge-youth',
    'bg'          => '#283593',
    'accent'      => '#c5cae9',
    'icon'        => 'stars',
    'height'      => 350,
  ],

  /* OUTREACH */
  [
    'title'       => 'Community Food Drive',
    'desc'        => 'Volunteers distributing food parcels to families in need across Pretoria East — every first Saturday.',
    'category'    => 'outreach',
    'cat_label'   => 'Outreach',
    'badge_class' => 'gi-badge-outreach',
    'bg'          => '#1b5e20',
    'accent'      => '#a5d6a7',
    'icon'        => 'heart',
    'height'      => 270,
  ],
  [
    'title'       => 'School Supplies Drive',
    'desc'        => 'Equipping underprivileged children with stationery and school bags ahead of the new academic year.',
    'category'    => 'outreach',
    'cat_label'   => 'Outreach',
    'badge_class' => 'gi-badge-outreach',
    'bg'          => '#2e7d32',
    'accent'      => '#c8e6c9',
    'icon'        => 'school',
    'height'      => 230,
  ],
  [
    'title'       => 'Care Home Visits',
    'desc'        => 'Members bringing joy, prayer and fellowship to elderly residents in local care homes.',
    'category'    => 'outreach',
    'cat_label'   => 'Outreach',
    'badge_class' => 'gi-badge-outreach',
    'bg'          => '#388e3c',
    'accent'      => '#dcedc8',
    'icon'        => 'users',
    'height'      => 290,
  ],

  /* EVENTS */
  [
    'title'       => 'Annual Church Picnic',
    'desc'        => 'Families gathering for a day of food, games and fellowship at the park — a beloved church tradition.',
    'category'    => 'events',
    'cat_label'   => 'Events',
    'badge_class' => 'gi-badge-events',
    'bg'          => '#3D0C2F',
    'accent'      => '#C9A84C',
    'icon'        => 'sun',
    'height'      => 250,
  ],
  [
    'title'       => 'Easter Celebration 2024',
    'desc'        => 'A powerful sunrise service and family celebration marking the resurrection of our Lord.',
    'category'    => 'events',
    'cat_label'   => 'Events',
    'badge_class' => 'gi-badge-events',
    'bg'          => '#4a0e35',
    'accent'      => '#F0D98A',
    'icon'        => 'cross',
    'height'      => 310,
  ],
  [
    'title'       => 'Women of Purpose Luncheon',
    'desc'        => 'Our women\'s ministry celebrating sisterhood, faith and purpose over a beautiful lunch gathering.',
    'category'    => 'events',
    'cat_label'   => 'Events',
    'badge_class' => 'gi-badge-events',
    'bg'          => '#5C1A47',
    'accent'      => '#f8bbd9',
    'icon'        => 'flower',
    'height'      => 270,
  ],

  /* LIFE */
  [
    'title'       => 'Water Baptism Sunday',
    'desc'        => 'Celebrating new beginnings as members publicly declared their faith through baptism.',
    'category'    => 'life',
    'cat_label'   => 'Church Life',
    'badge_class' => 'gi-badge-life',
    'bg'          => '#b34700',
    'accent'      => '#ffccbc',
    'icon'        => 'droplet',
    'height'      => 280,
  ],
  [
    'title'       => 'Cell Group Meeting',
    'desc'        => 'One of our 12 neighbourhood cell groups sharing a meal, scripture and prayer together.',
    'category'    => 'life',
    'cat_label'   => 'Church Life',
    'badge_class' => 'gi-badge-life',
    'bg'          => '#e65100',
    'accent'      => '#ffe0b2',
    'icon'        => 'home',
    'height'      => 240,
  ],
  [
    'title'       => 'New Members Welcome',
    'desc'        => 'Welcoming our newest family members into the Shammah Urban Church community.',
    'category'    => 'life',
    'cat_label'   => 'Church Life',
    'badge_class' => 'gi-badge-life',
    'bg'          => '#bf360c',
    'accent'      => '#ffab91',
    'icon'        => 'door-enter',
    'height'      => 260,
  ],
];

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- ══════════════ PAGE MAIN ══════════════ -->
<main id="main-content">

  <!-- ═══════════════════════════════════
       HERO
  ════════════════════════════════════ -->
  <section class="gallery-hero" aria-label="Gallery hero">
    <div class="gallery-hero-bg" aria-hidden="true"></div>
    <div class="gallery-hero-inner">
      <div class="hero-eyebrow">Our Church in Pictures</div>
      <h1>Moments of <em>Grace &amp; Community</em></h1>
      <p class="gallery-hero-sub">
        Browse snapshots from worship services, youth events, outreach programmes
        and everyday life at Shammah Urban Church — Pretoria East.
      </p>
    </div>
  </section>

  <!-- ═══════════════════════════════════
       FILTER TABS
  ════════════════════════════════════ -->
  <section class="gallery-filter-section" aria-label="Gallery filters">
    <div class="gallery-filter-bar" role="group" aria-label="Filter photos by category">
      <button class="gf-btn active" data-filter="all"   aria-pressed="true">All Photos</button>
      <button class="gf-btn"        data-filter="worship"  aria-pressed="false">Worship</button>
      <button class="gf-btn"        data-filter="youth"    aria-pressed="false">Youth</button>
      <button class="gf-btn"        data-filter="outreach" aria-pressed="false">Outreach</button>
      <button class="gf-btn"        data-filter="events"   aria-pressed="false">Events</button>
      <button class="gf-btn"        data-filter="life"     aria-pressed="false">Church Life</button>
    </div>
  </section>

  <!-- ═══════════════════════════════════
       MASONRY GRID
  ════════════════════════════════════ -->
  <section class="gallery-section" aria-label="Photo gallery">

    <div class="gallery-grid" id="galleryGrid">

      <?php foreach ($gallery_items as $idx => $item): ?>

      <article
        class="gallery-item"
        data-category="<?= htmlspecialchars($item['category']) ?>"
        data-index="<?= $idx ?>"
        role="button"
        tabindex="0"
        aria-label="View photo: <?= htmlspecialchars($item['title']) ?>"
      >

        <!-- SVG Illustration placeholder (replaces a real photo) -->
        <svg class="gi-svg"
             viewBox="0 0 400 <?= $item['height'] ?>"
             xmlns="http://www.w3.org/2000/svg"
             aria-hidden="true">
          <!-- Background -->
          <rect width="400" height="<?= $item['height'] ?>" fill="<?= $item['bg'] ?>"/>

          <!-- Subtle diagonal grid pattern -->
          <defs>
            <pattern id="grid<?= $idx ?>" width="30" height="30" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
              <line x1="0" y1="0" x2="0" y2="30" stroke="<?= $item['accent'] ?>" stroke-width="0.4" stroke-opacity="0.2"/>
            </pattern>
          </defs>
          <rect width="400" height="<?= $item['height'] ?>" fill="url(#grid<?= $idx ?>)"/>

          <!-- Decorative circles / orbs -->
          <circle cx="320" cy="<?= round($item['height'] * 0.18) ?>" r="80" fill="<?= $item['accent'] ?>" fill-opacity="0.08"/>
          <circle cx="60"  cy="<?= round($item['height'] * 0.75) ?>" r="55" fill="<?= $item['accent'] ?>" fill-opacity="0.10"/>

          <!-- Gold horizontal accent line -->
          <rect x="0" y="<?= round($item['height'] * 0.55) ?>" width="400" height="1" fill="<?= $item['accent'] ?>" fill-opacity="0.15"/>

          <!-- Centre icon -->
          <?php
          $cx = 200;
          $cy = round($item['height'] * 0.42);
          $icon = $item['icon'];

          // Big colourful icon for each photo (emoji work in every browser, no files needed).
          // 'main' = the large centre icon, 'side' = two small faded icons for atmosphere.
          $emoji = [
            'music'      => ['main' => '🎶', 'side' => ['🙌', '🎵']],
            'microphone' => ['main' => '🎤', 'side' => ['🎸', '🎶']],
            'flame'      => ['main' => '🕯️', 'side' => ['⭐', '🎄']],
            'bolt'       => ['main' => '⚡', 'side' => ['🎧', '🙌']],
            'tent'       => ['main' => '⛺', 'side' => ['🔥', '🌲']],
            'stars'      => ['main' => '🌟', 'side' => ['🎶', '✨']],
            'heart'      => ['main' => '🍞', 'side' => ['❤️', '🥫']],
            'school'     => ['main' => '🎒', 'side' => ['✏️', '📚']],
            'users'      => ['main' => '🤝', 'side' => ['❤️', '🏡']],
            'sun'        => ['main' => '🧺', 'side' => ['☀️', '🌳']],
            'cross'      => ['main' => '✝️', 'side' => ['🌅', '🕊️']],
            'flower'     => ['main' => '🌸', 'side' => ['🍽️', '💐']],
            'droplet'    => ['main' => '💧', 'side' => ['🕊️', '🙏']],
            'home'       => ['main' => '🏠', 'side' => ['📖', '🍲']],
            'door-enter' => ['main' => '🤗', 'side' => ['🚪', '💛']],
          ];
          $e = $emoji[$icon] ?? ['main' => '⛪', 'side' => ['✨', '🙏']];
          $ef = "'Segoe UI Emoji','Apple Color Emoji','Noto Color Emoji',sans-serif";

          // soft glow rings behind the main icon
          echo '<circle cx="'.$cx.'" cy="'.$cy.'" r="62" fill="'.$item['accent'].'" fill-opacity="0.10"/>';
          echo '<circle cx="'.$cx.'" cy="'.$cy.'" r="46" fill="'.$item['accent'].'" fill-opacity="0.16"/>';
          // main icon
          echo '<text x="'.$cx.'" y="'.($cy + 22).'" text-anchor="middle" font-size="64" font-family="'.$ef.'">'.$e['main'].'</text>';
          // two small faded icons
          echo '<text x="'.($cx - 118).'" y="'.($cy - 28).'" text-anchor="middle" font-size="26" fill-opacity="0.55" opacity="0.55" font-family="'.$ef.'">'.$e['side'][0].'</text>';
          echo '<text x="'.($cx + 118).'" y="'.($cy + 40).'" text-anchor="middle" font-size="26" opacity="0.55" font-family="'.$ef.'">'.$e['side'][1].'</text>';
          ?>

          <!-- Title text at bottom of card -->
          <text x="20" y="<?= $item['height'] - 28 ?>"
                font-family="Georgia, serif"
                font-size="13"
                font-weight="700"
                fill="white"
                fill-opacity="0.9"><?= htmlspecialchars($item['title']) ?></text>
          <text x="20" y="<?= $item['height'] - 12 ?>"
                font-family="Arial, sans-serif"
                font-size="10"
                fill="<?= $item['accent'] ?>"
                fill-opacity="0.9"><?= htmlspecialchars($item['cat_label']) ?></text>
        </svg>

        <!-- Category badge (top-left) -->
        <span class="gi-badge <?= htmlspecialchars($item['badge_class']) ?>" aria-hidden="true">
          <?= htmlspecialchars($item['cat_label']) ?>
        </span>

        <!-- Zoom icon (top-right, appears on hover) -->
        <span class="gi-zoom-icon" aria-hidden="true">
          <i class="ti ti-zoom-in"></i>
        </span>

        <!-- Hover overlay with title -->
        <div class="gi-overlay" aria-hidden="true">
          <div class="gi-overlay-title"><?= htmlspecialchars($item['title']) ?></div>
          <div class="gi-overlay-cat"><?= htmlspecialchars($item['cat_label']) ?></div>
        </div>

      </article>

      <?php endforeach; ?>

    </div><!-- /#galleryGrid -->

    <!-- Empty state shown by JS when a filter returns 0 results -->
    <div class="gallery-empty" id="galleryEmpty" role="status" aria-live="polite">
      <i class="ti ti-photo-off" aria-hidden="true"></i>
      <p>No photos in this category yet — check back soon!</p>
    </div>

  </section>


  <!-- ═══════════════════════════════════
       LIGHTBOX
  ════════════════════════════════════ -->
  <div class="gallery-lightbox" id="galleryLightbox"
       role="dialog" aria-modal="true" aria-label="Photo viewer"
       aria-hidden="true">

    <div class="lb-inner">

      <!-- Close -->
      <button class="lb-close" id="lbClose" aria-label="Close photo viewer">
        <i class="ti ti-x"></i>
      </button>

      <!-- Prev / Next -->
      <button class="lb-nav lb-prev" id="lbPrev" aria-label="Previous photo">
        <i class="ti ti-chevron-left"></i>
      </button>
      <button class="lb-nav lb-next" id="lbNext" aria-label="Next photo">
        <i class="ti ti-chevron-right"></i>
      </button>

      <!-- Media (SVG cloned here by JS) -->
      <div id="lbMedia" class="lb-media" aria-label="Photo"></div>

      <!-- Info -->
      <div class="lb-info">
        <div class="lb-title" id="lbTitle"></div>
        <div class="lb-desc"  id="lbDesc"></div>
        <div class="lb-meta">
          <span class="lb-tag" id="lbCat"></span>
        </div>
      </div>

    </div>
  </div>


  <!-- ═══════════════════════════════════
       CTA STRIP
  ════════════════════════════════════ -->
  <section class="gallery-cta" aria-label="Join us">
    <h2>Be Part of <em style="font-style:italic;color:var(--gold)">the Story</em></h2>
    <p>Every Sunday at 9:00 AM &amp; 11:00 AM · Maragon High School, Pretoria East</p>
    <div class="gallery-cta-btns">
      <a href="events.php"   class="btn-gold">
        <i class="ti ti-calendar" aria-hidden="true"></i> Upcoming Events
      </a>
      <a href="contact.php"  class="btn-ghost">
        <i class="ti ti-map-pin" aria-hidden="true"></i> Find Us
      </a>
    </div>
  </section>

</main><!-- /#main-content -->


<script>
/* ─── Gallery data passed from PHP ─── */
const GALLERY_ITEMS = <?= json_encode(array_map(fn($i) => [
  'title'    => $i['title'],
  'desc'     => $i['desc'],
  'category' => $i['category'],
  'catLabel' => $i['cat_label'],
], $gallery_items)) ?>;

const IS_LOGGED_IN = <?= json_encode($is_logged_in) ?>;
const CSRF_TOKEN   = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
</script>

<?php require_once 'includes/footer.php'; ?>
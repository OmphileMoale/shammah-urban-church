<?php
/* ═══════════════════════════════════════
   includes/data.php
   PURPOSE: All the data the home page needs.
   Right now it uses plain PHP arrays to
   simulate a database. Later you will replace
   these arrays with real PDO database queries.
   
   WHY A SEPARATE FILE?
   If you put data inside index.php you can't
   reuse it on events.php or other pages.
   Keeping it here means every page can do:
     require_once 'includes/data.php';
   and immediately have access to $events,
   $ministries, etc.
═══════════════════════════════════════ */

/* ── CHURCH STATISTICS ──────────────────
   These four numbers feed the animated
   counter band below the hero.
──────────────────────────────────────── */
$stats = [
  'members'    => 512,
  'ministries' => 8,
  'groups'     => 12,
  'years'      => 16,
];

/* ── ANNOUNCEMENTS (TICKER) ─────────────
   Each string appears in the scrolling bar
   at the very top of the page.
   The PHP in index.php joins them with ' · '
──────────────────────────────────────── */
$announcements = [
  "📅 Sunday Service — 9:00 AM & 11:00 AM · Main Sanctuary",
  "🏕️ Youth Camp 2025 — 24 May · Hartbeespoort Resort · Only 10 spots left!",
  "🙏 Community Prayer Night — 21 May @ 7:00 PM · Fellowship Hall",
  "💛 Building Fund: 62% of target reached — Your giving matters!",
  "📖 Wednesday Bible Study — Every week at 6:30 PM",
];

/* ── UPCOMING EVENTS ────────────────────
   Each event is an associative array
   (key => value pairs, like a row in a DB).
   
   KEY FIELDS EXPLAINED:
   id       – unique number, used by the RSVP fetch call
   title    – displayed as the card heading
   day/month– shown in the date box
   time     – service time text
   venue    – location string
   rsvp     – how many people have already RSVP'd
   capacity – maximum allowed attendees
   color    – hex code for the top stripe & progress bar
──────────────────────────────────────── */
$events = [
  [
    'id'       => 1,
    'title'    => 'Sunday Morning Service',
    'day'      => '15',
    'month'    => 'Jun',
    'year'     => '2025',
    'time'     => '9:00 AM',
    'datetime' => '2025-06-15 09:00:00',
    'venue'    => 'Main Sanctuary',
    'rsvp'     => 370,
    'capacity' => 500,
    'color'    => '#3D0C2F',
    'category' => 'Worship',
    'cat_key'  => 'worship',
    'cat_cls'  => 'cat-worship',
    'desc'     => 'Join us for our Sunday morning worship service as we gather together in praise, prayer, and the teaching of God\'s Word. Both the 9:00 AM and 11:00 AM services are fully accessible. Children\'s Ministry runs simultaneously for ages 4–12. All are welcome — no membership required for your first visit.',
  ],
  [
    'id'       => 2,
    'title'    => 'Youth Camp 2025',
    'day'      => '20',
    'month'    => 'Jun',
    'year'     => '2025',
    'time'     => 'Fri–Sun Weekend',
    'datetime' => '2025-06-20 16:00:00',
    'venue'    => 'Hartbeespoort Resort',
    'rsvp'     => 110,
    'capacity' => 120,
    'color'    => '#639922',
    'category' => 'Youth',
    'cat_key'  => 'youth',
    'cat_cls'  => 'cat-youth',
    'desc'     => 'An unforgettable weekend at Hartbeespoort for young people aged 13–25. Packed with worship nights, adventure activities, team-building challenges, and powerful ministry sessions led by Elder Nkosi and special guest speakers. Transport departs from the church at 4:00 PM on Friday. RSVP is mandatory — only 10 spots remain!',
  ],
  [
    'id'       => 3,
    'title'    => 'Community Prayer Night',
    'day'      => '18',
    'month'    => 'Jun',
    'year'     => '2025',
    'time'     => '7:00 PM',
    'datetime' => '2025-06-18 19:00:00',
    'venue'    => 'Fellowship Hall',
    'rsvp'     => 60,
    'capacity' => 150,
    'color'    => '#378ADD',
    'category' => 'Outreach',
    'cat_key'  => 'outreach',
    'cat_cls'  => 'cat-outreach',
    'desc'     => 'A powerful evening of corporate intercession for our nation, our city, and our families. Come as you are — bring your prayer requests and expect God to move. The night opens with 20 minutes of worship before we enter into focused prayer. Light refreshments served afterwards. Open to all members, guests, and community members.',
  ],
  [
    'id'       => 4,
    'title'    => 'Women of Purpose Meeting',
    'day'      => '25',
    'month'    => 'Jun',
    'year'     => '2025',
    'time'     => '10:00 AM',
    'datetime' => '2025-06-25 10:00:00',
    'venue'    => 'Conference Room B',
    'rsvp'     => 55,
    'capacity' => 100,
    'color'    => '#C9A84C',
    'category' => 'Women',
    'cat_key'  => 'women',
    'cat_cls'  => 'cat-women',
    'desc'     => 'Our monthly Women of Purpose gathering — a safe, uplifting space for women of all ages to connect, grow, and be encouraged in faith. This month\'s theme is "Strength in Seasons of Change", featuring a special message from Grace Mokoena, breakaway discussion groups, and a Q&A session. Tea and light snacks provided.',
  ],
  [
    'id'       => 5,
    'title'    => 'Children\'s Ministry Fun Day',
    'day'      => '28',
    'month'    => 'Jun',
    'year'     => '2025',
    'time'     => '9:00 AM – 1:00 PM',
    'datetime' => '2025-06-28 09:00:00',
    'venue'    => 'Church Grounds & Hall',
    'rsvp'     => 80,
    'capacity' => 80,
    'color'    => '#C0392B',
    'category' => 'Children',
    'cat_key'  => 'children',
    'cat_cls'  => 'cat-children',
    'desc'     => 'A special Saturday morning of games, crafts, Bible stories, and fun for children aged 4–12. Parents are welcome to stay and enjoy coffee and muffins in the hall while the kids enjoy activities on the church grounds. This event is completely free — no registration fee required. Fully booked for this month, but add yourself to the waiting list!',
  ],
];

/* ── PAST EVENTS ────────────────────────
   Shown in the "Past Events" section on
   events.php — greyed out and non-RSVP-able.
──────────────────────────────────────── */
$past_events = [
  [
    'id'    => 101,
    'title' => 'Easter Sunday Service 2025',
    'day'   => '20',
    'month' => 'Apr',
    'year'  => '2025',
    'venue' => 'Main Sanctuary',
    'rsvp'  => 486,
    'capacity' => 500,
  ],
  [
    'id'    => 102,
    'title' => 'Good Friday Prayer Service',
    'day'   => '18',
    'month' => 'Apr',
    'year'  => '2025',
    'venue' => 'Main Sanctuary',
    'rsvp'  => 310,
    'capacity' => 500,
  ],
  [
    'id'    => 103,
    'title' => 'Community Outreach Day',
    'day'   => '03',
    'month' => 'May',
    'year'  => '2025',
    'venue' => 'Sunnyside Park',
    'rsvp'  => 95,
    'capacity' => 100,
  ],
  [
    'id'    => 104,
    'title' => 'Worship Night — May',
    'day'   => '09',
    'month' => 'May',
    'year'  => '2025',
    'venue' => 'Main Sanctuary',
    'rsvp'  => 220,
    'capacity' => 300,
  ],
];

/* ── DAILY VERSE ────────────────────────
   Later you can make this dynamic:
   pick a verse based on date('N') (day of week)
   or pull from a database table of verses.
──────────────────────────────────────── */
$verse = [
  'text' => '"For I know the plans I have for you," declares the Lord,
             "plans to prosper you and not to harm you,
             plans to give you hope and a future."',
  'ref'  => 'Jeremiah 29:11',
];

/* ── MINISTRIES ─────────────────────────
   Shown as cards in the Ministries section.
   icon – an emoji used as a visual marker
   name – ministry name
   desc – short subtitle
──────────────────────────────────────── */
$ministries = [
  ['icon' => '🙏', 'name' => 'Worship',           'desc' => 'Choir, band & A/V team'],
  ['icon' => '👦', 'name' => 'Youth',              'desc' => 'Ages 13–25 · Fridays'],
  ['icon' => '👩', 'name' => 'Women of Purpose',   'desc' => 'Monthly gatherings'],
  ['icon' => '🌍', 'name' => 'Outreach',           'desc' => 'Community service'],
  ['icon' => '👶', 'name' => "Children's Ministry",'desc' => 'Ages 4–12 · Sundays'],
  ['icon' => '📖', 'name' => 'Cell Groups',        'desc' => '12 Pretoria locations'],
];

/* ── TICKER STRING ──────────────────────
   Built here so index.php can just echo it.
   We duplicate the string so the animation
   loops seamlessly (the second copy starts
   exactly where the first ends).
──────────────────────────────────────── */
$ticker_str = implode('  ·  &nbsp;&nbsp;', $announcements)
            . '  ·  &nbsp;&nbsp;'
            . implode('  ·  &nbsp;&nbsp;', $announcements);

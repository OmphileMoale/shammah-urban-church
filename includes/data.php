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

   The values below are only a FALLBACK, shown
   if the database cannot be reached.
   Call shammah_live_stats($stats) on a page to
   replace them with real numbers from the
   database, so they change as members join.
──────────────────────────────────────── */
define('CHURCH_FOUNDED_YEAR', 2009);   // "Serving Pretoria since 2009"
$stats = [
  'members'    => 512,
  'ministries' => 8,
  'groups'     => 12,
  'years'      => 16,
  'live'       => false,
];

/* Live numbers:
   members    → active rows in MEMBER (goes up when someone joins,
                down when an admin deactivates them)
   ministries → active rows in MINISTRY
   years      → this year minus the founding year (changes by itself)
   groups     → kept as a church setting (there is no cell-group table)  */
/* Shared database connection for public pages.
   Uses the same connection as the rest of the site. If MySQL is down it returns
   null, so the page can still open instead of showing a setup screen.        */
function shammah_pdo(): ?PDO
{
    $pdo = $GLOBALS['pdo'] ?? null;
    if ($pdo instanceof PDO) return $pdo;
    if (!empty($GLOBALS['shammah_pdo_tried'])) return null;      // do not retry a failed connection
    $GLOBALS['shammah_pdo_tried'] = true;

    $GLOBALS['DB_SOFT_FAIL'] = true;
    require_once __DIR__ . '/db.php';                            // sets $pdo (or null if MySQL is down)
    $GLOBALS['DB_SOFT_FAIL'] = false;

    $GLOBALS['pdo'] = ($pdo instanceof PDO) ? $pdo : null;
    return $GLOBALS['pdo'];
}

function shammah_live_stats(array $fallback): array
{
    $stats          = $fallback;
    $stats['years'] = max(1, (int) date('Y') - CHURCH_FOUNDED_YEAR);
    $stats['live']  = false;

    try {
        $pdo = shammah_pdo();
        if ($pdo instanceof PDO) {
            $stats['members']    = (int) $pdo->query('SELECT COUNT(*) FROM MEMBER WHERE is_active = 1')->fetchColumn();
            $stats['ministries'] = (int) $pdo->query('SELECT COUNT(*) FROM MINISTRY WHERE is_active = 1')->fetchColumn();
            $stats['live']       = true;
        }
    } catch (Throwable $e) {
        error_log('Shammah live stats failed: ' . $e->getMessage());
    }
    return $stats;
}

/* Upcoming events and their RSVP numbers, straight from the database.
   rsvp = confirmed rows in RSVP, so it goes up every time a member RSVPs and
   down when they cancel. The typed-in list above is only a fallback for when
   MySQL cannot be reached.                                                   */
function shammah_live_events(array $fallback): array
{
    $pdo = shammah_pdo();
    if (!($pdo instanceof PDO)) return $fallback;
    try {
        $rows = $pdo->query('
            SELECT e.event_id, e.title, e.event_date, e.time_str, e.venue, e.capacity, e.color, e.category, e.description,
                   (SELECT COUNT(*) FROM RSVP r WHERE r.event_id = e.event_id AND r.status = "confirmed") AS rsvp_count
            FROM   EVENT e
            WHERE  e.is_active = 1 AND e.event_date >= CURDATE()
            ORDER  BY e.event_date ASC')->fetchAll();
    } catch (Throwable $e) {
        error_log('Shammah live events failed: ' . $e->getMessage());
        return $fallback;
    }

    $events = [];
    foreach ($rows as $r) {
        $when = new DateTime($r['event_date']);
        $key  = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string) $r['category'])), '-') ?: 'general';
        $events[] = [
            'id'       => (int) $r['event_id'],
            'title'    => $r['title'],
            'day'      => $when->format('d'),
            'month'    => $when->format('M'),
            'year'     => $when->format('Y'),
            'time'     => $r['time_str'] !== '' ? $r['time_str'] : $when->format('g:i A'),
            'datetime' => $when->format('Y-m-d H:i:s'),
            'venue'    => $r['venue'],
            'rsvp'     => (int) $r['rsvp_count'],
            'capacity' => max(1, (int) $r['capacity']),
            'color'    => $r['color'],
            'category' => $r['category'],
            'cat_key'  => $key,
            'cat_cls'  => 'cat-' . $key,
            'desc'     => (string) $r['description'],
        ];
    }
    return $events;
}

/* Past events: how many members were marked present (ATTENDANCE). */
function shammah_live_past_events(array $fallback): array
{
    $pdo = shammah_pdo();
    if (!($pdo instanceof PDO)) return $fallback;
    try {
        $rows = $pdo->query('
            SELECT e.event_id, e.title, e.event_date, e.venue, e.capacity,
                   (SELECT COUNT(*) FROM ATTENDANCE a WHERE a.event_id = e.event_id AND a.status = "present") AS attended
            FROM   EVENT e
            WHERE  e.is_active = 1 AND e.event_date < CURDATE()
            ORDER  BY e.event_date DESC LIMIT 4')->fetchAll();
    } catch (Throwable $e) {
        error_log('Shammah past events failed: ' . $e->getMessage());
        return $fallback;
    }
    $past = [];
    foreach ($rows as $r) {
        $when = new DateTime($r['event_date']);
        $past[] = ['id' => (int) $r['event_id'], 'title' => $r['title'], 'day' => $when->format('d'),
                   'month' => $when->format('M'), 'year' => $when->format('Y'), 'venue' => $r['venue'],
                   'rsvp' => (int) $r['attended'], 'capacity' => max(1, (int) $r['capacity'])];
    }
    return $past;
}

/* "RSVPs this month": confirmed RSVPs made since the 1st of this month. */
function shammah_rsvps_this_month(int $fallback): int
{
    $pdo = shammah_pdo();
    if (!($pdo instanceof PDO)) return $fallback;
    try {
        return (int) $pdo->query('SELECT COUNT(*) FROM RSVP WHERE status = "confirmed"
                                  AND created_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01")')->fetchColumn();
    } catch (Throwable $e) {
        error_log('Shammah rsvps this month failed: ' . $e->getMessage());
        return $fallback;
    }
}

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
   A different verse every day.
   The list below is cycled by the date (South African time), so every
   visitor sees the same verse on the same day and tomorrow it changes.
   Text is the King James Version (public domain).
   To add a verse, add one more line to the list.

   Demo helper: add ?verse_day=1 to a page address to see tomorrow's
   verse, ?verse_day=2 for the day after, and so on.
──────────────────────────────────────── */
$verses = [
  ['text' => 'For I know the thoughts that I think toward you, saith the LORD, thoughts of peace, and not of evil, to give you an expected end.', 'ref' => 'Jeremiah 29:11'],
  ['text' => 'The LORD is my shepherd; I shall not want.', 'ref' => 'Psalm 23:1'],
  ['text' => 'Trust in the LORD with all thine heart; and lean not unto thine own understanding. In all thy ways acknowledge him, and he shall direct thy paths.', 'ref' => 'Proverbs 3:5-6'],
  ['text' => 'I can do all things through Christ which strengtheneth me.', 'ref' => 'Philippians 4:13'],
  ['text' => 'Fear thou not; for I am with thee: be not dismayed; for I am thy God: I will strengthen thee; yea, I will help thee; yea, I will uphold thee with the right hand of my righteousness.', 'ref' => 'Isaiah 41:10'],
  ['text' => 'Have not I commanded thee? Be strong and of a good courage; be not afraid, neither be thou dismayed: for the LORD thy God is with thee whithersoever thou goest.', 'ref' => 'Joshua 1:9'],
  ['text' => 'And we know that all things work together for good to them that love God, to them who are the called according to his purpose.', 'ref' => 'Romans 8:28'],
  ['text' => 'For God so loved the world, that he gave his only begotten Son, that whosoever believeth in him should not perish, but have everlasting life.', 'ref' => 'John 3:16'],
  ['text' => 'God is our refuge and strength, a very present help in trouble.', 'ref' => 'Psalm 46:1'],
  ['text' => 'Come unto me, all ye that labour and are heavy laden, and I will give you rest.', 'ref' => 'Matthew 11:28'],
  ['text' => 'But they that wait upon the LORD shall renew their strength; they shall mount up with wings as eagles; they shall run, and not be weary; and they shall walk, and not faint.', 'ref' => 'Isaiah 40:31'],
  ['text' => 'I will lift up mine eyes unto the hills, from whence cometh my help. My help cometh from the LORD, which made heaven and earth.', 'ref' => 'Psalm 121:1-2'],
  ['text' => 'And be not conformed to this world: but be ye transformed by the renewing of your mind, that ye may prove what is that good, and acceptable, and perfect, will of God.', 'ref' => 'Romans 12:2'],
  ['text' => 'For God hath not given us the spirit of fear; but of power, and of love, and of a sound mind.', 'ref' => '2 Timothy 1:7'],
  ['text' => 'The LORD is my light and my salvation; whom shall I fear? the LORD is the strength of my life; of whom shall I be afraid?', 'ref' => 'Psalm 27:1'],
  ['text' => 'But seek ye first the kingdom of God, and his righteousness; and all these things shall be added unto you.', 'ref' => 'Matthew 6:33'],
  ['text' => 'Thy word is a lamp unto my feet, and a light unto my path.', 'ref' => 'Psalm 119:105'],
  ['text' => 'And now abideth faith, hope, charity, these three; but the greatest of these is charity.', 'ref' => '1 Corinthians 13:13'],
  ['text' => 'But the fruit of the Spirit is love, joy, peace, longsuffering, gentleness, goodness, faith, meekness, temperance: against such there is no law.', 'ref' => 'Galatians 5:22-23'],
  ['text' => 'For by grace are ye saved through faith; and that not of yourselves: it is the gift of God: not of works, lest any man should boast.', 'ref' => 'Ephesians 2:8-9'],
  ['text' => 'Delight thyself also in the LORD; and he shall give thee the desires of thine heart.', 'ref' => 'Psalm 37:4'],
  ['text' => 'Thou wilt keep him in perfect peace, whose mind is stayed on thee: because he trusteth in thee.', 'ref' => 'Isaiah 26:3'],
  ['text' => 'Now faith is the substance of things hoped for, the evidence of things not seen.', 'ref' => 'Hebrews 11:1'],
  ['text' => 'O taste and see that the LORD is good: blessed is the man that trusteth in him.', 'ref' => 'Psalm 34:8'],
  ['text' => 'Be strong and of a good courage, fear not, nor be afraid of them: for the LORD thy God, he it is that doth go with thee; he will not fail thee, nor forsake thee.', 'ref' => 'Deuteronomy 31:6'],
  ['text' => 'He that dwelleth in the secret place of the most High shall abide under the shadow of the Almighty.', 'ref' => 'Psalm 91:1'],
  ['text' => 'Let your light so shine before men, that they may see your good works, and glorify your Father which is in heaven.', 'ref' => 'Matthew 5:16'],
  ['text' => 'Peace I leave with you, my peace I give unto you: not as the world giveth, give I unto you. Let not your heart be troubled, neither let it be afraid.', 'ref' => 'John 14:27'],
  ['text' => 'Enter into his gates with thanksgiving, and into his courts with praise: be thankful unto him, and bless his name.', 'ref' => 'Psalm 100:4'],
  ['text' => 'It is of the LORD\'s mercies that we are not consumed, because his compassions fail not. They are new every morning: great is thy faithfulness.', 'ref' => 'Lamentations 3:22-23'],
  ['text' => 'He hath shewed thee, O man, what is good; and what doth the LORD require of thee, but to do justly, and to love mercy, and to walk humbly with thy God?', 'ref' => 'Micah 6:8'],
  ['text' => 'I will praise thee; for I am fearfully and wonderfully made: marvellous are thy works; and that my soul knoweth right well.', 'ref' => 'Psalm 139:14'],
  ['text' => 'Casting all your care upon him; for he careth for you.', 'ref' => '1 Peter 5:7'],
  ['text' => 'Now the God of hope fill you with all joy and peace in believing, that ye may abound in hope, through the power of the Holy Ghost.', 'ref' => 'Romans 15:13'],
  ['text' => 'And whatsoever ye do, do it heartily, as to the Lord, and not unto men.', 'ref' => 'Colossians 3:23'],
  ['text' => 'If any of you lack wisdom, let him ask of God, that giveth to all men liberally, and upbraideth not; and it shall be given him.', 'ref' => 'James 1:5'],
  ['text' => 'Bless the LORD, O my soul: and all that is within me, bless his holy name. Bless the LORD, O my soul, and forget not all his benefits.', 'ref' => 'Psalm 103:1-2'],
  ['text' => 'Be careful for nothing; but in every thing by prayer and supplication with thanksgiving let your requests be made known unto God. And the peace of God, which passeth all understanding, shall keep your hearts and minds through Christ Jesus.', 'ref' => 'Philippians 4:6-7'],
  ['text' => 'And, lo, I am with you alway, even unto the end of the world.', 'ref' => 'Matthew 28:20'],
  ['text' => 'The name of the LORD is a strong tower: the righteous runneth into it, and is safe.', 'ref' => 'Proverbs 18:10'],
  ['text' => 'When thou passest through the waters, I will be with thee; and through the rivers, they shall not overflow thee: when thou walkest through the fire, thou shalt not be burned; neither shall the flame kindle upon thee.', 'ref' => 'Isaiah 43:2'],
  ['text' => 'This is the day which the LORD hath made; we will rejoice and be glad in it.', 'ref' => 'Psalm 118:24'],
  ['text' => 'But God commendeth his love toward us, in that, while we were yet sinners, Christ died for us.', 'ref' => 'Romans 5:8'],
  ['text' => 'I am the vine, ye are the branches: He that abideth in me, and I in him, the same bringeth forth much fruit: for without me ye can do nothing.', 'ref' => 'John 15:5'],
  ['text' => 'Jesus Christ the same yesterday, and to day, and for ever.', 'ref' => 'Hebrews 13:8'],
  ['text' => 'For his anger endureth but a moment; in his favour is life: weeping may endure for a night, but joy cometh in the morning.', 'ref' => 'Psalm 30:5'],
  ['text' => 'Commit thy works unto the LORD, and thy thoughts shall be established.', 'ref' => 'Proverbs 16:3'],
  ['text' => 'And let us not be weary in well doing: for in due season we shall reap, if we faint not.', 'ref' => 'Galatians 6:9'],
  ['text' => 'We love him, because he first loved us.', 'ref' => '1 John 4:19'],
  ['text' => 'Let the words of my mouth, and the meditation of my heart, be acceptable in thy sight, O LORD, my strength, and my redeemer.', 'ref' => 'Psalm 19:14'],
  ['text' => 'For we walk by faith, not by sight.', 'ref' => '2 Corinthians 5:7'],
  ['text' => 'My flesh and my heart faileth: but God is the strength of my heart, and my portion for ever.', 'ref' => 'Psalm 73:26'],
  ['text' => 'The LORD is good, a strong hold in the day of trouble; and he knoweth them that trust in him.', 'ref' => 'Nahum 1:7'],
  ['text' => 'Finally, my brethren, be strong in the Lord, and in the power of his might.', 'ref' => 'Ephesians 6:10'],
  ['text' => 'Iron sharpeneth iron; so a man sharpeneth the countenance of his friend.', 'ref' => 'Proverbs 27:17'],
  ['text' => 'Not forsaking the assembling of ourselves together, as the manner of some is; but exhorting one another: and so much the more, as ye see the day approaching.', 'ref' => 'Hebrews 10:25'],
  ['text' => 'But ye shall receive power, after that the Holy Ghost is come upon you: and ye shall be witnesses unto me both in Jerusalem, and in all Judaea, and in Samaria, and unto the uttermost part of the earth.', 'ref' => 'Acts 1:8'],
  ['text' => 'Behold, how good and how pleasant it is for brethren to dwell together in unity!', 'ref' => 'Psalm 133:1'],
  ['text' => 'Call unto me, and I will answer thee, and shew thee great and mighty things, which thou knowest not.', 'ref' => 'Jeremiah 33:3'],
  ['text' => 'Rejoice evermore. Pray without ceasing. In every thing give thanks: for this is the will of God in Christ Jesus concerning you.', 'ref' => '1 Thessalonians 5:16-18'],
  ['text' => 'Being confident of this very thing, that he which hath begun a good work in you will perform it until the day of Jesus Christ.', 'ref' => 'Philippians 1:6'],
  ['text' => 'What time I am afraid, I will trust in thee.', 'ref' => 'Psalm 56:3'],
  ['text' => 'Behold, God is my salvation; I will trust, and not be afraid: for the LORD JEHOVAH is my strength and my song; he also is become my salvation.', 'ref' => 'Isaiah 12:2'],
  ['text' => 'For with God nothing shall be impossible.', 'ref' => 'Luke 1:37'],
];

$verse_tz     = new DateTimeZone('Africa/Johannesburg');
$verse_now    = new DateTime('now', $verse_tz);
$verse_day    = (int) floor(($verse_now->getTimestamp() + $verse_now->getOffset()) / 86400);
$verse_shift  = isset($_GET['verse_day']) ? max(-400, min(400, (int) $_GET['verse_day'])) : 0;
$verse_count  = count($verses);
$verse        = $verses[(($verse_day + $verse_shift) % $verse_count + $verse_count) % $verse_count];
$verse['text'] = '“' . $verse['text'] . '”';
$verse['ref'] .= ' (KJV)';

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
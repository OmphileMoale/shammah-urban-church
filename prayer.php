<?php
/* ═══════════════════════════════════════════════════════════════
   prayer.php  –  PRAYER WALL (database-driven)

   What this file does:
   1. Creates its own tables the first time it runs.
   2. Visitors submit a prayer. It waits for a pastor to approve it
      (change PRAYER_NEEDS_APPROVAL to false to publish instantly).
   3. Pastors/admins approve, reject or mark prayers as answered.
   4. "I prayed" button raises the prayer's count (one per person).
   5. The four hero numbers and the category counts are CALCULATED
      from the database, so they rise as prayers are approved.
═══════════════════════════════════════════════════════════════ */
session_start();

require_once 'includes/data.php';
require_once 'includes/db.php';

/* ── SETTINGS ───────────────────────── */
const PRAYER_NEEDS_APPROVAL = true;   // false = prayers appear immediately
const BASE_SUBMITTED        = 142;    // starting numbers shown before real data
const BASE_INTERCESSIONS    = 1840;   // (set all three to 0 to show only real data)
const BASE_ANSWERED         = 38;

$PRAYER_CATEGORIES = [
    'Health & Healing'  => '❤️',
    'Family'            => '👨‍👩‍👧',
    'Financial'         => '💛',
    'Spiritual Growth'  => '📖',
    'Relationships'     => '🤝',
    'Thanksgiving'      => '🎉',
    'Protection'        => '🛡️',
    'Guidance'          => '🧭',
];

/* ── WHO IS LOGGED IN ───────────────── */
$is_logged_in = isset($_SESSION['member_id']);
$user_role    = $_SESSION['role'] ?? '';
$user_name    = $_SESSION['member_name'] ?? ($_SESSION['name'] ?? ($_SESSION['first_name'] ?? ''));
$is_pastor    = in_array($user_role, ['pastor', 'admin'], true);
$who          = $is_logged_in ? 'm' . (int) $_SESSION['member_id'] : 's' . session_id();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_ok(): bool {
    return isset($_POST['csrf'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf']);
}

/* ── CREATE TABLES (first run only) ─── */
$pdo->exec("CREATE TABLE IF NOT EXISTS PRAYER_WALL (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    member_id    INT NULL,
    display_name VARCHAR(80)  NOT NULL,
    is_anonymous TINYINT(1)   NOT NULL DEFAULT 0,
    category     VARCHAR(40)  NOT NULL,
    request_text TEXT         NOT NULL,
    status       ENUM('pending','approved','answered','rejected') NOT NULL DEFAULT 'pending',
    prayed_count INT          NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS PRAYER_WALL_PRAYED (
    prayer_id INT         NOT NULL,
    who       VARCHAR(80) NOT NULL,
    PRIMARY KEY (prayer_id, who)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* Starter prayers so the wall is not empty on day one */
if ((int) $pdo->query('SELECT COUNT(*) FROM PRAYER_WALL')->fetchColumn() === 0) {
    $seed = $pdo->prepare("INSERT INTO PRAYER_WALL
        (display_name, is_anonymous, category, request_text, status, prayed_count, created_at)
        VALUES (?, ?, ?, ?, 'approved', ?, DATE_SUB(NOW(), INTERVAL ? HOUR))");
    $seed->execute(['Anonymous', 1, 'Health & Healing',
        "Please pray for my mother's recovery from surgery. She needs strength and healing from God. The doctors say it will be a long road but we trust the Lord.", 24, 2]);
    $seed->execute(['Thandi M.', 0, 'Thanksgiving',
        'Praise God! After months of searching I finally got a job. Thank you to everyone who prayed with me.', 31, 10]);
    $seed->execute(['Sipho K.', 0, 'Guidance',
        'Praying for wisdom as I make a decision about studying further. Please ask God to open the right door.', 12, 30]);
}

/* ═══════════════════════════════════
   ACTIONS (POST)
═══════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ── "I PRAYED" (called by JS, returns JSON) ── */
    if ($action === 'pray') {
        header('Content-Type: application/json');
        if (!csrf_ok()) { echo json_encode(['ok' => false, 'msg' => 'Please refresh the page.']); exit; }
        $id = (int) ($_POST['id'] ?? 0);

        $chk = $pdo->prepare("SELECT 1 FROM PRAYER_WALL WHERE id = ? AND status IN ('approved','answered')");
        $chk->execute([$id]);
        if (!$chk->fetchColumn()) { echo json_encode(['ok' => false, 'msg' => 'Prayer not found.']); exit; }

        $ins = $pdo->prepare('INSERT IGNORE INTO PRAYER_WALL_PRAYED (prayer_id, who) VALUES (?, ?)');
        $ins->execute([$id, $who]);
        if ($ins->rowCount() === 1) {
            $pdo->prepare('UPDATE PRAYER_WALL SET prayed_count = prayed_count + 1 WHERE id = ?')->execute([$id]);
        }
        $n = $pdo->prepare('SELECT prayed_count FROM PRAYER_WALL WHERE id = ?');
        $n->execute([$id]);
        echo json_encode(['ok' => true, 'count' => (int) $n->fetchColumn()]);
        exit;
    }

    /* ── SUBMIT A PRAYER ── */
    if ($action === 'submit') {
        $flash = ['ok' => false, 'msg' => ''];
        $name  = trim($_POST['name'] ?? '');
        $cat   = trim($_POST['category'] ?? '');
        $text  = trim($_POST['request_text'] ?? '');
        $anon  = isset($_POST['anonymous']) ? 1 : 0;
        $errors = [];

        if (!csrf_ok())                              $errors[] = 'Security token mismatch. Please refresh the page.';
        if ($is_logged_in && $user_name !== '')      $name = $user_name;
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) $errors[] = 'Please enter your name (2 to 80 characters).';
        if (!isset($PRAYER_CATEGORIES[$cat]))        $errors[] = 'Please choose a category.';
        if (mb_strlen($text) < 10)                   $errors[] = 'Your prayer request must be at least 10 characters.';
        if (mb_strlen($text) > 1000)                 $errors[] = 'Your prayer request must be 1000 characters or fewer.';
        if (isset($_SESSION['pw_last']) && time() - $_SESSION['pw_last'] < 30)
                                                     $errors[] = 'Please wait a few seconds before submitting another request.';

        if ($errors) {
            $flash['msg'] = implode(' ', $errors);
        } else {
            $status = (!PRAYER_NEEDS_APPROVAL || $is_pastor) ? 'approved' : 'pending';
            $pdo->prepare('INSERT INTO PRAYER_WALL (member_id, display_name, is_anonymous, category, request_text, status)
                           VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$is_logged_in ? (int) $_SESSION['member_id'] : null, $name, $anon, $cat, $text, $status]);
            $_SESSION['pw_last'] = time();
            $flash = ['ok' => true, 'msg' => $status === 'approved'
                ? 'Thank you. Your prayer request is now on the wall.'
                : 'Thank you. Your prayer request was received and will appear once a pastor approves it.'];
        }
        $_SESSION['pw_flash'] = $flash;
        header('Location: prayer.php#submit');
        exit;
    }

    /* ── PASTOR: APPROVE / REJECT / ANSWERED ── */
    if ($action === 'moderate') {
        $map = ['approve' => 'approved', 'reject' => 'rejected', 'answered' => 'answered'];
        $do  = $_POST['do'] ?? '';
        if ($is_pastor && csrf_ok() && isset($map[$do])) {
            $pdo->prepare('UPDATE PRAYER_WALL SET status = ? WHERE id = ?')
                ->execute([$map[$do], (int) ($_POST['id'] ?? 0)]);
            $_SESSION['pw_flash'] = ['ok' => true, 'msg' => 'Prayer updated.'];
        } else {
            $_SESSION['pw_flash'] = ['ok' => false, 'msg' => 'You do not have permission to do that.'];
        }
        header('Location: prayer.php');
        exit;
    }
}

/* ═══════════════════════════════════
   DATA FOR THE PAGE
═══════════════════════════════════ */
$flash = $_SESSION['pw_flash'] ?? null;
unset($_SESSION['pw_flash']);

/* Hero numbers: base + live counts */
$row = $pdo->query("SELECT
        COALESCE(SUM(status IN ('approved','answered')), 0)                          AS submitted,
        COALESCE(SUM(CASE WHEN status IN ('approved','answered') THEN prayed_count END), 0) AS intercessions,
        COALESCE(SUM(status = 'answered'), 0)                                        AS answered,
        COUNT(DISTINCT CASE WHEN status IN ('approved','answered') THEN category END) AS cats
    FROM PRAYER_WALL")->fetch(PDO::FETCH_ASSOC);

$stat_submitted     = BASE_SUBMITTED     + (int) $row['submitted'];
$stat_intercessions = BASE_INTERCESSIONS + (int) $row['intercessions'];
$stat_answered      = BASE_ANSWERED      + (int) $row['answered'];
$stat_categories    = max(count($PRAYER_CATEGORIES), (int) $row['cats']);

/* Category chip counts */
$cat_counts = [];
$total_live = 0;
foreach ($pdo->query("SELECT category, COUNT(*) c FROM PRAYER_WALL
                      WHERE status IN ('approved','answered') GROUP BY category") as $r) {
    $cat_counts[$r['category']] = (int) $r['c'];
    $total_live += (int) $r['c'];
}

/* Filter + sort */
$cat  = $_GET['cat']  ?? '';
$sort = $_GET['sort'] ?? 'recent';
if (!isset($PRAYER_CATEGORIES[$cat])) $cat = '';
if (!in_array($sort, ['recent', 'prayed'], true)) $sort = 'recent';

$sql    = "SELECT * FROM PRAYER_WALL WHERE status IN ('approved','answered')";
$params = [];
if ($cat !== '') { $sql .= ' AND category = ?'; $params[] = $cat; }
$sql   .= $sort === 'prayed' ? ' ORDER BY prayed_count DESC, id DESC' : ' ORDER BY created_at DESC, id DESC';
$sql   .= ' LIMIT 50';
$list = $pdo->prepare($sql);
$list->execute($params);
$prayers = $list->fetchAll(PDO::FETCH_ASSOC);

/* Which of these has this visitor already prayed for? */
$already = [];
$a = $pdo->prepare('SELECT prayer_id FROM PRAYER_WALL_PRAYED WHERE who = ?');
$a->execute([$who]);
foreach ($a->fetchAll(PDO::FETCH_COLUMN) as $pid) $already[(int) $pid] = true;

/* Pastor queue */
$pending = $is_pastor
    ? $pdo->query("SELECT * FROM PRAYER_WALL WHERE status = 'pending' ORDER BY created_at ASC")->fetchAll(PDO::FETCH_ASSOC)
    : [];

function time_ago(string $dt): string {
    $s = max(0, time() - strtotime($dt));
    if ($s < 60)     return 'just now';
    if ($s < 3600)   return floor($s / 60)    . ' min ago';
    if ($s < 86400)  return floor($s / 3600)  . ' hours ago';
    if ($s < 604800) return floor($s / 86400) . ' days ago';
    return date('j M Y', strtotime($dt));
}
function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

/* ── PAGE META ── */
$page_title = 'Prayer Wall | Shammah Urban Church';
$page_desc  = 'Share a prayer request and pray for others in the Shammah Urban Church community.';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>
<style>
  .pw-flash{max-width:1100px;margin:20px auto 0;padding:14px 18px;border-radius:10px;font-size:.95rem}
  .pw-flash.ok{background:#e8f5e9;color:#1b5e20;border:1px solid #a5d6a7}
  .pw-flash.err{background:#fdecea;color:#b71c1c;border:1px solid #f5c6cb}
  .pw-stats{display:flex;flex-wrap:wrap;justify-content:center;gap:10px 40px;margin:28px auto 0;max-width:720px;
            padding:18px 24px;border:1px solid rgba(201,168,76,.35);border-radius:14px;background:rgba(255,255,255,.05)}
  .pw-stat{text-align:center}.pw-stat b{display:block;font-size:1.9rem;color:#c9a84c;font-family:Georgia,serif}
  .pw-stat span{font-size:.72rem;letter-spacing:.04em;opacity:.8}
  .pw-grid{display:grid;grid-template-columns:minmax(280px,380px) 1fr;gap:28px;max-width:1100px;margin:36px auto;padding:0 16px;align-items:start}
  @media(max-width:860px){.pw-grid{grid-template-columns:1fr}}
  .pw-card{background:#fff;border:1px solid #eadfe6;border-radius:14px;padding:22px}
  .pw-card h2,.pw-card h3{margin:0 0 14px;font-family:Georgia,serif;color:#3d0f33}
  .pw-card label{display:block;font-size:.8rem;margin:12px 0 5px;font-weight:600}
  .pw-card input[type=text],.pw-card select,.pw-card textarea{width:100%;padding:10px 12px;border:1px solid #d9ccd4;border-radius:8px;font:inherit;box-sizing:border-box}
  .pw-card textarea{min-height:120px;resize:vertical}
  .pw-count{font-size:.75rem;text-align:right;opacity:.7}
  .pw-btn{background:#4a0f3d;color:#c9a84c;border:0;border-radius:8px;padding:12px 18px;font-weight:700;cursor:pointer;width:100%;margin-top:14px}
  .pw-btn:hover{background:#5d1650}
  .pw-chips{display:flex;flex-wrap:wrap;gap:8px;margin:10px 0 18px}
  .pw-chip{padding:7px 12px;border:1px solid #d9ccd4;border-radius:999px;font-size:.8rem;text-decoration:none;color:#3d0f33;background:#fff}
  .pw-chip.on{background:#4a0f3d;color:#fff;border-color:#4a0f3d}
  .pw-chip small{opacity:.7;margin-left:4px}
  .pw-head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
  .pw-item{background:#fff;border:1px solid #eadfe6;border-left:4px solid #b3202f;border-radius:12px;padding:16px 18px;margin-bottom:14px}
  .pw-item.answered{border-left-color:#2e7d32}
  .pw-meta{display:flex;justify-content:space-between;font-size:.75rem;opacity:.75;margin-bottom:8px}
  .pw-tag{background:#fdecea;color:#b3202f;padding:3px 10px;border-radius:999px;font-weight:700;font-size:.68rem;text-transform:uppercase}
  .pw-badge-ans{background:#e8f5e9;color:#2e7d32;padding:3px 10px;border-radius:999px;font-weight:700;font-size:.68rem;margin-left:6px}
  .pw-text{line-height:1.55;margin:0 0 12px;white-space:pre-line}
  .pw-foot{display:flex;justify-content:space-between;align-items:center;font-size:.82rem;gap:10px;flex-wrap:wrap}
  .pw-pray{border:1px solid #4a0f3d;background:#fff;color:#4a0f3d;padding:7px 14px;border-radius:999px;cursor:pointer;font-weight:600}
  .pw-pray:disabled{background:#4a0f3d;color:#c9a84c;cursor:default}
  .pw-mod{display:flex;gap:6px;flex-wrap:wrap}
  .pw-mod button{border:0;border-radius:6px;padding:6px 10px;cursor:pointer;font-size:.78rem;color:#fff}
  .pw-mod .a{background:#2e7d32}.pw-mod .r{background:#b71c1c}.pw-mod .n{background:#4a0f3d}
  .pw-empty{text-align:center;padding:30px;opacity:.7}
  .pw-queue{border-left-color:#c9a84c;background:#fffaf0}
</style>

<main id="main-content">

  <!-- ═══ HERO ═══ -->
  <link rel="stylesheet" href="css/prayer.css">

  <section class="prayer-hero" aria-label="Prayer wall">
    <div class="prayer-hero-pattern" aria-hidden="true"></div>
    <div class="prayer-hero-inner">
      <div class="hero-eyebrow">Intercession &amp; Community</div>
      <h1>Prayer <em>Wall</em></h1>

      <p class="prayer-hero-verse">
        <em>“Carry each other’s burdens, and in this way you will fulfill the law of Christ.”</em>
        <span>— Galatians 6:2</span>
      </p>

      <div class="prayer-hero-stats" aria-label="Prayer statistics">
        <div class="p-stat"><strong><?= number_format($stat_submitted) ?>+</strong><span>Prayers Submitted</span></div>
        <div class="p-stat-divider" aria-hidden="true"></div>
        <div class="p-stat"><strong><?= number_format($stat_intercessions) ?>+</strong><span>Intercessions Made</span></div>
        <div class="p-stat-divider" aria-hidden="true"></div>
        <div class="p-stat"><strong><?= number_format($stat_answered) ?></strong><span>Answered Prayers</span></div>
        <div class="p-stat-divider" aria-hidden="true"></div>
        <div class="p-stat"><strong><?= (int) $stat_categories ?></strong><span>Prayer Categories</span></div>
      </div>
    </div>
  </section>

  <div class="breadcrumb-bar">
    <ol class="breadcrumb">
      <li><a href="index.php">Home</a></li>
      <li aria-current="page">Prayer Wall</li>
    </ol>
  </div>

  <?php if ($flash): ?>
    <div class="pw-flash <?= $flash['ok'] ? 'ok' : 'err' ?>" role="alert"><?= h($flash['msg']) ?></div>
  <?php endif; ?>

  <div class="pw-grid">

    <!-- ═══ SUBMIT FORM ═══ -->
    <aside class="pw-card" id="submit">
      <h2>Submit a Prayer Request</h2>
      <form method="post" action="prayer.php" novalidate>
        <input type="hidden" name="action" value="submit">
        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_token']) ?>">

        <label for="pw-name">Your Name</label>
        <input type="text" id="pw-name" name="name" maxlength="80" required
               value="<?= h($is_logged_in ? $user_name : '') ?>" <?= ($is_logged_in && $user_name !== '') ? 'readonly' : '' ?>>

        <label style="font-weight:400"><input type="checkbox" name="anonymous" value="1">
          Post anonymously — your name will not appear on the wall</label>

        <label for="pw-cat">Category *</label>
        <select id="pw-cat" name="category" required>
          <option value="">Choose a category</option>
          <?php foreach ($PRAYER_CATEGORIES as $c => $emoji): ?>
            <option value="<?= h($c) ?>"><?= $emoji . ' ' . h($c) ?></option>
          <?php endforeach; ?>
        </select>

        <label for="pw-text">Your Prayer Request *</label>
        <textarea id="pw-text" name="request_text" maxlength="1000" required
                  placeholder="Share what is on your heart…"></textarea>
        <div class="pw-count"><span id="pw-chars">0</span> / 1000</div>

        <button class="pw-btn" type="submit">🙏 Submit Prayer Request</button>
        <?php if (PRAYER_NEEDS_APPROVAL && !$is_pastor): ?>
          <p style="font-size:.75rem;opacity:.7;margin:10px 0 0">New requests appear on the wall after a pastor approves them.</p>
        <?php endif; ?>
      </form>
    </aside>

    <!-- ═══ WALL ═══ -->
    <section aria-label="Community prayer wall">

      <?php if ($is_pastor && $pending): ?>
        <div class="pw-card" style="margin-bottom:22px">
          <h3>Waiting for approval (<?= count($pending) ?>)</h3>
          <?php foreach ($pending as $p): ?>
            <div class="pw-item pw-queue">
              <div class="pw-meta">
                <span class="pw-tag"><?= h($p['category']) ?></span>
                <span><?= h($p['display_name']) ?> · <?= time_ago($p['created_at']) ?></span>
              </div>
              <p class="pw-text"><?= h($p['request_text']) ?></p>
              <form method="post" class="pw-mod">
                <input type="hidden" name="action" value="moderate">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <button class="a" name="do" value="approve">Approve</button>
                <button class="r" name="do" value="reject">Reject</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="pw-head">
        <h2 style="margin:0;font-family:Georgia,serif;color:#3d0f33">Prayer <em>Wall</em></h2>
        <form method="get">
          <?php if ($cat !== ''): ?><input type="hidden" name="cat" value="<?= h($cat) ?>"><?php endif; ?>
          <select name="sort" onchange="this.form.submit()" aria-label="Sort prayers">
            <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Most Recent</option>
            <option value="prayed" <?= $sort === 'prayed' ? 'selected' : '' ?>>Most Prayed For</option>
          </select>
        </form>
      </div>

      <div class="pw-chips">
        <a class="pw-chip <?= $cat === '' ? 'on' : '' ?>" href="prayer.php?sort=<?= h($sort) ?>">All <small><?= $total_live ?></small></a>
        <?php foreach ($PRAYER_CATEGORIES as $c => $emoji): ?>
          <a class="pw-chip <?= $cat === $c ? 'on' : '' ?>" href="prayer.php?cat=<?= urlencode($c) ?>&sort=<?= h($sort) ?>">
            <?= $emoji . ' ' . h($c) ?> <small><?= $cat_counts[$c] ?? 0 ?></small>
          </a>
        <?php endforeach; ?>
      </div>

      <?php if (!$prayers): ?>
        <div class="pw-empty">No prayers here yet. Be the first to share.</div>
      <?php endif; ?>

      <?php foreach ($prayers as $p):
            $pid   = (int) $p['id'];
            $name  = $p['is_anonymous'] ? 'Anonymous' : $p['display_name'];
            $done  = isset($already[$pid]); ?>
        <article class="pw-item <?= $p['status'] === 'answered' ? 'answered' : '' ?>">
          <div class="pw-meta">
            <span>
              <span class="pw-tag"><?= h($p['category']) ?></span>
              <?php if ($p['status'] === 'answered'): ?><span class="pw-badge-ans">✓ Answered</span><?php endif; ?>
            </span>
            <span><?= time_ago($p['created_at']) ?></span>
          </div>
          <p class="pw-text"><?= h($p['request_text']) ?></p>
          <div class="pw-foot">
            <span>— <?= h($name) ?></span>
            <span>
              <span id="cnt-<?= $pid ?>"><?= (int) $p['prayed_count'] ?></span> prayed
              <button class="pw-pray" data-id="<?= $pid ?>" <?= $done ? 'disabled' : '' ?>>
                <?= $done ? '🙏 You prayed' : '🙏 I prayed' ?>
              </button>
            </span>
          </div>
          <?php if ($is_pastor): ?>
            <form method="post" class="pw-mod" style="margin-top:10px">
              <input type="hidden" name="action" value="moderate">
              <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="id" value="<?= $pid ?>">
              <?php if ($p['status'] !== 'answered'): ?><button class="a" name="do" value="answered">Mark answered</button><?php endif; ?>
              <button class="r" name="do" value="reject">Remove</button>
            </form>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>

    </section>
  </div>
</main>

<script>
  const PW_CSRF = <?= json_encode($_SESSION['csrf_token']) ?>;

  /* character counter */
  const pwText = document.getElementById('pw-text');
  const pwChars = document.getElementById('pw-chars');
  if (pwText) pwText.addEventListener('input', () => pwChars.textContent = pwText.value.length);

  /* "I prayed" buttons */
  document.querySelectorAll('.pw-pray').forEach(btn => {
    btn.addEventListener('click', async () => {
      btn.disabled = true;
      try {
        const body = new URLSearchParams({ action: 'pray', id: btn.dataset.id, csrf: PW_CSRF });
        const res  = await fetch('prayer.php', { method: 'POST', body });
        const data = await res.json();
        if (data.ok) {
          document.getElementById('cnt-' + btn.dataset.id).textContent = data.count;
          btn.textContent = '🙏 You prayed';
        } else {
          btn.disabled = false;
          alert(data.msg || 'Could not record your prayer.');
        }
      } catch (e) {
        btn.disabled = false;
        alert('Connection problem. Please try again.');
      }
    });
  });
</script>

<?php require_once 'includes/footer.php'; ?>
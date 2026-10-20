<?php
/* ═══════════════════════════════════════
   admin_dashboard.php  —  ADMIN / PASTOR DASHBOARD
   ACCESS : role = admin or pastor only (RBAC)
   FEATURES:
     - KPI cards (members, donations, events, pending prayers)
     - Membership directory (activate / deactivate)
     - Donation report with category totals
     - Prayer request approval (approve / reject)
     - Contact messages and announcements
   SECURITY: role guard, CSRF token on every POST,
             prepared statements, output escaped.
═══════════════════════════════════════ */
session_start();
require_once 'includes/db.php';
require_once 'includes/data.php';   /* ticker for nav; loaded first so it cannot overwrite page variables */

/* ── ROLE GUARD ─────────────────────── */
if (!isset($_SESSION['member_id'])) {
    header('Location: login.php');
    exit;
}
$user_role = $_SESSION['role'] ?? 'member';
if (!in_array($user_role, ['admin', 'pastor'], true)) {
    http_response_code(403);
    header('Location: member_portal.php');
    exit;
}

$is_logged_in = true;
$user_name    = htmlspecialchars($_SESSION['member_name'] ?? 'Staff');
$member_id    = (int) $_SESSION['member_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ── HANDLE ACTIONS (POST) ──────────── */
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_ok = isset($_POST['csrf'])
               && hash_equals($_SESSION['csrf_token'], $_POST['csrf']);
    if (!$csrf_ok) {
        $flash = 'Security token mismatch. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $id     = (int) ($_POST['id'] ?? 0);

        if ($id < 1) {
            $flash = 'Invalid record.';
        } elseif ($action === 'approve' || $action === 'reject') {
            $new_status = $action === 'approve' ? 'approved' : 'rejected';
            $stmt = $pdo->prepare(
                'UPDATE PRAYER_REQUEST
                    SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                  WHERE request_id = ?'
            );
            $stmt->execute([$new_status, $member_id, $id]);
            $flash = 'Prayer request ' . $new_status . '.';
        } elseif ($action === 'toggle_member') {
            if ($id === $member_id) {
                $flash = 'You cannot deactivate your own account.';
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE MEMBER SET is_active = 1 - is_active WHERE member_id = ?'
                );
                $stmt->execute([$id]);
                $flash = 'Member status updated.';
            }
        } elseif ($action === 'mark_read') {
            $stmt = $pdo->prepare(
                'UPDATE CONTACT_MESSAGE SET is_read = 1 WHERE message_id = ?'
            );
            $stmt->execute([$id]);
            $flash = 'Message marked as read.';
        } else {
            $flash = 'Unknown action.';
        }
    }
}

/* ── LOAD DATA ──────────────────────── */
$kpi = [
    'members'   => (int) $pdo->query('SELECT COUNT(*) FROM MEMBER WHERE is_active = 1')->fetchColumn(),
    'donations' => (float) $pdo->query('SELECT COALESCE(SUM(amount),0) FROM DONATION')->fetchColumn(),
    'events'    => (int) $pdo->query('SELECT COUNT(*) FROM EVENT WHERE event_date >= NOW() AND is_active = 1')->fetchColumn(),
    'pending'   => (int) $pdo->query("SELECT COUNT(*) FROM PRAYER_REQUEST WHERE status = 'pending'")->fetchColumn(),
    'unread'    => (int) $pdo->query('SELECT COUNT(*) FROM CONTACT_MESSAGE WHERE is_read = 0')->fetchColumn(),
];

$members = $pdo->query(
    'SELECT member_id, first_name, last_name, email, role, membership_type, is_active
       FROM MEMBER ORDER BY member_id'
)->fetchAll();

$donations = $pdo->query(
    'SELECT d.donation_date, d.amount, d.donation_type, d.payment_method,
            m.first_name, m.last_name
       FROM DONATION d JOIN MEMBER m ON m.member_id = d.member_id
   ORDER BY d.donation_date DESC, d.donation_id DESC LIMIT 10'
)->fetchAll();

$by_type = $pdo->query(
    'SELECT donation_type, COUNT(*) AS gifts, SUM(amount) AS total
       FROM DONATION GROUP BY donation_type ORDER BY total DESC'
)->fetchAll();

$prayers = $pdo->query(
    "SELECT request_id, display_name, category, message, created_at
       FROM PRAYER_REQUEST WHERE status = 'pending'
   ORDER BY created_at ASC"
)->fetchAll();

$messages = $pdo->query(
    'SELECT message_id, name, email, subject, message, is_read, created_at
       FROM CONTACT_MESSAGE ORDER BY created_at DESC LIMIT 6'
)->fetchAll();

function e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

$page_title = 'Dashboard | Shammah Urban Church';
$page_desc  = 'Admin and pastor dashboard for Shammah Urban Church.';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>
<style>
  .dash{max-width:1180px;margin:0 auto;padding:40px 20px 70px}
  .dash h1{font-family:var(--font-d);color:var(--navy);font-size:clamp(24px,4vw,36px);margin-bottom:4px}
  .dash .sub{color:var(--text-3);margin-bottom:24px}
  .kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px;margin-bottom:28px}
  .kpi{background:#fff;border:1px solid var(--border);border-radius:var(--r-lg);padding:20px;box-shadow:var(--shadow-sm)}
  .kpi b{display:block;font-size:28px;color:var(--navy);font-family:var(--font-d)}
  .kpi span{font-size:13px;color:var(--text-3)}
  .panel{background:#fff;border:1px solid var(--border);border-radius:var(--r-lg);padding:22px;margin-bottom:24px;box-shadow:var(--shadow-sm)}
  .panel h2{font-family:var(--font-d);color:var(--navy);font-size:20px;margin-bottom:14px}
  .tbl-wrap{overflow-x:auto}
  .dash table{width:100%;border-collapse:collapse;font-size:14px}
  .dash th{background:var(--navy);color:var(--gold);text-align:left;padding:10px 12px;font-size:12px;letter-spacing:1px;text-transform:uppercase}
  .dash td{padding:10px 12px;border-bottom:1px solid var(--border);vertical-align:top}
  .pill{display:inline-block;padding:2px 10px;border-radius:99px;font-size:12px;font-weight:600}
  .pill.ok{background:#e6f4ea;color:#2e7d32}.pill.off{background:#fdecea;color:#b71c1c}.pill.role{background:#f5eaf0;color:#3d0c2f}
  .dbtn{border:0;border-radius:8px;padding:7px 14px;font-weight:700;cursor:pointer;font-size:13px;min-height:36px}
  .dbtn.go{background:var(--gold);color:var(--navy)}.dbtn.no{background:#fdecea;color:#b71c1c}.dbtn.neutral{background:#f5eaf0;color:var(--navy)}
  .flash{background:#fef9e7;border-left:4px solid var(--gold);padding:12px 16px;border-radius:0 8px 8px 0;margin-bottom:20px;font-weight:600;color:var(--navy)}
  .two{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:24px}
  .empty{color:var(--text-3);font-style:italic}
</style>

<main id="main-content">
  <div class="dash">
    <h1>Welcome, <em><?= $user_name ?></em></h1>
    <p class="sub"><?= e(ucfirst($user_role)) ?> dashboard · <?= date('l, j F Y') ?></p>

    <?php if ($flash): ?>
      <div class="flash" role="status"><?= e($flash) ?></div>
    <?php endif; ?>

    <section class="kpis" aria-label="Key figures">
      <div class="kpi"><b><?= $kpi['members'] ?></b><span>Active members</span></div>
      <div class="kpi"><b>R <?= number_format($kpi['donations'], 2) ?></b><span>Total donations recorded</span></div>
      <div class="kpi"><b><?= $kpi['events'] ?></b><span>Upcoming events</span></div>
      <div class="kpi"><b><?= $kpi['pending'] ?></b><span>Prayer requests awaiting approval</span></div>
      <div class="kpi"><b><?= $kpi['unread'] ?></b><span>Unread contact messages</span></div>
    </section>

    <!-- PRAYER APPROVAL -->
    <section class="panel" id="prayers">
      <h2>Prayer request approval</h2>
      <?php if (!$prayers): ?>
        <p class="empty">No requests waiting for approval.</p>
      <?php else: ?>
      <div class="tbl-wrap"><table>
        <thead><tr><th>From</th><th>Category</th><th>Request</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($prayers as $p): ?>
          <tr>
            <td><?= e($p['display_name']) ?></td>
            <td><?= e($p['category']) ?></td>
            <td><?= e($p['message']) ?></td>
            <td>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="id" value="<?= (int) $p['request_id'] ?>">
                <button class="dbtn go" name="action" value="approve">Approve</button>
                <button class="dbtn no" name="action" value="reject">Reject</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </section>

    <div class="two">
      <!-- DONATIONS -->
      <section class="panel" id="donations">
        <h2>Donation report</h2>
        <div class="tbl-wrap"><table>
          <thead><tr><th>Category</th><th>Gifts</th><th>Total</th></tr></thead>
          <tbody>
          <?php foreach ($by_type as $t): ?>
            <tr><td><?= e($t['donation_type']) ?></td><td><?= (int) $t['gifts'] ?></td><td>R <?= number_format((float) $t['total'], 2) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <h2 style="margin-top:20px">Latest gifts</h2>
        <div class="tbl-wrap"><table>
          <thead><tr><th>Date</th><th>Member</th><th>Type</th><th>Method</th><th>Amount</th></tr></thead>
          <tbody>
          <?php foreach ($donations as $d): ?>
            <tr>
              <td><?= e($d['donation_date']) ?></td>
              <td><?= e($d['first_name'] . ' ' . $d['last_name']) ?></td>
              <td><?= e($d['donation_type']) ?></td>
              <td><?= e($d['payment_method']) ?></td>
              <td>R <?= number_format((float) $d['amount'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      </section>

      <!-- CONTACT MESSAGES -->
      <section class="panel" id="messages">
        <h2>Contact messages</h2>
        <div class="tbl-wrap"><table>
          <thead><tr><th>From</th><th>Subject / message</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($messages as $m): ?>
            <tr>
              <td><strong><?= e($m['name']) ?></strong><br><small><?= e($m['email']) ?></small></td>
              <td><strong><?= e($m['subject']) ?></strong><br><?= e($m['message']) ?></td>
              <td>
                <?php if ($m['is_read']): ?>
                  <span class="pill ok">Read</span>
                <?php else: ?>
                  <form method="post">
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="id" value="<?= (int) $m['message_id'] ?>">
                    <button class="dbtn neutral" name="action" value="mark_read">Mark read</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      </section>
    </div>

    <!-- MEMBERS -->
    <section class="panel" id="members">
      <h2>Membership directory</h2>
      <div class="tbl-wrap"><table>
        <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Type</th><th>Role</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($members as $m): ?>
          <tr>
            <td><?= (int) $m['member_id'] ?></td>
            <td><?= e($m['first_name'] . ' ' . $m['last_name']) ?></td>
            <td><?= e($m['email']) ?></td>
            <td><?= e($m['membership_type']) ?></td>
            <td><span class="pill role"><?= e(ucfirst($m['role'])) ?></span></td>
            <td><?= $m['is_active'] ? '<span class="pill ok">Active</span>' : '<span class="pill off">Inactive</span>' ?></td>
            <td>
              <form method="post">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="id" value="<?= (int) $m['member_id'] ?>">
                <button class="dbtn neutral" name="action" value="toggle_member"
                        onclick="return confirm('Change this member\'s status?')">
                  <?= $m['is_active'] ? 'Deactivate' : 'Activate' ?>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </section>
  </div>
</main>

<?php require_once 'includes/footer.php'; ?>

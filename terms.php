<?php
session_start();
require_once 'includes/data.php';
$is_logged_in = isset($_SESSION['member_id']);
$user_name    = $is_logged_in ? htmlspecialchars($_SESSION['member_name'] ?? 'Member') : '';
$user_role    = $is_logged_in ? ($_SESSION['role'] ?? 'member') : '';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$page_title = 'Terms of Use | Shammah Urban Church';
$page_desc  = 'Shammah Urban Church Terms of Use.';
require_once 'includes/header.php';
require_once 'includes/nav.php';
?>
<main id="main-content">
  <section style="background:linear-gradient(135deg,var(--navy) 0%,#6B1A52 100%);padding:80px 24px 56px;text-align:center;position:relative;overflow:hidden">
    <div style="position:absolute;inset:0;opacity:.06;background-image:repeating-linear-gradient(45deg,var(--gold) 0,var(--gold) 1px,transparent 0,transparent 50%);background-size:20px 20px;pointer-events:none"></div>
    <div style="position:relative">
      <div style="font-size:11px;font-weight:600;letter-spacing:3px;text-transform:uppercase;color:var(--gold);margin-bottom:12px">Legal</div>
      <h1 style="font-family:var(--font-d);font-size:clamp(24px,4vw,40px);font-weight:800;color:#fff;line-height:1.2">Terms of <em style="color:var(--gold);font-style:italic">Use</em></h1>
    </div>
  </section>
  <section class="section" style="background:var(--ivory)">
    <div class="section-narrow" style="background:#fff;border:1px solid var(--border);border-radius:var(--r-lg);padding:40px 36px;box-shadow:var(--shadow-sm);font-size:14.5px;color:var(--text-2);line-height:1.8">
      <h2 class="section-title" style="margin-bottom:20px">Terms of <em>Use</em></h2>
      <p>By registering for a Shammah Urban Church member account you agree to use the platform responsibly and in accordance with the values of our church community.</p>
      <h3 style="color:var(--navy);font-family:var(--font-d);margin:24px 0 8px">Member Responsibilities</h3>
      <p>You are responsible for maintaining the confidentiality of your account credentials. You agree not to share your login details or use another member's account. Any activity under your account is your responsibility.</p>
      <h3 style="color:var(--navy);font-family:var(--font-d);margin:24px 0 8px">Acceptable Use</h3>
      <p>The member portal is provided for personal church-related use only. You may not use it for commercial purposes, to distribute unsolicited communications, or in any way that violates South African law.</p>
      <h3 style="color:var(--navy);font-family:var(--font-d);margin:24px 0 8px">Changes to Terms</h3>
      <p>We reserve the right to update these terms at any time. Continued use of the portal after changes are posted constitutes acceptance of the revised terms.</p>
      <p style="margin-top:24px;font-size:13px;color:var(--text-3)">Last updated: May 2025 · Shammah Urban Church, 1103 Eland Street, Mooikloof, Pretoria East, 0059</p>
      <div style="margin-top:28px">
        <a href="register.php" class="btn-outline-navy"><i class="ti ti-arrow-left"></i> Back to Registration</a>
      </div>
    </div>
  </section>
</main>
<?php require_once 'includes/footer.php'; ?>

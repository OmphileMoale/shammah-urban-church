<?php
session_start();
require_once 'includes/data.php';
$is_logged_in = isset($_SESSION['member_id']);
$user_name    = $is_logged_in ? htmlspecialchars($_SESSION['member_name'] ?? 'Member') : '';
$user_role    = $is_logged_in ? ($_SESSION['role'] ?? 'member') : '';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$page_title = 'Privacy Policy | Shammah Urban Church';
$page_desc  = 'Shammah Urban Church Privacy Policy.';
require_once 'includes/header.php';
require_once 'includes/nav.php';
?>
<main id="main-content">
  <section style="background:linear-gradient(135deg,var(--navy) 0%,#6B1A52 100%);padding:80px 24px 56px;text-align:center;position:relative;overflow:hidden">
    <div style="position:absolute;inset:0;opacity:.06;background-image:repeating-linear-gradient(45deg,var(--gold) 0,var(--gold) 1px,transparent 0,transparent 50%);background-size:20px 20px;pointer-events:none"></div>
    <div style="position:relative">
      <div style="font-size:11px;font-weight:600;letter-spacing:3px;text-transform:uppercase;color:var(--gold);margin-bottom:12px">Legal</div>
      <h1 style="font-family:var(--font-d);font-size:clamp(24px,4vw,40px);font-weight:800;color:#fff;line-height:1.2">Privacy <em style="color:var(--gold);font-style:italic">Policy</em></h1>
    </div>
  </section>
  <section class="section" style="background:var(--ivory)">
    <div class="section-narrow" style="background:#fff;border:1px solid var(--border);border-radius:var(--r-lg);padding:40px 36px;box-shadow:var(--shadow-sm);font-size:14.5px;color:var(--text-2);line-height:1.8">
      <h2 class="section-title" style="margin-bottom:20px">Our Commitment to Your <em>Privacy</em></h2>
      <p>Shammah Urban Church respects and protects the personal information of all members in accordance with the <strong>Protection of Personal Information Act (POPIA), Act 4 of 2013</strong>.</p>
      <h3 style="color:var(--navy);font-family:var(--font-d);margin:24px 0 8px">Information We Collect</h3>
      <p>We collect your name, contact details, date of birth, address, and ministry preferences solely to manage your church membership, communicate with you about events and services, and fulfil our pastoral responsibilities.</p>
      <h3 style="color:var(--navy);font-family:var(--font-d);margin:24px 0 8px">How We Use Your Data</h3>
      <p>Your data is used only for church administration, event management, donation receipts, and ministry coordination. We do not sell, rent, or share your personal information with third parties.</p>
      <h3 style="color:var(--navy);font-family:var(--font-d);margin:24px 0 8px">Your Rights Under POPIA</h3>
      <p>You have the right to access, correct, or request deletion of your personal information at any time. Contact us at <a href="mailto:info@shammahurbanchurch.co.za" style="color:var(--navy);font-weight:600">info@shammahurbanchurch.co.za</a> to exercise these rights.</p>
      <p style="margin-top:24px;font-size:13px;color:var(--text-3)">Last updated: May 2025 · Shammah Urban Church, 1103 Eland Street, Mooikloof, Pretoria East, 0059</p>
      <div style="margin-top:28px">
        <a href="register.php" class="btn-outline-navy"><i class="ti ti-arrow-left"></i> Back to Registration</a>
      </div>
    </div>
  </section>
</main>
<?php require_once 'includes/footer.php'; ?>

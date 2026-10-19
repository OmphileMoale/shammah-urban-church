<?php
/* ═══════════════════════════════════════
   includes/nav.php
   PURPOSE: The ticker announcement strip
   and the sticky navigation bar.
   
   REQUIRES (must be set before including):
   - $ticker_str  (from includes/data.php)
   - $is_logged_in (bool)
   - $user_name    (string, safe HTML)
   - $user_role    (string: 'admin'|'pastor'|'member')
   
   HOW PHP IS MIXED WITH HTML HERE:
   <?php if (...): ?> ... <?php else: ?> ... <?php endif; ?>
   is PHP's "alternative syntax" for conditionals inside HTML.
   It's cleaner than echo-ing HTML from inside PHP.
═══════════════════════════════════════ */
?>

<!-- ══════════ ANNOUNCEMENT TICKER ══════════ -->
<!--
  role="marquee" tells screen readers this is moving content.
  aria-label gives it an accessible name.
  The inner track is aria-hidden because screen readers
  would read it twice (we have two copies for the loop).
-->
<div class="ticker-wrap" aria-label="Latest announcements" role="marquee">
  <span class="ticker-badge">Live</span>
  <div class="ticker-track" aria-hidden="true">
    <!-- We output the ticker_str twice so it scrolls infinitely.
         The CSS animation only moves -50% (one full copy), then
         jumps back to 0 and repeats — creating a seamless loop. -->
    <span><?= $ticker_str ?></span>
    <span><?= $ticker_str ?></span>
  </div>
</div>

<!-- ══════════ NAVIGATION BAR ══════════ -->
<nav class="nav" id="mainNav" aria-label="Main navigation">

  <!-- Brand / logo -->
  <a class="nav-brand" href="index.php" aria-label="Shammah Urban Church home">
    <div class="nav-emblem" aria-hidden="true">SUC</div>
    <div class="nav-church">
      <strong>Shammah Urban Church</strong>
      <small>Pretoria, South Africa</small>
    </div>
  </a>

  <!-- Desktop link list -->
  <ul class="nav-links" role="list">
    <!--
      PHP basename(__FILE__) gives the current file name, e.g. "index.php".
      We compare it to each href to add class="active" to the current page.
      This highlights the correct nav item without writing separate nav
      HTML on every page.
    -->
    <li><a href="index.php"      <?= basename($_SERVER['PHP_SELF']) === 'index.php'      ? 'class="active" aria-current="page"' : '' ?>>Home</a></li>
    <li><a href="about.php"      <?= basename($_SERVER['PHP_SELF']) === 'about.php'      ? 'class="active" aria-current="page"' : '' ?>>About</a></li>
    <li><a href="events.php"     <?= basename($_SERVER['PHP_SELF']) === 'events.php'     ? 'class="active" aria-current="page"' : '' ?>>Events</a></li>
    <li><a href="sermons.php"    <?= basename($_SERVER['PHP_SELF']) === 'sermons.php'    ? 'class="active" aria-current="page"' : '' ?>>Sermons</a></li>
    <li><a href="ministries.php" <?= basename($_SERVER['PHP_SELF']) === 'ministries.php' ? 'class="active" aria-current="page"' : '' ?>>Ministries</a></li>
    <li><a href="donate.php"     <?= basename($_SERVER['PHP_SELF']) === 'donate.php'     ? 'class="active" aria-current="page"' : '' ?>>Give</a></li>
    <li><a href="prayer.php"     <?= basename($_SERVER['PHP_SELF']) === 'prayer.php'     ? 'class="active" aria-current="page"' : '' ?>>Prayer</a></li>
    <li><a href="contact.php"    <?= basename($_SERVER['PHP_SELF']) === 'contact.php'    ? 'class="active" aria-current="page"' : '' ?>>Contact</a></li>
    <li><a href="gallery.php"    <?= basename($_SERVER['PHP_SELF']) === 'gallery.php'    ? 'class="active" aria-current="page"' : '' ?>>Gallery</a></li>
  </ul>

  <!-- Login / Register / Portal buttons -->
  <div class="nav-actions">
    <?php if ($is_logged_in): ?>
      <!--
        If the user is admin or pastor, send them to admin_dashboard.php.
        Otherwise send them to member_portal.php.
        This is a ternary operator: condition ? value_if_true : value_if_false
      -->
      <a href="<?= ($user_role === 'admin' || $user_role === 'pastor') ? 'admin_dashboard.php' : 'member_portal.php' ?>"
         class="nav-portal" aria-label="Go to your portal">
        <i class="ti ti-user-circle" aria-hidden="true"></i>
        <?= $user_name ?>
      </a>
      <a href="logout.php">
        <button class="nav-btn-login">Logout</button>
      </a>
    <?php else: ?>
      <a href="login.php"><button class="nav-btn-login">Login</button></a>
      <a href="register.php"><button class="nav-btn-register">Register</button></a>
    <?php endif; ?>
  </div>

  <!-- Hamburger button (mobile only – shown via CSS at ≤768px) -->
  <button class="nav-hamburger" id="hamburger"
          aria-label="Open navigation menu"
          aria-expanded="false"
          aria-controls="navDrawer">
    <span></span>
    <span></span>
    <span></span>
  </button>
</nav>

<!-- ══════════ MOBILE DRAWER ══════════ -->
<!--
  This slides down from below the nav on mobile.
  JS in main.js toggles the class "open" on both
  the hamburger and this drawer when the burger is clicked.
-->
<div class="nav-drawer" id="navDrawer" role="dialog" aria-label="Mobile navigation">
  <a href="index.php">Home</a>
  <a href="about.php">About Us</a>
  <a href="events.php">Events</a>
  <a href="sermons.php">Sermons</a>
  <a href="ministries.php">Ministries</a>
  <a href="donate.php">Give Online</a>
  <a href="prayer.php">Prayer</a>
  <a href="contact.php">Contact</a>
  <a href="gallery.php">Gallery</a>
  <div class="drawer-btns">
    <button class="nav-btn-login"
            onclick="location.href='login.php'"
            style="border:1px solid rgba(255,255,255,.2);border-radius:var(--r);color:rgba(255,255,255,.7);background:transparent">
      Login
    </button>
    <button class="nav-btn-register"
            onclick="location.href='register.php'"
            style="border-radius:var(--r)">
      Register
    </button>
  </div>
</div>

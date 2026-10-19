<?php
/* ═══════════════════════════════════════
   includes/header.php
   PURPOSE: The HTML <head> section and
   the opening <body> tag.
   
   WHY SEPARATE?
   Every page on the site needs the same
   <head> block. Putting it here means you
   only update it once, not on every file.
   
   USAGE (at top of any page):
     <?php
       $page_title = 'About Us';
       $page_desc  = 'Learn about our church.';
       require_once 'includes/header.php';
     ?>
   
   The variables $page_title and $page_desc
   must be set BEFORE requiring this file.
═══════════════════════════════════════ */

// Provide safe defaults if the page didn't set them
$page_title = $page_title ?? 'Shammah Urban Church | Home';
$page_desc  = $page_desc  ?? 'Shammah Urban Church — Experience the Touch of His Presence. Join us Sundays in Pretoria, South Africa.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <!-- viewport: makes the site look correct on phones -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- meta description: used by Google in search results -->
  <meta name="description" content="<?= htmlspecialchars($page_desc) ?>">
  <title><?= htmlspecialchars($page_title) ?></title>

  <!-- Google Fonts: preconnect speeds up the font request -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;0,800;1,600&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

  <!-- Tabler Icons: a free icon library used for ti-calendar, ti-clock, etc. -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.0.0/tabler-icons.min.css">

  <!-- Our CSS files (order matters – style.css must load first
       because nav.css, hero.css and components.css all use the
       CSS variables defined in style.css) -->
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/nav.css">
  <link rel="stylesheet" href="css/hero.css">
  <link rel="stylesheet" href="css/components.css">
  <?php
  /*
   * Load page-specific CSS only when needed.
   * basename(__FILE__) would give header.php here,
   * so we use $_SERVER['PHP_SELF'] to get the actual page.
   * This keeps unnecessary CSS off every other page.
   */
  $current_page = basename($_SERVER['PHP_SELF']);
  if ($current_page === 'about.php'): ?>
  <link rel="stylesheet" href="css/about.css">
  <?php elseif ($current_page === 'contact.php'): ?>
  <link rel="stylesheet" href="css/contact.css">
  <?php elseif ($current_page === 'events.php'): ?>
  <link rel="stylesheet" href="css/events.css">
  <?php elseif ($current_page === 'register.php'): ?>
  <link rel="stylesheet" href="css/register.css">
  <?php elseif ($current_page === 'login.php'): ?>
  <link rel="stylesheet" href="css/login.css">
  <?php elseif ($current_page === 'donate.php'): ?>
  <link rel="stylesheet" href="css/donate.css">
  <?php elseif ($current_page === 'sermons.php'): ?>
  <link rel="stylesheet" href="css/sermons.css">
  <?php elseif ($current_page === 'volunteers.php'): ?>
  <link rel="stylesheet" href="css/volunteers.css">
  <?php elseif ($current_page === 'prayer.php'): ?>
  <link rel="stylesheet" href="css/prayer.css">
  <?php elseif ($current_page === 'member_portal.php'): ?>
  <link rel="stylesheet" href="css/portal.css">
  <?php elseif ($current_page === 'gallery.php'): ?>
  <link rel="stylesheet" href="css/gallery.css">
  <?php endif; ?>
</head>
<body id="top">

<!-- Accessibility: lets keyboard users skip the nav -->
<a href="#main-content" class="skip-link">Skip to main content</a>

<!-- Toast container: JS will inject notifications here -->
<div id="toast-container" role="alert" aria-live="polite"></div>

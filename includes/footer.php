<?php
/* ═══════════════════════════════════════
   includes/footer.php
   PURPOSE: Site footer + floating buttons.
   Included at the bottom of every page.
═══════════════════════════════════════ */
?>

<!-- ══════════ FOOTER ══════════ -->
<footer class="footer" role="contentinfo">
  <div class="footer-grid">

    <!-- Column 1: brand & social -->
    <div class="footer-brand">
      <div class="logo" aria-hidden="true">SUC</div>
      <h3>Shammah Urban Church</h3>
      <p class="tagline">"Experience the Touch of His Presence"</p>
      <p>
        1103 Eland Street, Mooikloof<br>
        Pretoria East, 0059, South Africa<br>
        +27 12 345 6789<br>
        info@shammahurbanchurch.co.za
      </p>
      <div class="footer-social" aria-label="Social media links">
        <a href="#" class="social-btn" aria-label="Facebook">
          <i class="ti ti-brand-facebook" aria-hidden="true"></i>
        </a>
        <a href="#" class="social-btn" aria-label="Instagram">
          <i class="ti ti-brand-instagram" aria-hidden="true"></i>
        </a>
        <a href="#" class="social-btn" aria-label="YouTube">
          <i class="ti ti-brand-youtube" aria-hidden="true"></i>
        </a>
        <a href="https://wa.me/27123456789" class="social-btn" aria-label="WhatsApp">
          <i class="ti ti-brand-whatsapp" aria-hidden="true"></i>
        </a>
      </div>
    </div>

    <!-- Column 2: quick links -->
    <div class="footer-col">
      <h4>Quick Links</h4>
      <a href="index.php">Home</a>
      <a href="about.php">About Us</a>
      <a href="events.php">Events</a>
      <a href="sermons.php">Sermons</a>
      <a href="gallery.php">Gallery</a>
    </div>

    <!-- Column 3: ministries -->
    <div class="footer-col">
      <h4>Ministries</h4>
      <a href="ministries.php">Worship</a>
      <a href="ministries.php">Youth</a>
      <a href="ministries.php">Women of Purpose</a>
      <a href="ministries.php">Outreach</a>
      <a href="ministries.php">Cell Groups</a>
    </div>

    <!-- Column 4: service times + quick actions -->
    <div class="footer-col">
      <h4>Service Times</h4>
      <p>Sunday: 9:00 AM &amp; 11:00 AM</p>
      <p>Wednesday: 6:30 PM</p>
      <p>Youth Friday: 6:00 PM</p>
      <br>
      <h4 style="margin-top:4px">Quick Actions</h4>
      <a href="donate.php">Give Online</a>
      <a href="prayer.php">Prayer Request</a>
      <a href="contact.php">Contact Us</a>
    </div>
  </div>

  <!-- Bottom bar: copyright + student info -->
  <!--
    date('Y') outputs the current 4-digit year automatically.
    No need to update it every January.
  -->
  <div class="footer-bottom">
    <span>© <?= date('Y') ?> Shammah Urban Church · All Rights Reserved</span>
    <span>Built by <strong style="color:rgba(255,255,255,.35)">CodeVision Team</strong>
          · ST10449832 &amp; ST10455873 · XISD5319</span>
  </div>
</footer>

<!-- ══════════ FLOATING BUTTONS ══════════ -->

<!-- WhatsApp chat button (bottom-right corner) -->
<a href="https://wa.me/27123456789?text=Hi%2C%20I%27d%20like%20to%20learn%20more%20about%20Shammah%20Urban%20Church"
   class="wa-btn"
   target="_blank"
   rel="noopener noreferrer"
   aria-label="Chat with us on WhatsApp">
  <i class="ti ti-brand-whatsapp" aria-hidden="true"></i>
  <span class="wa-tooltip">Chat with us on WhatsApp</span>
</a>

<!-- Back to top button (appears after user scrolls 300px) -->
<button class="back-top" id="backTop"
        aria-label="Back to top"
        onclick="window.scrollTo({top:0,behavior:'smooth'})">
  <i class="ti ti-arrow-up" aria-hidden="true"></i>
</button>

<!-- JavaScript files loaded at end of body so HTML renders first -->
<script src="js/toast.js"></script>
<script src="js/main.js"></script>
<script src="js/rsvp.js"></script>
<?php if (basename($_SERVER['PHP_SELF']) === 'events.php'): ?>
<script src="js/events.js"></script>
<?php elseif (basename($_SERVER['PHP_SELF']) === 'register.php'): ?>
<script src="js/register.js"></script>
<?php elseif (basename($_SERVER['PHP_SELF']) === 'sermons.php'): ?>
<script src="js/sermons.js"></script>
<?php elseif (basename($_SERVER['PHP_SELF']) === 'volunteers.php'): ?>
<script src="js/volunteers.js"></script>
<?php elseif (basename($_SERVER['PHP_SELF']) === 'prayer.php'): ?>
<script src="js/prayer.js"></script>
<?php elseif (basename($_SERVER['PHP_SELF']) === 'member_portal.php'): ?>
<script src="js/portal.js"></script>
<?php elseif (basename($_SERVER['PHP_SELF']) === 'gallery.php'): ?>
<script src="js/gallery.js"></script>
<?php endif; ?>

</body>
</html>

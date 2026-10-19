<?php
/* ═══════════════════════════════════════
   contact.php  –  CONTACT PAGE

   HOW THIS FILE WORKS:
   1. session_start()   – reads the PHP session
   2. require_once      – loads shared files
   3. FORM HANDLING     – processes POST submission
                          validates fields, sends email
                          via PHP mail(), returns JSON
   4. HTML sections     – Contact page body
   5. footer.php        – footer, floating buttons, JS
═══════════════════════════════════════ */
session_start();

require_once 'includes/data.php';

/* ── AUTH STATE ─────────────────────── */
$is_logged_in = isset($_SESSION['member_id']);
$user_name    = $is_logged_in ? htmlspecialchars($_SESSION['member_name'] ?? 'Member') : '';
$user_role    = $is_logged_in ? ($_SESSION['role'] ?? 'member') : '';

/* ═══════════════════════════════════════
   FORM HANDLER
   When the form is submitted via fetch()
   (AJAX), we return a JSON response
   instead of reloading the page.
   
   WHY JSON?
   It lets the JS show a toast notification
   without a full page reload — better UX.
   
   CSRF CHECK:
   We compare the token in the POST body
   against the one stored in $_SESSION.
   If they don't match, we reject the request
   to prevent cross-site forgery attacks.
═══════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Tell the browser we're returning JSON
    header('Content-Type: application/json');

    /* ── CSRF VALIDATION ────────────────
       $_POST['csrf'] must match $_SESSION['csrf_token'].
       hash_equals() is timing-safe (prevents timing attacks).
    ──────────────────────────────────── */
    $csrf_ok = isset($_POST['csrf'], $_SESSION['csrf_token'])
               && hash_equals($_SESSION['csrf_token'], $_POST['csrf']);
    if (!$csrf_ok) {
        echo json_encode(['ok' => false, 'msg' => 'Security token mismatch. Please refresh the page.']);
        exit;
    }

    /* ── SANITISE INPUTS ────────────────
       trim()            – removes leading/trailing whitespace
       htmlspecialchars()– converts < > & " to safe HTML entities
       filter_var()      – validates email format
    ──────────────────────────────────── */
    $name    = trim(htmlspecialchars($_POST['name']    ?? ''));
    $email   = trim(htmlspecialchars($_POST['email']   ?? ''));
    $subject = trim(htmlspecialchars($_POST['subject'] ?? ''));
    $message = trim(htmlspecialchars($_POST['message'] ?? ''));
    $phone   = trim(htmlspecialchars($_POST['phone']   ?? ''));

    /* ── SERVER-SIDE VALIDATION ─────────
       Always validate on the server even
       if JS validates first — JS can be
       bypassed by anyone with dev tools.
    ──────────────────────────────────── */
    $errors = [];
    if (strlen($name)    < 2)                        $errors[] = 'Please enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = 'Please enter a valid email address.';
    if (empty($subject))                             $errors[] = 'Please select a subject.';
    if (strlen($message) < 10)                       $errors[] = 'Your message must be at least 10 characters.';

    if (!empty($errors)) {
        echo json_encode(['ok' => false, 'msg' => implode(' ', $errors)]);
        exit;
    }

    /* ── SEND EMAIL ─────────────────────
       In production, replace the $sent = true
       line with a real mail() call:
       
       $to      = 'info@shammahurbanchurch.co.za';
       $headers = "From: {$name} <{$email}>\r\n"
                . "Reply-To: {$email}\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n";
       $body    = "Name: {$name}\nPhone: {$phone}\n\n{$message}";
       $sent    = mail($to, "[Contact Form] {$subject}", $body, $headers);
       
       For now we simulate success so you
       can test the UI without an SMTP server.
    ──────────────────────────────────── */
    $sent = true; // ← replace with real mail() above

    if ($sent) {
        // Log to session for demo — in production write to DB:
        // INSERT INTO CONTACT_LOG (name, email, subject, message, submitted_at) VALUES (...)
        echo json_encode(['ok' => true, 'msg' => "Thank you {$name}! Your message has been sent. We'll be in touch within 24 hours."]);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Sorry, we could not send your message right now. Please call or WhatsApp us directly.']);
    }
    exit;
}

/* ── GENERATE CSRF TOKEN ────────────────
   If no token exists yet, create one and
   store it in the session. It's used in
   the hidden form field and checked above.
──────────────────────────────────────── */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ── PAGE META ──────────────────────── */
$page_title = 'Contact Us | Shammah Urban Church';
$page_desc  = 'Get in touch with Shammah Urban Church — send us a message, find our location, or connect on social media.';

/* ── FAQ DATA ───────────────────────────
   Common questions answered inline.
   In production: SELECT * FROM FAQ ORDER BY sort_order ASC
──────────────────────────────────────── */
$faqs = [
    [
        'q' => 'Is parking available at the church?',
        'a' => 'Yes! Free parking is available in the Maragon High School parking lot on Eland Street. Disabled bays are marked near the main entrance.',
    ],
    [
        'q' => 'Are first-time visitors welcome?',
        'a' => 'Absolutely — everyone is welcome, no membership required. Our Welcome Desk team will greet you and answer any questions when you arrive.',
    ],
    [
        'q' => 'What should I wear?',
        'a' => 'Smart casual is the norm. Come as you are — we care more about your presence than your outfit.',
    ],
    [
        'q' => 'Is there childcare during the service?',
        'a' => 'Yes! Kids Church runs during both Sunday services for children aged 3–12. Our trained team makes it fun and age-appropriate.',
    ],
    [
        'q' => 'Can I watch services online?',
        'a' => 'Yes, all Sunday services are streamed live on our YouTube channel. Past sermons are also available on the Sermons page.',
    ],
    [
        'q' => 'How do I become a church member?',
        'a' => 'Register online or speak to a team member at the Welcome Desk. Our new members programme runs every quarter.',
    ],
];

/* ── HTML HEAD + OPENING BODY ───────── */
require_once 'includes/header.php';

/* ── TICKER + NAVIGATION ─────────────── */
require_once 'includes/nav.php';
?>

<!-- ══════════════ PAGE MAIN ══════════════ -->
<main id="main-content">

  <!-- ═══════════════════════════════════
       HERO / PAGE BANNER
       Same navy gradient pattern as About.
  ════════════════════════════════════ -->
  <section class="contact-hero" aria-label="Contact Shammah Urban Church">
    <div class="contact-hero-bg" aria-hidden="true"></div>
    <div class="contact-hero-inner">
      <div class="hero-eyebrow">We're Here For You</div>
      <h1>Get In <em>Touch</em></h1>
      <p class="contact-hero-sub">
        Have a question, prayer request, or want to visit for the first time?
        We'd love to hear from you.
      </p>
      <!-- Quick-action anchors jump to the form & info sections -->
      <div class="hero-btns">
        <a href="#contact-form" class="btn-gold">
          <i class="ti ti-mail" aria-hidden="true"></i> Send a Message
        </a>
        <a href="#find-us" class="btn-ghost">
          <i class="ti ti-map-pin" aria-hidden="true"></i> Find Us
        </a>
      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════
       QUICK CONNECT STRIP
       Big tappable blocks for the fastest
       contact methods — especially useful
       on mobile.
  ════════════════════════════════════ -->
  <section class="contact-quickstrip" aria-label="Quick contact options">
    <div class="section-wide">
      <div class="contact-quick-grid">

        <!-- Call -->
        <a href="tel:+27123456789" class="cq-item" aria-label="Call us">
          <div class="cq-icon" aria-hidden="true">
            <i class="ti ti-phone"></i>
          </div>
          <div class="cq-text">
            <strong>Call Us</strong>
            <span>+27 12 345 6789</span>
            <small>Mon–Fri 8AM–5PM · Sun 8AM–1PM</small>
          </div>
        </a>

        <!-- WhatsApp -->
        <a href="https://wa.me/27123456789?text=Hi%2C%20I%27d%20like%20to%20know%20more%20about%20Shammah%20Urban%20Church"
           target="_blank" rel="noopener noreferrer"
           class="cq-item cq-whatsapp" aria-label="WhatsApp us">
          <div class="cq-icon" aria-hidden="true">
            <i class="ti ti-brand-whatsapp"></i>
          </div>
          <div class="cq-text">
            <strong>WhatsApp</strong>
            <span>Chat with our team</span>
            <small>Usually replies within 1 hour</small>
          </div>
        </a>

        <!-- Email -->
        <a href="mailto:info@shammahurbanchurch.co.za" class="cq-item" aria-label="Email us">
          <div class="cq-icon" aria-hidden="true">
            <i class="ti ti-mail"></i>
          </div>
          <div class="cq-text">
            <strong>Email Us</strong>
            <span>info@shammahurbanchurch.co.za</span>
            <small>We reply within 24 hours</small>
          </div>
        </a>

        <!-- Directions -->
        <a href="https://maps.google.com/?q=1103+Eland+Street+Mooikloof+Pretoria+East"
           target="_blank" rel="noopener noreferrer"
           class="cq-item" aria-label="Get directions">
          <div class="cq-icon" aria-hidden="true">
            <i class="ti ti-navigation"></i>
          </div>
          <div class="cq-text">
            <strong>Get Directions</strong>
            <span>1103 Eland St, Mooikloof</span>
            <small>Opens Google Maps</small>
          </div>
        </a>

      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════
       MAIN CONTENT: FORM + MAP + INFO
       Two-column layout:
       Left  → contact form
       Right → info cards + Google Map
  ════════════════════════════════════ -->
  <section class="section contact-main-section" id="contact-form"
           aria-label="Contact form and church information">
    <div class="section-wide contact-two-col">

      <!-- ─── LEFT: CONTACT FORM ──────── -->
      <div class="contact-form-wrap">
        <div class="section-tag">Send a Message</div>
        <h2 class="section-title">We'd Love to <em>Hear From You</em></h2>
        <p class="section-sub" style="margin-bottom:28px">
          Fill in the form below and our team will get back to you within 24 hours.
        </p>

        <!--
          id="contactForm" is used by contact.js to intercept
          the submit event and send via fetch() (AJAX).
          
          novalidate disables the browser's built-in bubbles
          so our custom JS validation shows instead.
        -->
        <form id="contactForm" novalidate aria-label="Contact form">

          <!-- CSRF token (hidden) — verified server-side -->
          <input type="hidden" name="csrf"
                 value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

          <!-- Name + Phone row -->
          <div class="cf-row">
            <div class="cf-group">
              <label class="cf-label" for="cf-name">
                Full Name <span class="cf-required" aria-hidden="true">*</span>
              </label>
              <input class="cf-input" type="text" id="cf-name" name="name"
                     placeholder="e.g. Thabo Nkosi"
                     autocomplete="name"
                     required
                     aria-required="true"
                     aria-describedby="cf-name-err">
              <span class="cf-error" id="cf-name-err" role="alert"></span>
            </div>
            <div class="cf-group">
              <label class="cf-label" for="cf-phone">Phone (optional)</label>
              <input class="cf-input" type="tel" id="cf-phone" name="phone"
                     placeholder="e.g. 083 123 4567"
                     autocomplete="tel">
            </div>
          </div>

          <!-- Email -->
          <div class="cf-group">
            <label class="cf-label" for="cf-email">
              Email Address <span class="cf-required" aria-hidden="true">*</span>
            </label>
            <input class="cf-input" type="email" id="cf-email" name="email"
                   placeholder="e.g. thabo@email.com"
                   autocomplete="email"
                   required
                   aria-required="true"
                   aria-describedby="cf-email-err">
            <span class="cf-error" id="cf-email-err" role="alert"></span>
          </div>

          <!-- Subject dropdown -->
          <div class="cf-group">
            <label class="cf-label" for="cf-subject">
              Subject <span class="cf-required" aria-hidden="true">*</span>
            </label>
            <!--
              The subject options are hardcoded here.
              In production you could pull them from:
              SELECT subject_option FROM CONTACT_SUBJECTS ORDER BY sort_order
            -->
            <select class="cf-input cf-select" id="cf-subject" name="subject"
                    required aria-required="true" aria-describedby="cf-subject-err">
              <option value="">— Select a subject —</option>
              <option value="General Enquiry">General Enquiry</option>
              <option value="Membership">Membership</option>
              <option value="Events &amp; RSVP">Events &amp; RSVP</option>
              <option value="Prayer Request">Prayer Request</option>
              <option value="Pastoral Care">Pastoral Care</option>
              <option value="Volunteering">Volunteering</option>
              <option value="Donations &amp; Giving">Donations &amp; Giving</option>
              <option value="Media &amp; Sermons">Media &amp; Sermons</option>
            </select>
            <span class="cf-error" id="cf-subject-err" role="alert"></span>
          </div>

          <!-- Message textarea -->
          <div class="cf-group">
            <label class="cf-label" for="cf-message">
              Message <span class="cf-required" aria-hidden="true">*</span>
            </label>
            <textarea class="cf-input cf-textarea" id="cf-message" name="message"
                      placeholder="How can we help you? The more detail you give us, the better we can assist."
                      rows="5"
                      required
                      aria-required="true"
                      aria-describedby="cf-message-err"></textarea>
            <span class="cf-error" id="cf-message-err" role="alert"></span>
            <!-- Live character counter updated by contact.js -->
            <span class="cf-char-count" id="cf-char-count" aria-live="polite">0 / 500</span>
          </div>

          <!-- Submit button -->
          <button class="cf-submit" type="submit" id="cf-submit-btn">
            <i class="ti ti-send" aria-hidden="true"></i>
            <span id="cf-btn-label">Send Message</span>
          </button>

        </form>
      </div><!-- /.contact-form-wrap -->


      <!-- ─── RIGHT: INFO + MAP ─────── -->
      <div class="contact-info-wrap" id="find-us">

        <!-- 4 info cards -->
        <div class="contact-info-grid">

          <div class="ci-card">
            <div class="ci-icon" aria-hidden="true">📍</div>
            <h4>Our Address</h4>
            <p>1103 Eland Street<br>Mooikloof, Pretoria East<br>0059, South Africa</p>
          </div>

          <div class="ci-card">
            <div class="ci-icon" aria-hidden="true">📞</div>
            <h4>Phone</h4>
            <p>
              <a href="tel:+27123456789">+27 12 345 6789</a><br>
              Mon–Fri: 8:00 AM – 5:00 PM<br>
              Sundays: 8:00 AM – 1:00 PM
            </p>
          </div>

          <div class="ci-card">
            <div class="ci-icon" aria-hidden="true">✉️</div>
            <h4>Email</h4>
            <p>
              <a href="mailto:info@shammahurbanchurch.co.za">
                info@shammahurbanchurch.co.za
              </a><br>
              Replies within 24 hours
            </p>
          </div>

          <div class="ci-card">
            <div class="ci-icon" aria-hidden="true">⏰</div>
            <h4>Office Hours</h4>
            <p>
              Mon – Fri: 8:00 AM – 5:00 PM<br>
              Saturday: Closed<br>
              Sunday: 8:00 AM – 1:00 PM
            </p>
          </div>

        </div><!-- /.contact-info-grid -->

        <!-- Google Maps embed
             In production embed your real API key:
             <iframe src="https://www.google.com/maps/embed/v1/place
               ?key=YOUR_API_KEY
               &q=1103+Eland+Street+Mooikloof+Pretoria+East">
             </iframe>
             The iframe below uses the free embed (no key needed for basic use).
        -->
        <div class="contact-map-wrap" aria-label="Google Maps showing church location">
          <iframe
            class="contact-map-iframe"
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3591.5!2d28.3453!3d-25.7889!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x1e9564b2e9f4f1d5%3A0x0!2zTWFyYWdvbiBNb29pa2xvb2Y!5e0!3m2!1sen!2sza!4v1234567890"
            width="100%"
            height="280"
            style="border:0;"
            allowfullscreen=""
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            title="Shammah Urban Church location — 1103 Eland Street, Mooikloof, Pretoria East">
          </iframe>
          <!-- Fallback link if iframe blocked -->
          <a href="https://maps.google.com/?q=1103+Eland+Street+Mooikloof+Pretoria+East"
             target="_blank" rel="noopener noreferrer"
             class="contact-map-link">
            <i class="ti ti-external-link" aria-hidden="true"></i>
            Open in Google Maps
          </a>
        </div>

        <!-- Social media links -->
        <div class="contact-social-wrap">
          <h4 class="contact-social-title">Follow &amp; Connect</h4>
          <div class="contact-social-grid">
            <a href="#" class="cs-btn cs-fb" aria-label="Facebook"
               target="_blank" rel="noopener noreferrer">
              <i class="ti ti-brand-facebook" aria-hidden="true"></i>
              <span>Facebook</span>
            </a>
            <a href="#" class="cs-btn cs-ig" aria-label="Instagram"
               target="_blank" rel="noopener noreferrer">
              <i class="ti ti-brand-instagram" aria-hidden="true"></i>
              <span>Instagram</span>
            </a>
            <a href="#" class="cs-btn cs-yt" aria-label="YouTube"
               target="_blank" rel="noopener noreferrer">
              <i class="ti ti-brand-youtube" aria-hidden="true"></i>
              <span>YouTube</span>
            </a>
            <a href="https://wa.me/27123456789" class="cs-btn cs-wa" aria-label="WhatsApp"
               target="_blank" rel="noopener noreferrer">
              <i class="ti ti-brand-whatsapp" aria-hidden="true"></i>
              <span>WhatsApp</span>
            </a>
          </div>
        </div>

      </div><!-- /.contact-info-wrap -->

    </div><!-- /.contact-two-col -->
  </section>


  <!-- ═══════════════════════════════════
       HOW TO FIND US / DIRECTIONS
  ════════════════════════════════════ -->
  <section class="section contact-directions-section"
           aria-label="Directions and transport">
    <div class="section-wide">
      <div class="section-tag" style="justify-content:center">Getting Here</div>
      <h2 class="section-title" style="text-align:center">
        How to <em>Find Us</em>
      </h2>
      <p class="section-sub" style="text-align:center;margin:0 auto 40px">
        We're easy to reach from anywhere in Pretoria.
      </p>

      <div class="contact-directions-grid">

        <div class="cd-card">
          <div class="cd-icon" aria-hidden="true">🚗</div>
          <h4>By Car</h4>
          <p>From the N1 take the Bougainville off-ramp towards Mooikloof. Head east on Eland Street. Maragon High School is on your right — the church meets on the school grounds with free parking available.</p>
        </div>

        <div class="cd-card">
          <div class="cd-icon" aria-hidden="true">🚌</div>
          <h4>By Bus / Taxi</h4>
          <p>Take a taxi towards Mooikloof / Pretoria East. Alight at the Eland Street stop near Maragon High School. The venue is a short walk from the main gate.</p>
        </div>

        <div class="cd-card">
          <div class="cd-icon" aria-hidden="true">🚇</div>
          <h4>By Gautrain</h4>
          <p>Alight at Hatfield or Pretoria Gautrain Station. Take an Uber or taxi east towards Mooikloof — approximately 15–20 minutes. We recommend arranging a lift or using the Sunday Uber Pool option.</p>
        </div>

        <div class="cd-card">
          <div class="cd-icon" aria-hidden="true">🅿️</div>
          <h4>Parking</h4>
          <p>Free parking is available in the Maragon High School parking lot on Eland Street. Disabled bays are marked near the main entrance. Overflow parking is available on event days.</p>
        </div>

      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════
       FAQ SECTION
       Accordion-style — JS in contact.js
       toggles the open/close state.
  ════════════════════════════════════ -->
  <section class="section contact-faq-section"
           aria-label="Frequently asked questions"
           style="background:var(--rose)">
    <div class="section-wide">
      <div class="section-tag" style="justify-content:center">Got Questions?</div>
      <h2 class="section-title" style="text-align:center">
        Frequently Asked <em>Questions</em>
      </h2>
      <p class="section-sub" style="text-align:center;margin:0 auto 40px">
        Can't find your answer? Send us a message using the form above.
      </p>

      <div class="faq-list" role="list">
        <?php foreach ($faqs as $i => $faq): ?>
        <!--
          Each FAQ item is a <details> element.
          <details> + <summary> is native HTML accordion —
          no JS needed for open/close, but we add animation via CSS.
          
          open attribute on <details> expands it by default.
          We only open the first one.
        -->
        <details class="faq-item" role="listitem"
                 <?= $i === 0 ? 'open' : '' ?>>
          <summary class="faq-question">
            <span><?= htmlspecialchars($faq['q']) ?></span>
            <i class="ti ti-chevron-down faq-chevron" aria-hidden="true"></i>
          </summary>
          <div class="faq-answer">
            <p><?= htmlspecialchars($faq['a']) ?></p>
          </div>
        </details>
        <?php endforeach; ?>
      </div>

      <!-- Still have questions? prompt -->
      <div style="text-align:center;margin-top:40px">
        <p style="color:var(--text-2);margin-bottom:16px;font-size:15px">
          Still have a question we haven't answered?
        </p>
        <a href="#contact-form" class="btn-outline-navy">
          <i class="ti ti-message" aria-hidden="true"></i>
          Ask Us Directly
        </a>
      </div>

    </div>
  </section>


  <!-- ═══════════════════════════════════
       CTA SECTION — Visit Us Sunday
  ════════════════════════════════════ -->
  <section class="section contact-cta-section" aria-label="Visit us this Sunday">
    <div class="contact-cta-inner">
      <div class="about-cta-eyebrow">You're Always Welcome</div>
      <h2>Join Us <em>This Sunday</em></h2>
      <p>
        9:00 AM &amp; 11:00 AM · Main Sanctuary · 1103 Eland Street, Mooikloof, Pretoria East
      </p>
      <div class="hero-btns" style="justify-content:center">
        <a href="register.php" class="btn-gold">
          <i class="ti ti-calendar-event" aria-hidden="true"></i> Plan My Visit
        </a>
        <a href="sermons.php" class="btn-ghost">
          <i class="ti ti-player-play" aria-hidden="true"></i> Watch Online
        </a>
      </div>
    </div>
  </section>

</main><!-- /#main-content -->


<!-- ═══════════════════════════════════════
     PHP → JS BRIDGE
════════════════════════════════════════ -->
<script>
  const IS_LOGGED_IN = <?= json_encode($is_logged_in) ?>;
  const CSRF_TOKEN   = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
</script>

<!-- contact.js loaded before footer.php's scripts -->
<script src="js/contact.js"></script>

<?php require_once 'includes/footer.php'; ?>

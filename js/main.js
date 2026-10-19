/* ═══════════════════════════════════════
   js/main.js
   PURPOSE: General page interactivity.
   Handles:
   1. Sticky nav shadow on scroll
   2. Back-to-top button visibility
   3. Mobile hamburger / drawer toggle
   4. Animated stat counter
   5. Keyboard accessibility (Escape closes drawer)
═══════════════════════════════════════ */

/* ──────────────────────────────────────
   1 & 2: SCROLL LISTENER
   
   window.addEventListener('scroll', fn)
   runs fn every time the user scrolls.
   
   { passive: true } is a performance hint:
   it tells the browser "this listener will
   never call preventDefault()" so it can
   optimise the scroll rendering.
──────────────────────────────────────── */
const nav     = document.getElementById('mainNav');
const backTop = document.getElementById('backTop');

window.addEventListener('scroll', () => {
  // Add shadow to nav after any scrolling
  nav.classList.toggle('scrolled', window.scrollY > 10);

  // Show back-to-top button after 300px
  backTop.classList.toggle('visible', window.scrollY > 300);
}, { passive: true });


/* ──────────────────────────────────────
   3: HAMBURGER / MOBILE DRAWER
   
   When the hamburger button is clicked:
   - Toggle the 'open' class on the button
     (CSS rotates the 3 bars into an X)
   - Toggle the 'open' class on the drawer
     (CSS slides it down from above)
   - Set aria-expanded for screen readers
   - Lock body scroll so the page doesn't
     scroll behind the open menu
──────────────────────────────────────── */
const hamburger = document.getElementById('hamburger');
const drawer    = document.getElementById('navDrawer');

hamburger.addEventListener('click', () => {
  const isOpen = hamburger.classList.toggle('open');
  drawer.classList.toggle('open', isOpen);
  hamburger.setAttribute('aria-expanded', isOpen);
  document.body.style.overflow = isOpen ? 'hidden' : '';
});

// Close drawer if user clicks anywhere outside it
document.addEventListener('click', (e) => {
  // e.target = the element that was clicked
  // nav.contains(e.target) = was the click inside the nav?
  if (!nav.contains(e.target) && !drawer.contains(e.target)) {
    hamburger.classList.remove('open');
    drawer.classList.remove('open');
    hamburger.setAttribute('aria-expanded', false);
    document.body.style.overflow = '';
  }
});


/* ──────────────────────────────────────
   4: ANIMATED STAT COUNTER
   
   Uses IntersectionObserver to detect when
   the stats band scrolls into view, then
   animates each number from 0 up to its
   target value over 1.8 seconds.
   
   IntersectionObserver is more efficient
   than checking scroll position manually –
   the browser handles the detection natively.
──────────────────────────────────────── */

/**
 * animateCount – count a number up from 0 to target
 * @param {HTMLElement} el – the .stat-num element
 */
function animateCount(el) {
  const target   = parseInt(el.dataset.target, 10); // from data-target attribute
  const suffix   = el.dataset.suffix || '';          // from data-suffix attribute (e.g. '+')
  const duration = 1800;  // total animation time in ms
  const step     = 16;    // update interval (≈60fps)
  const steps    = duration / step;
  const increment = target / steps;
  let current = 0;

  const timer = setInterval(() => {
    current += increment;
    if (current >= target) {
      // Animation done – show exact target value
      el.textContent = target.toLocaleString() + suffix;
      clearInterval(timer); // stop the interval
    } else {
      // Still animating – show rounded current value
      el.textContent = Math.floor(current).toLocaleString() + suffix;
    }
  }, step);
}

// Create observer – fires when statsBand enters the viewport
const statsObserver = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (entry.isIntersecting) {
      // Start counting each stat number
      document.querySelectorAll('.stat-num').forEach(animateCount);
      // Stop observing – we only want to animate once
      statsObserver.disconnect();
    }
  });
}, { threshold: 0.4 }); // 40% of the element must be visible

const statsBand = document.querySelector('.stats-band');
if (statsBand) statsObserver.observe(statsBand);


/* ──────────────────────────────────────
   5: KEYBOARD ACCESSIBILITY
   
   Pressing Escape while the mobile menu
   is open should close it and return
   focus to the hamburger button.
──────────────────────────────────────── */
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && drawer.classList.contains('open')) {
    hamburger.classList.remove('open');
    drawer.classList.remove('open');
    hamburger.setAttribute('aria-expanded', false);
    document.body.style.overflow = '';
    hamburger.focus(); // return keyboard focus to the button
  }
});

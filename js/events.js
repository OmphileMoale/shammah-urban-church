/* ═══════════════════════════════════════
   js/events.js
   PURPOSE: Interactivity for events.php.
   Handles:
   1. Category filter pills (client-side filtering)
   2. Live keyword search
   3. Countdown timers on each event card
   4. Share to WhatsApp button
   5. Detail panel show / hide
═══════════════════════════════════════ */

/* ──────────────────────────────────────
   1 & 2: FILTER + SEARCH
   Both work on the same set of .event-card-full
   elements and combine their criteria so
   a user can filter by category AND search
   at the same time.
──────────────────────────────────────── */
const filterPills  = document.querySelectorAll('.filter-pill');
const searchInput  = document.getElementById('eventSearch');
const eventCards   = document.querySelectorAll('.event-card-full');
const emptyState   = document.getElementById('eventsEmpty');

/**
 * applyFilters – re-runs whenever the active pill
 * or the search term changes.
 * Shows/hides cards whose data-category and
 * data-title match the current criteria.
 */
function applyFilters() {
  // Which pill is currently active?
  const activePill = document.querySelector('.filter-pill.active');
  const activecat  = activePill ? activePill.dataset.cat : 'all';

  // Current search term, lowercase for case-insensitive comparison
  const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';

  let visibleCount = 0;

  eventCards.forEach(card => {
    const cardCat   = (card.dataset.category || '').toLowerCase();
    const cardTitle = (card.dataset.title    || '').toLowerCase();
    const cardVenue = (card.dataset.venue    || '').toLowerCase();

    // Category match: 'all' matches everything, otherwise must match
    const catMatch  = activecat === 'all' || cardCat === activecat;

    // Search match: card title or venue contains the search term
    const srchMatch = searchTerm === '' ||
                      cardTitle.includes(searchTerm) ||
                      cardVenue.includes(searchTerm);

    const visible = catMatch && srchMatch;
    card.style.display = visible ? '' : 'none';
    if (visible) visibleCount++;
  });

  // Show empty-state message if nothing matches
  if (emptyState) {
    emptyState.style.display = visibleCount === 0 ? '' : 'none';
  }
}

// Attach click listeners to filter pills
filterPills.forEach(pill => {
  pill.addEventListener('click', () => {
    filterPills.forEach(p => p.classList.remove('active'));
    pill.classList.add('active');
    applyFilters();
  });
});

// Attach input listener to search box
if (searchInput) {
  searchInput.addEventListener('input', applyFilters);
}


/* ──────────────────────────────────────
   3: COUNTDOWN TIMERS
   Each event card has a data-eventdate attribute
   (format: "YYYY-MM-DD HH:MM:SS").
   We compute the difference between now and
   that date, then display it as:
     X days  Y hrs  Z mins  W secs
   The timer updates every second.
──────────────────────────────────────── */

/**
 * formatCountdown – converts a millisecond
 * difference into a human-readable string.
 * @param {number} ms – milliseconds remaining
 * @returns {string}
 */
function formatCountdown(ms) {
  if (ms <= 0) return 'Starting now!';

  const totalSecs = Math.floor(ms / 1000);
  const days  = Math.floor(totalSecs / 86400);
  const hours = Math.floor((totalSecs % 86400) / 3600);
  const mins  = Math.floor((totalSecs % 3600) / 60);
  const secs  = totalSecs % 60;

  if (days > 0)  return `${days}d ${hours}h ${mins}m`;
  if (hours > 0) return `${hours}h ${mins}m ${secs}s`;
  return `${mins}m ${secs}s`;
}

// Find all countdown display elements and tick every second
const countdownEls = document.querySelectorAll('[data-countdown]');

if (countdownEls.length > 0) {
  function tickCountdowns() {
    const now = Date.now();
    countdownEls.forEach(el => {
      const eventDate = new Date(el.dataset.countdown).getTime();
      const diff = eventDate - now;
      el.textContent = diff > 0 ? formatCountdown(diff) : 'Happening now!';
    });
  }
  tickCountdowns();                      // run immediately
  setInterval(tickCountdowns, 1000);     // then every second
}


/* ──────────────────────────────────────
   4: SHARE TO WHATSAPP
   Clicking the share button on a card
   opens a WhatsApp chat with a pre-filled
   message containing the event name and URL.
──────────────────────────────────────── */
document.querySelectorAll('.event-share-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const title = btn.dataset.title || 'an event';
    const url   = window.location.href;
    const msg   = encodeURIComponent(
      `Hi! Check out this event at Shammah Urban Church: "${title}" — ${url}`
    );
    window.open(`https://wa.me/?text=${msg}`, '_blank', 'noopener');
  });
});


/* ──────────────────────────────────────
   5: DETAIL PANEL TOGGLE
   "Details" button shows the detail panel
   and scrolls it into view smoothly.
   "← Back to Events" hides it again.
──────────────────────────────────────── */
const detailPanel  = document.getElementById('eventDetailPanel');
const detailBtns   = document.querySelectorAll('.js-show-detail');
const backBtns     = document.querySelectorAll('.js-hide-detail');

// Detail data fields inside the panel
const detailTitle  = document.getElementById('detailTitle');
const detailDate   = document.getElementById('detailDate');
const detailTime   = document.getElementById('detailTime');
const detailVenue  = document.getElementById('detailVenue');
const detailCap    = document.getElementById('detailCap');
const detailDesc   = document.getElementById('detailDesc');
const detailCat    = document.getElementById('detailCat');
const detailAccent = document.getElementById('detailAccent');
const detailRsvpBtn= document.getElementById('detailRsvpBtn');

detailBtns.forEach(btn => {
  btn.addEventListener('click', () => {
    if (!detailPanel) return;

    // Read all data attributes from the clicked button
    const d = btn.dataset;

    // Populate panel fields
    if (detailTitle)  detailTitle.textContent  = d.title  || '';
    if (detailDate)   detailDate.textContent   = d.date   || '';
    if (detailTime)   detailTime.textContent   = d.time   || '';
    if (detailVenue)  detailVenue.textContent  = d.venue  || '';
    if (detailCap)    detailCap.textContent    = d.spots  || '';
    if (detailDesc)   detailDesc.textContent   = d.desc   || '';
    if (detailCat)  { detailCat.textContent    = d.cat    || ''; detailCat.className = 'event-cat-tag ' + (d.catclass || ''); }
    if (detailAccent) detailAccent.style.background = d.color || 'var(--navy)';

    // Wire up the RSVP button inside the detail panel
    if (detailRsvpBtn) {
      const eid   = d.eventid   || 0;
      const etitle = d.title    || '';
      const isFull = d.full === '1';
      if (isFull) {
        detailRsvpBtn.textContent = 'Fully Booked';
        detailRsvpBtn.className   = 'rsvp-btn-full full';
        detailRsvpBtn.onclick     = null;
      } else {
        detailRsvpBtn.innerHTML   = '<i class="ti ti-calendar-plus"></i> RSVP Now';
        detailRsvpBtn.className   = 'rsvp-btn-full';
        detailRsvpBtn.onclick     = () => handleRSVP(eid, etitle);
      }
    }

    // Show the panel and scroll to it
    detailPanel.style.display = '';
    detailPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
});

backBtns.forEach(btn => {
  btn.addEventListener('click', () => {
    if (detailPanel) {
      detailPanel.style.display = 'none';
      // Scroll back to the top of the events grid
      document.querySelector('.events-main')?.scrollIntoView({ behavior: 'smooth' });
    }
  });
});

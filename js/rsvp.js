/* ═══════════════════════════════════════
   js/rsvp.js
   PURPOSE: Handles the RSVP button clicks
   on event cards.
   
   HOW IT WORKS:
   - If user is NOT logged in → show a toast
     and redirect to login after 1.8 seconds.
   - If user IS logged in → send a POST request
     to rsvp.php using the Fetch API (modern
     replacement for XMLHttpRequest).
   
   NOTE: The variable IS_LOGGED_IN and
   CSRF_TOKEN are printed into the page by
   index.php as JavaScript variables.
   This bridges PHP session data → JavaScript.
═══════════════════════════════════════ */

/**
 * handleRSVP – called by the "RSVP Now" button on each event card
 * 
 * @param {number} eventId    – the event's numeric ID
 * @param {string} eventTitle – the event's name (for the toast message)
 */
function handleRSVP(eventId, eventTitle) {

  // IS_LOGGED_IN is a JS variable injected by PHP (see bottom of index.php)
  if (!IS_LOGGED_IN) {
    // User must log in first
    showToast('Please login to RSVP for events.', 'warn');
    // Redirect to login after 1.8s so the user can read the toast
    setTimeout(() => {
      location.href = 'login.php?redirect=index.php';
    }, 1800);
    return; // stop the function here
  }

  /* ── FETCH API ────────────────────────
     fetch() makes an HTTP request from JS
     without reloading the page (AJAX).
     
     We POST to rsvp.php with:
     - Content-Type: application/json
       (tells server we're sending JSON)
     - X-CSRF-Token: a security token from
       the PHP session (prevents CSRF attacks)
     - body: JSON string with the event_id
  ──────────────────────────────────────── */
  fetch('rsvp.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': CSRF_TOKEN, // injected by PHP
    },
    body: JSON.stringify({ event_id: eventId }),
  })
  // .then() chains handle the async response
  .then((response) => response.json()) // parse response as JSON
  .then((data) => {
    if (data.success) {
      // rsvp.php returned { success: true }
      showToast(`✅ RSVP confirmed for "${eventTitle}"!`, 'success');
    } else {
      // rsvp.php returned { success: false, message: '...' }
      showToast(data.message || 'Something went wrong. Try again.', 'error');
    }
  })
  // .catch() handles network errors (no internet, server down, etc.)
  .catch(() => {
    showToast('Network error. Please try again.', 'error');
  });
}

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

    /* ── SAVE THE RSVP ────────────────────
       POST to rsvp_handler.php, which saves a row in the RSVP table
       (capacity is checked there). On success the page reloads so the
       RSVP count, spots remaining and progress bar come from the
       database and are always correct.
    ──────────────────────────────────────── */
    const form = new FormData();
    form.append('action', 'rsvp');
    form.append('event_id', eventId);
    form.append('csrf', CSRF_TOKEN);   // injected by PHP

    fetch('rsvp_handler.php', {
        method: 'POST',
        body: form,
        credentials: 'same-origin',
    })
        .then((response) => response.json())
        .then((data) => {
            if (data.ok) {
                showToast(data.already
                    ? `You are already registered for "${eventTitle}".`
                    : `✅ RSVP confirmed for "${eventTitle}"!`, 'success');
                if (!data.already) setTimeout(() => location.reload(), 1200);
            } else {
                showToast(data.msg || 'Something went wrong. Try again.', 'error');
            }
        })
        .catch(() => {
            showToast('Network error. Please try again.', 'error');
        });
}
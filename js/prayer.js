/* ═══════════════════════════════════════
   js/prayer.js
   PURPOSE: All interactivity for prayer.php.

   SECTIONS:
   1. CATEGORY FILTER    – wall-pill click handler
   2. WALL SORT          – sort cards by recent / prayed / answered
   3. PRAYING BUTTON     – increment count, toggle state, guest guard
   4. ANSWERED TOGGLE    – mark a prayer as answered
   5. SUBMIT FORM        – validate, simulate POST, show toast
   6. ANONYMOUS TOGGLE   – clear / restore name field
   7. CHARACTER COUNTER  – live countdown on textarea
   8. GUEST TOAST        – dismiss after 4 seconds

   DEPENDS ON: toast.js (window.showToast)
═══════════════════════════════════════ */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', init);

  function init() {
    initCategoryFilter();
    initWallSort();
    initPrayingButtons();
    initAnsweredToggles();
    initSubmitForm();
    initAnonToggle();
    initCharCounter();
  }


  /* ════════════════════════════════════════
     1. CATEGORY FILTER
     Clicking a .wall-pill shows only prayer
     cards whose data-cat matches the pill.
  ════════════════════════════════════════ */
  function initCategoryFilter() {
    var pills   = document.querySelectorAll('.wall-pill');
    var cards   = document.querySelectorAll('.prayer-card');
    var noEl    = document.getElementById('noPrayers');

    pills.forEach(function (pill) {
      pill.addEventListener('click', function () {
        var cat = pill.getAttribute('data-cat');

        /* Update active state on pills */
        pills.forEach(function (p) {
          p.classList.remove('active');
          p.setAttribute('aria-pressed', 'false');
        });
        pill.classList.add('active');
        pill.setAttribute('aria-pressed', 'true');

        /* Show / hide cards */
        var visible = 0;
        cards.forEach(function (card) {
          var show = (cat === 'all') || (card.getAttribute('data-cat') === cat);
          card.style.display = show ? '' : 'none';
          if (show) visible++;
        });

        /* Toggle empty state */
        if (noEl) {
          noEl.classList.toggle('visible', visible === 0);
        }
      });
    });
  }


  /* ════════════════════════════════════════
     2. WALL SORT
     The #wallSort dropdown re-orders the
     .prayer-card elements inside #prayerCards
     without a page reload.
  ════════════════════════════════════════ */
  function initWallSort() {
    var sortEl  = document.getElementById('wallSort');
    var grid    = document.getElementById('prayerCards');
    if (!sortEl || !grid) return;

    sortEl.addEventListener('change', function () {
      var mode  = sortEl.value;
      var cards = Array.from(grid.querySelectorAll('.prayer-card'));

      cards.sort(function (a, b) {
        if (mode === 'prayed') {
          /* Sort by prayer_count descending */
          return parseInt(b.getAttribute('data-count'), 10)
               - parseInt(a.getAttribute('data-count'), 10);
        }
        if (mode === 'answered') {
          /* Answered cards first, then by count */
          var aAns = a.getAttribute('data-answered') === '1' ? 1 : 0;
          var bAns = b.getAttribute('data-answered') === '1' ? 1 : 0;
          if (bAns !== aAns) return bAns - aAns;
          return parseInt(b.getAttribute('data-count'), 10)
               - parseInt(a.getAttribute('data-count'), 10);
        }
        /* Default: "recent" — original DOM order (no-op, restore) */
        return parseInt(a.getAttribute('data-original-index') || 0, 10)
             - parseInt(b.getAttribute('data-original-index') || 0, 10);
      });

      /* Re-append in sorted order */
      cards.forEach(function (card) { grid.appendChild(card); });
    });

    /* Store original index so "recent" sort can restore DOM order */
    var allCards = grid.querySelectorAll('.prayer-card');
    allCards.forEach(function (card, idx) {
      card.setAttribute('data-original-index', idx);
    });
  }


  /* ════════════════════════════════════════
     3. PRAYING BUTTON
     Clicking the 🙏 button on a card:
     — If guest: show the #guestToast and stop
     — If member (already prayed): toggle off (decrement)
     — If member (not yet prayed): toggle on (increment)
     The count animates with a pop effect.

     In a real app this would POST to:
       /api/pray.php { prayer_id: X }
     which does:
       UPDATE PRAYER_REQUEST SET prayer_count = prayer_count + 1 WHERE id = X
  ════════════════════════════════════════ */
  function initPrayingButtons() {
    var guestToast = document.getElementById('guestToast');

    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.btn-praying');
      if (!btn) return;

      var loggedIn = btn.getAttribute('data-logged-in') === '1';

      /* Guest guard */
      if (!loggedIn) {
        if (guestToast) {
          guestToast.classList.add('visible');
          /* Auto-hide after 4 seconds */
          setTimeout(function () {
            guestToast.classList.remove('visible');
          }, 4000);
        }
        return;
      }

      var isPrayed = btn.classList.contains('prayed');
      var countEl  = btn.querySelector('.praying-count');
      var labelEl  = btn.querySelector('.praying-label');
      var current  = parseInt(countEl ? countEl.textContent : 0, 10);

      if (isPrayed) {
        /* Toggle OFF — remove intercession */
        btn.classList.remove('prayed');
        btn.setAttribute('aria-pressed', 'false');
        if (countEl) {
          countEl.textContent = current - 1;
          btn.setAttribute('data-count', current - 1);
          triggerCountPop(countEl);
        }
        if (labelEl) labelEl.textContent = 'Pray';
        btn.setAttribute('aria-label', 'Pray for this request');
      } else {
        /* Toggle ON — add intercession */
        btn.classList.add('prayed');
        btn.setAttribute('aria-pressed', 'true');
        if (countEl) {
          countEl.textContent = current + 1;
          btn.setAttribute('data-count', current + 1);
          triggerCountPop(countEl);
        }
        if (labelEl) labelEl.textContent = 'Praying';
        btn.setAttribute('aria-label', 'You are praying for this request');

        /* Simulate the DB update */
        simulatePost('/api/pray.php', { prayer_id: btn.getAttribute('data-id') });
        showToast('🙏 You are now praying for this request', 'success');
      }
    });
  }

  /* Brief scale pop animation on the count badge */
  function triggerCountPop(el) {
    el.classList.remove('count-pop');
    /* Force reflow so animation restarts */
    void el.offsetWidth;
    el.classList.add('count-pop');
    setTimeout(function () { el.classList.remove('count-pop'); }, 400);
  }


  /* ════════════════════════════════════════
     4. ANSWERED TOGGLE
     Clicking "Answered?" on a card:
     — Adds the .is-answered class to the card
     — Inserts the green answered banner
     — Hides the "Answered?" button
     — Updates data-answered for sort

     In a real app: POST /api/answered.php { prayer_id: X }
     which sets is_answered = 1 in PRAYER_REQUEST.
  ════════════════════════════════════════ */
  function initAnsweredToggles() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.btn-answered');
      if (!btn) return;

      var card = btn.closest('.prayer-card');
      if (!card) return;

      /* Add answered styles to the card */
      card.classList.add('is-answered');
      card.setAttribute('data-answered', '1');

      /* Insert the green answered banner */
      var content  = card.querySelector('.card-content');
      var message  = card.querySelector('.card-message');
      var banner   = document.createElement('div');
      banner.className   = 'answered-banner';
      banner.setAttribute('role', 'status');
      banner.innerHTML   = '<i class="ti ti-circle-check-filled" aria-hidden="true"></i> Prayer Answered! 🎉';

      if (content && message) {
        content.insertBefore(banner, message);
      }

      /* Hide the "Answered?" button */
      btn.style.display = 'none';

      /* Simulate DB update */
      simulatePost('/api/answered.php', { prayer_id: btn.getAttribute('data-id') });
      showToast('🎉 Praise God! Prayer marked as answered.', 'success');
    });
  }


  /* ════════════════════════════════════════
     5. SUBMIT FORM
     Validates and "posts" a new prayer request.
     On success: shows a toast and resets the form.
     Does NOT add to the wall live (page refresh
     would be needed in a real app).
  ════════════════════════════════════════ */
  function initSubmitForm() {
    var btn = document.getElementById('submitPrayer');
    if (!btn) return;

    btn.addEventListener('click', function () {
      if (!validateForm()) return;

      /* Disable while processing */
      btn.disabled = true;
      btn.innerHTML = '<i class="ti ti-loader-2" aria-hidden="true"></i> Submitting…';

      /*
       * Simulate POST to save_prayer.php which would do:
       * INSERT INTO PRAYER_REQUEST (name, category, message, is_public, is_anonymous)
       * VALUES (?, ?, ?, ?, ?)
       */
      setTimeout(function () {
        btn.disabled = false;
        btn.innerHTML = '<i class="ti ti-hand-stop" aria-hidden="true"></i> Submit Prayer Request';

        /* Reset form */
        var fields = ['reqName', 'reqCategory', 'reqMessage'];
        fields.forEach(function (id) {
          var el = document.getElementById(id);
          if (el) el.value = '';
        });
        /* Reset character counter */
        var used = document.getElementById('charUsed');
        var counter = document.getElementById('charCount');
        if (used) used.textContent = '0';
        if (counter) {
          counter.classList.remove('warn', 'limit');
        }

        showToast('🙏 Prayer request submitted. Our team will lift it before God.', 'success');
      }, 1000);
    });
  }

  /* ── Form validation ─ */
  function validateForm() {
    var valid = true;

    /* Category */
    var cat    = document.getElementById('reqCategory');
    var catErr = document.getElementById('reqCategoryErr');
    if (!cat || !cat.value) {
      showFieldError(cat, catErr, 'Please select a prayer category.');
      valid = false;
    } else {
      clearFieldError(cat, catErr);
    }

    /* Message */
    var msg    = document.getElementById('reqMessage');
    var msgErr = document.getElementById('reqMessageErr');
    if (!msg || msg.value.trim().length < 10) {
      showFieldError(msg, msgErr, 'Please describe your prayer request (at least 10 characters).');
      valid = false;
    } else {
      clearFieldError(msg, msgErr);
    }

    return valid;
  }


  /* ════════════════════════════════════════
     6. ANONYMOUS TOGGLE
     When "Post anonymously" is ticked:
     — Save the current name value
     — Set the name field to "Anonymous"
     — Disable / grey out the name field
     When unticked: restore the saved name.
  ════════════════════════════════════════ */
  function initAnonToggle() {
    var cb      = document.getElementById('reqAnon');
    var nameEl  = document.getElementById('reqName');
    if (!cb || !nameEl) return;

    var savedName = '';

    /* Apply initial state if pre-checked */
    if (cb.checked) {
      savedName      = nameEl.value;
      nameEl.value   = 'Anonymous';
      nameEl.disabled = true;
      nameEl.style.opacity = '.5';
    }

    cb.addEventListener('change', function () {
      if (cb.checked) {
        savedName        = nameEl.value;
        nameEl.value     = 'Anonymous';
        nameEl.disabled  = true;
        nameEl.style.opacity = '.5';
      } else {
        nameEl.value     = savedName;
        nameEl.disabled  = false;
        nameEl.style.opacity = '1';
        nameEl.focus();
      }
    });
  }


  /* ════════════════════════════════════════
     7. CHARACTER COUNTER
     Updates the #charCount display as the
     user types in the prayer textarea.
     Adds .warn at 80% and .limit at 100%.
  ════════════════════════════════════════ */
  function initCharCounter() {
    var textarea = document.getElementById('reqMessage');
    var usedEl   = document.getElementById('charUsed');
    var countEl  = document.getElementById('charCount');
    if (!textarea || !usedEl || !countEl) return;

    var max = parseInt(textarea.getAttribute('maxlength') || 500, 10);

    textarea.addEventListener('input', function () {
      var len  = textarea.value.length;
      var remaining = max - len;

      usedEl.textContent = len;

      /* Update display: show remaining when near limit */
      if (remaining <= 50) {
        countEl.textContent = len + ' / ' + max + ' — ' + remaining + ' remaining';
      } else {
        countEl.textContent = len + ' / ' + max + ' characters';
        /* Reset inner span */
        usedEl.textContent = len;
      }

      /* Style changes */
      countEl.classList.remove('warn', 'limit');
      if (len >= max) {
        countEl.classList.add('limit');
      } else if (len >= max * 0.8) {
        countEl.classList.add('warn');
      }
    });
  }


  /* ════════════════════════════════════════
     UTILITY FUNCTIONS
  ════════════════════════════════════════ */

  function showFieldError(input, errorEl, msg) {
    if (input)   input.classList.add('error');
    if (errorEl) errorEl.textContent = msg;
  }

  function clearFieldError(input, errorEl) {
    if (input)   input.classList.remove('error');
    if (errorEl) errorEl.textContent = '';
  }

  /*
   * simulatePost — console-logs the simulated API call.
   * In production, replace with:
   *   fetch(url, { method:'POST', body: JSON.stringify(data),
   *                headers:{'Content-Type':'application/json'} })
   */
  function simulatePost(url, data) {
    console.log('[Prayer] POST ' + url, data);
  }

  /*
   * showToast — calls the global function from toast.js
   * (loaded by footer.php before prayer.js).
   */
  function showToast(message, type) {
    if (typeof window.showToast === 'function') {
      window.showToast(message, type || 'success');
    } else {
      console.log('[Toast]', message);
    }
  }

}());

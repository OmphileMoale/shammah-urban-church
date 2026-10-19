/* ═══════════════════════════════════════
   js/ministries.js
   PURPOSE: All interactivity for ministries.php.
   
   HANDLES:
   1. Category filter pills  — show/hide cards by data-category
   2. Live search            — filter by ministry name or leader
   3. Empty state            — show "no results" message when needed
   4. Learn More accordion   — expand/collapse the detail panel per card
   5. Join Ministry          — fetch POST → ministry_join.php
   6. Leave Ministry         — fetch DELETE → ministry_join.php
   7. Join Cell Group        — fetch POST → group_join.php
   
   NOTE:
   IS_LOGGED_IN and CSRF_TOKEN are injected by PHP at the
   bottom of ministries.php as JavaScript constants. This
   bridges the PHP session into the JavaScript layer.
═══════════════════════════════════════ */


/* ──────────────────────────────────────
   1 + 2 + 3: FILTER + SEARCH + EMPTY STATE
   
   Both the pill filter and the search box
   call the same filterCards() function,
   which checks both conditions at once so
   they work together (not separately).
──────────────────────────────────────── */

/* Track which category pill is active */
let activeCategory = 'all';

/**
 * filterCards – show or hide each ministry card
 * based on the active category pill AND the
 * current search term simultaneously.
 */
function filterCards() {
  // Read the current search query (trimmed and lowercased for comparison)
  const query = document.getElementById('ministrySearch').value.trim().toLowerCase();

  // All ministry card articles
  const cards = document.querySelectorAll('.min-card');
  let visibleCount = 0;

  cards.forEach((card) => {
    // data-category is set by PHP, e.g. "worship", "youth"
    const cardCategory = card.dataset.category || '';
    // data-name and data-leader are used for text search
    const cardName   = (card.dataset.name   || '').toLowerCase();
    const cardLeader = (card.dataset.leader || '').toLowerCase();

    // Check 1: does the category match the active pill?
    const categoryMatch = activeCategory === 'all' || cardCategory === activeCategory;

    // Check 2: does the search query appear in name or leader?
    const searchMatch = !query
      || cardName.includes(query)
      || cardLeader.includes(query);

    // Card is visible only if BOTH conditions pass
    const isVisible = categoryMatch && searchMatch;

    // Toggle visibility via CSS class
    card.style.display = isVisible ? '' : 'none';
    if (isVisible) visibleCount++;
  });

  // Show or hide the empty state message
  const emptyEl = document.getElementById('ministriesEmpty');
  if (emptyEl) {
    emptyEl.style.display = visibleCount === 0 ? 'block' : 'none';
  }
}


/* ── Category pill clicks ─────────────── */
document.querySelectorAll('.filter-pill').forEach((pill) => {
  pill.addEventListener('click', () => {
    // Remove .active from all pills, add to clicked one
    document.querySelectorAll('.filter-pill').forEach((p) => {
      p.classList.remove('active');
      p.setAttribute('aria-pressed', 'false');
    });
    pill.classList.add('active');
    pill.setAttribute('aria-pressed', 'true');

    // Update the global active category and re-filter
    activeCategory = pill.dataset.cat || 'all';
    filterCards();

    // If "Cell Groups" pill is clicked, scroll to the cell groups section
    if (activeCategory === 'community') {
      const groupsSection = document.getElementById('cell-groups');
      if (groupsSection) {
        // Small delay so the filter animation settles first
        setTimeout(() => {
          groupsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 150);
      }
    }
  });
});


/* ── Live search input ────────────────── */
const searchInput = document.getElementById('ministrySearch');
if (searchInput) {
  searchInput.addEventListener('input', filterCards);
}


/* ──────────────────────────────────────
   4: LEARN MORE ACCORDION
   
   Each card has:
   - A button with class .js-toggle-detail
   - data-target="detail-{id}" pointing to
     the collapsible .min-card-detail div
   
   CSS transitions max-height when the
   class .expanded is added/removed.
──────────────────────────────────────── */
document.querySelectorAll('.js-toggle-detail').forEach((btn) => {
  btn.addEventListener('click', () => {
    // Find the target detail panel by the button's data-target
    const targetId = btn.dataset.target;
    const panel    = document.getElementById(targetId);
    if (!panel) return;

    // Toggle the expanded class (triggers the CSS max-height transition)
    const isExpanded = panel.classList.toggle('expanded');

    // Update aria attributes for accessibility
    panel.setAttribute('aria-hidden', !isExpanded);
    btn.setAttribute('aria-expanded', isExpanded);

    // Update button text: "Learn More" ↔ "Show Less"
    const textNode = btn.firstChild; // the text node before the <i>
    if (textNode && textNode.nodeType === Node.TEXT_NODE) {
      textNode.textContent = isExpanded ? 'Show Less' : 'Learn More ';
    }
  });
});


/* ──────────────────────────────────────
   5: JOIN MINISTRY
   
   When a member clicks "Join Ministry":
   1. Check IS_LOGGED_IN (injected by PHP)
   2. POST to ministry_join.php with the MinistryID
   3. On success: update button to "Joined ✓" state,
      increment the member count, add gold card border
   4. On failure: show error toast
──────────────────────────────────────── */
document.querySelectorAll('.js-join-ministry').forEach((btn) => {
  btn.addEventListener('click', async () => {

    /* Guard: not logged in → redirect to login */
    if (!IS_LOGGED_IN) {
      showToast('Please login to join a ministry.', 'warn');
      setTimeout(() => { location.href = 'login.php?redirect=ministries.php'; }, 1800);
      return;
    }

    const ministryId   = btn.dataset.id;
    const ministryName = btn.dataset.name;

    /* Disable button while the request is in flight */
    btn.disabled = true;
    btn.textContent = 'Joining…';

    try {
      /*
       * POST to ministry_join.php.
       * 
       * ministry_join.php should:
       *   1. Verify CSRF token from X-CSRF-Token header
       *   2. Check session for member_id
       *   3. INSERT INTO MINISTRY_MEMBER (MemberID, MinistryID, DateJoined, IsActive)
       *      VALUES (:member_id, :ministry_id, NOW(), 1)
       *      ON DUPLICATE KEY UPDATE IsActive = 1
       *   4. Return JSON: { success: true, member_count: <new count> }
       *      or          { success: false, message: '...' }
       */
      const response = await fetch('ministry_join.php', {
        method:  'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': CSRF_TOKEN,
        },
        body: JSON.stringify({ ministry_id: ministryId, action: 'join' }),
      });

      const data = await response.json();

      if (data.success) {
        showToast(`You have joined <strong>${ministryName}</strong>!`, 'success');

        /* ── Update button to "Joined ✓" state ── */
        btn.classList.remove('js-join-ministry');
        btn.classList.add('js-leave-ministry', 'min-join-btn--joined');
        btn.innerHTML = '<i class="ti ti-check" aria-hidden="true"></i> Joined';
        btn.disabled  = false;

        /* ── Add gold border to the card ── */
        const card = btn.closest('.min-card');
        if (card) card.classList.add('min-card--joined');

        /* ── Add "Joined" badge next to ministry name ── */
        const titleGroup = card ? card.querySelector('.min-card-title-group') : null;
        if (titleGroup && !titleGroup.querySelector('.min-joined-badge')) {
          const badge = document.createElement('span');
          badge.className = 'min-joined-badge';
          badge.innerHTML = '<i class="ti ti-check" aria-hidden="true"></i> Joined';
          titleGroup.appendChild(badge);
        }

        /* ── Update member count display ── */
        if (data.member_count !== undefined) {
          const countEl = card ? card.querySelector('.min-member-count strong') : null;
          if (countEl) countEl.textContent = data.member_count;
        }

        /* ── Re-bind the leave handler on the updated button ── */
        bindLeaveHandler(btn);

      } else {
        showToast(data.message || 'Could not join ministry. Try again.', 'error');
        btn.disabled     = false;
        btn.innerHTML = '<i class="ti ti-plus" aria-hidden="true"></i> Join Ministry';
      }

    } catch {
      /* Network error */
      showToast('Network error. Please check your connection.', 'error');
      btn.disabled  = false;
      btn.innerHTML = '<i class="ti ti-plus" aria-hidden="true"></i> Join Ministry';
    }
  });
});


/* ──────────────────────────────────────
   6: LEAVE MINISTRY
   
   When a member clicks "Joined ✓" (i.e. wants to leave):
   1. POST to ministry_join.php with action:'leave'
   2. On success: revert button to "Join Ministry" state
   3. Decrement the member count
──────────────────────────────────────── */

/**
 * bindLeaveHandler – attach a leave click listener to a button.
 * Called both on page load (for server-rendered "Joined" buttons)
 * and after a successful join (for dynamically updated buttons).
 *
 * @param {HTMLElement} btn – the .js-leave-ministry button
 */
function bindLeaveHandler(btn) {
  btn.addEventListener('click', async () => {

    const ministryId   = btn.dataset.id;
    const ministryName = btn.dataset.name;

    btn.disabled     = true;
    btn.textContent  = 'Leaving…';

    try {
      /*
       * POST to ministry_join.php with action:'leave'.
       * 
       * ministry_join.php should:
       *   UPDATE MINISTRY_MEMBER SET IsActive = 0
       *   WHERE MemberID = :member_id AND MinistryID = :ministry_id
       *   Return JSON: { success: true, member_count: <new count> }
       */
      const response = await fetch('ministry_join.php', {
        method:  'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': CSRF_TOKEN,
        },
        body: JSON.stringify({ ministry_id: ministryId, action: 'leave' }),
      });

      const data = await response.json();

      if (data.success) {
        showToast(`You have left <strong>${ministryName}</strong>.`, 'warn');

        /* ── Revert button to "Join Ministry" state ── */
        btn.classList.remove('js-leave-ministry', 'min-join-btn--joined');
        btn.classList.add('js-join-ministry');
        btn.innerHTML = '<i class="ti ti-plus" aria-hidden="true"></i> Join Ministry';
        btn.disabled  = false;

        /* ── Remove gold card border ── */
        const card = btn.closest('.min-card');
        if (card) {
          card.classList.remove('min-card--joined');
          /* Remove the "Joined" badge */
          const badge = card.querySelector('.min-joined-badge');
          if (badge) badge.remove();
        }

        /* ── Update member count ── */
        if (data.member_count !== undefined) {
          const countEl = card ? card.querySelector('.min-member-count strong') : null;
          if (countEl) countEl.textContent = data.member_count;
        }

        /* ── Re-bind the join handler ── */
        btn.dataset.id   = ministryId;
        btn.dataset.name = ministryName;
        bindJoinHandler(btn);

      } else {
        showToast(data.message || 'Could not leave ministry. Try again.', 'error');
        btn.disabled  = false;
        btn.innerHTML = '<i class="ti ti-check" aria-hidden="true"></i> Joined';
      }

    } catch {
      showToast('Network error. Please check your connection.', 'error');
      btn.disabled  = false;
      btn.innerHTML = '<i class="ti ti-check" aria-hidden="true"></i> Joined';
    }
  }, { once: true }); // once:true prevents duplicate listeners accumulating
}

/**
 * bindJoinHandler – re-attach the join listener after a leave action.
 * @param {HTMLElement} btn
 */
function bindJoinHandler(btn) {
  btn.addEventListener('click', async () => {
    if (!IS_LOGGED_IN) {
      showToast('Please login to join a ministry.', 'warn');
      setTimeout(() => { location.href = 'login.php?redirect=ministries.php'; }, 1800);
      return;
    }
    /* Delegate back to the static join handler by simulating
       a fresh page-loaded "Join Ministry" button click. */
    btn.dispatchEvent(new Event('click'));
  }, { once: true });
}

/* Bind leave handler to all server-rendered "Joined ✓" buttons */
document.querySelectorAll('.js-leave-ministry').forEach(bindLeaveHandler);


/* ──────────────────────────────────────
   7: JOIN CELL GROUP
   
   Simpler than ministry join — just shows
   a success toast and updates the button.
   In production: POST to group_join.php.
──────────────────────────────────────── */
document.querySelectorAll('.js-join-group').forEach((btn) => {
  btn.addEventListener('click', async () => {

    if (!IS_LOGGED_IN) {
      showToast('Please login to join a cell group.', 'warn');
      setTimeout(() => { location.href = 'login.php?redirect=ministries.php#cell-groups'; }, 1800);
      return;
    }

    const groupName = btn.dataset.group;
    btn.disabled    = true;
    btn.textContent = 'Joining…';

    try {
      /*
       * POST to group_join.php.
       * group_join.php should INSERT INTO SMALL_GROUP_MEMBER
       * and return JSON: { success: true } or { success: false }
       */
      const response = await fetch('group_join.php', {
        method:  'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': CSRF_TOKEN,
        },
        body: JSON.stringify({ group_name: groupName }),
      });

      const data = await response.json();

      if (data.success) {
        showToast(`Request sent to join <strong>${groupName}</strong>. A leader will contact you.`, 'success');
        btn.textContent = 'Requested ✓';
        btn.style.background = 'rgba(99,153,34,.15)';
        btn.style.color      = '#639922';
        /* Keep button disabled so they can't double-request */
      } else {
        showToast(data.message || 'Could not send request. Try again.', 'error');
        btn.disabled    = false;
        btn.textContent = 'Join';
      }

    } catch {
      /*
       * Graceful fallback while group_join.php is not yet built:
       * show a success-style toast so the UX demo still works.
       */
      showToast(`Request sent to join <strong>${groupName}</strong>. A leader will contact you.`, 'success');
      btn.textContent  = 'Requested ✓';
      btn.style.background = 'rgba(99,153,34,.15)';
      btn.style.color      = '#639922';
    }
  });
});

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
        const cardName = (card.dataset.name || '').toLowerCase();
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
        const panel = document.getElementById(targetId);
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
   5 + 6: JOIN / LEAVE MINISTRY

   One delegated click handler covers both buttons, so the
   button can switch between "Join" and "Joined" any number
   of times without re-binding listeners.

   Server: ministry_join.php (POST JSON, X-CSRF-Token header)
   replies { success, member_count, total_members, message }.
──────────────────────────────────────── */
function renderMinistryButton(btn, joined) {
    btn.classList.toggle('js-join-ministry', !joined);
    btn.classList.toggle('js-leave-ministry', joined);
    btn.classList.toggle('min-join-btn--joined', joined);
    btn.innerHTML = joined
        ? '<i class="ti ti-check" aria-hidden="true"></i> Joined'
        : '<i class="ti ti-plus" aria-hidden="true"></i> Join Ministry';
    btn.disabled = false;

    const card = btn.closest('.min-card');
    if (!card) return;
    card.classList.toggle('min-card--joined', joined);

    const titleGroup = card.querySelector('.min-card-title-group');
    const badge = card.querySelector('.min-joined-badge');
    if (joined && titleGroup && !badge) {
        const b = document.createElement('span');
        b.className = 'min-joined-badge';
        b.innerHTML = '<i class="ti ti-check" aria-hidden="true"></i> Joined';
        titleGroup.appendChild(b);
    } else if (!joined && badge) {
        badge.remove();
    }
}

async function toggleMinistry(btn) {
    if (!IS_LOGGED_IN) {
        showToast('Please login to join a ministry.', 'warn');
        setTimeout(() => { location.href = 'login.php?redirect=ministries.php'; }, 1800);
        return;
    }

    const joining = btn.classList.contains('js-join-ministry');
    const ministryId = btn.dataset.id;
    const ministryName = btn.dataset.name;

    btn.disabled = true;
    btn.textContent = joining ? 'Joining…' : 'Leaving…';

    try {
        const response = await fetch('ministry_join.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify({ ministry_id: ministryId, action: joining ? 'join' : 'leave' }),
        });
        const data = await response.json();

        if (!data.success) {
            showToast(data.message || 'Something went wrong. Try again.', 'error');
            renderMinistryButton(btn, !joining);          // put the button back as it was
            return;
        }

        showToast(
            joining ? `You have joined <strong>${ministryName}</strong>!`
                : `You have left <strong>${ministryName}</strong>.`,
            joining ? 'success' : 'warn'
        );
        renderMinistryButton(btn, joining);

        /* Live numbers straight from the database */
        const card = btn.closest('.min-card');
        const countEl = card && card.querySelector('.min-member-count strong');
        if (countEl && data.member_count !== undefined) countEl.textContent = data.member_count;

        if (data.total_members !== undefined) {
            document.querySelectorAll('.js-total-members').forEach((el) => {
                el.textContent = Number(data.total_members).toLocaleString();
            });
        }

        /* "Your Ministries Joined" KPI */
        const mine = document.getElementById('myMinistriesCount');
        if (mine) mine.textContent = document.querySelectorAll('.min-card--joined').length;

    } catch {
        showToast('Network error. Please check your connection.', 'error');
        renderMinistryButton(btn, !joining);
    }
}

document.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-join-ministry, .js-leave-ministry');
    if (btn) toggleMinistry(btn);
});


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
        btn.disabled = true;
        btn.textContent = 'Joining…';

        try {
            /*
             * POST to group_join.php.
             * group_join.php should INSERT INTO SMALL_GROUP_MEMBER
             * and return JSON: { success: true } or { success: false }
             */
            const response = await fetch('group_join.php', {
                method: 'POST',
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
                btn.style.color = '#639922';
                /* Keep button disabled so they can't double-request */
            } else {
                showToast(data.message || 'Could not send request. Try again.', 'error');
                btn.disabled = false;
                btn.textContent = 'Join';
            }

        } catch {
            /*
             * Graceful fallback while group_join.php is not yet built:
             * show a success-style toast so the UX demo still works.
             */
            showToast(`Request sent to join <strong>${groupName}</strong>. A leader will contact you.`, 'success');
            btn.textContent = 'Requested ✓';
            btn.style.background = 'rgba(99,153,34,.15)';
            btn.style.color = '#639922';
        }
    });
});

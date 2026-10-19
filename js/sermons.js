/* ═══════════════════════════════════════
   js/sermons.js
   PURPOSE: All interactivity for sermons.php.

   FEATURES:
   1. Tab switching (All / By Series / By Speaker)
   2. Live search on title + speaker
   3. Dropdown filters (Series, Year, Speaker)
   4. "Clear filters" link
   5. Results counter ("Showing X of Y")
   6. Audio player toggle
   7. Share button (copies link + shows toast)
   8. "Browse by speaker" jumps to By Speaker tab
   9. Series tile → filters All tab by that series
   10. Pagination (client-side, 4 cards per page)
═══════════════════════════════════════ */


/* ──────────────────────────────────────
   HELPERS
──────────────────────────────────────── */

/**
 * $(selector) – shorthand for querySelector
 * $$(selector) – shorthand for querySelectorAll
 */
const $  = (s, ctx = document) => ctx.querySelector(s);
const $$ = (s, ctx = document) => [...ctx.querySelectorAll(s)];


/* ──────────────────────────────────────
   1. TAB SWITCHING
   Clicking a tab button shows/hides the
   matching .tab-panel using data-tab attr.
──────────────────────────────────────── */
const tabBtns   = $$('.s-tab');
const tabPanels = $$('.tab-panel');

/**
 * activateTab – show a panel, mark its button active,
 *               hide all others.
 * @param {string} tabId – value of data-tab attribute
 */
function activateTab(tabId) {
  tabBtns.forEach(btn => {
    btn.classList.toggle('active', btn.dataset.tab === tabId);
    // aria-selected communicates state to screen readers
    btn.setAttribute('aria-selected', btn.dataset.tab === tabId);
  });
  tabPanels.forEach(panel => {
    panel.classList.toggle('active', panel.id === `tab-${tabId}`);
  });
}

tabBtns.forEach(btn => {
  btn.addEventListener('click', () => activateTab(btn.dataset.tab));
  // Keyboard: Space or Enter activates a tab (accessibility)
  btn.addEventListener('keydown', (e) => {
    if (e.key === ' ' || e.key === 'Enter') {
      e.preventDefault();
      activateTab(btn.dataset.tab);
    }
  });
});


/* ──────────────────────────────────────
   2 & 3. LIVE SEARCH + DROPDOWN FILTERS

   All .sermon-card elements have
   data-title, data-speaker, data-series,
   data-year attributes set in PHP.

   We read these and hide/show cards that
   do not match the current filter state.
──────────────────────────────────────── */
const searchInput    = $('#sermonSearch');
const seriesSelect   = $('#seriesFilter');
const yearSelect     = $('#yearFilter');
const speakerSelect  = $('#speakerFilter');
const clearBtn       = $('#clearFilters');
const resultsLabel   = $('#resultsCount');
const noResults      = $('#noResults');
const allCards       = $$('.sermon-card');

/** Current pagination state */
let currentPage  = 1;
const PAGE_SIZE  = 4; // cards per page

/**
 * applyFilters – reads all filter values and
 *                shows/hides cards accordingly.
 *                Also updates the results counter
 *                and pagination.
 */
function applyFilters() {
  const query   = searchInput.value.toLowerCase().trim();
  const series  = seriesSelect.value;
  const year    = yearSelect.value;
  const speaker = speakerSelect.value;

  // Determine whether any filter is active
  const hasFilter = query || series || year || speaker;
  clearBtn.classList.toggle('visible', !!hasFilter);

  // Filter: collect matching cards
  const matchingCards = allCards.filter(card => {
    const titleMatch   = card.dataset.title.toLowerCase().includes(query);
    const speakerMatch = card.dataset.speaker.toLowerCase().includes(query);
    const textMatch    = titleMatch || speakerMatch;
    const seriesMatch  = !series  || card.dataset.series  === series;
    const yearMatch    = !year    || card.dataset.year     === year;
    const spkMatch     = !speaker || card.dataset.speaker  === speaker;
    return textMatch && seriesMatch && yearMatch && spkMatch;
  });

  // Reset to page 1 whenever filters change
  currentPage = 1;

  // Update UI
  renderPage(matchingCards);
}

/**
 * renderPage – shows only the cards for the
 *              current page, hides the rest,
 *              and updates pagination controls.
 * @param {HTMLElement[]} cards – filtered card list
 */
function renderPage(cards) {
  const total    = cards.length;
  const start    = (currentPage - 1) * PAGE_SIZE;
  const end      = start + PAGE_SIZE;
  const pageCards = cards.slice(start, end);

  // Show / hide each card
  allCards.forEach(c => c.style.display = 'none');
  pageCards.forEach(c => c.style.display = 'flex');

  // Results counter e.g. "Showing 4 of 8 sermons"
  const showing = Math.min(end, total) - start;
  resultsLabel.innerHTML = `Showing <strong>${showing}</strong> of <strong>${total}</strong> sermon${total !== 1 ? 's' : ''}`;

  // No-results message
  noResults.style.display = total === 0 ? 'block' : 'none';

  // Re-render pagination
  renderPagination(total);
}

/* Attach filter listeners */
searchInput.addEventListener('input',  applyFilters);
seriesSelect.addEventListener('change', applyFilters);
yearSelect.addEventListener('change',  applyFilters);
speakerSelect.addEventListener('change', applyFilters);


/* ──────────────────────────────────────
   4. CLEAR FILTERS
──────────────────────────────────────── */
clearBtn.addEventListener('click', () => {
  searchInput.value  = '';
  seriesSelect.value = '';
  yearSelect.value   = '';
  speakerSelect.value = '';
  applyFilters();
  searchInput.focus();
});


/* ──────────────────────────────────────
   10. PAGINATION (CLIENT-SIDE)
   Shows page number buttons below the
   sermon grid and keeps the total count
   accurate after filtering.
──────────────────────────────────────── */
const paginationEl   = $('#paginationBtns');
const paginationInfo = $('#paginationInfo');

/**
 * renderPagination – builds ← 1 2 3 → controls.
 * @param {number} total – total matching cards
 */
function renderPagination(total) {
  const totalPages = Math.ceil(total / PAGE_SIZE);

  // Update "Showing X–Y of Z" info text
  const start = (currentPage - 1) * PAGE_SIZE + 1;
  const end   = Math.min(currentPage * PAGE_SIZE, total);
  paginationInfo.textContent = total > 0
    ? `Showing ${start}–${end} of ${total} sermons`
    : '';

  // Build pagination buttons
  paginationEl.innerHTML = '';

  if (totalPages <= 1) return; // no buttons needed for ≤1 page

  // ← Previous button
  const prev = makePageBtn('‹', () => {
    currentPage--;
    applyFilters(); // re-filter will call renderPage → renderPagination
  });
  if (currentPage === 1) prev.disabled = true;
  paginationEl.appendChild(prev);

  // Numbered page buttons
  for (let i = 1; i <= totalPages; i++) {
    const btn = makePageBtn(i, () => {
      currentPage = i;
      applyFilters();
    });
    if (i === currentPage) btn.classList.add('active');
    paginationEl.appendChild(btn);
  }

  // → Next button
  const next = makePageBtn('›', () => {
    currentPage++;
    applyFilters();
  });
  if (currentPage === totalPages) next.disabled = true;
  paginationEl.appendChild(next);
}

/**
 * makePageBtn – creates a single page button element
 * @param {string|number} label
 * @param {Function}      onClick
 */
function makePageBtn(label, onClick) {
  const btn = document.createElement('button');
  btn.className = 'page-btn';
  btn.textContent = label;
  btn.addEventListener('click', onClick);
  return btn;
}

// Initial render: show all cards on load
applyFilters();


/* ──────────────────────────────────────
   6. AUDIO PLAYER TOGGLE
   Each "🎧 Listen" button reveals a mini
   audio player strip below its card.
   Clicking again collapses it.
──────────────────────────────────────── */
$$('.listen-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const player = btn.closest('.sermon-card, .featured-sermon').querySelector('.audio-player');
    if (!player) return;

    const isVisible = player.classList.contains('visible');
    // Close any other open players first
    $$('.audio-player.visible').forEach(p => {
      p.classList.remove('visible');
      p.classList.remove('paused');
      const playBtn = p.querySelector('.audio-play-btn');
      if (playBtn) playBtn.innerHTML = '<i class="ti ti-player-play-filled"></i>';
    });
    // Toggle this one
    if (!isVisible) {
      player.classList.add('visible');
      showToast('Audio player ready — click ▶ to play', 'success', 3000);
    }
  });
});

/* Play/pause the waveform animation */
$$('.audio-play-btn').forEach(playBtn => {
  playBtn.addEventListener('click', () => {
    const player = playBtn.closest('.audio-player');
    const isPaused = player.classList.toggle('paused');
    playBtn.innerHTML = isPaused
      ? '<i class="ti ti-player-play-filled"></i>'
      : '<i class="ti ti-player-pause-filled"></i>';
  });
});


/* ──────────────────────────────────────
   7. SHARE BUTTON
   Copies the page URL + a ?sermon= query
   string to the clipboard, then shows a
   toast notification.
──────────────────────────────────────── */
$$('.share-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const sermonTitle = btn.dataset.title || 'sermon';
    const shareUrl = `${window.location.origin}${window.location.pathname}?sermon=${encodeURIComponent(sermonTitle)}`;
    navigator.clipboard.writeText(shareUrl).then(() => {
      showToast('Link copied to clipboard! 📋', 'success', 3000);
    }).catch(() => {
      // Fallback if clipboard API not available
      showToast('Share: ' + shareUrl, 'warn', 5000);
    });
  });
});


/* ──────────────────────────────────────
   8. SERIES TILE → FILTER ON ALL TAB
   Clicking a series tile on the "By Series"
   tab switches to the "All" tab and pre-fills
   the series filter dropdown.
──────────────────────────────────────── */
$$('.series-tile').forEach(tile => {
  tile.addEventListener('click', () => {
    const seriesName = tile.dataset.series;
    // Switch to All tab
    activateTab('all');
    // Set the series dropdown
    seriesSelect.value = seriesName;
    // Scroll to search bar
    $('#sermonSearch').scrollIntoView({ behavior: 'smooth', block: 'center' });
    applyFilters();
    showToast(`Filtering by series: ${seriesName}`, 'success', 2500);
  });
});


/* ──────────────────────────────────────
   9. SPEAKER CARD → FILTER ALL TAB
   Same as series tiles but filters by speaker.
──────────────────────────────────────── */
$$('.speaker-card').forEach(card => {
  card.addEventListener('click', () => {
    const spkName = card.dataset.speaker;
    activateTab('all');
    speakerSelect.value = spkName;
    $('#sermonSearch').scrollIntoView({ behavior: 'smooth', block: 'center' });
    applyFilters();
    showToast(`Showing sermons by ${spkName}`, 'success', 2500);
  });
});


/* ──────────────────────────────────────
   NOTES BUTTON — unavailable state
   If notes aren't ready yet, show a toast
   instead of trying to open a file.
──────────────────────────────────────── */
$$('.c-btn.notes.unavailable').forEach(btn => {
  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    showToast('Sermon notes coming soon for this message', 'warn', 3000);
  });
});


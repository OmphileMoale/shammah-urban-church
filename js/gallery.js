/* ═══════════════════════════════════════
   js/gallery.js
   PURPOSE: Filter tabs + lightbox for gallery.php
═══════════════════════════════════════ */

(function () {
  'use strict';

  /* ── DOM refs ── */
  const grid      = document.getElementById('galleryGrid');
  const emptyMsg  = document.getElementById('galleryEmpty');
  const lightbox  = document.getElementById('galleryLightbox');
  const lbMedia   = document.getElementById('lbMedia');
  const lbTitle   = document.getElementById('lbTitle');
  const lbDesc    = document.getElementById('lbDesc');
  const lbCat     = document.getElementById('lbCat');
  const lbClose   = document.getElementById('lbClose');
  const lbPrev    = document.getElementById('lbPrev');
  const lbNext    = document.getElementById('lbNext');
  const filterBtns = document.querySelectorAll('.gf-btn');

  /* All gallery article elements */
  const items = Array.from(grid.querySelectorAll('.gallery-item'));

  /* Current visible (filtered) indices + active position */
  let visibleIndices = items.map((_, i) => i);
  let currentLbPos   = 0; // position within visibleIndices

  /* ────────────────────────────────────
     FILTER LOGIC
  ──────────────────────────────────── */
  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const filter = btn.dataset.filter;

      /* Update active button */
      filterBtns.forEach(b => {
        b.classList.remove('active');
        b.setAttribute('aria-pressed', 'false');
      });
      btn.classList.add('active');
      btn.setAttribute('aria-pressed', 'true');

      /* Show / hide items */
      let count = 0;
      visibleIndices = [];

      items.forEach((item, idx) => {
        const match = filter === 'all' || item.dataset.category === filter;
        if (match) {
          item.removeAttribute('data-hidden');
          visibleIndices.push(idx);
          count++;
        } else {
          item.setAttribute('data-hidden', '');
        }
      });

      /* Empty state */
      if (count === 0) {
        emptyMsg.classList.add('visible');
      } else {
        emptyMsg.classList.remove('visible');
      }
    });
  });

  /* ────────────────────────────────────
     LIGHTBOX — OPEN
  ──────────────────────────────────── */
  function openLightbox(itemIndex) {
    /* itemIndex = index in the items[] array */
    /* Find position in visibleIndices */
    currentLbPos = visibleIndices.indexOf(itemIndex);
    if (currentLbPos === -1) currentLbPos = 0;

    populateLightbox(itemIndex);

    lightbox.classList.add('open');
    lightbox.setAttribute('aria-hidden', 'false');
    lbClose.focus();
    document.body.style.overflow = 'hidden';
  }

  function populateLightbox(itemIndex) {
    const data = GALLERY_ITEMS[itemIndex];
    const item = items[itemIndex];

    /* Clone the SVG into the lightbox media area */
    const svg = item.querySelector('svg');
    lbMedia.innerHTML = '';
    if (svg) {
      const clone = svg.cloneNode(true);
      clone.style.width  = '100%';
      clone.style.height = 'auto';
      clone.style.display = 'block';
      clone.style.maxHeight = '65vh';
      clone.removeAttribute('aria-hidden');
      lbMedia.appendChild(clone);
    }

    lbTitle.textContent = data.title;
    lbDesc.textContent  = data.desc;
    lbCat.textContent   = data.catLabel;
  }

  /* ────────────────────────────────────
     LIGHTBOX — CLOSE
  ──────────────────────────────────── */
  function closeLightbox() {
    lightbox.classList.remove('open');
    lightbox.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    /* Return focus to the item that triggered */
    const idx = visibleIndices[currentLbPos];
    if (items[idx]) items[idx].focus();
  }

  /* ────────────────────────────────────
     LIGHTBOX — NAVIGATE
  ──────────────────────────────────── */
  function navigate(direction) {
    if (visibleIndices.length === 0) return;
    currentLbPos = (currentLbPos + direction + visibleIndices.length) % visibleIndices.length;
    populateLightbox(visibleIndices[currentLbPos]);
  }

  /* ────────────────────────────────────
     EVENT LISTENERS
  ──────────────────────────────────── */

  /* Open lightbox on click or Enter/Space */
  items.forEach((item, idx) => {
    item.addEventListener('click', () => openLightbox(idx));
    item.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        openLightbox(idx);
      }
    });
  });

  /* Close */
  lbClose.addEventListener('click', closeLightbox);

  /* Click outside lb-inner to close */
  lightbox.addEventListener('click', e => {
    if (e.target === lightbox) closeLightbox();
  });

  /* Prev / Next */
  lbPrev.addEventListener('click', () => navigate(-1));
  lbNext.addEventListener('click', () => navigate(1));

  /* Keyboard navigation */
  document.addEventListener('keydown', e => {
    if (!lightbox.classList.contains('open')) return;
    if (e.key === 'Escape')     closeLightbox();
    if (e.key === 'ArrowLeft')  navigate(-1);
    if (e.key === 'ArrowRight') navigate(1);
  });

})();

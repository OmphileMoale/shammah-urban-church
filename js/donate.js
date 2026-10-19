/* ═══════════════════════════════════════════════════
   js/donate.js
   PURPOSE: All interactivity for donate.php.
   
   WHAT THIS FILE DOES:
   1. Preset amount buttons       — click to select
   2. Custom amount input         — show/hide + validate
   3. Category description        — shows hint on change
   4. EFT panel                   — shows when EFT selected
   5. Copy account number         — clipboard API
   6. Notes character counter     — live count
   7. Real-time field validation  — inline error messages
   8. Form submit (AJAX)          — fetch() + JSON response
   9. Receipt reveal              — populates + shows receipt
   10. Fund bar animation         — IntersectionObserver
   
   HOW IT CONNECTS TO PHP:
   - donate.js sends fetch('donate.php', { method:'POST' })
   - donate.php validates + returns JSON
   - donate.js reads JSON and updates the UI
═══════════════════════════════════════════════════ */


/* ── ELEMENT REFERENCES ──────────────────────────
   We grab all elements once at the top.
   This is faster than querying the DOM inside
   every function call.
──────────────────────────────────────────────────── */
const donateForm      = document.getElementById('donateForm');
const submitBtn       = document.getElementById('df-submit');
const submitLabel     = document.getElementById('df-submit-label');
const submitIcon      = document.getElementById('df-submit-icon');

const presetBtns      = document.querySelectorAll('.df-preset');
const customWrap      = document.getElementById('df-custom-wrap');
const customInput     = document.getElementById('df-custom-amount');
const amountHidden    = document.getElementById('df-amount-val');
const amountErr       = document.getElementById('df-amount-err');

const categorySelect  = document.getElementById('df-category');
const catDesc         = document.getElementById('df-cat-desc');

const methodSelect    = document.getElementById('df-method');
const eftPanel        = document.getElementById('df-eft-panel');
const copyBtn         = document.getElementById('df-copy-btn');
const accNumEl        = document.getElementById('df-acc-num');

const notesTextarea   = document.getElementById('df-notes');
const notesCount      = document.getElementById('df-notes-count');

const receipt         = document.getElementById('donate-receipt');
const placeholder     = document.getElementById('donate-receipt-placeholder');


/* ══════════════════════════════════════════════════
   1. PRESET AMOUNT BUTTONS
   ──────────────────────────────────────────────────
   When a preset is clicked:
   - Remove .active from all buttons
   - Add .active to the clicked one
   - If it's a fixed amount: set hidden input + hide custom box
   - If it's "Custom": show custom input + clear hidden value
══════════════════════════════════════════════════ */
presetBtns.forEach(btn => {
  btn.addEventListener('click', () => {

    /* Clear active state from all buttons */
    presetBtns.forEach(b => b.classList.remove('active'));

    /* Set active on clicked button */
    btn.classList.add('active');

    const val = btn.dataset.amount;
    /*
     * dataset.amount reads the data-amount="100" HTML attribute.
     * btn.dataset.amount === "100" (always a string from HTML)
     */

    if (val === 'custom') {
      /* Show the custom input box (remove hidden attribute) */
      customWrap.hidden = false;
      customInput.focus(); /* put cursor straight into the box */
      amountHidden.value = ''; /* clear until they type */
    } else {
      /* Fixed amount — set hidden input + hide custom box */
      customWrap.hidden = true;
      amountHidden.value = val;
      customInput.value  = '';
      clearError(amountHidden, amountErr);
    }
  });
});

/* When user types in the custom box, sync to hidden input */
if (customInput) {
  customInput.addEventListener('input', () => {
    amountHidden.value = customInput.value;
    /* Clear error as soon as they start typing */
    if (customInput.value) clearError(amountHidden, amountErr);
  });
}


/* ══════════════════════════════════════════════════
   2. CATEGORY DESCRIPTION TOOLTIP
   ──────────────────────────────────────────────────
   Each <option> has a data-desc attribute.
   When the user picks a category, we read
   selectedOption.dataset.desc and show it
   in the #df-cat-desc paragraph.
══════════════════════════════════════════════════ */
if (categorySelect) {
  categorySelect.addEventListener('change', () => {
    const selected = categorySelect.options[categorySelect.selectedIndex];
    /*
     * categorySelect.selectedIndex = the index number of
     * the currently selected option.
     * categorySelect.options[n] = that option element.
     * .dataset.desc = the data-desc="..." attribute value.
     */
    catDesc.textContent = selected.dataset.desc || '';

    /* Clear error when a valid selection is made */
    if (categorySelect.value) {
      clearError(categorySelect, document.getElementById('df-cat-err'));
    }
  });
}


/* ══════════════════════════════════════════════════
   3. EFT BANKING PANEL + COPY BUTTON
   ──────────────────────────────────────────────────
   When method = "EFT": remove hidden from the panel
   When method changes away: add hidden back
══════════════════════════════════════════════════ */
if (methodSelect) {
  methodSelect.addEventListener('change', () => {

    /* Show/hide the EFT panel */
    if (methodSelect.value === 'EFT') {
      eftPanel.hidden = false;
    } else {
      eftPanel.hidden = true;
    }

    /* Clear error on valid selection */
    if (methodSelect.value) {
      clearError(methodSelect, document.getElementById('df-method-err'));
    }
  });
}

/* Copy account number to clipboard */
if (copyBtn && accNumEl) {
  copyBtn.addEventListener('click', async () => {
    try {
      /*
       * navigator.clipboard.writeText() is the modern
       * clipboard API. It requires HTTPS or localhost.
       * The await means we wait for it to finish before
       * showing the "Copied ✓" feedback.
       */
      await navigator.clipboard.writeText(
        accNumEl.textContent.replace(/\s/g, '') /* remove spaces from "6234 5678 9012" */
      );

      /* Visual feedback — change button text temporarily */
      copyBtn.classList.add('copied');
      copyBtn.innerHTML = '<i class="ti ti-check" aria-hidden="true"></i> Copied ✓';

      /* Reset after 2 seconds */
      setTimeout(() => {
        copyBtn.classList.remove('copied');
        copyBtn.innerHTML = '<i class="ti ti-copy" aria-hidden="true"></i> Copy';
      }, 2000);

    } catch (err) {
      /* Fallback if clipboard API fails (e.g. no HTTPS) */
      console.warn('Clipboard write failed:', err);
    }
  });
}


/* ══════════════════════════════════════════════════
   4. NOTES CHARACTER COUNTER
   ──────────────────────────────────────────────────
   Updates the "X / 200" counter below the textarea
   on every keystroke.
══════════════════════════════════════════════════ */
if (notesTextarea && notesCount) {
  notesTextarea.addEventListener('input', () => {
    const len = notesTextarea.value.length;
    notesCount.textContent = len + ' / 200';
    /* Turn red when close to limit */
    notesCount.style.color = len > 180 ? 'var(--red)' : '';
  });
}


/* ══════════════════════════════════════════════════
   5. VALIDATION HELPERS
   ──────────────────────────────────────────────────
   showError  — adds red border + shows error message
   clearError — removes red border + clears message
   
   Using functions keeps the code DRY (Don't Repeat
   Yourself). Same logic used in register.js and login.js
   so it's consistent across the whole project.
══════════════════════════════════════════════════ */
function showError(input, errEl, msg) {
  input.classList.add('error');
  input.classList.remove('valid');
  if (errEl) errEl.textContent = msg;
}

function clearError(input, errEl) {
  input.classList.remove('error');
  input.classList.add('valid');
  if (errEl) errEl.textContent = '';
}

/* Run all validations — returns true only if ALL pass */
function validateForm() {
  let valid = true;

  /* ── Validate amount ── */
  const amt = parseFloat(amountHidden.value);
  /*
   * parseFloat converts the string "250" to the number 250.
   * If the value is empty or "abc", parseFloat returns NaN.
   * isNaN(NaN) = true, isNaN(250) = false.
   */
  if (!amountHidden.value || isNaN(amt) || amt < 10 || amt > 999999) {
    showError(amountHidden, amountErr, 'Please select or enter a valid amount (min R10).');
    valid = false;
  } else {
    clearError(amountHidden, amountErr);
  }

  /* ── Validate category ── */
  const catErr = document.getElementById('df-cat-err');
  if (!categorySelect || !categorySelect.value) {
    if (categorySelect) showError(categorySelect, catErr, 'Please select a donation category.');
    valid = false;
  } else {
    if (categorySelect) clearError(categorySelect, catErr);
  }

  /* ── Validate method ── */
  const methodErr = document.getElementById('df-method-err');
  if (!methodSelect || !methodSelect.value) {
    if (methodSelect) showError(methodSelect, methodErr, 'Please select a payment method.');
    valid = false;
  } else {
    if (methodSelect) clearError(methodSelect, methodErr);
  }

  /* ── Validate guest fields (only if not logged in) ── */
  if (!IS_LOGGED_IN) {
    const gnameInput = document.getElementById('df-gname');
    const gnameErr   = document.getElementById('df-gname-err');
    const gemailInput = document.getElementById('df-gemail');
    const gemailErr   = document.getElementById('df-gemail-err');
    const emailRe     = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (gnameInput && gnameInput.value.trim().length < 2) {
      showError(gnameInput, gnameErr, 'Please enter your full name.');
      valid = false;
    } else if (gnameInput) {
      clearError(gnameInput, gnameErr);
    }

    if (gemailInput && !emailRe.test(gemailInput.value.trim())) {
      showError(gemailInput, gemailErr, 'Please enter a valid email address.');
      valid = false;
    } else if (gemailInput) {
      clearError(gemailInput, gemailErr);
    }
  }

  return valid;
}


/* ══════════════════════════════════════════════════
   6. FORM SUBMIT — AJAX with fetch()
   ──────────────────────────────────────────────────
   Instead of a normal form submit (which reloads
   the page), we intercept the submit event and send
   the data using fetch(). This lets us:
   - Show a loading spinner
   - Display the receipt without a page reload
   - Show a toast notification
   
   WHAT HAPPENS STEP BY STEP:
   1. e.preventDefault()  — stop normal page reload
   2. validateForm()       — check all fields
   3. Disable submit btn  — prevent double-click
   4. fetch('donate.php') — POST the form data
   5. Parse JSON response — read ok/msg/ref etc.
   6. If ok: show receipt + toast
   7. If error: show error message + shake form
   8. Re-enable submit btn
══════════════════════════════════════════════════ */
if (donateForm) {
  donateForm.addEventListener('submit', async (e) => {
    e.preventDefault(); /* stop browser default submit */

    /* Step 2 — validate */
    if (!validateForm()) {
      /* Shake the form to draw attention to errors */
      donateForm.classList.add('shake');
      setTimeout(() => donateForm.classList.remove('shake'), 500);
      return; /* stop here — don't send to server */
    }

    /* Step 3 — loading state */
    submitBtn.disabled      = true;
    submitLabel.textContent = 'Processing…';
    submitIcon.className    = 'ti ti-loader-2';
    /*
     * We change the icon class to a spinner icon.
     * ti-loader-2 is a rotating circle in Tabler Icons.
     * The CSS animation on the icon makes it spin.
     */

    try {
      /* Step 4 — send form data to donate.php */
      const formData = new FormData(donateForm);
      /*
       * FormData automatically collects all <input>,
       * <select>, and <textarea> values from the form,
       * including the hidden CSRF input.
       */

      const response = await fetch('donate.php', {
        method: 'POST',
        body:   formData,
      });
      /*
       * await pauses this function until the server
       * responds. While waiting, the browser is free
       * to handle other events (scrolling, etc.).
       */

      /* Step 5 — parse the JSON response from donate.php */
      const data = await response.json();
      /*
       * data will look like:
       * { ok: true, ref: "DON-2025-0849", name: "Thabo", ... }
       * or
       * { ok: false, msg: "Please select a valid amount." }
       */

      if (data.ok) {
        /* ── Step 6: SUCCESS ── */

        /* Show toast notification */
        if (typeof showToast === 'function') {
          showToast(data.msg, 'success');
        }

        /* Populate the receipt with real data from PHP */
        populateReceipt(data);

        /* Scroll to the receipt smoothly */
        if (receipt) {
          receipt.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        /* Reset the form for a new donation */
        donateForm.reset();
        presetBtns.forEach(b => b.classList.remove('active'));
        if (customWrap) customWrap.hidden = true;
        if (catDesc) catDesc.textContent = '';
        if (eftPanel) eftPanel.hidden = true;
        if (notesCount) notesCount.textContent = '0 / 200';

      } else {
        /* ── Step 7: ERROR ── */
        if (typeof showToast === 'function') {
          showToast(data.msg, 'error');
        }

        /* Shake form */
        donateForm.classList.add('shake');
        setTimeout(() => donateForm.classList.remove('shake'), 500);

        /* Re-enable submit */
        submitBtn.disabled      = false;
        submitLabel.textContent = 'Submit Donation';
        submitIcon.className    = 'ti ti-heart';
      }

    } catch (err) {
      /* Network error (no internet, server down, etc.) */
      console.error('Donate fetch error:', err);
      if (typeof showToast === 'function') {
        showToast('Network error. Please check your connection.', 'error');
      }

      /* Re-enable submit so they can try again */
      submitBtn.disabled      = false;
      submitLabel.textContent = 'Submit Donation';
      submitIcon.className    = 'ti ti-heart';
    }
  });
}


/* ══════════════════════════════════════════════════
   7. RECEIPT REVEAL
   ──────────────────────────────────────────────────
   Takes the JSON data from the server and fills in
   each field on the receipt, then shows it.
══════════════════════════════════════════════════ */
function populateReceipt(data) {
  /* Fill each field by element ID */
  document.getElementById('dr-ref').textContent      = data.ref      ?? '—';
  document.getElementById('dr-name').textContent     = data.name     ?? '—';
  document.getElementById('dr-category').textContent = data.category ?? '—';
  document.getElementById('dr-method').textContent   = data.method   ?? '—';
  document.getElementById('dr-date').textContent     = data.date     ?? '—';
  document.getElementById('dr-amount').textContent   = data.amount   ?? '—';

  /* Hide placeholder, show receipt */
  if (placeholder) placeholder.hidden = true;
  if (receipt)     receipt.hidden     = false;
  /*
   * Setting .hidden = false is the same as removing
   * the hidden HTML attribute. It makes the element
   * visible again.
   */
}


/* ══════════════════════════════════════════════════
   8. FUND BAR ANIMATION — IntersectionObserver
   ──────────────────────────────────────────────────
   The progress bars start at width: 0%.
   We use IntersectionObserver to detect when each
   bar scrolls into the viewport, then animate it
   to the percentage stored in data-pct.
   
   WHY NOT JUST ANIMATE ON PAGE LOAD?
   If the user never scrolls to the fund section,
   the animation runs unseen. IntersectionObserver
   triggers it exactly when they see it — much more
   impressive.
══════════════════════════════════════════════════ */
const barFills = document.querySelectorAll('.fund-bar-fill');

if (barFills.length > 0) {
  const barObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        /*
         * entry.isIntersecting = true when the element
         * enters the visible area of the browser window.
         */
        const fill = entry.target;
        const pct  = fill.dataset.pct; /* e.g. "62" */
        fill.style.width = pct + '%';
        /*
         * Setting the width triggers the CSS transition:
         * transition: width 1.2s cubic-bezier(...)
         * The bar smoothly animates from 0% to 62%.
         */
        barObserver.unobserve(fill); /* animate once — stop watching */
      }
    });
  }, { threshold: 0.3 });
  /*
   * threshold: 0.3 means the animation triggers when
   * 30% of the bar is visible. Not 0% (too early, might
   * not be seen) and not 100% (too late, bar might be
   * half off-screen).
   */

  barFills.forEach(fill => barObserver.observe(fill));
}


/* ══════════════════════════════════════════════════
   9. REAL-TIME VALIDATION ON BLUR
   ──────────────────────────────────────────────────
   "blur" fires when the user clicks away from a field.
   We validate immediately so they get feedback before
   they even try to submit.
══════════════════════════════════════════════════ */
if (categorySelect) {
  categorySelect.addEventListener('blur', () => {
    const catErr = document.getElementById('df-cat-err');
    if (!categorySelect.value) {
      showError(categorySelect, catErr, 'Please select a donation category.');
    }
  });
}

if (methodSelect) {
  methodSelect.addEventListener('blur', () => {
    const methodErr = document.getElementById('df-method-err');
    if (!methodSelect.value) {
      showError(methodSelect, methodErr, 'Please select a payment method.');
    }
  });
}

/* Guest name + email blur validation */
const gnameInput  = document.getElementById('df-gname');
const gemailInput = document.getElementById('df-gemail');

if (gnameInput) {
  gnameInput.addEventListener('blur', () => {
    const err = document.getElementById('df-gname-err');
    if (gnameInput.value.trim().length < 2) {
      showError(gnameInput, err, 'Please enter your full name.');
    } else {
      clearError(gnameInput, err);
    }
  });
}

if (gemailInput) {
  gemailInput.addEventListener('blur', () => {
    const err = document.getElementById('df-gemail-err');
    const re  = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!re.test(gemailInput.value.trim())) {
      showError(gemailInput, err, 'Please enter a valid email address.');
    } else {
      clearError(gemailInput, err);
    }
  });
}

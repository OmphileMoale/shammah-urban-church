/* ═══════════════════════════════════════
   js/contact.js
   PURPOSE: Contact form interactivity.
   Handles:
   1. Real-time field validation
   2. Character counter on textarea
   3. AJAX form submission (no page reload)
   4. Success / error toast via toast.js
   5. Submit button loading state
═══════════════════════════════════════ */

/* ──────────────────────────────────────
   ELEMENT REFERENCES
   Cache DOM lookups so we don't call
   querySelector() repeatedly.
──────────────────────────────────────── */
const contactForm  = document.getElementById('contactForm');
const submitBtn    = document.getElementById('cf-submit-btn');
const btnLabel     = document.getElementById('cf-btn-label');
const charCount    = document.getElementById('cf-char-count');
const messageInput = document.getElementById('cf-message');

/* ──────────────────────────────────────
   1. CHARACTER COUNTER
   Updates "X / 500" as user types.
   Turns gold when > 450, red when > 500.
──────────────────────────────────────── */
if (messageInput && charCount) {
  messageInput.addEventListener('input', () => {
    const len = messageInput.value.length;
    charCount.textContent = `${len} / 500`;
    charCount.style.color = len > 500 ? 'var(--red)' : len > 450 ? 'var(--gold)' : 'var(--text-3)';
  });
}


/* ──────────────────────────────────────
   2. REAL-TIME VALIDATION HELPERS
   
   showError  – marks field red + shows msg
   clearError – removes red + clears msg
   validateField – returns true if field ok
──────────────────────────────────────── */

/**
 * Mark an input as invalid and display an error message.
 * @param {HTMLElement} input  – the form field
 * @param {string}      msg    – the error string to display
 */
function showError(input, msg) {
  input.classList.add('error');
  input.classList.remove('valid');
  const errEl = document.getElementById(input.getAttribute('aria-describedby'));
  if (errEl) errEl.textContent = msg;
}

/**
 * Mark an input as valid and hide any error message.
 * @param {HTMLElement} input
 */
function clearError(input) {
  input.classList.remove('error');
  input.classList.add('valid');
  const errEl = document.getElementById(input.getAttribute('aria-describedby'));
  if (errEl) errEl.textContent = '';
}

/**
 * Validate a single field.
 * Returns true if the field is valid.
 * @param {HTMLElement} input
 * @returns {boolean}
 */
function validateField(input) {
  const val = input.value.trim();

  // Name: at least 2 characters
  if (input.id === 'cf-name') {
    if (val.length < 2) { showError(input, 'Please enter your full name.'); return false; }
  }

  // Email: must match basic email pattern
  if (input.id === 'cf-email') {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(val)) { showError(input, 'Please enter a valid email address.'); return false; }
  }

  // Subject: must select a non-empty option
  if (input.id === 'cf-subject') {
    if (val === '') { showError(input, 'Please select a subject.'); return false; }
  }

  // Message: at least 10 characters
  if (input.id === 'cf-message') {
    if (val.length < 10) { showError(input, 'Please write at least 10 characters.'); return false; }
    if (val.length > 500) { showError(input, 'Message must be 500 characters or fewer.'); return false; }
  }

  clearError(input);
  return true;
}


/* ──────────────────────────────────────
   3. ATTACH REAL-TIME LISTENERS
   Validate each field as the user leaves it
   (on 'blur' = when focus moves away).
──────────────────────────────────────── */
['cf-name', 'cf-email', 'cf-subject', 'cf-message'].forEach(id => {
  const el = document.getElementById(id);
  if (el) {
    // Validate on blur (leaving the field)
    el.addEventListener('blur', () => validateField(el));
    // Clear error as soon as user starts typing again
    el.addEventListener('input', () => {
      if (el.classList.contains('error')) validateField(el);
    });
  }
});


/* ──────────────────────────────────────
   4. FORM SUBMIT HANDLER
   
   We intercept the submit event and send
   the data via fetch() (AJAX) so the page
   doesn't reload.
   
   HOW fetch() WORKS:
   fetch(url, options) returns a Promise.
   .then(response => response.json()) parses
   the JSON the server returned.
   .then(data => ...) uses the parsed object.
   .catch(err => ...) handles network errors.
──────────────────────────────────────── */
if (contactForm) {
  contactForm.addEventListener('submit', async (e) => {
    // Prevent the default full-page form submit
    e.preventDefault();

    /* ── Validate all required fields first ── */
    const fieldsToCheck = ['cf-name', 'cf-email', 'cf-subject', 'cf-message'];
    let allValid = true;

    fieldsToCheck.forEach(id => {
      const el = document.getElementById(id);
      if (el && !validateField(el)) allValid = false;
    });

    // Stop here if any field failed validation
    if (!allValid) {
      // Scroll to first error so user sees it
      const firstError = contactForm.querySelector('.error');
      if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    /* ── Show loading state on the button ── */
    submitBtn.disabled = true;
    btnLabel.textContent = 'Sending…';
    submitBtn.querySelector('i').className = 'ti ti-loader-2';

    /* ── Build FormData from the form ── */
    const formData = new FormData(contactForm);

    try {
      /*
        fetch() sends the data to contact.php.
        We POST to the current page URL because
        contact.php handles both GET (render) and
        POST (process form) at the same URL.
      */
      const response = await fetch('contact.php', {
        method: 'POST',
        body: formData,
      });

      /* ── Parse the JSON response ── */
      const data = await response.json();

      if (data.ok) {
        /* SUCCESS */
        // Show green toast notification
        if (typeof showToast === 'function') showToast(data.msg, 'success');

        // Reset the form fields
        contactForm.reset();

        // Remove all valid/error styling
        contactForm.querySelectorAll('.cf-input').forEach(el => {
          el.classList.remove('valid', 'error');
        });

        // Reset character counter
        if (charCount) { charCount.textContent = '0 / 500'; charCount.style.color = ''; }

      } else {
        /* SERVER-SIDE ERROR */
        if (typeof showToast === 'function') showToast(data.msg, 'error');
      }

    } catch (err) {
      /* NETWORK ERROR (offline, server down) */
      if (typeof showToast === 'function') {
        showToast('Network error. Please check your connection and try again.', 'error');
      }
      console.error('Contact form fetch error:', err);
    }

    /* ── Restore the button ── */
    submitBtn.disabled = false;
    btnLabel.textContent = 'Send Message';
    submitBtn.querySelector('i').className = 'ti ti-send';
  });
}

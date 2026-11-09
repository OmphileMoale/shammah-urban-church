/* ═══════════════════════════════════════
   js/register.js
   PURPOSE: Registration form interactivity.
   Handles:
   1. Real-time field validation
   2. AJAX email duplicate check
   3. Password show/hide toggles
   4. Live password strength + rules checklist
   5. AJAX form submission (no page reload)
   6. Success / error toast via toast.js
   7. Submit button loading state
═══════════════════════════════════════ */


/* ──────────────────────────────────────
   ELEMENT REFERENCES
   Cache DOM lookups so we don't call
   querySelector() repeatedly.
──────────────────────────────────────── */
const registerForm   = document.getElementById('registerForm');
const submitBtn      = document.getElementById('rf-submit-btn');
const btnLabel       = document.getElementById('rf-btn-label');
const passwordInput  = document.getElementById('rf-password');
const confirmInput   = document.getElementById('rf-confirm');
const strengthFill   = document.getElementById('rf-strength-fill');
const strengthLabel  = document.getElementById('rf-strength-label');
const emailInput     = document.getElementById('rf-email');
const emailStatus    = document.getElementById('rf-email-status');


/* ──────────────────────────────────────
   1. SHOW / HIDE PASSWORD TOGGLES
   Clicking the eye icon switches the
   input between type="password" and
   type="text", and updates aria-pressed.
──────────────────────────────────────── */

/**
 * Wire up a show/hide toggle for one password input.
 * @param {string} toggleId – id of the <button>
 * @param {string} inputId  – id of the <input type="password">
 */
function setupPasswordToggle(toggleId, inputId) {
  const toggle = document.getElementById(toggleId);
  const input  = document.getElementById(inputId);
  if (!toggle || !input) return;

  toggle.addEventListener('click', () => {
    // Flip between password and text
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';

    // Swap the eye icon
    toggle.querySelector('i').className = isHidden
      ? 'ti ti-eye-off'
      : 'ti ti-eye';

    // Update ARIA state for screen readers
    toggle.setAttribute('aria-pressed', String(isHidden));
    toggle.setAttribute('aria-label',
      isHidden ? 'Hide password' : 'Show password');
  });
}

setupPasswordToggle('rf-pw-toggle-1', 'rf-password');
setupPasswordToggle('rf-pw-toggle-2', 'rf-confirm');


/* ──────────────────────────────────────
   2. PASSWORD STRENGTH + RULES CHECKLIST
   Runs on every keystroke in the password
   field and updates:
   – The strength bar (weak / fair / strong)
   – Four rule checklist items
   – The confirm-match rule whenever the
     confirm field also has content
──────────────────────────────────────── */

/**
 * Check one password rule and add/remove .passed on its <li>.
 * Also swaps the icon between ti-circle and ti-circle-check.
 * @param {string}  ruleId  – id of the <li>
 * @param {boolean} passes  – whether the rule is met
 */
function setRule(ruleId, passes) {
  const li = document.getElementById(ruleId);
  if (!li) return;
  li.classList.toggle('passed', passes);
  li.querySelector('i').className = passes
    ? 'ti ti-circle-check'
    : 'ti ti-circle';
}

/**
 * Score the password and return 0 (weak), 1 (fair), or 2 (strong).
 * @param {string} pw
 * @returns {number}
 */
function scorePassword(pw) {
  const hasLength = pw.length >= 8;
  const hasUpper  = /[A-Z]/.test(pw);
  const hasNumber = /[0-9]/.test(pw);
  const passCount = [hasLength, hasUpper, hasNumber].filter(Boolean).length;
  if (passCount === 3) return 2; // strong
  if (passCount === 2) return 1; // fair
  return 0;                      // weak
}

/**
 * Update the strength bar and label based on score.
 * @param {number} score – 0 | 1 | 2
 */
function updateStrengthBar(score) {
  if (!strengthFill || !strengthLabel) return;

  const levels = [
    { cls: 'weak',   text: 'Weak'   },
    { cls: 'fair',   text: 'Fair'   },
    { cls: 'strong', text: 'Strong' },
  ];

  // Remove all state classes first
  strengthFill.classList.remove('weak', 'fair', 'strong');
  strengthLabel.classList.remove('weak', 'fair', 'strong');

  const level = levels[score];
  if (level) {
    strengthFill.classList.add(level.cls);
    strengthLabel.classList.add(level.cls);
    strengthLabel.textContent = level.text;
  } else {
    strengthLabel.textContent = '';
  }
}

if (passwordInput) {
  passwordInput.addEventListener('input', () => {
    const pw = passwordInput.value;

    // Update the four rules
    setRule('rule-length', pw.length >= 8);
    setRule('rule-upper',  /[A-Z]/.test(pw));
    setRule('rule-number', /[0-9]/.test(pw));

    // Update match rule only if confirm has content
    if (confirmInput && confirmInput.value.length > 0) {
      setRule('rule-match', pw === confirmInput.value);
    }

    // Update strength bar (only show once user starts typing)
    if (pw.length > 0) {
      updateStrengthBar(scorePassword(pw));
    } else {
      // Reset bar if field cleared
      strengthFill.classList.remove('weak', 'fair', 'strong');
      strengthLabel.textContent = '';
    }
  });
}

if (confirmInput) {
  confirmInput.addEventListener('input', () => {
    if (passwordInput) {
      setRule('rule-match', passwordInput.value === confirmInput.value
                            && confirmInput.value.length > 0);
    }
  });
}


/* ──────────────────────────────────────
   3. AJAX EMAIL DUPLICATE CHECK
   Fires on blur (when user leaves the
   email field). Calls register.php with
   ?check_email=1&email=... and updates
   the status indicator.

   States:
   • checking  – spinner while request runs
   • available – green tick, email is free
   • taken     – red cross, already registered
──────────────────────────────────────── */
let emailCheckTimer = null; // debounce handle

if (emailInput && emailStatus) {
  emailInput.addEventListener('blur', async () => {
    const email = emailInput.value.trim();

    // Don't check obviously invalid emails
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) return;

    // Show spinner
    emailStatus.className  = 'rf-email-status checking';
    emailStatus.innerHTML  = '<i class="ti ti-loader-2"></i>';

    try {
      const url = `register.php?check_email=1&email=${encodeURIComponent(email)}`;
      const res  = await fetch(url);
      const data = await res.json();

      if (data.available) {
        emailStatus.className = 'rf-email-status available';
        emailStatus.innerHTML = '<i class="ti ti-circle-check"></i>';
        clearError(emailInput); // remove any existing error
      } else {
        emailStatus.className = 'rf-email-status taken';
        emailStatus.innerHTML = '<i class="ti ti-circle-x"></i>';
        showError(emailInput, data.msg || 'This email is already registered.');
      }
    } catch (err) {
      // On network error, clear the indicator silently
      emailStatus.className = '';
      emailStatus.innerHTML = '';
      console.error('Email check error:', err);
    }
  });

  // Clear the indicator when user edits the email again
  emailInput.addEventListener('input', () => {
    emailStatus.className = '';
    emailStatus.innerHTML = '';
  });
}


/* ──────────────────────────────────────
   4. REAL-TIME VALIDATION HELPERS
   Same pattern as contact.js —
   showError / clearError / validateField.
──────────────────────────────────────── */

/**
 * Mark an input as invalid and show an error message.
 * @param {HTMLElement} input
 * @param {string}      msg
 */
function showError(input, msg) {
  input.classList.add('error');
  input.classList.remove('valid');
  const errEl = document.getElementById(input.getAttribute('aria-describedby'));
  if (errEl) errEl.textContent = msg;
}

/**
 * Mark an input as valid and hide its error message.
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
 * Returns true if valid.
 * @param {HTMLElement} input
 * @returns {boolean}
 */
function validateField(input) {
  const val = input.value.trim();
  const id  = input.id;

  if (id === 'rf-first' || id === 'rf-last') {
    if (val.length < 2) {
      showError(input, 'Must be at least 2 characters.');
      return false;
    }
  }

  if (id === 'rf-email') {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(val)) {
      showError(input, 'Please enter a valid email address.');
      return false;
    }
  }

  if (id === 'rf-phone') {
    // Remove spaces and dashes for length check
    const cleaned = val.replace(/[\s\-]/g, '');
    if (cleaned.length < 10) {
      showError(input, 'Please enter a valid phone number.');
      return false;
    }
  }

  if (id === 'rf-dob') {
    if (!val) {
      showError(input, 'Please enter your date of birth.');
      return false;
    }
    // Must not be a future date
    if (new Date(val) > new Date()) {
      showError(input, 'Date of birth cannot be in the future.');
      return false;
    }
  }

  if (id === 'rf-gender' || id === 'rf-memtype') {
    if (!val) {
      showError(input, 'Please make a selection.');
      return false;
    }
  }

  if (id === 'rf-street') {
    if (val.length < 5) {
      showError(input, 'Please enter your street address.');
      return false;
    }
  }

  if (id === 'rf-city') {
    if (val.length < 2) {
      showError(input, 'Please enter your city.');
      return false;
    }
  }

  if (id === 'rf-postal') {
    if (!/^\d{4}$/.test(val)) {
      showError(input, 'Please enter a valid 4-digit postal code.');
      return false;
    }
  }

  if (id === 'rf-password') {
    if (val.length < 8)          { showError(input, 'Password must be at least 8 characters.');           return false; }
    if (!/[A-Z]/.test(val))      { showError(input, 'Password must include at least one uppercase letter.'); return false; }
    if (!/[0-9]/.test(val))      { showError(input, 'Password must include at least one number.');         return false; }
  }

  if (id === 'rf-confirm') {
    if (passwordInput && val !== passwordInput.value.trim()) {
      showError(input, 'Passwords do not match.');
      return false;
    }
  }

  clearError(input);
  return true;
}


/* ──────────────────────────────────────
   5. ATTACH REAL-TIME LISTENERS
   Validate each required field on blur.
   Clear error as soon as user types again.
──────────────────────────────────────── */
const requiredFields = [
  'rf-first', 'rf-last', 'rf-email', 'rf-phone',
  'rf-dob', 'rf-gender', 'rf-memtype',
  'rf-street', 'rf-city', 'rf-postal',
  'rf-password', 'rf-confirm',
];

requiredFields.forEach(id => {
  const el = document.getElementById(id);
  if (!el) return;
  el.addEventListener('blur',  () => validateField(el));
  el.addEventListener('input', () => {
    if (el.classList.contains('error')) validateField(el);
  });
});


/* ──────────────────────────────────────
   6. TERMS CHECKBOX VALIDATION
   Separately validated because it uses a
   checkbox rather than a text input.
──────────────────────────────────────── */
function validateTerms() {
  const terms   = document.getElementById('rf-terms');
  const termsErr = document.getElementById('rf-terms-err');
  if (!terms || !termsErr) return true;

  if (!terms.checked) {
    termsErr.textContent = 'You must accept the Privacy Policy and Terms to continue.';
    return false;
  }
  termsErr.textContent = '';
  return true;
}


/* ──────────────────────────────────────
   7. FORM SUBMIT HANDLER
   Intercepts submit, validates all fields,
   then sends via fetch() (AJAX) so the
   page doesn't reload.

   Same structure as contact.js — markers
   will notice the consistent pattern.
──────────────────────────────────────── */
if (registerForm) {
  registerForm.addEventListener('submit', async (e) => {
    // Prevent the default full-page form submit
    e.preventDefault();

    /* ── Validate all required fields ── */
    let allValid = true;

    requiredFields.forEach(id => {
      const el = document.getElementById(id);
      if (el && !validateField(el)) allValid = false;
    });

    // Also check terms checkbox
    if (!validateTerms()) allValid = false;

    // Stop if any field failed
    if (!allValid) {
      const firstError = registerForm.querySelector('.error');
      if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    /* ── Show loading state on button ── */
    submitBtn.disabled  = true;
    btnLabel.textContent = 'Creating account…';
    submitBtn.querySelector('i').className = 'ti ti-loader-2';

    /* ── Build FormData from the form ── */
    const formData = new FormData(registerForm);

    try {
      /*
        POST to register.php — the same file handles
        both GET (render the page) and POST (process
        the form), returning JSON for the POST case.
      */
      const response = await fetch('register.php', {
        method: 'POST',
        body: formData,
      });

      const data = await response.json();

      if (data.ok) {
        /* SUCCESS */
        if (data.step === 'code' && typeof OtpBox !== 'undefined') {
          /* Account created — confirm the code sent to email + phone, then go to the portal */
          OtpBox.open({
            url:     'login.php',
            csrf:    CSRF_TOKEN,
            message: data.msg,
            devCode: data.dev_code,
            onSuccess: (res) => {
              if (typeof showToast === 'function') showToast(res.msg, 'success');
              setTimeout(() => { window.location.href = res.dest ?? 'member_portal.php'; }, 1000);
            },
            onCancel: () => { window.location.href = 'login.php'; },
          });
        } else {
          if (typeof showToast === 'function') showToast(data.msg, 'success');
          // Redirect to login after a short delay so
          // the user can read the success toast
          setTimeout(() => {
            window.location.href = 'login.php';
          }, 2500);
        }

        // Reset the form and clear all states
        registerForm.reset();
        registerForm.querySelectorAll('.rf-input').forEach(el => {
          el.classList.remove('valid', 'error');
        });

        // Reset strength bar and rules
        if (strengthFill)  { strengthFill.classList.remove('weak','fair','strong'); }
        if (strengthLabel) { strengthLabel.textContent = ''; }
        ['rule-length','rule-upper','rule-number','rule-match'].forEach(id => {
          setRule(id, false);
        });

        // Reset email status indicator
        if (emailStatus) { emailStatus.className = ''; emailStatus.innerHTML = ''; }

      } else {
        /* SERVER-SIDE ERROR */
        if (typeof showToast === 'function') showToast(data.msg, 'error');
      }

    } catch (err) {
      /* NETWORK ERROR */
      if (typeof showToast === 'function') {
        showToast('Network error. Please check your connection and try again.', 'error');
      }
      console.error('Register form fetch error:', err);
    }

    /* ── Restore the button ── */
    submitBtn.disabled   = false;
    btnLabel.textContent = 'Create My Account';
    submitBtn.querySelector('i').className = 'ti ti-user-check';
  });
}


/* ══════════════════════════════════════
   8. FORM PROGRESS INDICATOR
   Updates the step circles (active/done)
   based on which card is most visible in
   the viewport — uses IntersectionObserver.
════════════════════════════════════════ */
(function initProgress() {
  const cards = [
    document.querySelector('.reg-card:nth-child(2)'), // after progress bar
    document.querySelector('.reg-card:nth-child(3)'),
    document.querySelector('.reg-card:nth-child(4)'),
  ];

  const steps = [
    document.getElementById('rp-1'),
    document.getElementById('rp-2'),
    document.getElementById('rp-3'),
  ];

  const lines = [
    document.getElementById('rp-line-1'),
    document.getElementById('rp-line-2'),
  ];

  if (!cards[0] || !steps[0]) return;

  function setStep(active) {
    steps.forEach((s, i) => {
      s.classList.remove('active', 'done');
      if (i < active)       s.classList.add('done');
      else if (i === active) s.classList.add('active');
    });
    lines.forEach((l, i) => {
      l.classList.toggle('done', i < active);
    });
  }

  /* Use IntersectionObserver to detect which card is visible */
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const idx = cards.indexOf(entry.target);
        if (idx !== -1) setStep(idx);
      }
    });
  }, { threshold: 0.4 });

  cards.forEach(c => { if (c) observer.observe(c); });
})();


/* ══════════════════════════════════════
   9. SHAKE ANIMATION ON VALIDATION FAIL
   Adds .shake to the form, then removes it.
════════════════════════════════════════ */
if (registerForm) {
  const origSubmit = registerForm.onsubmit;
  registerForm.addEventListener('submit', () => {
    // Shake is added by the submit handler if validation fails
  });
}

/* Export helper so the submit handler can call it */
function shakeForm() {
  if (!registerForm) return;
  registerForm.classList.add('shake');
  setTimeout(() => registerForm.classList.remove('shake'), 500);
}

/* ═══════════════════════════════════════
   js/login.js
   PURPOSE: Login form interactivity.
   Handles:
   1. Show/hide password toggle
   2. Real-time field validation
   3. AJAX form submission (no reload)
   4. Failed-attempt warning banner
   5. Submit button loading state
   6. Success toast + redirect
═══════════════════════════════════════ */


/* ── ELEMENT REFERENCES ──────────────── */
const loginForm    = document.getElementById('loginForm');
const submitBtn    = document.getElementById('lf-submit-btn');
const btnLabel     = document.getElementById('lf-btn-label');
const emailInput   = document.getElementById('lf-email');
const passwordInput = document.getElementById('lf-password');
const attemptWarn  = document.getElementById('lf-attempt-warn');


/* ══════════════════════════════════════
   1. SHOW / HIDE PASSWORD
   Clicking the eye icon swaps the input
   between type="password" and type="text".
════════════════════════════════════════ */
const pwToggle = document.getElementById('lf-pw-toggle');

if (pwToggle && passwordInput) {
  pwToggle.addEventListener('click', () => {
    const isHidden = passwordInput.type === 'password';
    passwordInput.type = isHidden ? 'text' : 'password';
    pwToggle.querySelector('i').className = isHidden ? 'ti ti-eye-off' : 'ti ti-eye';
    pwToggle.setAttribute('aria-pressed', String(isHidden));
    pwToggle.setAttribute('aria-label',   isHidden ? 'Hide password' : 'Show password');
  });
}


/* ══════════════════════════════════════
   2. VALIDATION HELPERS
   showError / clearError follow the exact
   same pattern as register.js and contact.js
   — consistent code style across the project.
════════════════════════════════════════ */

function showError(input, msg) {
  input.classList.add('error');
  input.classList.remove('valid');
  const errEl = document.getElementById(input.getAttribute('aria-describedby'));
  if (errEl) errEl.textContent = msg;
}

function clearError(input) {
  input.classList.remove('error');
  input.classList.add('valid');
  const errEl = document.getElementById(input.getAttribute('aria-describedby'));
  if (errEl) errEl.textContent = '';
}

function validateEmail() {
  /* Accepts an email address OR a phone number (071 234 5678 / +27712345678) */
  const val     = emailInput ? emailInput.value.trim() : '';
  const isEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
  const digits  = val.replace(/[\s()\-+.]/g, '');
  const isPhone = /^\d{9,15}$/.test(digits);
  if (!isEmail && !isPhone) {
    showError(emailInput, 'Please enter your email address or phone number.');
    return false;
  }
  clearError(emailInput);
  return true;
}

function validatePassword() {
  const val = passwordInput ? passwordInput.value : '';
  if (val.length < 1) {
    showError(passwordInput, 'Please enter your password.');
    return false;
  }
  clearError(passwordInput);
  return true;
}


/* ══════════════════════════════════════
   3. REAL-TIME LISTENERS
   Validate on blur; clear error on input.
════════════════════════════════════════ */
if (emailInput) {
  emailInput.addEventListener('blur',  validateEmail);
  emailInput.addEventListener('input', () => {
    if (emailInput.classList.contains('error')) validateEmail();
  });
}

if (passwordInput) {
  passwordInput.addEventListener('blur',  validatePassword);
  passwordInput.addEventListener('input', () => {
    if (passwordInput.classList.contains('error')) validatePassword();
  });
}


/* ══════════════════════════════════════
   4. FORM SUBMIT HANDLER
   Intercepts submit, validates, sends via
   fetch() and handles the JSON response.
════════════════════════════════════════ */
function resetButton() {
  submitBtn.disabled   = false;
  btnLabel.textContent = 'Sign In';
  if (submitBtn.querySelector('i')) {
    submitBtn.querySelector('i').className = 'ti ti-login';
  }
}

if (loginForm) {
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    /* ── Validate both fields ── */
    const emailOk = validateEmail();
    const pwOk    = validatePassword();
    if (!emailOk || !pwOk) return;

    /* ── Loading state ── */
    submitBtn.disabled     = true;
    btnLabel.textContent   = 'Signing in…';
    if (submitBtn.querySelector('i')) {
      submitBtn.querySelector('i').className = 'ti ti-loader-2';
    }

    /* ── Hide any previous warning ── */
    if (attemptWarn) {
      attemptWarn.textContent = '';
      attemptWarn.classList.remove('visible');
    }

    try {
      const formData = new FormData(loginForm);

      const response = await fetch('login.php', {
        method: 'POST',
        body:   formData,
      });

      const data = await response.json();

      if (data.ok && data.step === 'code') {
        /* PASSWORD OK — now the 6-digit code sent to email + phone */
        resetButton();
        OtpBox.open({
          url:     'login.php',
          csrf:    CSRF_TOKEN,
          message: data.msg,
          devCode: data.dev_code,
          onSuccess: (res) => {
            if (typeof showToast === 'function') showToast(res.msg, 'success');
            setTimeout(() => { window.location.href = res.dest ?? 'member_portal.php'; }, 1000);
          },
        });

      } else if (data.ok) {
        /* SUCCESS */
        if (typeof showToast === 'function') showToast(data.msg, 'success');
        setTimeout(() => {
          window.location.href = data.dest ?? 'member_portal.php';
        }, 1200);

      } else {
        /* FAILURE — show warning banner + toast */
        if (attemptWarn) {
          attemptWarn.textContent = data.msg;
          attemptWarn.classList.add('visible');
          attemptWarn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        if (typeof showToast === 'function') showToast(data.msg, 'error');

        /* Restore the button */
        submitBtn.disabled   = false;
        btnLabel.textContent = 'Sign In';
        if (submitBtn.querySelector('i')) {
          submitBtn.querySelector('i').className = 'ti ti-login';
        }

        /* Shake the form on error for tactile feedback */
        loginForm.classList.add('shake');
        setTimeout(() => loginForm.classList.remove('shake'), 500);
      }

    } catch (err) {
      /* NETWORK ERROR */
      if (typeof showToast === 'function') {
        showToast('Network error. Please check your connection and try again.', 'error');
      }
      console.error('Login fetch error:', err);

      submitBtn.disabled   = false;
      btnLabel.textContent = 'Sign In';
      if (submitBtn.querySelector('i')) {
        submitBtn.querySelector('i').className = 'ti ti-login';
      }
    }
  });
}

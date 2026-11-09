/* ═══════════════════════════════════════
   js/otp.js
   PURPOSE: The "Enter the 6-digit code" box.
   After the password is accepted (login.php),
   an account is created (register.php) or a reset
   is asked for (forgot_password.php), the server
   sends a code to the member's email AND phone.
   This box collects it and posts it back.

   USAGE:
     OtpBox.open({
       url:      'login.php',          // page that checks the code
       csrf:     CSRF_TOKEN,
       title:    'Enter your code',    // optional
       message:  data.msg,             // "We sent a code to th•••@email.com and +27•••678"
       devCode:  data.dev_code,        // only in TEST MODE (email/SMS not set up)
       extraFields: () => ({}),        // more POST fields, e.g. new password
       extraHtml:   '',                // more inputs shown under the code
       onSuccess:   (data) => {...}
     });
═══════════════════════════════════════ */
const OtpBox = (() => {
  let overlay = null;

  function close() {
    if (overlay) overlay.remove();
    overlay = null;
  }

  function open(opts) {
    close();
    overlay = document.createElement('div');
    overlay.className = 'otp-overlay';
    overlay.innerHTML = `
      <div class="otp-box" role="dialog" aria-modal="true" aria-labelledby="otp-title">
        <div class="otp-icon"><i class="ti ti-device-mobile-message" aria-hidden="true"></i></div>
        <h2 id="otp-title" class="otp-title">${opts.title || 'Enter your code'}</h2>
        <p class="otp-msg" id="otp-msg"></p>
        <form class="otp-form" novalidate>
          <label class="otp-label" for="otp-code">6-digit code</label>
          <input class="otp-input" id="otp-code" name="code" inputmode="numeric"
                 autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="••••••" required>
          ${opts.extraHtml || ''}
          <div class="otp-error" id="otp-error" role="alert" aria-live="polite"></div>
          <button class="otp-verify" type="submit">
            <i class="ti ti-shield-check" aria-hidden="true"></i> <span>Verify</span>
          </button>
          <div class="otp-links">
            <button type="button" class="otp-link" id="otp-resend">Resend code</button>
            <button type="button" class="otp-link" id="otp-cancel">Cancel</button>
          </div>
        </form>
      </div>`;
    document.body.appendChild(overlay);

    const msgEl  = overlay.querySelector('#otp-msg');
    const errEl  = overlay.querySelector('#otp-error');
    const input  = overlay.querySelector('#otp-code');
    const form   = overlay.querySelector('.otp-form');
    const verify = overlay.querySelector('.otp-verify');
    const resend = overlay.querySelector('#otp-resend');

    const setMessage = (text, dev) => {
      msgEl.textContent = text || 'We sent a 6-digit code to your email and phone.';
      msgEl.classList.toggle('otp-msg--dev', Boolean(dev));   // highlighted in TEST MODE
    };
    setMessage(opts.message, opts.devCode);
    input.focus();

    input.addEventListener('input', () => {
      input.value = input.value.replace(/\D/g, '').slice(0, 6);
      errEl.textContent = '';
    });

    const post = async (fields) => {
      const fd = new FormData();
      fd.append('csrf', opts.csrf);
      Object.entries(fields).forEach(([k, v]) => fd.append(k, v));
      const res = await fetch(opts.url, { method: 'POST', body: fd });
      return res.json();
    };

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!/^\d{6}$/.test(input.value)) {
        errEl.textContent = 'Please enter the 6 digits from your email or SMS.';
        return;
      }
      const extra = opts.extraFields ? opts.extraFields(overlay) : {};
      if (extra === false) return;               // caller showed its own error
      verify.disabled = true;
      try {
        const data = await post({ action: 'verify', code: input.value, ...extra });
        if (data.ok) {
          close();
          opts.onSuccess(data);
        } else {
          errEl.textContent = data.msg;
          input.select();
        }
      } catch (err) {
        errEl.textContent = 'Network error. Please try again.';
      }
      verify.disabled = false;
    });

    resend.addEventListener('click', async () => {
      resend.disabled = true;
      try {
        const data = await post({ action: 'resend' });
        if (data.ok) {
          setMessage(data.msg, data.dev_code);
          if (typeof showToast === 'function') showToast('A new code is on its way.', 'success');
        } else {
          errEl.textContent = data.msg;
        }
      } catch (err) {
        errEl.textContent = 'Network error. Please try again.';
      }
      setTimeout(() => { resend.disabled = false; }, 3000);
    });

    overlay.querySelector('#otp-cancel').addEventListener('click', () => {
      close();
      if (opts.onCancel) opts.onCancel();
    });
  }

  return { open, close };
})();

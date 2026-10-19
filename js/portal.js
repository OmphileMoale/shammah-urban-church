/* ═══════════════════════════════════════
   js/portal.js
   PURPOSE: All interactivity for member_portal.php
   1. Sidebar tab switching + active state
   2. Mobile sidebar open/close
   3. "Edit Profile" button → switches to profile tab
   4. Profile form AJAX submission
   5. Password form AJAX submission + show/hide toggles
   6. RSVP buttons (reuses existing rsvp.js pattern)
   7. Cancel RSVP buttons
═══════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', () => {


  /* ─────────────────────────────────────
     1. SIDEBAR TAB SWITCHING
     Each .ps-item has data-tab="<id>".
     Clicking it:
     – removes .active from all sidebar items
     – adds .active to the clicked item
     – hides all .portal-panel sections
     – shows the matching #tab-<id> section
     – updates the mobile title bar text
     – closes the sidebar on mobile
  ───────────────────────────────────── */
  const sidebarItems = document.querySelectorAll('.ps-item[data-tab]');
  const panels       = document.querySelectorAll('.portal-panel');
  const mobileTitle  = document.getElementById('pmb-title');

  /**
   * Switch to a named tab.
   * @param {string} tabName – value of data-tab attribute
   */
  function switchTab(tabName) {
    // Update sidebar active state
    sidebarItems.forEach(item => {
      const isTarget = item.dataset.tab === tabName;
      item.classList.toggle('active', isTarget);
      item.setAttribute('aria-current', isTarget ? 'true' : 'false');
    });

    // Show/hide content panels
    panels.forEach(panel => {
      const isTarget = panel.id === `tab-${tabName}`;
      panel.hidden = !isTarget;
      if (isTarget) {
        panel.classList.add('active');
        panel.removeAttribute('hidden');
      } else {
        panel.classList.remove('active');
        panel.setAttribute('hidden', '');
      }
    });

    // Update mobile bar title
    if (mobileTitle) {
      const activeItem = document.querySelector(`.ps-item[data-tab="${tabName}"]`);
      if (activeItem) {
        // Extract text without the badge number
        mobileTitle.textContent = activeItem.textContent.trim().replace(/\d+$/, '').trim();
      }
    }

    // Close sidebar on mobile
    closeSidebar();

    // Scroll the main content to the top
    const portalMain = document.querySelector('.portal-main');
    if (portalMain) portalMain.scrollTop = 0;
  }

  // Wire up sidebar nav buttons
  sidebarItems.forEach(item => {
    item.addEventListener('click', () => switchTab(item.dataset.tab));
  });

  // Wire up any inline "data-tab" buttons inside panels
  // (e.g. "View all →", "Edit Profile" button)
  document.querySelectorAll('[data-tab]:not(.ps-item)').forEach(btn => {
    btn.addEventListener('click', () => {
      const tab = btn.dataset.tab;
      if (tab) switchTab(tab);
    });
  });


  /* ─────────────────────────────────────
     2. MOBILE SIDEBAR OPEN / CLOSE
  ───────────────────────────────────── */
  const sidebar        = document.getElementById('portalSidebar');
  const sidebarToggle  = document.getElementById('sidebarToggle');
  const overlay        = document.getElementById('portalOverlay');

  function openSidebar() {
    sidebar?.classList.add('open');
    overlay?.classList.add('active');
    sidebarToggle?.setAttribute('aria-expanded', 'true');
    overlay?.removeAttribute('aria-hidden');
  }

  function closeSidebar() {
    sidebar?.classList.remove('open');
    overlay?.classList.remove('active');
    sidebarToggle?.setAttribute('aria-expanded', 'false');
    overlay?.setAttribute('aria-hidden', 'true');
  }

  sidebarToggle?.addEventListener('click', () => {
    const isOpen = sidebar?.classList.contains('open');
    isOpen ? closeSidebar() : openSidebar();
  });

  // Clicking the overlay closes the sidebar
  overlay?.addEventListener('click', closeSidebar);

  // ESC key closes sidebar
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeSidebar();
  });


  /* ─────────────────────────────────────
     3. PROFILE FORM SUBMISSION
     fetch() POSTs to member_portal.php
     with action=update_profile.
     Shows toast on success/failure.
  ───────────────────────────────────── */
  const profileForm      = document.getElementById('profileForm');
  const profileSubmitBtn = document.getElementById('profileSubmitBtn');
  const profileBtnLabel  = document.getElementById('profileBtnLabel');

  if (profileForm) {
    profileForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      // Client-side validation
      let valid = true;

      const phone  = profileForm.querySelector('#pf-phone');
      const street = profileForm.querySelector('#pf-street');
      const city   = profileForm.querySelector('#pf-city');
      const postal = profileForm.querySelector('#pf-postal');

      // Clear previous errors
      [phone, street, city, postal].forEach(f => clearFieldError(f));

      if (phone.value.replace(/[\s\-]/g, '').length < 10) {
        showFieldError(phone, 'document.getElementById("pf-phone-err")', 'Please enter a valid phone number.');
        setError(phone, 'pf-phone-err', 'Please enter a valid phone number.');
        valid = false;
      }
      if (street.value.trim().length < 5) {
        setError(street, 'pf-street-err', 'Please enter your street address.');
        valid = false;
      }
      if (city.value.trim().length < 2) {
        setError(city, 'pf-city-err', 'Please enter your city.');
        valid = false;
      }
      if (!/^\d{4}$/.test(postal.value.trim())) {
        setError(postal, 'pf-postal-err', 'Please enter a valid 4-digit postal code.');
        valid = false;
      }

      if (!valid) return;

      // Loading state
      profileSubmitBtn.disabled = true;
      profileBtnLabel.textContent = 'Saving…';

      try {
        const formData = new FormData(profileForm);
        const response = await fetch('member_portal.php', {
          method: 'POST',
          body: formData,
        });
        const data = await response.json();

        if (data.ok) {
          if (typeof showToast === 'function') showToast(data.msg, 'success');
          // Clear all valid states
          [phone, street, city, postal].forEach(f => f.classList.add('valid'));
        } else {
          if (typeof showToast === 'function') showToast(data.msg, 'error');
        }
      } catch (err) {
        if (typeof showToast === 'function') showToast('Network error. Please try again.', 'error');
        console.error('Profile update error:', err);
      }

      profileSubmitBtn.disabled = false;
      profileBtnLabel.textContent = 'Save Changes';
    });
  }


  /* ─────────────────────────────────────
     4. PASSWORD FORM SUBMISSION
     fetch() POSTs to member_portal.php
     with action=change_password.
  ───────────────────────────────────── */
  const passwordForm      = document.getElementById('passwordForm');
  const passwordSubmitBtn = document.getElementById('passwordSubmitBtn');
  const passwordBtnLabel  = document.getElementById('passwordBtnLabel');

  if (passwordForm) {
    passwordForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const current   = passwordForm.querySelector('#pf-current');
      const newPw     = passwordForm.querySelector('#pf-newpw');
      const confirmPw = passwordForm.querySelector('#pf-confirmpw');

      // Clear errors
      [current, newPw, confirmPw].forEach(f => clearFieldError(f));

      let valid = true;

      if (current.value.length < 1) {
        setError(current, 'pf-current-err', 'Please enter your current password.');
        valid = false;
      }
      if (newPw.value.length < 8) {
        setError(newPw, 'pf-newpw-err', 'New password must be at least 8 characters.');
        valid = false;
      } else if (!/[A-Z]/.test(newPw.value)) {
        setError(newPw, 'pf-newpw-err', 'Must include at least one uppercase letter.');
        valid = false;
      } else if (!/[0-9]/.test(newPw.value)) {
        setError(newPw, 'pf-newpw-err', 'Must include at least one number.');
        valid = false;
      }
      if (newPw.value !== confirmPw.value) {
        setError(confirmPw, 'pf-confirmpw-err', 'Passwords do not match.');
        valid = false;
      }

      if (!valid) return;

      passwordSubmitBtn.disabled = true;
      passwordBtnLabel.textContent = 'Updating…';

      try {
        const formData = new FormData(passwordForm);
        const response = await fetch('member_portal.php', {
          method: 'POST',
          body: formData,
        });
        const data = await response.json();

        if (data.ok) {
          if (typeof showToast === 'function') showToast(data.msg, 'success');
          passwordForm.reset();
          [current, newPw, confirmPw].forEach(f => clearFieldError(f));
        } else {
          if (typeof showToast === 'function') showToast(data.msg, 'error');
        }
      } catch (err) {
        if (typeof showToast === 'function') showToast('Network error. Please try again.', 'error');
        console.error('Password change error:', err);
      }

      passwordSubmitBtn.disabled = false;
      passwordBtnLabel.textContent = 'Update Password';
    });
  }


  /* ─────────────────────────────────────
     5. SHOW / HIDE PASSWORD TOGGLES
     All .pf-pw-toggle buttons use a
     data-target attribute to know which
     input to toggle.
  ───────────────────────────────────── */
  document.querySelectorAll('.pf-pw-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId = btn.dataset.target;
      const input    = document.getElementById(targetId);
      if (!input) return;

      const isHidden = input.type === 'password';
      input.type = isHidden ? 'text' : 'password';

      btn.querySelector('i').className = isHidden ? 'ti ti-eye-off' : 'ti ti-eye';
      btn.setAttribute('aria-pressed', String(isHidden));
      btn.setAttribute('aria-label',   isHidden ? 'Hide password' : 'Show password');
    });
  });


  /* ─────────────────────────────────────
     6. RSVP BUTTONS
     Reuses the same pattern as rsvp.js —
     POSTs to rsvp_handler.php and shows
     a toast. We call the global submitRsvp()
     if rsvp.js is loaded, otherwise handle
     it inline here.
  ───────────────────────────────────── */
  document.querySelectorAll('.rsvp-btn:not([disabled])').forEach(btn => {
    btn.addEventListener('click', () => {
      const eventId = btn.dataset.eventId;
      if (!eventId) return;


      btn.disabled = true;
      btn.textContent = 'Saving…';

      fetch('rsvp_handler.php', {
        method: 'POST',
        body: new URLSearchParams({ event_id: eventId, action: 'rsvp', csrf: CSRF_TOKEN })
      })
      .then(r => r.json())
      .then(data => {
        if (data.ok) {
          if (typeof showToast === 'function') showToast(data.msg, 'success');
          btn.textContent = "RSVP’d ✓";
          btn.classList.add('ppae-rsvp--disabled');
        } else {
          if (typeof showToast === 'function') showToast(data.msg, 'error');
          btn.disabled = false;
          btn.textContent = 'RSVP';
        }
      })
      .catch(() => {
        if (typeof showToast === 'function') showToast('Network error. Please try again.', 'error');
        btn.disabled = false;
        btn.textContent = 'RSVP';
      });
    });
  });


  /* ─────────────────────────────────────
     7. CANCEL RSVP BUTTONS
  ───────────────────────────────────── */
  document.querySelectorAll('.pp-cancel-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      if (!confirm('Are you sure you want to cancel this RSVP?')) return;

      const eventId = btn.dataset.eventId;

      fetch('rsvp_handler.php', {
        method: 'POST',
        body: new URLSearchParams({ event_id: eventId, action: 'cancel', csrf: CSRF_TOKEN })
      })
      .then(r => r.json())
      .then(data => {
        if (data.ok) {
          if (typeof showToast === 'function') showToast(data.msg, 'success');
          btn.closest('tr')?.remove();
        } else {
          if (typeof showToast === 'function') showToast(data.msg, 'error');
        }
      })
      .catch(() => {
        if (typeof showToast === 'function') showToast('Network error. Please try again.', 'error');
      });
    });
  });


  /* ─────────────────────────────────────
     HELPERS: field error display
  ───────────────────────────────────── */

  /**
   * Show an error on a form field.
   * @param {HTMLElement} input
   * @param {string}      errId  – id of the error <span>
   * @param {string}      msg
   */
  function setError(input, errId, msg) {
    input.classList.add('error');
    input.classList.remove('valid');
    const el = document.getElementById(errId);
    if (el) el.textContent = msg;
  }

  /**
   * Clear the error state from a field.
   * @param {HTMLElement} input
   */
  function clearFieldError(input) {
    input.classList.remove('error', 'valid');
    const errId = input.getAttribute('aria-describedby');
    if (errId) {
      const el = document.getElementById(errId);
      if (el) el.textContent = '';
    }
  }

  // Unused duplicate — keeping function name to avoid reference errors
  function showFieldError(input) {
    input.classList.add('error');
  }

  // Clear error as the user types
  document.querySelectorAll('.pf-input').forEach(input => {
    input.addEventListener('input', () => {
      if (input.classList.contains('error')) clearFieldError(input);
    });
  });

}); // end DOMContentLoaded

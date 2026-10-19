/* ═══════════════════════════════════════
   js/volunteers.js
   PURPOSE: All interactivity for volunteers.php.

   SECTIONS:
   1. TAB SWITCHING     – v-tab click handler
   2. CATEGORY FILTER   – cat-pill click handler
   3. REGISTER MODAL    – open / close / submit
   4. CANCEL SHIFT      – cancel a scheduled shift
   5. LOG HOURS FORM    – validate & submit
   6. PROGRESS BAR      – animate when summary tab opens
   7. KEYBOARD NAV      – close modal with Escape key

   DEPENDS ON: toast.js (showToast function)
═══════════════════════════════════════ */
(function () {
  'use strict';

  /* ── Wait for DOM ───────────────────── */
  document.addEventListener('DOMContentLoaded', init);

  function init() {
    initTabs();
    initCategoryFilter();
    initModal();
    initCancelShift();
    initLogHoursForm();
    initKeyboardNav();
  }


  /* ════════════════════════════════════════
     1. TAB SWITCHING
     Clicking a .v-tab button shows the
     matching .tab-panel and hides the rest.
  ════════════════════════════════════════ */
  function initTabs() {
    var tabs   = document.querySelectorAll('.v-tab');
    var panels = document.querySelectorAll('.tab-panel');

    tabs.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var target = btn.getAttribute('data-tab');

        /* Update tab button states */
        tabs.forEach(function (t) {
          t.classList.remove('active');
          t.setAttribute('aria-selected', 'false');
        });
        btn.classList.add('active');
        btn.setAttribute('aria-selected', 'true');

        /* Show the correct panel */
        panels.forEach(function (panel) {
          panel.classList.remove('active');
        });
        var targetPanel = document.getElementById('tab-' + target);
        if (targetPanel) {
          targetPanel.classList.add('active');
        }

        /*
         * When the summary tab opens, trigger the
         * progress bar animation after a short delay
         * (so the CSS transition is visible).
         */
        if (target === 'summary') {
          animateProgressBar();
        }
      });
    });
  }


  /* ════════════════════════════════════════
     2. CATEGORY FILTER PILLS
     Clicking a .cat-pill shows only role
     cards that match the pill's data-cat.
     "all" shows every card.
  ════════════════════════════════════════ */
  function initCategoryFilter() {
    var pills   = document.querySelectorAll('.cat-pill');
    var cards   = document.querySelectorAll('.role-card');
    var noRoles = document.getElementById('noRoles');

    pills.forEach(function (pill) {
      pill.addEventListener('click', function () {
        var cat = pill.getAttribute('data-cat');

        /* Update pill active states */
        pills.forEach(function (p) {
          p.classList.remove('active');
          p.setAttribute('aria-pressed', 'false');
        });
        pill.classList.add('active');
        pill.setAttribute('aria-pressed', 'true');

        /* Show/hide role cards */
        var visible = 0;
        cards.forEach(function (card) {
          if (cat === 'all' || card.getAttribute('data-cat') === cat) {
            card.style.display = '';
            visible++;
          } else {
            card.style.display = 'none';
          }
        });

        /* Toggle the "no results" message */
        if (noRoles) {
          if (visible === 0) {
            noRoles.classList.add('visible');
          } else {
            noRoles.classList.remove('visible');
          }
        }
      });
    });
  }


  /* ════════════════════════════════════════
     3. REGISTER MODAL
     Open: clicking any .open-modal button.
     Close: clicking overlay, × button,
            or pressing Escape.
     Submit: clicking #submitRegister.
  ════════════════════════════════════════ */
  function initModal() {
    var overlay  = document.getElementById('registerModal');
    var closeBtn = document.getElementById('closeModal');
    var cancelBtn = document.getElementById('cancelModal');
    var submitBtn = document.getElementById('submitRegister');

    if (!overlay) return;

    /* Open modal when any "Register" card button is clicked */
    document.addEventListener('click', function (e) {
      var trigger = e.target.closest('.open-modal');
      if (!trigger) return;

      var roleTitle = trigger.getAttribute('data-role-title') || 'this role';
      var roleId    = trigger.getAttribute('data-role-id') || '';

      /* Set modal heading to the specific role */
      var heading = document.getElementById('modalRoleTitle');
      if (heading) heading.textContent = 'Register for: ' + roleTitle;

      /* Store role ID in the hidden input */
      var roleIdInput = document.getElementById('regRoleId');
      if (roleIdInput) roleIdInput.value = roleId;

      openModal();
    });

    /* Close button (×) */
    if (closeBtn) {
      closeBtn.addEventListener('click', closeModal);
    }
    /* Cancel button */
    if (cancelBtn) {
      cancelBtn.addEventListener('click', closeModal);
    }

    /* Click outside the modal box to close */
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) closeModal();
    });

    /* Submit registration */
    if (submitBtn) {
      submitBtn.addEventListener('click', handleRegisterSubmit);
    }

    /* ── Helper: open ─ */
    function openModal() {
      overlay.classList.add('open');
      overlay.setAttribute('aria-hidden', 'false');
      /* Focus the first input for accessibility */
      var firstInput = overlay.querySelector('input[type="text"]');
      if (firstInput) {
        setTimeout(function () { firstInput.focus(); }, 100);
      }
      /* Prevent body scroll */
      document.body.style.overflow = 'hidden';
    }

    /* ── Helper: close ─ */
    function closeModal() {
      overlay.classList.remove('open');
      overlay.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      clearModalErrors();
    }

    /* ── Handle submit ─ */
    function handleRegisterSubmit() {
      if (!validateModal()) return;

      /* Disable button to prevent double-submit */
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="ti ti-loader-2" aria-hidden="true"></i> Submitting…';

      /*
       * Simulate a network request (1.2s delay).
       * In a real app, replace this with a fetch() to a PHP endpoint
       * that inserts into the VOLUNTEER table.
       */
      setTimeout(function () {
        closeModal();
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="ti ti-send" aria-hidden="true"></i> Submit Registration';

        var roleTitle = document.getElementById('modalRoleTitle').textContent.replace('Register for: ', '');
        showToast('✓ Registration submitted for ' + roleTitle + '! We\'ll be in touch soon.', 'success');

        /* Reset form fields */
        document.getElementById('regName').value  = '';
        document.getElementById('regEmail').value = '';
        document.getElementById('regPhone').value = '';
        document.getElementById('regSkills').value = '';
        document.querySelectorAll('.avail-check input').forEach(function (cb) {
          cb.checked = false;
        });
      }, 1200);
    }

    /* ── Validate modal form ─ */
    function validateModal() {
      var valid = true;

      /* Name */
      var name = document.getElementById('regName');
      var nameErr = document.getElementById('regNameErr');
      if (name && name.value.trim().length < 2) {
        showFieldError(name, nameErr, 'Please enter your full name.');
        valid = false;
      } else {
        clearFieldError(name, nameErr);
      }

      /* Email */
      var email = document.getElementById('regEmail');
      var emailErr = document.getElementById('regEmailErr');
      var emailOk = email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim());
      if (!emailOk) {
        showFieldError(email, emailErr, 'Please enter a valid email address.');
        valid = false;
      } else {
        clearFieldError(email, emailErr);
      }

      /* Availability — at least one checkbox must be ticked */
      var checked = document.querySelectorAll('.avail-check input:checked');
      var availErr = document.getElementById('regAvailErr');
      if (checked.length === 0) {
        if (availErr) availErr.textContent = 'Please select at least one availability slot.';
        valid = false;
      } else {
        if (availErr) availErr.textContent = '';
      }

      return valid;
    }

    function clearModalErrors() {
      ['regNameErr', 'regEmailErr', 'regAvailErr'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.textContent = '';
      });
      ['regName', 'regEmail'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.classList.remove('error');
      });
    }
  }


  /* ════════════════════════════════════════
     4. CANCEL SHIFT
     Clicking "Can't make it" on a shift card
     removes it from the list with a toast.
  ════════════════════════════════════════ */
  function initCancelShift() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.btn-cancel-shift');
      if (!btn) return;

      var card = btn.closest('.shift-card');
      if (!card) return;

      var roleName = card.querySelector('.shift-role');
      var name = roleName ? roleName.textContent : 'your shift';

      /*
       * Confirm before cancelling — avoids accidental removal.
       * In a real app this would be a custom modal, not browser confirm().
       */
      if (!window.confirm('Cancel your shift for ' + name + '?')) return;

      /* Animate out */
      card.style.transition = 'opacity .3s, transform .3s';
      card.style.opacity    = '0';
      card.style.transform  = 'translateX(20px)';

      setTimeout(function () {
        card.remove();
        showToast('Shift cancelled. Please let the ministry leader know.', 'warn');
      }, 320);
    });
  }


  /* ════════════════════════════════════════
     5. LOG HOURS FORM
     Validates and "submits" the log hours form.
     Shows a success toast and clears the form.
  ════════════════════════════════════════ */
  function initLogHoursForm() {
    var submitBtn = document.getElementById('submitHours');
    if (!submitBtn) return;

    submitBtn.addEventListener('click', function () {
      if (!validateLogForm()) return;

      /* Disable while processing */
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="ti ti-loader-2" aria-hidden="true"></i> Saving…';

      /*
       * Simulate saving to VOLUNTEER_HOURS table.
       * Replace with fetch() to save_hours.php in production.
       */
      setTimeout(function () {
        var hours = document.getElementById('logHours').value;
        var role  = document.getElementById('logRole').value;

        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="ti ti-clock-plus" aria-hidden="true"></i> Submit Hours';

        /* Clear form */
        document.getElementById('logDate').value  = '';
        document.getElementById('logRole').value  = '';
        document.getElementById('logHours').value = '';
        document.getElementById('logNotes').value = '';

        showToast('✓ ' + hours + ' hours logged for ' + role + '. Thank you for serving!', 'success');
      }, 900);
    });
  }

  /* ── Validate the log hours form ─ */
  function validateLogForm() {
    var valid = true;

    /* Date */
    var date    = document.getElementById('logDate');
    var dateErr = document.getElementById('logDateErr');
    if (!date || !date.value) {
      showFieldError(date, dateErr, 'Please select the date of your shift.');
      valid = false;
    } else {
      clearFieldError(date, dateErr);
    }

    /* Role */
    var role    = document.getElementById('logRole');
    var roleErr = document.getElementById('logRoleErr');
    if (!role || !role.value) {
      showFieldError(role, roleErr, 'Please select a ministry role.');
      valid = false;
    } else {
      clearFieldError(role, roleErr);
    }

    /* Hours */
    var hours    = document.getElementById('logHours');
    var hoursErr = document.getElementById('logHoursErr');
    var hVal     = parseFloat(hours ? hours.value : 0);
    if (!hours || isNaN(hVal) || hVal < 0.5 || hVal > 24) {
      showFieldError(hours, hoursErr, 'Please enter a valid number of hours (0.5–24).');
      valid = false;
    } else {
      clearFieldError(hours, hoursErr);
    }

    return valid;
  }


  /* ════════════════════════════════════════
     6. PROGRESS BAR ANIMATION
     Called when the summary tab becomes
     active. Reads --pct from the PHP output
     and applies it after a short delay so
     the CSS transition runs visibly.
  ════════════════════════════════════════ */
  function animateProgressBar() {
    var fill = document.getElementById('progressFill');
    if (!fill) return;

    /*
     * PHP set style="--pct:0%" initially (so bar starts at 0).
     * We read the real percentage from the data-* attribute
     * written by PHP, then update the property.
     *
     * Because PHP printed --pct:0% in the inline style, we
     * use a data attribute on the element as the real value.
     * See: <div class="progress-fill" data-pct="<?= $hours_pct ?>%">
     * 
     * Fallback: read the percentage from a data attribute on the track.
     */
    var track = fill.closest('.progress-track');
    var pct   = track ? track.getAttribute('aria-valuenow') : null;
    var max   = track ? track.getAttribute('aria-valuemax') : null;

    if (pct !== null && max !== null) {
      var computed = Math.min(100, Math.round((parseFloat(pct) / parseFloat(max)) * 100));
      setTimeout(function () {
        fill.style.setProperty('--pct', computed + '%');
      }, 80);
    }
  }


  /* ════════════════════════════════════════
     7. KEYBOARD NAVIGATION
     Close the modal when Escape is pressed.
  ════════════════════════════════════════ */
  function initKeyboardNav() {
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        var overlay = document.getElementById('registerModal');
        if (overlay && overlay.classList.contains('open')) {
          overlay.classList.remove('open');
          overlay.setAttribute('aria-hidden', 'true');
          document.body.style.overflow = '';
        }
      }
    });
  }


  /* ════════════════════════════════════════
     UTILITY FUNCTIONS
  ════════════════════════════════════════ */

  /* Show red error text + red border on an input */
  function showFieldError(input, errorEl, msg) {
    if (input)   input.classList.add('error');
    if (errorEl) errorEl.textContent = msg;
  }

  /* Remove error styling */
  function clearFieldError(input, errorEl) {
    if (input)   input.classList.remove('error');
    if (errorEl) errorEl.textContent = '';
  }

  /*
   * showToast(message, type)
   * type: 'success' | 'error' | 'warn'
   * Defined in toast.js — already loaded by footer.php.
   * We check it exists before calling to avoid errors.
   */
  function showToast(message, type) {
    if (typeof window.showToast === 'function') {
      window.showToast(message, type || 'success');
    } else {
      /* Fallback if toast.js didn't load */
      console.log('[Toast]', message);
    }
  }

}());

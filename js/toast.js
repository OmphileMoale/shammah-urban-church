/* ═══════════════════════════════════════
   js/toast.js
   PURPOSE: A reusable notification system.
   Shows small popup messages at the top-right
   corner of the screen after actions like RSVP.
   
   HOW TO USE (from any other JS file):
     showToast('Your message here');
     showToast('Something went wrong', 'error');
     showToast('Please log in first', 'warn');
   
   The function is global (no import needed)
   because this file loads before main.js and rsvp.js.
═══════════════════════════════════════ */

/**
 * showToast – display a notification popup
 *
 * @param {string} message  – the text to show
 * @param {string} type     – 'success' | 'error' | 'warn'
 * @param {number} duration – milliseconds before it disappears (default 4000)
 */
function showToast(message, type = 'success', duration = 4000) {

  // 1. Find the container div (defined in includes/header.php)
  const container = document.getElementById('toast-container');

  // 2. Map type → icon class (Tabler icons)
  const icons = {
    success: 'ti-circle-check',
    error:   'ti-circle-x',
    warn:    'ti-alert-circle',
  };

  // 3. Create the toast element
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.setAttribute('role', 'status'); // screen readers announce it

  // innerHTML sets the icon + message text
  // We use template literals (backtick strings) for readability
  toast.innerHTML = `
    <i class="ti ${icons[type] || icons.success}" aria-hidden="true"></i>
    <span>${message}</span>
  `;

  // 4. Add it to the container (it appears on screen)
  container.appendChild(toast);

  // 5. After `duration` ms, fade it out then remove it from the DOM
  setTimeout(() => {
    // Play the CSS animation in reverse (creates a fade-out)
    toast.style.animation = 'toastIn .3s ease reverse';
    // Wait for the animation to finish (280ms) then remove
    setTimeout(() => toast.remove(), 280);
  }, duration);
}

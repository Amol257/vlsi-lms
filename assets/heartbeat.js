/**
 * VLSI LMS : Student Session Heartbeat Client
 * Enforces single active device concurrency.
 */
(function() {
  let intervalId = null;
  let isSending = false;

  async function sendHeartbeat() {
    if (isSending) return;
    isSending = true;

    try {
      const csrfMeta = document.querySelector('meta[name="csrf-token"]');
      const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

      const res = await fetch('/api/heartbeat.php', {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        credentials: 'same-origin'
      });

      if (res.status === 401) {
        clearInterval(intervalId);
        window.location.href = '/login.php?reason=other-device';
      }
    } catch (err) {
      // Network hiccup; will retry on next cycle
    } finally {
      isSending = false;
    }
  }

  // Periodic heartbeat every 30 seconds
  intervalId = setInterval(sendHeartbeat, 30000);

  // Resume heartbeat immediately when user switches back to tab
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      sendHeartbeat();
    }
  });

  // Initial trigger
  setTimeout(sendHeartbeat, 5000);
})();

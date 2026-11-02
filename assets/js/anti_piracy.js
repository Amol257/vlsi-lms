/**
 * VLSI LMS : Custom Chrome and Browser Anti-Piracy and Screen-Capture Engine
 * Protects video lectures against screen recording, capture tools, and DOM tampering.
 * Fully self-contained with prototype integrity sealing and fail-closed state.
 */

(function (window, document) {
  'use strict';

  // 1. Snapshot and Seal Core Native Prototypes to Prevent Monkey-Patching
  const _origAddEventListener = EventTarget.prototype.addEventListener;
  const _origRemoveEventListener = EventTarget.prototype.removeEventListener;
  const _origFetch = window.fetch ? window.fetch.bind(window) : null;
  const _origSendBeacon = navigator.sendBeacon ? navigator.sendBeacon.bind(navigator) : null;
  const _origSetTimeout = window.setTimeout.bind(window);
  const _origClearTimeout = window.clearTimeout.bind(window);
  const _origSetInterval = window.setInterval.bind(window);
  const _origClearInterval = window.clearInterval.bind(window);

  try {
    if (Object.freeze) {
      Object.freeze(EventTarget.prototype);
      Object.freeze(Document.prototype);
    }
  } catch (e) {
    // Continue in restrictive environments
  }

  // 2. Configuration State
  const config = {
    videoId: 0,
    studentEmail: '',
    clientIp: '',
    csrfToken: '',
    maxViolations: 5,
    blurGraceMs: 500, // Short grace period to absorb accidental desktop clicks
    heartbeatIntervalMs: 25000
  };

  let violationCount = 0;
  let isBlackedOut = false;
  let blurTimer = null;
  let videoEl = null;
  let containerEl = null;
  let overlayEl = null;
  let watermarkEl = null;
  let watermarkCanvas = null;
  let watermarkCtx = null;
  let animFrameId = null;

  // 3. Dispatch Security Incident Beacon to Backend
  function sendSecurityEvent(type, details) {
    const payload = JSON.stringify({
      video_id: config.videoId,
      violation_type: type,
      details: details || '',
      csrf_token: config.csrfToken
    });

    if (_origSendBeacon) {
      const blob = new Blob([payload], { type: 'application/json' });
      _origSendBeacon('/api/security_violation.php', blob);
    } else if (_origFetch) {
      _origFetch('/api/security_violation.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': config.csrfToken
        },
        body: payload,
        credentials: 'same-origin',
        keepalive: true
      }).catch(function () {});
    }
  }

  // 4. Overwrite Clipboard to Neutralize Screenshot Buffers
  function purgeClipboard() {
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText('VLSI Insights Course Content is Protected. Screen capture is prohibited.').catch(function () {});
      }
    } catch (e) {}
  }

  // 5. Activate Screen Blackout
  function triggerBlackout(reason, severity) {
    isBlackedOut = true;

    // Instantly freeze video playback
    if (videoEl && !videoEl.paused) {
      try {
        videoEl.pause();
      } catch (e) {}
    }

    // Display pitch-black overlay
    if (overlayEl) {
      overlayEl.style.display = 'flex';
      const reasonEl = overlayEl.querySelector('.sec-reason-text');
      if (reasonEl) {
        reasonEl.textContent = reason;
      }
    }

    purgeClipboard();

    violationCount++;
    sendSecurityEvent(severity || 'window_blur', reason);

    if (violationCount >= config.maxViolations) {
      if (overlayEl) {
        const titleEl = overlayEl.querySelector('.sec-title');
        const reasonEl = overlayEl.querySelector('.sec-reason-text');
        if (titleEl) titleEl.textContent = 'ACCOUNT ACCESS SUSPENDED';
        if (reasonEl) {
          reasonEl.textContent = 'Repeated screen recording or capture violations detected. Your session has been revoked. Contact admin@vlsiinsights.com.';
        }
      }
      _origSetTimeout(function () {
        window.location.href = '/login.php?reason=security_violation';
      }, 3000);
    }
  }

  // 6. Restore Playback when Security Conditions Clear
  function releaseBlackout() {
    if (violationCount >= config.maxViolations) {
      return; // Permalocked
    }
    isBlackedOut = false;
    if (overlayEl) {
      overlayEl.style.display = 'none';
    }
  }

  // 7. Dynamic Canvas-Fused Watermark Renderer
  function initWatermarkCanvas() {
    if (!containerEl) return;

    watermarkCanvas = document.createElement('canvas');
    watermarkCanvas.id = 'securityWatermarkCanvas';
    watermarkCanvas.style.position = 'absolute';
    watermarkCanvas.style.inset = '0';
    watermarkCanvas.style.width = '100%';
    watermarkCanvas.style.height = '100%';
    watermarkCanvas.style.pointerEvents = 'none';
    watermarkCanvas.style.zIndex = '12';
    containerEl.appendChild(watermarkCanvas);

    watermarkCtx = watermarkCanvas.getContext('2d');
    let x = 40;
    let y = 60;
    let dx = 0.5;
    let dy = 0.3;

    function renderFrame() {
      if (!watermarkCanvas || !watermarkCtx || !containerEl) return;

      const rect = containerEl.getBoundingClientRect();
      if (watermarkCanvas.width !== rect.width || watermarkCanvas.height !== rect.height) {
        watermarkCanvas.width = rect.width;
        watermarkCanvas.height = rect.height;
      }

      watermarkCtx.clearRect(0, 0, watermarkCanvas.width, watermarkCanvas.height);

      if (isBlackedOut) {
        // Draw solid black frame in canvas buffer
        watermarkCtx.fillStyle = '#000000';
        watermarkCtx.fillRect(0, 0, watermarkCanvas.width, watermarkCanvas.height);
      } else {
        // Render bouncing semi-transparent dynamic watermark text
        x += dx;
        y += dy;
        if (x <= 20 || x >= watermarkCanvas.width - 260) dx = -dx;
        if (y <= 30 || y >= watermarkCanvas.height - 30) dy = -dy;

        const timeStr = new Date().toUTCString();
        const text = config.studentEmail + ' | ' + config.clientIp + ' | ' + timeStr;

        watermarkCtx.font = '600 12px monospace';
        watermarkCtx.fillStyle = 'rgba(255, 255, 255, 0.28)';
        watermarkCtx.shadowColor = 'rgba(0, 0, 0, 0.7)';
        watermarkCtx.shadowBlur = 3;
        watermarkCtx.fillText(text, x, y);
      }

      animFrameId = requestAnimationFrame(renderFrame);
    }

    animFrameId = requestAnimationFrame(renderFrame);
  }

  // 8. Hook Screen Capture and Recording APIs (getDisplayMedia)
  function trapScreenCaptureApis() {
    if (navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) {
      const origGetDisplayMedia = navigator.mediaDevices.getDisplayMedia.bind(navigator.mediaDevices);
      navigator.mediaDevices.getDisplayMedia = function () {
        triggerBlackout('Screen recording or screen share attempt was intercepted.', 'screen_share_attempt');
        return Promise.reject(new DOMException('Screen capture is prohibited by policy.', 'NotAllowedError'));
      };
    }
  }

  // 9. Hotkey and Screenshot Key Interception
  function setupKeyboardGuards() {
    window.addEventListener('keydown', function (e) {
      // PrintScreen Key
      if (e.key === 'PrintScreen' || e.keyCode === 44) {
        e.preventDefault();
        triggerBlackout('Screenshot capture intercepted.', 'print_screen_key');
        return false;
      }

      // Windows Snipping Tool (Win + Shift + S), Mac Grab (Cmd + Shift + 3/4/5)
      if ((e.shiftKey && (e.metaKey || e.altKey) && (e.key === 's' || e.key === 'S')) ||
          (e.metaKey && e.shiftKey && (e.key === '3' || e.key === '4' || e.key === '5'))) {
        e.preventDefault();
        triggerBlackout('Screenshot utility shortcut intercepted.', 'screenshot_shortcut');
        return false;
      }

      // Developer Tools shortcuts: F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+Shift+C
      if (e.key === 'F12' ||
          (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'i' || e.key === 'J' || e.key === 'j' || e.key === 'C' || e.key === 'c')) ||
          (e.ctrlKey && (e.key === 'u' || e.key === 'U' || e.key === 's' || e.key === 'S' || e.key === 'p' || e.key === 'P'))) {
        e.preventDefault();
        triggerBlackout('Unauthorized keyboard shortcut intercepted.', 'devtools_opened');
        return false;
      }
    }, true);

    // Clear clipboard when window is unfocused
    window.addEventListener('keyup', function (e) {
      if (e.key === 'PrintScreen' || e.keyCode === 44) {
        purgeClipboard();
      }
    }, true);
  }

  // 10. Focus and Tab Visibility Guards
  function setupFocusGuards() {
    window.addEventListener('blur', function () {
      if (blurTimer) _origClearTimeout(blurTimer);
      blurTimer = _origSetTimeout(function () {
        triggerBlackout('Playback paused: Active window focus is required to view video.', 'window_blur');
      }, config.blurGraceMs);
    });

    window.addEventListener('focus', function () {
      if (blurTimer) {
        _origClearTimeout(blurTimer);
        blurTimer = null;
      }
      releaseBlackout();
    });

    document.addEventListener('visibilitychange', function () {
      if (document.hidden) {
        triggerBlackout('Playback paused: Tab was hidden or switched.', 'tab_hidden');
      } else {
        releaseBlackout();
      }
    });
  }

  // 11. DevTools Detection via Timing Benchmark and Dimension Deltas
  function setupDevToolsMonitor() {
    function checkDevToolsDimensions() {
      const widthThreshold = window.outerWidth - window.innerWidth > 160;
      const heightThreshold = window.outerHeight - window.innerHeight > 160;
      if (widthThreshold || heightThreshold) {
        triggerBlackout('Developer tools dock detected.', 'devtools_opened');
      }
    }

    _origSetInterval(checkDevToolsDimensions, 2000);

    // Active timing benchmark trap
    function debuggerTimingTrap() {
      const start = performance.now();
      // Benchmark execution latency
      (function () {}).constructor('debugger')();
      const elapsed = performance.now() - start;
      if (elapsed > 100) {
        triggerBlackout('Script inspection or debugger detected.', 'debugger_trap');
      }
    }

    _origSetInterval(debuggerTimingTrap, 3000);
  }

  // 12. DOM MutationObserver Tamper Defense
  function setupMutationDefense() {
    if (!window.MutationObserver) return;

    const observer = new MutationObserver(function (mutations) {
      for (let i = 0; i < mutations.length; i++) {
        const m = mutations[i];

        // Check if watermark or overlay was removed
        if (m.removedNodes && m.removedNodes.length > 0) {
          for (let j = 0; j < m.removedNodes.length; j++) {
            const node = m.removedNodes[j];
            if (node === overlayEl || node === watermarkCanvas || node === watermarkEl) {
              triggerBlackout('Security display component was removed.', 'dom_tampering');
              if (videoEl) videoEl.src = '';
              return;
            }
          }
        }

        // Check if inline style was altered to hide security components
        if (m.type === 'attributes' && m.attributeName === 'style') {
          const target = m.target;
          if (target === watermarkCanvas || target === watermarkEl) {
            const style = window.getComputedStyle(target);
            if (style.display === 'none' || style.visibility === 'hidden' || parseFloat(style.opacity) < 0.1) {
              triggerBlackout('Watermark component styling was altered.', 'dom_tampering');
              if (videoEl) videoEl.src = '';
              return;
            }
          }
        }
      }
    });

    if (containerEl) {
      observer.observe(containerEl, {
        attributes: true,
        childList: true,
        subtree: true,
        attributeFilter: ['style', 'class', 'hidden']
      });
    }
  }

  // 13. Public Initializer
  window.VLSI_AntiPiracy = {
    init: function (opts) {
      config.videoId = opts.videoId || 0;
      config.studentEmail = opts.studentEmail || 'Student';
      config.clientIp = opts.clientIp || '';
      config.csrfToken = opts.csrfToken || '';

      videoEl = document.getElementById(opts.videoElementId || 'lmsVideo');
      containerEl = document.getElementById(opts.containerElementId || 'playerContainer');
      overlayEl = document.getElementById(opts.overlayElementId || 'securityBlackoutOverlay');
      watermarkEl = document.getElementById(opts.watermarkElementId || 'watermark');

      trapScreenCaptureApis();
      setupKeyboardGuards();
      setupFocusGuards();
      setupDevToolsMonitor();
      initWatermarkCanvas();
      setupMutationDefense();
    },
    destroy: function () {
      if (animFrameId) cancelAnimationFrame(animFrameId);
      if (blurTimer) _origClearTimeout(blurTimer);
    }
  };

})(window, document);

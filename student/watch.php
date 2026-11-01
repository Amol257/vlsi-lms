<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/video_helper.php';

$student = require_role(['student', 'admin', 'teacher']);
$pdo = get_db();

$video_id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$video = null;
$progress = null;
$program = null;
$playlist_url = '';
$all_videos = [];

if ($pdo && $video_id > 0) {
    // 1. Shared access check: Enforces admin/teacher allowance, video approved status, trial or active enrollment
    $access = lms_check_video_access($student, $video_id, $pdo);
    if ($access['allowed'] && !empty($access['video'])) {
        $video = $access['video'];
        $program = [
            'title' => $video['program_title'],
            'slug' => $video['program_slug']
        ];

        // Check previous playback position
        try {
            $prog_stmt = $pdo->prepare("SELECT position_sec, completed FROM video_progress WHERE user_id = ? AND video_id = ? LIMIT 1");
            $prog_stmt->execute([$student['id'], $video_id]);
            $progress = $prog_stmt->fetch();

            // Determine if student has full program enrollment to filter curriculum sidebar
            $is_enrolled = false;
            if ($student['role'] === 'admin' || $student['role'] === 'teacher') {
                $is_enrolled = true;
            } else {
                $enr_check = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND program_id = ? AND status = 'active' AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1");
                $enr_check->execute([$student['id'], $video['program_id']]);
                if ($enr_check->fetch()) {
                    $is_enrolled = true;
                }
            }

            // Curriculum sidebar: Enrolled users see all approved videos; non-enrolled users see only trial previews
            if ($is_enrolled) {
                $list_stmt = $pdo->prepare("SELECT id, title, duration_sec, is_trial FROM videos WHERE program_id = ? AND status = 'approved' ORDER BY id ASC");
                $list_stmt->execute([$video['program_id']]);
            } else {
                $list_stmt = $pdo->prepare("SELECT id, title, duration_sec, is_trial FROM videos WHERE program_id = ? AND status = 'approved' AND is_trial = 1 ORDER BY id ASC");
                $list_stmt->execute([$video['program_id']]);
            }
            $all_videos = $list_stmt->fetchAll();

            $playlist_url = "/api/video_playlist.php?id=" . $video_id;
        } catch (PDOException $e) {
            error_log("Watch page fetch error: " . $e->getMessage());
        }
    }
}

// Clean 404 response if video does not exist or access is denied: no demo or fallback data in production paths
if (!$video) {
    http_response_code(404);
    ?>
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Video Not Found - VLSI LMS</title>
      <link rel="stylesheet" href="/assets/style.css">
    </head>
    <body class="role-student">
      <div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;">
        <div class="card" style="max-width: 480px; text-align: center; padding: 32px;">
          <h2 style="margin-bottom: 12px;">404: Video Not Found</h2>
          <p style="color: var(--muted); margin-bottom: 24px;">The requested video lecture does not exist, has been unpublished, or is unavailable.</p>
          <a href="/student/index.php" class="btn btn-primary">Return to Dashboard</a>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

$client_ip = get_client_ip();
$resume_pos = !empty($progress['position_sec']) ? (int)$progress['position_sec'] : 0;
$hidden_pause_sec = (int)lms_config('player.hidden_pause_seconds', 60);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<title><?= h($video['title']) ?> - VLSI LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/assets/style.css">
<script src="/assets/vendor/hls.min.js" integrity="sha384-9v3HcdYrO3D+OPDTjZ40RXocgE4GtXVCd3/mCS62JsM93JXgI1afJVuwjFvsu6ni" crossorigin="anonymous"></script>
<style>
.video-stage-wrap {
  position: relative;
  width: 100%;
  background: #000000;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 8px 24px rgba(0,0,0,0.3);
}
.video-player {
  width: 100%;
  max-height: 580px;
  display: block;
  background: #000;
}
/* Moving Security Watermark */
.security-watermark {
  position: absolute;
  pointer-events: none;
  color: rgba(255, 255, 255, 0.35);
  font-family: monospace;
  font-size: 13px;
  font-weight: 600;
  text-shadow: 1px 1px 2px rgba(0,0,0,0.8);
  padding: 4px 8px;
  user-select: none;
  z-index: 10;
  transition: top 1s ease-in-out, left 1s ease-in-out;
}
.player-error-overlay {
  display: none;
  position: absolute;
  inset: 0;
  background: rgba(15, 23, 42, 0.92);
  z-index: 20;
  color: #fff;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  padding: 24px;
  text-align: center;
}
</style>
</head>
<body class="role-student">
<div class="shell">
  <aside class="sidebar">
    <a class="brand" href="/student/index.php">
      <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/></svg></span>
      <span><b>VLSI LMS</b><small>Student Classroom</small></span>
    </a>
    <nav>
      <a href="/student/index.php">
        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
        <span>My Batches</span>
      </a>
      <a href="/student/programs.php">
        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        <span>Programs</span>
      </a>
      <a href="/student/webinars.php">
        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 10l5-5v14l-5-5M4 6h11a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"/></svg>
        <span>Webinars</span>
      </a>
    </nav>
    <div class="sidebar-footer">
      <a href="/logout.php" style="display: flex; align-items: center; gap: 8px; color: #f87171; font-weight: 500;">
        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span>Sign Out</span>
      </a>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-btn"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
      <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
        <h1><?= h($video['title']) ?></h1>
        <a href="/student/index.php" class="btn btn-sm">Back to Dashboard</a>
      </div>
    </header>

    <main class="content">
      <div class="grid-side align-start">
        <!-- VIDEO PLAYER MAIN CONTAINER -->
        <div>
          <div class="video-stage-wrap" id="playerContainer">
            <video id="lmsVideo" class="video-player" controls playsinline></video>
            <!-- Watermark Overlay -->
            <div id="watermark" class="security-watermark">
              <?= h($student['email']) ?> | <?= h($client_ip) ?> | <span id="watermarkClock"></span>
            </div>
            <!-- Friendly Player Error Overlay -->
            <div id="playerErrorOverlay" class="player-error-overlay">
              <p id="playerErrorText" style="font-size: 15px; margin-bottom: 16px; color: #fca5a5; max-width: 460px; line-height: 1.5;"></p>
              <button type="button" class="btn btn-sm btn-primary" id="btnReloadPlayer">Reload Player</button>
            </div>
          </div>

          <div class="card" style="margin-top: 20px;">
            <div class="card-head">
              <div>
                <h2 style="margin: 0 0 4px 0; font-size: 20px;"><?= h($video['title']) ?></h2>
                <span class="small muted"><?= h($video['program_title'] ?? 'VLSI Masterclass') ?></span>
              </div>
              <div id="statusIndicator">
                <?php if (!empty($progress['completed'])): ?>
                  <span class="badge badge-green">Watched</span>
                <?php else: ?>
                  <span class="badge badge-blue">In Progress</span>
                <?php endif; ?>
              </div>
            </div>

            <div class="prose" style="margin-top: 14px; color: var(--ink-2); line-height: 1.6;">
              <?= nl2br(h($video['description'] ?? '')) ?>
            </div>
          </div>
        </div>

        <!-- PROGRAM PLAYLIST SIDEBAR -->
        <div>
          <div class="card flush">
            <div class="card-head pad">
              <h3 style="margin: 0; font-size: 16px;">Program Curriculum</h3>
            </div>
            <ul class="lecture-list compact">
              <?php $i = 1; foreach ($all_videos as $item): ?>
                <?php $is_active = ((int)$item['id'] === (int)$video['id']); ?>
                <li class="<?= $is_active ? 'current' : '' ?>">
                  <a href="/student/watch.php?id=<?= (int)$item['id'] ?>">
                    <span class="ln"><?= $i++ ?></span>
                    <span class="lt">
                      <b><?= h($item['title']) ?></b>
                      <?php if (!empty($item['is_trial'])): ?>
                        <span class="badge badge-blue" style="font-size: 10px; padding: 1px 5px;">Free Preview</span>
                      <?php endif; ?>
                    </span>
                    <span class="lm"><?= round(((int)$item['duration_sec']) / 60) ?>m</span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<script src="/assets/app.js"></script>
<script src="/assets/heartbeat.js"></script>

<script>
// Pluggable VideoProvider Interface (Allows Drop-in Hardware DRM later)
const VideoProvider = {
  type: 'hls_aes128',
  hlsInstance: null,
  networkRetryCount: 0,
  maxNetworkRetries: 3,
  retryDelayMs: 1500,
  hasRecovered403: false,
  isReloading403: false,

  showPlayerError(message) {
    const errorOverlay = document.getElementById('playerErrorOverlay');
    const errorText = document.getElementById('playerErrorText');
    if (errorOverlay && errorText) {
      errorText.textContent = message;
      errorOverlay.style.display = 'flex';
    } else {
      alert(message);
    }
  },

  hidePlayerError() {
    const errorOverlay = document.getElementById('playerErrorOverlay');
    if (errorOverlay) {
      errorOverlay.style.display = 'none';
    }
  },

  async handle403Recovery(videoEl, sourceUrl) {
    if (this.hasRecovered403 || this.isReloading403) {
      this.showPlayerError('Playback authorization expired or access was revoked. Please refresh the page or check your program enrollment.');
      if (this.hlsInstance) {
        this.hlsInstance.destroy();
      }
      return;
    }

    this.isReloading403 = true;
    this.hasRecovered403 = true;
    const resumeTime = Math.max(0, videoEl.currentTime || 0);

    try {
      const sep = sourceUrl.includes('?') ? '&' : '?';
      const refreshUrl = sourceUrl + sep + '_r=' + Date.now();
      const res = await fetch(refreshUrl, { credentials: 'same-origin' });
      if (!res.ok) {
        throw new Error('Failed to refresh playlist: HTTP ' + res.status);
      }

      if (this.hlsInstance) {
        this.hlsInstance.destroy();
      }

      this.init(videoEl, refreshUrl, resumeTime);
      this.isReloading403 = false;
    } catch (e) {
      this.isReloading403 = false;
      this.showPlayerError('Unable to renew playback session. Please sign in again or reload the page.');
    }
  },

  init(videoEl, sourceUrl, startSeconds) {
    this.hidePlayerError();
    if (Hls.isSupported()) {
      this.hlsInstance = new Hls({
        debug: false,
        xhrSetup: function(xhr) {
          xhr.withCredentials = true;
        }
      });

      this.hlsInstance.loadSource(sourceUrl);
      this.hlsInstance.attachMedia(videoEl);

      this.hlsInstance.on(Hls.Events.MANIFEST_PARSED, () => {
        this.networkRetryCount = 0;
        if (startSeconds > 5) {
          videoEl.currentTime = startSeconds;
        }
      });

      this.hlsInstance.on(Hls.Events.ERROR, (event, data) => {
        const httpStatus = data.response?.code || data.response?.status;

        // Displaced session (HTTP 401) immediately halts playback and redirects
        if (httpStatus === 401) {
          if (this.hlsInstance) {
            this.hlsInstance.destroy();
          }
          window.location.href = '/login.php?reason=other-device';
          return;
        }

        // Detect HTTP 403 on key or segment request
        const is403 = (httpStatus === 403) || 
                      (data.details === Hls.ErrorDetails.KEY_LOAD_ERROR && httpStatus === 403) ||
                      (data.details === Hls.ErrorDetails.FRAG_LOAD_ERROR && httpStatus === 403);

        if (is403) {
          this.handle403Recovery(videoEl, sourceUrl);
          return;
        }

        if (data.fatal) {
          if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
            if (this.networkRetryCount < this.maxNetworkRetries) {
              this.networkRetryCount++;
              setTimeout(() => {
                if (this.hlsInstance) {
                  this.hlsInstance.startLoad();
                }
              }, this.retryDelayMs);
            } else {
              this.showPlayerError('A network error occurred while streaming video segments. Please check your internet connection.');
              if (this.hlsInstance) {
                this.hlsInstance.destroy();
              }
            }
          } else {
            this.showPlayerError('A playback error occurred. Please try reloading the player.');
            if (this.hlsInstance) {
              this.hlsInstance.destroy();
            }
          }
        }
      });
    } else if (videoEl.canPlayType('application/vnd.apple.mpegurl')) {
      videoEl.src = sourceUrl;
      videoEl.addEventListener('loadedmetadata', () => {
        if (startSeconds > 5) {
          videoEl.currentTime = startSeconds;
        }
      });
      videoEl.addEventListener('error', () => {
        this.showPlayerError('Error loading video stream on your browser.');
      });
    }
  },

  destroy() {
    if (this.hlsInstance) {
      this.hlsInstance.destroy();
    }
  }
};

const videoEl = document.getElementById('lmsVideo');
const playlistUrl = <?= json_encode($playlist_url) ?>;
const resumeSeconds = <?= (int)$resume_pos ?>;
const videoId = <?= (int)$video['id'] ?>;
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

// Attach reload listener for error overlay without inline onclick
document.getElementById('btnReloadPlayer')?.addEventListener('click', () => {
  location.reload();
});

// Initialize Player via Provider Interface
VideoProvider.init(videoEl, playlistUrl, resumeSeconds);

// Watermark Jitter and Clock
function updateWatermark() {
  const wm = document.getElementById('watermark');
  const clock = document.getElementById('watermarkClock');
  const now = new Date();
  clock.textContent = now.toLocaleTimeString();

  const topPercent = Math.floor(Math.random() * 70) + 10;
  const leftPercent = Math.floor(Math.random() * 50) + 10;
  wm.style.top = topPercent + '%';
  wm.style.left = leftPercent + '%';
}
setInterval(updateWatermark, 20000);
updateWatermark();

// Anti-piracy: Tab hidden timer (pause only when tab has been hidden for configured seconds)
const hiddenPauseSeconds = <?= (int)$hidden_pause_sec ?>;
let hiddenTimer = null;

document.addEventListener('visibilitychange', () => {
  if (document.hidden) {
    // Send latest progress snapshot when tab is hidden
    sendProgress(Math.floor(videoEl.currentTime || 0), Math.floor(videoEl.duration || 0), false);

    if (hiddenPauseSeconds > 0) {
      hiddenTimer = setTimeout(() => {
        if (!videoEl.paused) {
          videoEl.pause();
        }
      }, hiddenPauseSeconds * 1000);
    }
  } else {
    if (hiddenTimer) {
      clearTimeout(hiddenTimer);
      hiddenTimer = null;
    }
  }
});

// Periodic progress recording (every 10 seconds)
let lastProgressTime = 0;
let consecutivePlaySeconds = 0;

videoEl.addEventListener('timeupdate', () => {
  const current = Math.floor(videoEl.currentTime || 0);

  // Reset 403 recovery allowance after 120 seconds of continuous playback
  if (!videoEl.paused) {
    consecutivePlaySeconds++;
    if (consecutivePlaySeconds >= 120) {
      VideoProvider.hasRecovered403 = false;
      consecutivePlaySeconds = 0;
    }
  }

  if (Math.abs(current - lastProgressTime) >= 10) {
    lastProgressTime = current;
    sendProgress(current, Math.floor(videoEl.duration || 0), false);
  }
});

// Reset lastProgressTime when seeking backwards
videoEl.addEventListener('seeking', () => {
  if (videoEl.currentTime < lastProgressTime) {
    lastProgressTime = Math.floor(videoEl.currentTime);
  }
});

// Progress on pause
videoEl.addEventListener('pause', () => {
  sendProgress(Math.floor(videoEl.currentTime || 0), Math.floor(videoEl.duration || 0), false);
});

// Progress on video completed
videoEl.addEventListener('ended', () => {
  sendProgress(Math.floor(videoEl.duration || 0), Math.floor(videoEl.duration || 0), true);
  const statusEl = document.getElementById('statusIndicator');
  if (statusEl) {
    statusEl.innerHTML = '<span class="badge badge-green">Watched</span>';
  }
});

// Asynchronous Progress Dispatcher with CSRF protection
async function sendProgress(pos, dur, completed) {
  try {
    const fd = new FormData();
    fd.append('video_id', videoId);
    fd.append('position_sec', pos);
    fd.append('duration_sec', dur);
    fd.append('csrf_token', csrfToken);
    if (completed) fd.append('completed', 1);

    await fetch('/api/video_progress.php', {
      method: 'POST',
      body: fd,
      headers: {
        'X-CSRF-Token': csrfToken
      },
      credentials: 'same-origin'
    });
  } catch (err) {
    // Silently continue
  }
}

// Beacon dispatch on window pagehide / unload
window.addEventListener('pagehide', () => {
  const fd = new FormData();
  fd.append('video_id', videoId);
  fd.append('position_sec', Math.floor(videoEl.currentTime || 0));
  fd.append('duration_sec', Math.floor(videoEl.duration || 0));
  fd.append('csrf_token', csrfToken);
  navigator.sendBeacon('/api/video_progress.php', fd);
});
</script>
</body>
</html>

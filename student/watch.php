<?php
require_once __DIR__ . '/../db.php';
$student = require_role(['student', 'admin', 'teacher']);
$pdo = get_db();

$video_id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$video = null;
$progress = null;
$program = null;
$playlist_url = '';
$all_videos = [];

if ($pdo && $video_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT v.*, p.title as program_title, p.slug as program_slug 
            FROM videos v 
            JOIN programs p ON v.program_id = p.id 
            WHERE v.id = ? LIMIT 1");
        $stmt->execute([$video_id]);
        $video = $stmt->fetch();

        if ($video) {
            $program = [
                'title' => $video['program_title'],
                'slug' => $video['program_slug']
            ];

            // Check previous playback position
            $prog_stmt = $pdo->prepare("SELECT position_sec, completed FROM video_progress WHERE user_id = ? AND video_id = ? LIMIT 1");
            $prog_stmt->execute([$student['id'], $video_id]);
            $progress = $prog_stmt->fetch();

            // Load other videos in same program
            $list_stmt = $pdo->prepare("SELECT id, title, duration_sec, is_trial FROM videos WHERE program_id = ? AND status = 'approved' ORDER BY id ASC");
            $list_stmt->execute([$video['program_id']]);
            $all_videos = $list_stmt->fetchAll();

            $playlist_url = "/api/video_playlist.php?id=" . $video_id;
        }
    } catch (PDOException $e) {
        error_log("Watch page load error: " . $e->getMessage());
    }
}

// Fallback sample if no DB video selected yet
if (!$video) {
    $video = [
        'id' => 1,
        'title' => 'Introduction to Physical Design and Tapeout Flow',
        'description' => 'Comprehensive walkthrough of netlist to GDSII synthesis, floorplan partitioning, and timing signoff constraints.',
        'duration_sec' => 3600,
        'is_trial' => 1,
        'program_title' => 'Physical Design Masterclass'
    ];
    $all_videos = [$video];
    $playlist_url = "/api/video_playlist.php?id=1";
}

$client_ip = get_client_ip();
$resume_pos = !empty($progress['position_sec']) ? (int)$progress['position_sec'] : 0;
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
      <a href="/student/player.php" class="on">
        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        <span>Lecture Player</span>
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

  init(videoEl, sourceUrl, startSeconds) {
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
        if (startSeconds > 5) {
          videoEl.currentTime = startSeconds;
        }
      });

      this.hlsInstance.on(Hls.Events.ERROR, (event, data) => {
        if (data.fatal) {
          console.error('Fatal streaming error:', data);
          if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
            this.hlsInstance.startLoad();
          } else {
            this.hlsInstance.destroy();
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

// Initialize Player via Provider Interface
VideoProvider.init(videoEl, playlistUrl, resumeSeconds);

// Watermark Jitter & Clock
function updateWatermark() {
  const wm = document.getElementById('watermark');
  const clock = document.getElementById('watermarkClock');
  const now = new Date();
  clock.textContent = now.toLocaleTimeString();

  // Random coordinates within video bounding box
  const topPercent = Math.floor(Math.random() * 70) + 10;
  const leftPercent = Math.floor(Math.random() * 50) + 10;
  wm.style.top = topPercent + '%';
  wm.style.left = leftPercent + '%';
}
setInterval(updateWatermark, 20000);
updateWatermark();

// Anti-piracy: Pause on window blur or tab hide
document.addEventListener('visibilitychange', () => {
  if (document.hidden && !videoEl.paused) {
    videoEl.pause();
  }
});
window.addEventListener('blur', () => {
  if (!videoEl.paused) {
    videoEl.pause();
  }
});

// Periodic progress recording (every 10 seconds)
let lastProgressTime = 0;
videoEl.addEventListener('timeupdate', () => {
  const current = Math.floor(videoEl.currentTime);
  if (current - lastProgressTime >= 10) {
    lastProgressTime = current;
    sendProgress(current, Math.floor(videoEl.duration || 0), false);
  }
});

videoEl.addEventListener('ended', () => {
  sendProgress(Math.floor(videoEl.duration), Math.floor(videoEl.duration), true);
  document.getElementById('statusIndicator').innerHTML = '<span class="badge badge-green">Watched</span>';
});

async function sendProgress(pos, dur, completed) {
  try {
    const fd = new FormData();
    fd.append('video_id', videoId);
    fd.append('position_sec', pos);
    fd.append('duration_sec', dur);
    if (completed) fd.append('completed', 1);

    await fetch('/api/video_progress.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    });
  } catch (err) {
    // Silently continue
  }
}
</script>
</body>
</html>

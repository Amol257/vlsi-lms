<?php
require_once __DIR__ . '/../db.php';
$student = require_role(['student', 'admin', 'teacher']);
$pdo = get_db();

$webinars = [];
if ($pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `live_sessions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `type` enum('webinar','class') NOT NULL DEFAULT 'webinar',
            `title` varchar(255) NOT NULL,
            `description` text DEFAULT NULL,
            `starts_at` datetime NOT NULL,
            `meet_url` varchar(255) NOT NULL,
            `program_id` int(11) DEFAULT NULL,
            `created_by` int(11) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_starts_at` (`starts_at`),
            KEY `idx_type` (`type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $pdo->query("SELECT * FROM live_sessions WHERE type = 'webinar' ORDER BY starts_at DESC LIMIT 10");
        $webinars = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Webinars query error: " . $e->getMessage());
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<title>Live Webinars - VLSI LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/assets/style.css">
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
      <a href="/student/player.php">
        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        <span>Lecture Player</span>
      </a>
      <a href="/student/webinars.php" class="on">
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
      <h1>Live Technical Webinars</h1>
      <div class="top-user">
        <a href="/student/index.php" class="btn btn-sm">Back to Dashboard</a>
      </div>
    </header>

    <main class="content">
      <div style="max-width: 760px; margin: 0 auto;">
        <section class="card" style="padding: 32px; border-radius: 12px;">
          <div style="margin-bottom: 24px;">
            <span class="badge badge-blue" style="margin-bottom: 8px;">Interactive Masterclass</span>
            <h2 style="margin: 4px 0 8px 0; font-size: 22px;">Join Scheduled Live Webinar</h2>
            <p class="muted" style="margin: 0;">Connect directly to our live Google Meet sessions with VLSI industry experts and instructors.</p>
          </div>

          <div id="joinStatusAlert" style="display:none; padding: 12px 14px; border-radius: 8px; font-weight: 500; margin-bottom: 18px;"></div>

          <?php if (empty($webinars)): ?>
            <div style="padding: 24px; text-align: center; background: #f8fafc; border-radius: 8px; border: 1px dashed var(--line);">
              <p class="muted" style="margin: 0;">There are no scheduled webinars at this time. Check back soon for upcoming sessions.</p>
            </div>
          <?php else: ?>
            <form id="joinWebinarForm" style="display: flex; flex-direction: column; gap: 18px;">
              <label style="display: flex; flex-direction: column; gap: 6px; font-weight: 600;">
                <span>Select Webinar Session</span>
                <select id="webinarSelect" style="padding: 12px; font-size: 15px; border-radius: 8px; border: 1px solid var(--line); background: #ffffff;">
                  <?php $first = true; foreach ($webinars as $w): ?>
                    <?php
                      $starts_time = strtotime($w['starts_at']);
                      $formatted_date = date('D, M j, Y \a\t g:i A T', $starts_time);
                      $diff = time() - $starts_time;
                      $status_tag = ($diff >= -600 && $diff <= 10800) ? ' [LIVE NOW]' : (($diff < -600) ? ' [Upcoming]' : ' [Past]');
                    ?>
                    <option value="<?= (int)$w['id'] ?>" <?= $first ? 'selected' : '' ?>>
                      <?= h($w['title']) ?> : <?= $formatted_date ?><?= $status_tag ?>
                    </option>
                    <?php $first = false; ?>
                  <?php endforeach; ?>
                </select>
              </label>

              <div id="webinarDetailsBox" style="padding: 14px; background: #f8fafc; border-radius: 8px; border: 1px solid var(--line); font-size: 14px; color: var(--ink-2);">
                <div id="webinarDescription">Select a session above to view details.</div>
              </div>

              <button type="button" id="joinBtn" class="btn btn-primary btn-block" style="padding: 14px; font-size: 16px; font-weight: 600;">
                Join the webinar
              </button>
            </form>
          <?php endif; ?>

          <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--line); font-size: 13px; color: var(--muted);">
            <b>Note on joining:</b> Access opens exactly 10 minutes prior to scheduled start time. Ensure you have microphone permissions enabled in your browser.
          </div>
        </section>
      </div>
    </main>
  </div>
</div>

<script src="/assets/app.js"></script>
<script src="/assets/heartbeat.js"></script>
<script>
const webinarsData = <?= json_encode($webinars) ?>;

function updateDetails() {
  const select = document.getElementById('webinarSelect');
  if (!select) return;
  const currentId = parseInt(select.value, 10);
  const found = webinarsData.find(w => parseInt(w.id, 10) === currentId);
  const descEl = document.getElementById('webinarDescription');
  if (found && found.description) {
    descEl.textContent = found.description;
  } else {
    descEl.textContent = 'Live interactive webinar hosted via Google Meet.';
  }
}

const selectEl = document.getElementById('webinarSelect');
if (selectEl) {
  selectEl.addEventListener('change', updateDetails);
  updateDetails();
}

const joinBtn = document.getElementById('joinBtn');
if (joinBtn) {
  joinBtn.addEventListener('click', async () => {
    const webinarId = document.getElementById('webinarSelect').value;
    const statusAlert = document.getElementById('joinStatusAlert');

    statusAlert.style.display = 'none';

    // Step 1: Open blank tab immediately to bypass popup blockers
    const newTab = window.open('about:blank', '_blank');

    try {
      joinBtn.disabled = true;
      joinBtn.textContent = 'Connecting to Google Meet...';

      // Step 2: Request validated Meet link from API
      const res = await fetch('/api/live_join.php?id=' + encodeURIComponent(webinarId), {
        method: 'GET',
        headers: {
          'Accept': 'application/json'
        },
        credentials: 'same-origin'
      });

      const data = await res.json();

      if (!res.ok) {
        // Close opened tab and display error message
        if (newTab) newTab.close();
        statusAlert.className = '';
        statusAlert.style.background = 'var(--red-soft)';
        statusAlert.style.color = 'var(--red)';
        statusAlert.textContent = data.error || 'Could not join webinar.';
        statusAlert.style.display = 'block';
        return;
      }

      // Step 3: Redirect blank tab to verified Google Meet URL
      if (newTab && data.meet_url) {
        newTab.location.href = data.meet_url;
      } else if (data.meet_url) {
        window.location.href = data.meet_url;
      }

    } catch (err) {
      if (newTab) newTab.close();
      statusAlert.style.background = 'var(--red-soft)';
      statusAlert.style.color = 'var(--red)';
      statusAlert.textContent = 'Network error while attempting to join webinar.';
      statusAlert.style.display = 'block';
    } finally {
      joinBtn.disabled = false;
      joinBtn.textContent = 'Join the webinar';
    }
  });
}
</script>
</body>
</html>

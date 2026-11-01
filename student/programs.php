<?php
require_once __DIR__ . '/../db.php';
$student = require_role(['student', 'admin', 'teacher']);
$pdo = get_db();

$programs = [];
$my_enrollments = [];

if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM programs WHERE is_active = 1 ORDER BY id ASC");
        $programs = $stmt->fetchAll();

        $enr_stmt = $pdo->prepare("SELECT program_id, status, expires_at FROM enrollments WHERE user_id = ? AND status = 'active'");
        $enr_stmt->execute([$student['id']]);
        $rows = $enr_stmt->fetchAll();
        foreach ($rows as $r) {
            $my_enrollments[$r['program_id']] = $r;
        }
    } catch (PDOException $e) {
        error_log("Programs query error: " . $e->getMessage());
    }
}

$razorpay_key = lms_config('razorpay.key_id', '');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<title>VLSI Programs Catalog - VLSI LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/assets/style.css">
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
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
      <a href="/student/programs.php" class="on">
        <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        <span>Programs</span>
      </a>
      <a href="/student/player.php">
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
      <h1>VLSI Insights Training Programs</h1>
      <div class="top-user">
        <a href="/student/index.php" class="btn btn-sm">Back to Classroom</a>
      </div>
    </header>

    <main class="content">
      <div class="grid-2">
        <?php foreach ($programs as $p): ?>
          <?php $is_enrolled = isset($my_enrollments[$p['id']]); ?>
          <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
            <div>
              <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                <span class="badge <?= $is_enrolled ? 'badge-green' : 'badge-blue' ?>">
                  <?= $is_enrolled ? 'Enrolled' : 'Available' ?>
                </span>
                <span style="font-size: 18px; font-weight: 700; color: var(--ink);">INR <?= number_format($p['price_inr']) ?></span>
              </div>
              <h2 style="font-size: 18px; margin: 4px 0 8px 0;"><?= h($p['title']) ?></h2>
              <p class="small muted" style="margin-bottom: 16px; line-height: 1.5;"><?= h($p['description']) ?></p>
            </div>

            <div style="border-top: 1px solid var(--line); padding-top: 14px; margin-top: 14px;">
              <?php if ($is_enrolled): ?>
                <a href="/student/player.php" class="btn btn-primary btn-block">Enter Program Classroom</a>
              <?php else: ?>
                <button type="button" class="btn btn-primary btn-block" onclick="startCheckout(<?= (int)$p['id'] ?>)">Enroll via Razorpay</button>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </main>
  </div>
</div>

<script src="/assets/app.js"></script>
<script src="/assets/heartbeat.js"></script>
<script>
async function startCheckout(programId) {
  const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  const fd = new FormData();
  fd.append('program_id', programId);
  fd.append('csrf_token', csrfToken);

  try {
    const res = await fetch('/api/checkout_create.php', {
      method: 'POST',
      body: fd,
      headers: { 'Accept': 'application/json' }
    });
    const data = await res.json();

    if (!res.ok) {
      alert(data.error || 'Checkout initiation failed.');
      return;
    }

    if (data.key_id && data.key_id !== 'rzp_live_placeholder' && typeof Razorpay !== 'undefined') {
      const options = {
        key: data.key_id,
        amount: data.amount_paise,
        currency: 'INR',
        name: 'VLSI Insights',
        description: data.program_title,
        order_id: data.order_id,
        prefill: {
          name: data.user.name,
          email: data.user.email
        },
        theme: { color: '#0284c7' },
        handler: function(response) {
          alert('Payment successful! Processing enrollment...');
          window.location.reload();
        }
      };
      const rzp = new Razorpay(options);
      rzp.open();
    } else {
      alert('Order created: ' + data.order_id + '. In test mode, access will activate on payment webhook.');
    }

  } catch (err) {
    alert('Network error while creating checkout session.');
  }
}
</script>
</body>
</html>

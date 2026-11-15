<?php
/**
 * Unified Teacher / Instructor Sidebar Navigation Component
 * Expected variable: $active_tab (string: 'lectures', 'upload', 'submissions')
 */
$active_tab = $active_tab ?? 'lectures';
?>
<aside class="sidebar">
  <a class="brand" href="/teacher/index.php">
    <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/></svg></span>
    <span><b>VLSI LMS</b><small>Instructor Portal</small></span>
  </a>
  <nav>
    <a href="/teacher/index.php" class="<?= $active_tab === 'lectures' ? 'on' : '' ?>">
      <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
      <span>My Lectures</span>
    </a>
    <a href="/teacher/upload.php" class="<?= $active_tab === 'upload' ? 'on' : '' ?>">
      <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      <span>Upload / Submit Lecture</span>
    </a>
    <a href="/teacher/submissions.php" class="<?= $active_tab === 'submissions' ? 'on' : '' ?>">
      <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      <span>Review Submissions</span>
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="/logout.php" style="display: flex; align-items: center; gap: 8px; color: #f87171; font-weight: 500;">
      <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      <span>Sign Out</span>
    </a>
  </div>
</aside>

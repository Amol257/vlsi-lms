<?php
/**
 * Unified Student Sidebar Navigation Component
 * Expected variable: $active_tab (string: 'batches', 'programs', 'player', 'assignments', 'webinars')
 */
$active_tab = $active_tab ?? 'batches';
?>
<aside class="sidebar">
  <a class="brand" href="/student/index.php">
    <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/></svg></span>
    <span><b>VLSI LMS</b><small>Student Classroom</small></span>
  </a>
  <nav>
    <a href="/student/index.php" class="<?= $active_tab === 'batches' ? 'on' : '' ?>">
      <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
      <span>My Batches</span>
    </a>
    <a href="/student/programs.php" class="<?= $active_tab === 'programs' ? 'on' : '' ?>">
      <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
      <span>Programs</span>
    </a>
    <a href="/student/watch.php" class="<?= $active_tab === 'player' ? 'on' : '' ?>">
      <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="5 3 19 12 5 21 5 3"/></svg>
      <span>Lecture Player</span>
    </a>
    <a href="/student/assignments.php" class="<?= $active_tab === 'assignments' ? 'on' : '' ?>">
      <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      <span>Assignments</span>
    </a>
    <a href="/student/webinars.php" class="<?= $active_tab === 'webinars' ? 'on' : '' ?>">
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


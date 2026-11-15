<?php
/**
 * Unified Admin Sidebar Navigation Component
 * Expected variable: $active_tab (string: 'dashboard', 'students', 'teachers', 'batches', 'courses', 'lectures', 'videos', 'webinars', 'enrolments', 'announcements', 'reports', 'audit')
 */
$active_tab = $active_tab ?? 'dashboard';
?>
<aside class="sidebar">
  <a class="brand" href="/admin/index.php">
    <span class="brand-mark">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/></svg>
    </span>
    <span><b>VLSI LMS</b><small>Admin Console</small></span>
  </a>
  <nav>
    <a href="/admin/index.php" class="<?= $active_tab === 'dashboard' ? 'on' : '' ?>">
      <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
      <span>Dashboard</span>
    </a>
    <a href="/admin/users.php?role=student" class="<?= $active_tab === 'students' ? 'on' : '' ?>">
      <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14c2 .6 3.5 2.6 3.5 6"/></svg>
      <span>Students</span>
    </a>
    <a href="/admin/users.php?role=teacher" class="<?= $active_tab === 'teachers' ? 'on' : '' ?>">
      <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="7" r="4"/><path d="M5.5 21c0-4 3.5-7 6.5-7s6.5 3 6.5 7"/></svg>
      <span>Teachers</span>
    </a>
    <a href="/admin/batches.php" class="<?= $active_tab === 'batches' ? 'on' : '' ?>">
      <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
      <span>Batches</span>
    </a>
    <a href="/admin/courses.php" class="<?= $active_tab === 'courses' ? 'on' : '' ?>">
      <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
      <span>Courses &amp; Subjects</span>
    </a>
    <a href="/admin/lectures.php" class="<?= $active_tab === 'lectures' ? 'on' : '' ?>">
      <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="5 3 19 12 5 21 5 3"/></svg>
      <span>Lectures</span>
    </a>
    <a href="/admin/videos.php" class="<?= $active_tab === 'videos' ? 'on' : '' ?>">
      <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="2" width="20" height="20" rx="2.18"/><line x1="7" y1="2" x2="7" y2="22"/><line x1="17" y1="2" x2="17" y2="22"/><line x1="2" y1="12" x2="22" y2="12"/><line x1="2" y1="7" x2="7" y2="7"/><line x1="2" y1="17" x2="7" y2="17"/><line x1="17" y1="17" x2="22" y2="17"/><line x1="17" y1="7" x2="22" y2="7"/></svg>
      <span>Video Library</span>
    </a>
    <a href="/admin/webinars.php" class="<?= $active_tab === 'webinars' ? 'on' : '' ?>">
      <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 10l5-5v14l-5-5M4 6h11a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"/></svg>
      <span>Webinars</span>
    </a>
    <a href="/admin/enrolments.php" class="<?= $active_tab === 'enrolments' ? 'on' : '' ?>">
      <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
      <span>Enrolments</span>
    </a>
    <a href="/admin/announcements.php" class="<?= $active_tab === 'announcements' ? 'on' : '' ?>">
      <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <span>Announcements</span>
    </a>
    <a href="/admin/reports.php" class="<?= $active_tab === 'reports' ? 'on' : '' ?>">
      <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      <span>Reports</span>
    </a>
    <a href="/admin/audit.php" class="<?= $active_tab === 'audit' ? 'on' : '' ?>">
      <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      <span>Audit Log</span>
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="/logout.php" style="display: flex; align-items: center; gap: 8px; color: #f87171; font-weight: 500;">
      <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      <span>Sign Out</span>
    </a>
  </div>
</aside>


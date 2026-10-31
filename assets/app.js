/**
 * VLSI LMS — APPLICATION LOGIC
 * Replicates astarindo LMS behaviours: video resume, heartbeat tracking, sidebar drawers, modal forms
 */
(function() {
  'use strict';

  // 1. Mobile Sidebar Navigation Drawer
  var menuBtn = document.querySelector('.menu-btn');
  var scrim = document.querySelector('.scrim');
  if (menuBtn) {
    menuBtn.addEventListener('click', function() {
      document.body.classList.toggle('nav-open');
    });
  }
  if (scrim) {
    scrim.addEventListener('click', function() {
      document.body.classList.remove('nav-open');
    });
  }

  // 2. Synchronize Header User Avatar & Name
  var whoName = document.querySelector('.who-name');
  var avatar = document.querySelector('.avatar');
  if (window.LMS) {
    var user = window.LMS.getCurrentUser();
    if (user) {
      if (whoName) whoName.textContent = user.name || user.email;
      if (avatar) avatar.textContent = (user.name || user.email || 'U').charAt(0).toUpperCase();
    }
  }

  // 3. Student Video Player with Timestamp Resume & Heartbeat
  var player = document.getElementById('player');
  if (player && window.LMS) {
    player.addEventListener('contextmenu', function(e) { e.preventDefault(); });
    var resumeSec = parseInt(player.dataset.resume, 10) || 0;
    if (resumeSec > 5) {
      player.addEventListener('loadedmetadata', function() {
        if (resumeSec < player.duration - 5) {
          player.currentTime = resumeSec;
        }
      }, { once: true });
    }

    var lectureId = player.dataset.lecture;
    var lastSent = -1;
    var started = false;

    var recordHeartbeat = function(event) {
      var pos = Math.floor(player.currentTime || 0);
      if (event === 'tick' && Math.abs(pos - lastSent) < 10) return;
      lastSent = pos;
      var res = window.LMS.updateLectureProgress(lectureId, pos, Math.floor(player.duration || 0), event);
      var note = document.getElementById('progressNote');
      if (res && res.ok && note) {
        note.textContent = ' · ' + (res.completed ? 'Completed ✓' : res.percent + '% watched');
      }
    };

    player.addEventListener('play', function() {
      if (!started) { started = true; recordHeartbeat('start'); }
    });
    player.addEventListener('timeupdate', function() { recordHeartbeat('tick'); });
    player.addEventListener('pause', function() { recordHeartbeat('pause'); });
    player.addEventListener('ended', function() { recordHeartbeat('complete'); });
    window.addEventListener('pagehide', function() { if (started) recordHeartbeat('pause'); });
  }

  // 4. External Video "Mark as watched" Button
  var markDoneWrap = document.querySelector('.mark-done');
  if (markDoneWrap && window.LMS) {
    var markBtn = markDoneWrap.querySelector('button');
    if (markBtn) {
      markBtn.addEventListener('click', function() {
        var lid = markDoneWrap.dataset.lecture;
        var dur = parseInt(markDoneWrap.dataset.duration, 10) || 3600;
        var res = window.LMS.updateLectureProgress(lid, dur, dur, 'complete');
        markDoneWrap.classList.add('done');
        markBtn.textContent = '✓ Watched';
        var note = document.getElementById('progressNote');
        if (note) note.textContent = ' · Completed ✓';
      });
    }
  }

  // 5. Table Search & Filter helper
  var filterInput = document.querySelector('.filters input[type=search], .filters input[type=text]');
  if (filterInput) {
    filterInput.addEventListener('input', function() {
      var query = filterInput.value.toLowerCase().trim();
      var rows = document.querySelectorAll('tbody tr');
      rows.forEach(function(row) {
        var text = row.textContent.toLowerCase();
        row.style.display = (text.indexOf(query) !== -1 || query === '') ? '' : 'none';
      });
    });
  }
})();

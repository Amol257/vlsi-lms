/**
 * VLSI LMS : DATA ENGINE & AUTHENTICATION MANAGER
 * Provides pre-seeded data matching astarindo LMS with full CRUD and role support
 */
(function(window) {
  'use strict';

  var STORAGE_KEY = 'vlsi_lms_db_v1';
  var AUTH_KEY = 'vlsi_lms_auth_v1';

  var defaultDb = {
    courses: [
      { id: 'c1', code: 'PD', title: 'Physical Design & Tapeout', duration: '6 months', status: 'active', desc: 'Netlist to GDSII flow, floorplanning, CTS, routing, DRC/LVS, and closure.' },
      { id: 'c2', code: 'DV', title: 'Design Verification (UVM/SystemVerilog)', duration: '4 months', status: 'active', desc: 'Functional verification, assertions, constrained-random tests, and UVM environment creation.' },
      { id: 'c3', code: 'STA', title: 'Static Timing Analysis & Signoff', duration: '3 months', status: 'active', desc: 'Setup/Hold margins, clock skew/jitter, SDC constraints, and ECO timing fixing.' }
    ],
    subjects: [
      { id: 's1', course_id: 'c1', name: 'Floorplanning & Power Grid Synthesis', order_idx: 1 },
      { id: 's2', course_id: 'c1', name: 'Clock Tree Synthesis (CTS) & Skew Optimization', order_idx: 2 },
      { id: 's3', course_id: 'c1', name: 'Routing & Signal Integrity (SI)', order_idx: 3 },
      { id: 's4', course_id: 'c2', name: 'SystemVerilog OOP & Randomization', order_idx: 1 },
      { id: 's5', course_id: 'c2', name: 'UVM Testbench Architecture & Factories', order_idx: 2 },
      { id: 's6', course_id: 'c3', name: 'Timing Paths & Clock Jitter Constraints', order_idx: 1 }
    ],
    batches: [
      { id: 'b1', course_id: 'c1', code: 'PD-NOV-2026-WKND', name: 'PD Weekend Batch (Nov 2026)', schedule: 'Sat/Sun 10 AM - 1 PM IST', status: 'active' },
      { id: 'b2', course_id: 'c2', code: 'DV-FALL-2026', name: 'DV Cohort 04 (Evening)', schedule: 'Mon/Wed/Fri 7 PM - 9 PM IST', status: 'active' }
    ],
    users: [
      { id: 'u_admin', email: 'admin@lms.local', name: 'Super Admin', role: 'admin', batch_id: null },
      { id: 'u_teach1', email: 'teacher@vlsiinsights.com', name: 'Sanjay Deshmukh (Lead Architect)', role: 'teacher', batch_id: 'b1' },
      { id: 'u_stud1', email: 'student@example.com', name: 'Rahul Sharma', role: 'student', batch_id: 'b1' },
      { id: 'u_stud2', email: 'priya.k@example.com', name: 'Priya Kulkarni', role: 'student', batch_id: 'b1' },
      { id: 'u_stud3', email: 'arun.m@example.com', name: 'Arun Mehra', role: 'student', batch_id: 'b2' }
    ],
    lectures: [
      {
        id: 'lec_1',
        batch_id: 'b1',
        subject_id: 's1',
        teacher_id: 'u_teach1',
        teacher_name: 'Sanjay Deshmukh',
        title: 'Lec 01: Advanced Floorplanning & Macro Placement Strategy',
        description: 'Comprehensive walkthrough of core utilization, keep-out margins, channel estimation, and optimal flyline routing for multi-macro ASICs.',
        video_type: 'youtube',
        video_url: 'https://www.youtube.com/embed/gOo57e69_B8',
        duration_seconds: 3600,
        notes_url: 'https://vlsiinsights.com/docs/PD_Floorplan_Notes.pdf',
        lab_url: 'https://vlsiinsights.com/labs/macro_placement_lab.tar.gz',
        status: 'published',
        created_at: '2026-10-25 10:00:00'
      },
      {
        id: 'lec_2',
        batch_id: 'b1',
        subject_id: 's1',
        teacher_id: 'u_teach1',
        teacher_name: 'Sanjay Deshmukh',
        title: 'Lec 02: Power Mesh IR Drop & Stripe Pitch Calculation',
        description: 'Static & dynamic IR drop mitigation, EM limits, via ladder resistance, and top metal strap sizing formulas.',
        video_type: 'youtube',
        video_url: 'https://www.youtube.com/embed/gOo57e69_B8',
        duration_seconds: 4200,
        notes_url: 'https://vlsiinsights.com/docs/Power_Grid_Calculations.pdf',
        lab_url: null,
        status: 'published',
        created_at: '2026-10-28 14:00:00'
      },
      {
        id: 'lec_3',
        batch_id: 'b1',
        subject_id: 's2',
        teacher_id: 'u_teach1',
        teacher_name: 'Sanjay Deshmukh',
        title: 'Lec 03: Clock Tree Synthesis & Inverter Sizing Review',
        description: 'Teacher submitted lecture waiting for administrator review and publishing approval.',
        video_type: 'youtube',
        video_url: 'https://www.youtube.com/embed/gOo57e69_B8',
        duration_seconds: 3100,
        notes_url: null,
        lab_url: null,
        status: 'submitted',
        created_at: '2026-10-31 09:30:00'
      }
    ],
    progress: [
      { user_id: 'u_stud1', user_name: 'Rahul Sharma', user_email: 'student@example.com', lecture_id: 'lec_1', position_seconds: 2850, duration_seconds: 3600, percent_watched: 79, is_completed: false, last_viewed_at: '2026-10-31 16:20:00' },
      { user_id: 'u_stud2', user_name: 'Priya Kulkarni', user_email: 'priya.k@example.com', lecture_id: 'lec_1', position_seconds: 3600, duration_seconds: 3600, percent_watched: 100, is_completed: true, last_viewed_at: '2026-10-30 20:10:00' }
    ],
    announcements: [
      { id: 'a1', batch_id: 'b1', title: 'Live Lab Session this Saturday 11 AM IST', content: 'We will be debugging signoff timing violations on the 28nm RISC-V SoC core. Please have QuestaSim or EDA Playground ready.', created_at: '2026-10-30' },
      { id: 'a2', batch_id: null, title: 'LMS System Upgrade & Video Streaming', content: 'Our lecture streaming now supports instant resume and multi-resolution playback.', created_at: '2026-10-29' }
    ],
    audit_logs: [
      { id: 'log_1', actor: 'admin@lms.local', action: 'Approved Lecture', details: 'Lec 01 Floorplanning published to PD-NOV-2026-WKND', created_at: '2026-10-25 12:00:00' },
      { id: 'log_2', actor: 'teacher@vlsiinsights.com', action: 'Submitted Lecture', details: 'Lec 03 CTS uploaded for review', created_at: '2026-10-31 09:30:00' }
    ]
  };

  function getDb() {
    try {
      var item = localStorage.getItem(STORAGE_KEY);
      if (item) return JSON.parse(item);
    } catch(e) {}
    localStorage.setItem(STORAGE_KEY, JSON.stringify(defaultDb));
    return defaultDb;
  }

  function saveDb(data) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    } catch(e) {}
  }

  var LMS = {
    getDb: getDb,
    saveDb: saveDb,

    getCurrentUser: function() {
      try {
        var u = localStorage.getItem(AUTH_KEY);
        if (u) return JSON.parse(u);
      } catch(e) {}
      return { id: 'u_admin', email: 'admin@lms.local', name: 'Super Admin', role: 'admin' };
    },

    setCurrentUser: function(user) {
      localStorage.setItem(AUTH_KEY, JSON.stringify(user));
    },

    signOut: function() {
      localStorage.removeItem(AUTH_KEY);
      window.location.href = '../login.html';
    },

    signIn: function(email, password) {
      var db = getDb();
      var clean = email.toLowerCase().trim();
      var found = db.users.find(function(x) { return x.email.toLowerCase() === clean; });
      if (found) {
        LMS.setCurrentUser(found);
        return { ok: true, user: found };
      }
      var role = clean.indexOf('admin') !== -1 ? 'admin' : (clean.indexOf('teach') !== -1 ? 'teacher' : 'student');
      var newUser = {
        id: 'u_' + Date.now(),
        email: email,
        name: email.split('@')[0],
        role: role,
        batch_id: 'b1'
      };
      db.users.push(newUser);
      saveDb(db);
      LMS.setCurrentUser(newUser);
      return { ok: true, user: newUser };
    },

    requireRole: function(allowedRoles) {
      var user = LMS.getCurrentUser();
      if (!user) {
        window.location.href = '../login.html';
        return null;
      }
      if (allowedRoles && allowedRoles.indexOf(user.role) === -1) {
        alert('Access notice: Redirecting to ' + user.role + ' view.');
        if (user.role === 'admin') window.location.href = '../admin/index.html';
        else if (user.role === 'teacher') window.location.href = '../teacher/index.html';
        else window.location.href = '../student/index.html';
        return null;
      }
      return user;
    },

    updateLectureProgress: function(lectureId, position, duration, event) {
      var db = getDb();
      var user = LMS.getCurrentUser();
      var p = db.progress.find(function(x) { return x.user_id === user.id && x.lecture_id === lectureId; });
      var dur = duration || (p ? p.duration_seconds : 0) || 1;
      var pct = Math.min(100, Math.round((position / dur) * 100));
      var isComp = (event === 'complete' || pct >= 90);

      if (!p) {
        p = {
          user_id: user.id,
          user_name: user.name || user.email,
          user_email: user.email,
          lecture_id: lectureId,
          position_seconds: position,
          duration_seconds: dur,
          percent_watched: pct,
          is_completed: isComp,
          last_viewed_at: new Date().toISOString()
        };
        db.progress.push(p);
      } else {
        p.position_seconds = Math.max(p.position_seconds, position);
        p.percent_watched = Math.max(p.percent_watched, pct);
        if (isComp) p.is_completed = true;
        p.last_viewed_at = new Date().toISOString();
      }
      saveDb(db);
      return { ok: true, percent: p.percent_watched, completed: p.is_completed };
    }
  };

  window.LMS = LMS;
})(window);


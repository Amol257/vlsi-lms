-- ==============================================================================
-- VLSI LMS : Sample Seed Data for Hostinger MySQL Deployment
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Training Programs
INSERT INTO `programs` (`id`, `slug`, `title`, `description`, `price_inr`, `vlsiinsights_url`, `is_active`) VALUES
(1, 'physical-design', 'Physical Design & Tapeout Masterclass', 'Comprehensive Netlist-to-GDSII flow, floorplanning, CTS, routing, DRC/LVS, and signoff closure.', 29999, 'https://www.vlsiinsights.com/physical-design', 1),
(2, 'design-verification', 'Design Verification (UVM & SystemVerilog)', 'Advanced functional verification, SVA assertions, constrained-random tests, and UVM architecture.', 24999, 'https://www.vlsiinsights.com/design-verification', 1),
(3, 'sta-signoff', 'Static Timing Analysis & Signoff Closure', 'Setup and Hold margins, clock skew, SDC constraints, and ECO timing fixing.', 19999, 'https://www.vlsiinsights.com/sta', 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `price_inr` = VALUES(`price_inr`);

-- 2. Curriculum Modules / Subjects
INSERT INTO `lms_courses` (`id`, `code`, `title`, `duration`, `description`, `status`) VALUES
(1, 'PD', 'Physical Design & Tapeout', '6 months', 'Full ASIC Physical Design methodology.', 'active'),
(2, 'DV', 'Design Verification (UVM/SystemVerilog)', '4 months', 'ASIC verification methodology.', 'active')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

INSERT INTO `lms_subjects` (`id`, `course_id`, `name`, `order_idx`) VALUES
(1, 1, 'Floorplanning & Power Grid Synthesis', 1),
(2, 1, 'Clock Tree Synthesis (CTS) & Skew Optimization', 2),
(3, 1, 'Routing & Signal Integrity (SI)', 3),
(4, 2, 'SystemVerilog OOP & Randomization', 1),
(5, 2, 'UVM Testbench Architecture & Factories', 2)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 3. Batches
INSERT INTO `lms_batches` (`id`, `course_id`, `code`, `name`, `schedule`, `status`) VALUES
(1, 1, 'PD-NOV-2026-WKND', 'PD Weekend Batch (Nov 2026)', 'Sat/Sun 10 AM - 1 PM IST', 'active'),
(2, 2, 'DV-FALL-2026', 'DV Cohort 04 (Evening)', 'Mon/Wed/Fri 7 PM - 9 PM IST', 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 4. Sample Academic Resources
INSERT INTO `resources` (`id`, `program_id`, `module_id`, `title`, `file_path`, `type`, `due_at`, `created_at`) VALUES
(1, 1, 1, 'Lab 01: Macro Placement and Keep-out Constraints', 'resources/lab01_macro_placement.pdf', 'lab', DATE_ADD(NOW(), INTERVAL 14 DAY), NOW()),
(2, 1, 2, 'Assignment 02: Clock Latency and Inverter Sizing Calculation', 'resources/assign02_cts_sizing.pdf', 'assignment', DATE_ADD(NOW(), INTERVAL 21 DAY), NOW()),
(3, 1, 1, 'Reference Guide: 28nm DRC and Antenna Rules', 'resources/ref_28nm_drc_rules.pdf', 'note', NULL, NOW())
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- 5. Sample Live Webinars
INSERT INTO `live_sessions` (`id`, `type`, `title`, `description`, `starts_at`, `meet_url`, `program_id`, `created_at`) VALUES
(1, 'webinar', 'Live Signoff Timing and ECO Debugging Masterclass', 'Hands-on session analyzing real PrimeTime timing violation reports on a 28nm RISC-V quad-core processor.', DATE_ADD(NOW(), INTERVAL 2 DAY), 'https://meet.google.com/abc-defg-hij', 1, NOW()),
(2, 'webinar', 'UVM Scoreboarding and Coverage Closure Strategies', 'Deep dive into transaction-level modeling (TLM) FIFO analysis and functional coverage groups.', DATE_ADD(NOW(), INTERVAL 9 DAY), 'https://meet.google.com/xyz-uvwx-rst', 2, NOW())
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- 6. Announcements
INSERT INTO `lms_announcements` (`id`, `batch_id`, `title`, `content`, `created_at`) VALUES
(1, 1, 'Welcome to VLSI Insights Physical Design Cohort!', 'All lecture recordings, notes, and lab testbenches are available under your enrolled classroom. Please join the upcoming live webinar this weekend.', NOW()),
(2, NULL, 'Platform Upgrade: High-Definition Video Player Active', 'Our streaming portal now supports auto-resume, encrypted playback, and session concurrency controls.', NOW())
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

SET FOREIGN_KEY_CHECKS = 1;

-- StudentOrbit CEO Dashboard — seed data
-- Idempotent: truncates then inserts, so the CEO can reset to a clean demo.

USE `studentorbit`;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `tasks`;
TRUNCATE TABLE `leads`;
TRUNCATE TABLE `todos`;
TRUNCATE TABLE `verticals`;
TRUNCATE TABLE `phases`;
TRUNCATE TABLE `settings`;
SET FOREIGN_KEY_CHECKS = 1;

-- Top-level monthly goals
INSERT INTO `settings` (`k`, `v`) VALUES
  ('revenue_target_month', '20000000'),
  ('students_target_month', '150000');

-- 6 roadmap phases (position defines unlock order)
INSERT INTO `phases` (`id`, `name`, `position`, `description`) VALUES
  (1, 'Foundation & Setup',     1, 'Legal, branding, core team, tooling'),
  (2, 'Product & Platform',     2, 'Build the student-facing platform and ops'),
  (3, 'Pilot Launch',           3, 'Launch with partner colleges, gather feedback'),
  (4, 'Growth & Marketing',     4, 'Ambassadors, workshops, webinars at scale'),
  (5, 'Revenue & Partnerships', 5, 'Govt/private/overseas deals, monetisation'),
  (6, 'Scale & Expansion',      6, 'Multi-city, new verticals, consolidation');

-- 7 business verticals (targets sum to ₹1.98 Cr, near the ₹2 Cr goal)
INSERT INTO `verticals`
  (`id`, `name`, `slug`, `revenue_target`, `revenue_actual`, `students_target`, `students_actual`, `color`, `sort_order`) VALUES
  (1, 'Government',       'govt',       5000000, 1200000, 40000, 9500,  '#2563eb', 1),
  (2, 'Private',          'private',    5000000, 2100000, 35000, 14200, '#16a34a', 2),
  (3, 'Overseas',         'overseas',   4000000,  600000, 20000, 3100,  '#9333ea', 3),
  (4, 'Educational',      'edu',        2000000,  900000, 25000, 11800, '#ea580c', 4),
  (5, 'Clubs',            'clubs',      1500000,  450000, 15000, 6700,  '#0891b2', 5),
  (6, 'Public Speaking',  'speaking',   1300000,  300000, 10000, 4200,  '#db2777', 6),
  (7, 'Research',         'research',   1000000,  150000,  5000, 1300,  '#65a30d', 7);

-- Tasks (shared by Roadmap + Kanban). Phase 1 all done so Phase 2 unlocks.
INSERT INTO `tasks`
  (`title`, `phase_id`, `vertical_id`, `status`, `owner`, `priority`, `due_date`, `board_position`) VALUES
  -- Phase 1 — Foundation (all done -> unlocks phase 2)
  ('Register company & GST',            1, NULL, 'done', 'Uttam',   'high',   '2026-09-01', 1),
  ('Finalise brand & logo',             1, NULL, 'done', 'Design',  'medium', '2026-09-05', 2),
  ('Hire core team (4)',                1, NULL, 'done', 'Uttam',   'high',   '2026-09-10', 3),
  ('Set up tooling & accounts',         1, NULL, 'done', 'Ops',     'low',    '2026-09-12', 4),
  -- Phase 2 — Product & Platform (in progress)
  ('Define platform MVP scope',         2, NULL, 'done',        'Product', 'high',   '2026-09-20', 1),
  ('Build student onboarding flow',     2, 4,    'in_progress', 'Dev',     'high',   '2026-10-15', 2),
  ('Payments integration',              2, 2,    'in_progress', 'Dev',     'high',   '2026-10-18', 3),
  ('Admin dashboard v1',                2, NULL, 'review',      'Dev',     'medium', '2026-10-05', 4),
  ('Content library setup',             2, 4,    'todo',        'Content', 'medium', '2026-10-25', 5),
  ('Mobile responsive QA',              2, NULL, 'todo',        'QA',      'low',    '2026-11-01', 6),
  -- Phase 3 — Pilot Launch
  ('Sign 3 pilot colleges',             3, 1,    'in_progress', 'Uttam',   'high',   '2026-10-30', 1),
  ('Pilot onboarding kit',              3, NULL, 'todo',        'Ops',     'medium', '2026-11-05', 2),
  ('Feedback survey tooling',           3, NULL, 'todo',        'Product', 'low',    '2026-11-10', 3),
  -- Phase 4 — Growth & Marketing
  ('Recruit 50 campus ambassadors',     4, 5,    'todo',        'Growth',  'high',   '2026-11-20', 1),
  ('Workshop series plan',              4, 6,    'todo',        'Growth',  'medium', '2026-11-25', 2),
  ('Webinar funnel setup',              4, NULL, 'todo',        'Growth',  'medium', '2026-12-01', 3),
  -- Phase 5 — Revenue & Partnerships
  ('Govt tender applications',          5, 1,    'todo',        'Uttam',   'high',   '2026-12-10', 1),
  ('Overseas university MoUs',          5, 3,    'todo',        'BizDev',  'high',   '2026-12-15', 2),
  -- Phase 6 — Scale
  ('Expand to 3 new cities',            6, NULL, 'todo',        'Uttam',   'medium', '2027-01-15', 1),
  ('Research vertical launch',          6, 7,    'todo',        'Product', 'low',    '2027-02-01', 2);

-- Leads across all stages; some due today (2026-10-12) to show the follow-up highlight.
INSERT INTO `leads`
  (`name`, `phone`, `college`, `vertical_id`, `source`, `stage`, `followup_date`, `notes`, `pipeline_position`) VALUES
  ('Ravi Teja',       '9876543210', 'Raghu Engineering College', 2, 'workshop',   'new',        '2026-10-12', 'Met at campus workshop',        1),
  ('Priya Sharma',    '9876543211', 'Andhra University',         1, 'webinar',    'contacted',  '2026-10-12', 'Interested in govt programs',   1),
  ('Karthik Reddy',   '9876543212', 'GITAM',                     3, 'ambassador', 'interested', '2026-10-14', 'Wants overseas pathway',        1),
  ('Sneha Patel',     '9876543213', 'VIT',                       4, 'social',     'interested', '2026-10-16', 'Edu content subscriber',        2),
  ('Arjun Nair',      '9876543214', 'NIT Warangal',              5, 'workshop',   'converted',  NULL,         'Joined clubs program',          1),
  ('Meera Iyer',      '9876543215', 'IIT Hyderabad',             6, 'webinar',    'converted',  NULL,         'Public speaking cohort',        2),
  ('Vikram Singh',    '9876543216', 'BITS Pilani',               2, 'ambassador', 'lost',       NULL,         'Chose a competitor',            1),
  ('Ananya Das',      '9876543217', 'Raghu Engineering College', 7, 'social',     'new',        '2026-10-13', 'Research internship enquiry',   2),
  ('Rahul Verma',     '9876543218', 'JNTU Kakinada',             1, 'workshop',   'contacted',  '2026-10-15', 'Follow up after exams',         2),
  ('Divya Menon',     '9876543219', 'SRM',                       3, 'webinar',    'interested', '2026-10-12', 'Overseas MS aspirant',          3);

-- Todos
INSERT INTO `todos` (`text`, `done`, `bucket`, `position`) VALUES
  ('Call 3 pilot colleges',              0, 'today', 1),
  ('Review payments integration PR',     0, 'today', 2),
  ('Approve ambassador stipend budget',  1, 'today', 3),
  ('Finalise Q4 OKRs',                   0, 'week',  1),
  ('Draft govt tender proposal',         0, 'week',  2),
  ('Interview 2 growth hires',           0, 'week',  3);

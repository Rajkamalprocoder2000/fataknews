START TRANSACTION;

DELETE FROM notifications
WHERE user_id IN (SELECT id FROM (SELECT id FROM users WHERE username IN (
  'vikram_manager','riya_editor','karan_editor','amit_reporter','neha_reporter',
  'meera_reporter','sonia_hr','priya_user','arjun_user','sana_user'
)) d)
   OR actor_id IN (SELECT id FROM (SELECT id FROM users WHERE username IN (
  'vikram_manager','riya_editor','karan_editor','amit_reporter','neha_reporter',
  'meera_reporter','sonia_hr','priya_user','arjun_user','sana_user'
)) d);

DELETE FROM follows
WHERE follower_id IN (SELECT id FROM (SELECT id FROM users WHERE username IN (
  'vikram_manager','riya_editor','karan_editor','amit_reporter','neha_reporter',
  'meera_reporter','sonia_hr','priya_user','arjun_user','sana_user'
)) d)
   OR following_id IN (SELECT id FROM (SELECT id FROM users WHERE username IN (
  'vikram_manager','riya_editor','karan_editor','amit_reporter','neha_reporter',
  'meera_reporter','sonia_hr','priya_user','arjun_user','sana_user'
)) d);

DELETE FROM bookmarks
WHERE user_id IN (SELECT id FROM (SELECT id FROM users WHERE username IN (
  'vikram_manager','riya_editor','karan_editor','amit_reporter','neha_reporter',
  'meera_reporter','sonia_hr','priya_user','arjun_user','sana_user'
)) d)
   OR post_id IN (SELECT id FROM (SELECT id FROM posts WHERE slug IN (
  'election-war-room-2026','startup-funding-wave-2026','cricket-camp-road-to-world-cup',
  'monsoon-health-alert-2026','ai-classrooms-bihar-pilot','box-office-friday-surge',
  'cyber-fraud-checklist-2026','remote-work-tax-debate','lucknow-street-food-map',
  'night-shift-notes-from-the-city','city-budget-live-blog','newsroom-tools-we-still-need',
  'old-town-traffic-experiment'
)) p);

DELETE FROM reactions
WHERE user_id IN (SELECT id FROM (SELECT id FROM users WHERE username IN (
  'vikram_manager','riya_editor','karan_editor','amit_reporter','neha_reporter',
  'meera_reporter','sonia_hr','priya_user','arjun_user','sana_user'
)) d)
   OR (target_type='post' AND target_id IN (SELECT id FROM (SELECT id FROM posts WHERE slug IN (
  'election-war-room-2026','startup-funding-wave-2026','cricket-camp-road-to-world-cup',
  'monsoon-health-alert-2026','ai-classrooms-bihar-pilot','box-office-friday-surge',
  'cyber-fraud-checklist-2026','remote-work-tax-debate','lucknow-street-food-map',
  'night-shift-notes-from-the-city','city-budget-live-blog','newsroom-tools-we-still-need',
  'old-town-traffic-experiment'
)) p));

DELETE FROM comments
WHERE user_id IN (SELECT id FROM (SELECT id FROM users WHERE username IN (
  'vikram_manager','riya_editor','karan_editor','amit_reporter','neha_reporter',
  'meera_reporter','sonia_hr','priya_user','arjun_user','sana_user'
)) d)
   OR post_id IN (SELECT id FROM (SELECT id FROM posts WHERE slug IN (
  'election-war-room-2026','startup-funding-wave-2026','cricket-camp-road-to-world-cup',
  'monsoon-health-alert-2026','ai-classrooms-bihar-pilot','box-office-friday-surge',
  'cyber-fraud-checklist-2026','remote-work-tax-debate','lucknow-street-food-map',
  'night-shift-notes-from-the-city','city-budget-live-blog','newsroom-tools-we-still-need',
  'old-town-traffic-experiment'
)) p);

DELETE FROM post_tags
WHERE post_id IN (SELECT id FROM (SELECT id FROM posts WHERE slug IN (
  'election-war-room-2026','startup-funding-wave-2026','cricket-camp-road-to-world-cup',
  'monsoon-health-alert-2026','ai-classrooms-bihar-pilot','box-office-friday-surge',
  'cyber-fraud-checklist-2026','remote-work-tax-debate','lucknow-street-food-map',
  'night-shift-notes-from-the-city','city-budget-live-blog','newsroom-tools-we-still-need',
  'old-town-traffic-experiment'
)) p);

DELETE FROM payroll
WHERE user_id IN (SELECT id FROM (SELECT id FROM users WHERE username IN (
  'vikram_manager','riya_editor','karan_editor','amit_reporter','neha_reporter',
  'meera_reporter','sonia_hr'
)) d);

DELETE FROM attendance
WHERE user_id IN (SELECT id FROM (SELECT id FROM users WHERE username IN (
  'vikram_manager','riya_editor','karan_editor','amit_reporter','neha_reporter',
  'meera_reporter','sonia_hr'
)) d);

DELETE FROM leaves
WHERE user_id IN (SELECT id FROM (SELECT id FROM users WHERE username IN (
  'vikram_manager','riya_editor','karan_editor','amit_reporter','neha_reporter',
  'meera_reporter','sonia_hr'
)) d);

DELETE FROM employee_profiles
WHERE user_id IN (SELECT id FROM (SELECT id FROM users WHERE username IN (
  'vikram_manager','riya_editor','karan_editor','amit_reporter','neha_reporter',
  'meera_reporter','sonia_hr'
)) d);

DELETE FROM posts
WHERE slug IN (
  'election-war-room-2026','startup-funding-wave-2026','cricket-camp-road-to-world-cup',
  'monsoon-health-alert-2026','ai-classrooms-bihar-pilot','box-office-friday-surge',
  'cyber-fraud-checklist-2026','remote-work-tax-debate','lucknow-street-food-map',
  'night-shift-notes-from-the-city','city-budget-live-blog','newsroom-tools-we-still-need',
  'old-town-traffic-experiment'
);

DELETE FROM users
WHERE username IN (
  'vikram_manager','riya_editor','karan_editor','amit_reporter','neha_reporter',
  'meera_reporter','sonia_hr','priya_user','arjun_user','sana_user'
);

SET @demo_hash = '$2y$12$aSwXfQ9gp8eOpdHkzxj9YO.xq22Yb4OYmgZHRWf0p9exu6LlHqvJi';
SET @admin_id = (SELECT id FROM users WHERE username='admin' LIMIT 1);

INSERT INTO users (
  role_id, username, email, password_hash, full_name, bio, location, website,
  is_verified, is_active, email_verified, badge_level, points, followers_count, following_count, posts_count
) VALUES
(3, 'vikram_manager', 'vikram.manager@fataknews.test', @demo_hash, 'Vikram Sethi', 'Content manager focused on newsroom workflows and editorial quality.', 'New Delhi', 'https://fataknews.test/vikram', 1, 1, 1, 'gold', 1240, 0, 0, 0),
(4, 'riya_editor', 'riya.editor@fataknews.test', @demo_hash, 'Riya Malhotra', 'Editor covering technology, business, and internet culture.', 'Mumbai', 'https://fataknews.test/riya', 1, 1, 1, 'silver', 980, 0, 0, 0),
(4, 'karan_editor', 'karan.editor@fataknews.test', @demo_hash, 'Karan Verma', 'Education and policy editor with a data-first approach.', 'Patna', 'https://fataknews.test/karan', 0, 1, 1, 'silver', 720, 0, 0, 0),
(5, 'amit_reporter', 'amit.reporter@fataknews.test', @demo_hash, 'Amit Tiwari', 'Political field reporter tracking elections, budgets, and policy shifts.', 'Lucknow', 'https://fataknews.test/amit', 1, 1, 1, 'press', 1480, 0, 0, 0),
(5, 'neha_reporter', 'neha.reporter@fataknews.test', @demo_hash, 'Neha Kapoor', 'Sports and civic reporter covering stadium stories and city beats.', 'Jaipur', 'https://fataknews.test/neha', 1, 1, 1, 'gold', 1105, 0, 0, 0),
(5, 'meera_reporter', 'meera.reporter@fataknews.test', @demo_hash, 'Meera Nair', 'Reporter focused on health, entertainment, and human stories.', 'Kochi', 'https://fataknews.test/meera', 0, 1, 1, 'silver', 860, 0, 0, 0),
(6, 'sonia_hr', 'sonia.hr@fataknews.test', @demo_hash, 'Sonia Arora', 'HR lead managing attendance, leaves, and team operations.', 'Gurugram', 'https://fataknews.test/sonia', 1, 1, 1, 'bronze', 540, 0, 0, 0),
(7, 'priya_user', 'priya.user@fataknews.test', @demo_hash, 'Priya Singh', 'Community member who posts local recommendations and reader notes.', 'Lucknow', NULL, 0, 1, 1, 'bronze', 260, 0, 0, 0),
(7, 'arjun_user', 'arjun.user@fataknews.test', @demo_hash, 'Arjun Rao', 'Night owl reader with an interest in city stories and public transport.', 'Bengaluru', NULL, 0, 1, 1, 'bronze', 210, 0, 0, 0),
(7, 'sana_user', 'sana.user@fataknews.test', @demo_hash, 'Sana Qureshi', 'Community contributor following politics, tech, and cultural trends.', 'Hyderabad', NULL, 0, 1, 1, 'bronze', 305, 0, 0, 0);

INSERT INTO employee_profiles (
  user_id, department_id, designation, employee_code, joining_date, salary, bank_account,
  pan_number, aadhar_number, address, emergency_contact, reporting_to, is_active
)
SELECT id, 1, 'Content Manager', 'FN-MGR-001', '2024-02-15', 86000.00, '111100001111', 'PANVK001', 'AADHARVK001', 'Sector 45, New Delhi', '9810000001', @admin_id, 1 FROM users WHERE username='vikram_manager'
UNION ALL
SELECT id, 3, 'Senior Editor', 'FN-EDT-001', '2024-03-01', 72000.00, '111100001112', 'PANRY001', 'AADHARRY001', 'Powai, Mumbai', '9810000002', (SELECT id FROM users WHERE username='vikram_manager'), 1 FROM users WHERE username='riya_editor'
UNION ALL
SELECT id, 1, 'Education Editor', 'FN-EDT-002', '2024-03-10', 69000.00, '111100001113', 'PANKR001', 'AADHARKR001', 'Boring Road, Patna', '9810000003', (SELECT id FROM users WHERE username='vikram_manager'), 1 FROM users WHERE username='karan_editor'
UNION ALL
SELECT id, 2, 'Political Reporter', 'FN-RPT-001', '2024-04-01', 52000.00, '111100001114', 'PANAM001', 'AADHARAM001', 'Hazratganj, Lucknow', '9810000004', (SELECT id FROM users WHERE username='vikram_manager'), 1 FROM users WHERE username='amit_reporter'
UNION ALL
SELECT id, 2, 'Sports Reporter', 'FN-RPT-002', '2024-04-12', 51000.00, '111100001115', 'PANNE001', 'AADHARNE001', 'Civil Lines, Jaipur', '9810000005', (SELECT id FROM users WHERE username='vikram_manager'), 1 FROM users WHERE username='neha_reporter'
UNION ALL
SELECT id, 2, 'Feature Reporter', 'FN-RPT-003', '2024-05-05', 50500.00, '111100001116', 'PANME001', 'AADHARME001', 'Panampilly Nagar, Kochi', '9810000006', (SELECT id FROM users WHERE username='vikram_manager'), 1 FROM users WHERE username='meera_reporter'
UNION ALL
SELECT id, 4, 'HR Lead', 'FN-HR-001', '2024-02-20', 64000.00, '111100001117', 'PANSO001', 'AADHARSO001', 'DLF Phase 3, Gurugram', '9810000007', @admin_id, 1 FROM users WHERE username='sonia_hr';

INSERT INTO posts (
  user_id, category_id, type, title, slug, excerpt, content, source_name, source_url, status,
  is_breaking, is_featured, is_trending, allow_comments, views_count, likes_count, comments_count,
  shares_count, bookmarks_count, reading_time, published_at, approved_by, created_at
) VALUES
((SELECT id FROM users WHERE username='amit_reporter'), (SELECT id FROM categories WHERE slug='elections'), 'news', 'Election War Room 2026: Ground Signals Harden in Four Key Seats', 'election-war-room-2026', 'A field report from four high-voltage constituencies where campaign messaging is tightening by the hour.', '<p>Campaign teams have shifted from broad promises to hyper-local voter management in the final phase of canvassing.</p><p>Reporters on the ground say booth-level planning, volunteer rotation, and WhatsApp micro-campaigns are now deciding momentum seat by seat.</p>', 'FatakNews Desk', 'https://fataknews.test/election-war-room', 'published', 1, 1, 1, 1, 18500, 0, 0, 58, 0, 4, NOW() - INTERVAL 5 HOUR, @admin_id, NOW() - INTERVAL 6 HOUR),
((SELECT id FROM users WHERE username='riya_editor'), (SELECT id FROM categories WHERE slug='technology'), 'news', 'Startup Funding Wave 2026: Smaller Cities Lead the New Creator Tools Boom', 'startup-funding-wave-2026', 'Seed-stage funding is clustering around product teams building creator tools outside the usual metro hubs.', '<p>Investors are showing stronger confidence in focused SaaS products built from tier-two and tier-three cities.</p><p>The shift is being driven by lower burn rates, better remote collaboration, and niche products with faster monetization.</p>', 'FatakNews Tech', 'https://fataknews.test/startup-funding', 'published', 0, 1, 1, 1, 12300, 0, 0, 41, 0, 3, NOW() - INTERVAL 1 DAY, @admin_id, NOW() - INTERVAL 1 DAY - INTERVAL 2 HOUR),
((SELECT id FROM users WHERE username='neha_reporter'), (SELECT id FROM categories WHERE slug='sports'), 'news', 'Cricket Camp Road to World Cup: Fitness Block Gets Longer, Nets Get Sharper', 'cricket-camp-road-to-world-cup', 'The pre-tournament camp has moved into a more intense workload phase with extra fielding and middle-over drills.', '<p>Coaches are prioritizing workload management and match-simulation scenarios after early fitness screens cleared most of the squad.</p><p>The support staff believes sharper middle-over execution will define the campaign more than powerplay aggression.</p>', 'FatakNews Sports', 'https://fataknews.test/cricket-camp', 'published', 0, 0, 1, 1, 9800, 0, 0, 22, 0, 3, NOW() - INTERVAL 2 DAY, @admin_id, NOW() - INTERVAL 2 DAY - INTERVAL 2 HOUR),
((SELECT id FROM users WHERE username='meera_reporter'), (SELECT id FROM categories WHERE slug='health'), 'news', 'Monsoon Health Alert 2026: Local Clinics Brace for Fever and Waterborne Cases', 'monsoon-health-alert-2026', 'Doctors are advising early symptom reporting as rainfall spikes and drainage complaints increase.', '<p>Primary care centers are stocking fever medication, oral rehydration solutions, and rapid test kits ahead of the next rainfall cycle.</p><p>Public health officers say awareness campaigns in schools and market areas are being scaled up this week.</p>', 'FatakNews Health', 'https://fataknews.test/monsoon-health', 'published', 0, 0, 0, 1, 6100, 0, 0, 11, 0, 3, NOW() - INTERVAL 3 DAY, @admin_id, NOW() - INTERVAL 3 DAY - INTERVAL 2 HOUR),
((SELECT id FROM users WHERE username='karan_editor'), (SELECT id FROM categories WHERE slug='education'), 'news', 'AI Classrooms Bihar Pilot Expands After Attendance and Quiz Scores Improve', 'ai-classrooms-bihar-pilot', 'The pilot program is being extended after schools reported stronger attendance and faster feedback loops.', '<p>Teachers say auto-generated practice sets reduced correction time and helped them focus on weaker student groups.</p><p>Education officials are reviewing training requirements before the next cluster of schools is added.</p>', 'FatakNews Education', 'https://fataknews.test/ai-classrooms', 'published', 0, 0, 0, 1, 5400, 0, 0, 9, 0, 3, NOW() - INTERVAL 4 DAY, @admin_id, NOW() - INTERVAL 4 DAY - INTERVAL 3 HOUR),
((SELECT id FROM users WHERE username='meera_reporter'), (SELECT id FROM categories WHERE slug='entertainment'), 'news', 'Box Office Friday Surge: Mid-Budget Films Find Strong Evening Occupancy', 'box-office-friday-surge', 'Cinema chains are reporting stronger late-evening turnout for two mid-budget releases this weekend.', '<p>Distributor estimates suggest word-of-mouth is outperforming early projections in urban multiplex circuits.</p><p>Trade analysts now expect a steadier second-day hold than most titles in this budget range usually see.</p>', 'FatakNews Entertainment', 'https://fataknews.test/box-office-friday', 'published', 0, 0, 0, 1, 7600, 0, 0, 16, 0, 2, NOW() - INTERVAL 18 HOUR, @admin_id, NOW() - INTERVAL 19 HOUR),
((SELECT id FROM users WHERE username='riya_editor'), (SELECT id FROM categories WHERE slug='crime'), 'news', 'Cyber Fraud Checklist 2026: What to Freeze First After a UPI Scam', 'cyber-fraud-checklist-2026', 'A practical response guide on what victims should freeze, report, and document in the first hour.', '<p>Cyber cells say the first sixty minutes are critical for freezing wallet chains, preserving evidence, and raising coordinated complaints.</p><p>Victims are advised to contact banks, the cyber helpline, and local police in parallel rather than sequentially.</p>', 'FatakNews Crime', 'https://fataknews.test/cyber-fraud', 'published', 0, 0, 1, 1, 8700, 0, 0, 18, 0, 3, NOW() - INTERVAL 6 HOUR, @admin_id, NOW() - INTERVAL 7 HOUR),
((SELECT id FROM users WHERE username='vikram_manager'), (SELECT id FROM categories WHERE slug='business'), 'news', 'Remote Work Tax Debate Reopens as Finance Teams Push for Clearer State Rules', 'remote-work-tax-debate', 'Companies want simpler guidance on compliance for employees working across state boundaries.', '<p>Finance heads say uncertainty around work location declarations is creating avoidable payroll complexity for distributed teams.</p><p>Industry groups are asking for a more practical rulebook before the next tax cycle begins.</p>', 'FatakNews Business', 'https://fataknews.test/remote-work-tax', 'published', 0, 0, 0, 1, 6900, 0, 0, 12, 0, 3, NOW() - INTERVAL 2 DAY - INTERVAL 5 HOUR, @admin_id, NOW() - INTERVAL 2 DAY - INTERVAL 6 HOUR),
((SELECT id FROM users WHERE username='priya_user'), (SELECT id FROM categories WHERE slug='lifestyle'), 'community_post', 'Lucknow Street Food Map: Eight Stops That Still Feel Worth the Queue', 'lucknow-street-food-map', 'A community post mapping eight reliable evening food stops with timing notes and crowd tips.', '<p>I spent three weekends revisiting old favorites and testing whether they still justify the queue.</p><p>This list is optimized for an evening walking route, not just individual stall quality.</p>', NULL, NULL, 'published', 0, 0, 0, 1, 2100, 0, 0, 4, 0, 2, NOW() - INTERVAL 12 HOUR, @admin_id, NOW() - INTERVAL 13 HOUR),
((SELECT id FROM users WHERE username='arjun_user'), (SELECT id FROM categories WHERE slug='lifestyle'), 'thought', 'Night Shift Notes From the City: Why the Last Bus Still Matters', 'night-shift-notes-from-the-city', 'A reader essay on night transport, safety, and what late commuters notice first.', '<p>The last reliable bus is not just a commute option. It changes how safe a city feels after midnight.</p><p>When routes disappear early, workers, students, and women pay the hidden cost immediately.</p>', NULL, NULL, 'published', 0, 0, 0, 1, 1700, 0, 0, 3, 0, 2, NOW() - INTERVAL 10 HOUR, @admin_id, NOW() - INTERVAL 11 HOUR),
((SELECT id FROM users WHERE username='amit_reporter'), (SELECT id FROM categories WHERE slug='business'), 'news', 'City Budget Live Blog', 'city-budget-live-blog', 'Live notes prepared for the city budget session and awaiting final editorial clearance.', '<p>This draft collects the key tax, transport, and infrastructure lines expected to dominate the budget speech.</p><p>It is still being updated with final source confirmations.</p>', 'FatakNews Budget Desk', 'https://fataknews.test/city-budget-live', 'pending', 0, 0, 0, 1, 0, 0, 0, 0, 0, 2, NULL, NULL, NOW() - INTERVAL 2 HOUR),
((SELECT id FROM users WHERE username='karan_editor'), (SELECT id FROM categories WHERE slug='technology'), 'article', 'Newsroom Tools We Still Need', 'newsroom-tools-we-still-need', 'An internal-style article on what small editorial teams still lack in modern publishing stacks.', '<p>Editors still spend too much time moving copy across systems that should already talk to each other.</p><p>The next wave of newsroom tooling has to solve verification, routing, and packaging together.</p>', NULL, NULL, 'pending', 0, 0, 0, 1, 0, 0, 0, 0, 0, 3, NULL, NULL, NOW() - INTERVAL 90 MINUTE),
((SELECT id FROM users WHERE username='neha_reporter'), (SELECT id FROM categories WHERE slug='crime'), 'news', 'Old Town Traffic Experiment', 'old-town-traffic-experiment', 'A rejected draft on the first-day outcome of a traffic diversion experiment in the old city.', '<p>The story was rejected because source confirmations were incomplete and early conclusions were too broad.</p>', NULL, NULL, 'rejected', 0, 0, 0, 1, 0, 0, 0, 0, 0, 2, NULL, @admin_id, NOW() - INTERVAL 1 DAY);

INSERT INTO posts (
  user_id, category_id, type, title, slug, excerpt, content, video_url, source_name, source_url, status,
  is_breaking, is_featured, is_trending, allow_comments, views_count, likes_count, comments_count,
  shares_count, bookmarks_count, reading_time, published_at, approved_by, location, created_at
) VALUES
((SELECT id FROM users WHERE username='riya_editor'), (SELECT id FROM categories WHERE slug='technology'), 'news', 'Creator Desk Setup Reel: Compact AI Studio Tricks Going Viral', 'creator-desk-setup-reel-viral', 'A quick visual post on how small creator teams are building compact AI-ready recording setups.', '<p>Editors are noticing a jump in creator clips that focus on efficient desk layouts, small lighting rigs, and fast edit pipelines.</p><p>This Explore card is meant to feel like a social-first visual brief rather than a full-length article.</p>', 'https://www.youtube.com/watch?v=ysz5S6PUM-U', 'YouTube', 'https://www.youtube.com/watch?v=ysz5S6PUM-U', 'published', 0, 0, 1, 1, 7400, 0, 0, 32, 0, 2, NOW() - INTERVAL 35 MINUTE, @admin_id, 'explore', NOW() - INTERVAL 40 MINUTE),
((SELECT id FROM users WHERE username='meera_reporter'), (SELECT id FROM categories WHERE slug='entertainment'), 'news', 'Backstage Soundcheck Clip: Indie Tour Visuals Dominate Fan Shares', 'backstage-soundcheck-clip-indie-tour', 'Fan-shot backstage clips are outpacing polished teasers in both shares and repeat views.', '<p>Music pages are leaning into more raw, vertical video moments because fan engagement is stronger when the footage feels immediate and slightly unfiltered.</p><p>The newsroom is curating this as a fast entertainment visual update for Explore.</p>', NULL, 'Instagram', 'https://www.instagram.com/p/CmEsqahLLoZ/', 'published', 0, 0, 1, 1, 6900, 0, 0, 27, 0, 2, NOW() - INTERVAL 1 HOUR, @admin_id, 'explore', NOW() - INTERVAL 70 MINUTE),
((SELECT id FROM users WHERE username='amit_reporter'), (SELECT id FROM categories WHERE slug='business'), 'news', 'Quick Market Explainer: Why Founder Salary Clips Are Pulling Big Engagement', 'founder-salary-clips-big-engagement', 'Short founder salary explainers are drawing more saves than long business panels this week.', '<p>Users are responding to compact breakdowns that convert startup compensation jargon into simple salary bands, dilution examples, and funding-stage context.</p><p>That blend of utility and speed is exactly why the format is now showing up heavily in Explore.</p>', 'https://www.youtube.com/watch?v=jNQXAC9IVRw', 'YouTube', 'https://www.youtube.com/watch?v=jNQXAC9IVRw', 'published', 0, 0, 1, 1, 6400, 0, 0, 21, 0, 2, NOW() - INTERVAL 2 HOUR, @admin_id, 'explore', NOW() - INTERVAL 130 MINUTE),
((SELECT id FROM users WHERE username='neha_reporter'), (SELECT id FROM categories WHERE slug='sports'), 'news', 'Training Ground Short: Fielding Drill Cam Gets Replay Love', 'training-ground-short-fielding-drill', 'A tight fielding-drill clip is getting replay traffic because the movement is easy to study frame by frame.', '<p>Sports audiences are repeatedly watching training-ground shorts that isolate technique, footwork, and timing in under thirty seconds.</p><p>These are the kinds of compact utility clips that perform especially well inside an Explore feed.</p>', NULL, 'X', 'https://x.com/SANDY4AYU/status/2037747927075246501?s=20', 'published', 0, 0, 1, 1, 5900, 0, 0, 18, 0, 2, NOW() - INTERVAL 3 HOUR, @admin_id, 'explore', NOW() - INTERVAL 190 MINUTE),
((SELECT id FROM users WHERE username='karan_editor'), (SELECT id FROM categories WHERE slug='education'), 'news', 'Exam Prep Swipe File: Five Revision Screens Students Are Saving', 'exam-prep-swipe-file-revision-screens', 'Students are saving compact revision slide posts that summarise formulas and timelines in one swipeable set.', '<p>Teachers say fast revision cards are more shareable when each screen handles one concept cleanly and keeps text density low.</p><p>This makes the format useful both for mobile studying and for an Explore-style visual stream.</p>', NULL, 'Instagram', 'https://www.instagram.com/p/DR_JtZiDSk_/', 'published', 0, 0, 1, 1, 5200, 0, 0, 16, 0, 2, NOW() - INTERVAL 4 HOUR, @admin_id, 'explore', NOW() - INTERVAL 250 MINUTE);

INSERT IGNORE INTO tags (name, slug) VALUES
('election','election'),
('budget','budget'),
('cricket','cricket'),
('health','health'),
('education','education'),
('cyber','cyber'),
('community','community'),
('lifestyle','lifestyle'),
('startups','startups'),
('technology','technology'),
('policy','policy');

INSERT INTO post_tags (post_id, tag_id) VALUES
((SELECT id FROM posts WHERE slug='election-war-room-2026'), (SELECT id FROM tags WHERE slug='election')),
((SELECT id FROM posts WHERE slug='election-war-room-2026'), (SELECT id FROM tags WHERE slug='policy')),
((SELECT id FROM posts WHERE slug='startup-funding-wave-2026'), (SELECT id FROM tags WHERE slug='startups')),
((SELECT id FROM posts WHERE slug='startup-funding-wave-2026'), (SELECT id FROM tags WHERE slug='technology')),
((SELECT id FROM posts WHERE slug='cricket-camp-road-to-world-cup'), (SELECT id FROM tags WHERE slug='cricket')),
((SELECT id FROM posts WHERE slug='monsoon-health-alert-2026'), (SELECT id FROM tags WHERE slug='health')),
((SELECT id FROM posts WHERE slug='ai-classrooms-bihar-pilot'), (SELECT id FROM tags WHERE slug='education')),
((SELECT id FROM posts WHERE slug='cyber-fraud-checklist-2026'), (SELECT id FROM tags WHERE slug='cyber')),
((SELECT id FROM posts WHERE slug='lucknow-street-food-map'), (SELECT id FROM tags WHERE slug='community')),
((SELECT id FROM posts WHERE slug='night-shift-notes-from-the-city'), (SELECT id FROM tags WHERE slug='lifestyle'));

INSERT INTO comments (post_id, user_id, parent_id, content, likes_count, is_approved, is_pinned, created_at) VALUES
((SELECT id FROM posts WHERE slug='election-war-room-2026'), (SELECT id FROM users WHERE username='sana_user'), NULL, 'Ground reporting like this is exactly what I wanted to test in the local build.', 2, 1, 0, NOW() - INTERVAL 4 HOUR),
((SELECT id FROM posts WHERE slug='election-war-room-2026'), (SELECT id FROM users WHERE username='priya_user'), NULL, 'The booth-level point is strong. The tone feels realistic.', 1, 1, 0, NOW() - INTERVAL 3 HOUR),
((SELECT id FROM posts WHERE slug='startup-funding-wave-2026'), (SELECT id FROM users WHERE username='arjun_user'), NULL, 'Would love a follow-up with names of the most active tier-two startup hubs.', 0, 1, 0, NOW() - INTERVAL 20 HOUR),
((SELECT id FROM posts WHERE slug='cricket-camp-road-to-world-cup'), (SELECT id FROM users WHERE username='sana_user'), NULL, 'The middle-over angle makes sense. That is where recent games drifted.', 1, 1, 0, NOW() - INTERVAL 1 DAY),
((SELECT id FROM posts WHERE slug='monsoon-health-alert-2026'), (SELECT id FROM users WHERE username='priya_user'), NULL, 'Useful public service story. The quick action list would be good as a graphic too.', 0, 1, 0, NOW() - INTERVAL 2 DAY),
((SELECT id FROM posts WHERE slug='cyber-fraud-checklist-2026'), (SELECT id FROM users WHERE username='vikram_manager'), NULL, 'Practical format. This should be pinned on the homepage when needed.', 3, 1, 1, NOW() - INTERVAL 5 HOUR),
((SELECT id FROM posts WHERE slug='lucknow-street-food-map'), (SELECT id FROM users WHERE username='meera_reporter'), NULL, 'Community posts with local detail like this make the feed feel alive.', 1, 1, 0, NOW() - INTERVAL 8 HOUR),
((SELECT id FROM posts WHERE slug='night-shift-notes-from-the-city'), (SELECT id FROM users WHERE username='riya_editor'), NULL, 'Sharp opinion piece. The opening paragraph lands well.', 1, 1, 0, NOW() - INTERVAL 7 HOUR);

INSERT INTO follows (follower_id, following_id, created_at) VALUES
((SELECT id FROM users WHERE username='priya_user'), (SELECT id FROM users WHERE username='amit_reporter'), NOW() - INTERVAL 5 DAY),
((SELECT id FROM users WHERE username='priya_user'), (SELECT id FROM users WHERE username='riya_editor'), NOW() - INTERVAL 4 DAY),
((SELECT id FROM users WHERE username='priya_user'), (SELECT id FROM users WHERE username='meera_reporter'), NOW() - INTERVAL 2 DAY),
((SELECT id FROM users WHERE username='arjun_user'), (SELECT id FROM users WHERE username='neha_reporter'), NOW() - INTERVAL 3 DAY),
((SELECT id FROM users WHERE username='arjun_user'), (SELECT id FROM users WHERE username='sana_user'), NOW() - INTERVAL 1 DAY),
((SELECT id FROM users WHERE username='sana_user'), (SELECT id FROM users WHERE username='priya_user'), NOW() - INTERVAL 2 DAY),
((SELECT id FROM users WHERE username='sana_user'), (SELECT id FROM users WHERE username='amit_reporter'), NOW() - INTERVAL 6 DAY),
((SELECT id FROM users WHERE username='riya_editor'), (SELECT id FROM users WHERE username='amit_reporter'), NOW() - INTERVAL 8 DAY);

INSERT INTO bookmarks (user_id, post_id, created_at) VALUES
((SELECT id FROM users WHERE username='priya_user'), (SELECT id FROM posts WHERE slug='startup-funding-wave-2026'), NOW() - INTERVAL 1 DAY),
((SELECT id FROM users WHERE username='priya_user'), (SELECT id FROM posts WHERE slug='cricket-camp-road-to-world-cup'), NOW() - INTERVAL 12 HOUR),
((SELECT id FROM users WHERE username='arjun_user'), (SELECT id FROM posts WHERE slug='cyber-fraud-checklist-2026'), NOW() - INTERVAL 5 HOUR),
((SELECT id FROM users WHERE username='sana_user'), (SELECT id FROM posts WHERE slug='lucknow-street-food-map'), NOW() - INTERVAL 6 HOUR),
((SELECT id FROM users WHERE username='riya_editor'), (SELECT id FROM posts WHERE slug='election-war-room-2026'), NOW() - INTERVAL 4 HOUR);

INSERT INTO reactions (user_id, target_type, target_id, reaction_type, created_at) VALUES
((SELECT id FROM users WHERE username='priya_user'), 'post', (SELECT id FROM posts WHERE slug='election-war-room-2026'), 'fire', NOW() - INTERVAL 4 HOUR),
((SELECT id FROM users WHERE username='sana_user'), 'post', (SELECT id FROM posts WHERE slug='election-war-room-2026'), 'like', NOW() - INTERVAL 3 HOUR),
((SELECT id FROM users WHERE username='riya_editor'), 'post', (SELECT id FROM posts WHERE slug='election-war-room-2026'), 'clap', NOW() - INTERVAL 2 HOUR),
((SELECT id FROM users WHERE username='arjun_user'), 'post', (SELECT id FROM posts WHERE slug='startup-funding-wave-2026'), 'like', NOW() - INTERVAL 20 HOUR),
((SELECT id FROM users WHERE username='vikram_manager'), 'post', (SELECT id FROM posts WHERE slug='startup-funding-wave-2026'), 'clap', NOW() - INTERVAL 19 HOUR),
((SELECT id FROM users WHERE username='sana_user'), 'post', (SELECT id FROM posts WHERE slug='startup-funding-wave-2026'), 'wow', NOW() - INTERVAL 18 HOUR),
((SELECT id FROM users WHERE username='priya_user'), 'post', (SELECT id FROM posts WHERE slug='cricket-camp-road-to-world-cup'), 'love', NOW() - INTERVAL 1 DAY),
((SELECT id FROM users WHERE username='arjun_user'), 'post', (SELECT id FROM posts WHERE slug='cricket-camp-road-to-world-cup'), 'like', NOW() - INTERVAL 1 DAY),
((SELECT id FROM users WHERE username='meera_reporter'), 'post', (SELECT id FROM posts WHERE slug='monsoon-health-alert-2026'), 'clap', NOW() - INTERVAL 2 DAY),
((SELECT id FROM users WHERE username='amit_reporter'), 'post', (SELECT id FROM posts WHERE slug='ai-classrooms-bihar-pilot'), 'like', NOW() - INTERVAL 3 DAY),
((SELECT id FROM users WHERE username='riya_editor'), 'post', (SELECT id FROM posts WHERE slug='cyber-fraud-checklist-2026'), 'fire', NOW() - INTERVAL 5 HOUR),
((SELECT id FROM users WHERE username='priya_user'), 'post', (SELECT id FROM posts WHERE slug='cyber-fraud-checklist-2026'), 'like', NOW() - INTERVAL 4 HOUR),
((SELECT id FROM users WHERE username='sana_user'), 'post', (SELECT id FROM posts WHERE slug='cyber-fraud-checklist-2026'), 'wow', NOW() - INTERVAL 4 HOUR),
((SELECT id FROM users WHERE username='admin'), 'post', (SELECT id FROM posts WHERE slug='remote-work-tax-debate'), 'clap', NOW() - INTERVAL 1 DAY),
((SELECT id FROM users WHERE username='meera_reporter'), 'post', (SELECT id FROM posts WHERE slug='lucknow-street-food-map'), 'love', NOW() - INTERVAL 9 HOUR),
((SELECT id FROM users WHERE username='sana_user'), 'post', (SELECT id FROM posts WHERE slug='lucknow-street-food-map'), 'like', NOW() - INTERVAL 8 HOUR),
((SELECT id FROM users WHERE username='riya_editor'), 'post', (SELECT id FROM posts WHERE slug='night-shift-notes-from-the-city'), 'clap', NOW() - INTERVAL 7 HOUR),
((SELECT id FROM users WHERE username='priya_user'), 'post', (SELECT id FROM posts WHERE slug='night-shift-notes-from-the-city'), 'love', NOW() - INTERVAL 6 HOUR);

INSERT INTO notifications (user_id, actor_id, type, title, message, link, is_read, created_at) VALUES
((SELECT id FROM users WHERE username='amit_reporter'), (SELECT id FROM users WHERE username='priya_user'), 'follow', 'New follower', 'Priya Singh started following you.', '/@priya_user', 0, NOW() - INTERVAL 1 DAY),
((SELECT id FROM users WHERE username='priya_user'), (SELECT id FROM users WHERE username='meera_reporter'), 'comment', 'New comment', 'Meera Nair commented on your community post.', '/lifestyle/lucknow-street-food-map', 0, NOW() - INTERVAL 8 HOUR),
((SELECT id FROM users WHERE username='karan_editor'), @admin_id, 'approval', 'Story pending', 'Your article is in the review queue.', '/employee/create', 1, NOW() - INTERVAL 70 MINUTE),
((SELECT id FROM users WHERE username='neha_reporter'), @admin_id, 'review', 'Draft rejected', 'Old Town Traffic Experiment needs stronger source confirmation.', '/employee/create', 0, NOW() - INTERVAL 20 HOUR),
((SELECT id FROM users WHERE username='sonia_hr'), @admin_id, 'task', 'HR reminder', 'Two leave requests are waiting for review.', '/hr/leaves', 0, NOW() - INTERVAL 2 HOUR),
((SELECT id FROM users WHERE username='riya_editor'), (SELECT id FROM users WHERE username='sana_user'), 'follow', 'New follower', 'Sana Qureshi started following you.', '/@sana_user', 1, NOW() - INTERVAL 6 HOUR);

INSERT INTO attendance (user_id, date, check_in, check_out, status, work_hours, notes) VALUES
((SELECT id FROM users WHERE username='vikram_manager'), CURDATE(), '09:05:00', '18:10:00', 'present', 9.08, 'Editorial planning review'),
((SELECT id FROM users WHERE username='riya_editor'), CURDATE(), '09:32:00', '18:41:00', 'present', 9.15, 'Tech desk lead'),
((SELECT id FROM users WHERE username='karan_editor'), CURDATE(), '10:12:00', '19:05:00', 'late', 8.88, 'Visited partner school in the morning'),
((SELECT id FROM users WHERE username='amit_reporter'), CURDATE(), '08:47:00', '17:38:00', 'present', 8.85, 'Field reporting shift'),
((SELECT id FROM users WHERE username='meera_reporter'), CURDATE(), '11:05:00', NULL, 'present', NULL, 'Working on health follow-up'),
((SELECT id FROM users WHERE username='sonia_hr'), CURDATE(), '09:18:00', '18:00:00', 'present', 8.70, 'HR desk');

INSERT INTO leaves (user_id, leave_type_id, from_date, to_date, days, reason, status, approved_by, remarks, applied_at) VALUES
((SELECT id FROM users WHERE username='neha_reporter'), 2, CURDATE() + INTERVAL 3 DAY, CURDATE() + INTERVAL 4 DAY, 2, 'Family event out of station.', 'pending', NULL, NULL, NOW() - INTERVAL 1 DAY),
((SELECT id FROM users WHERE username='karan_editor'), 3, CURDATE() + INTERVAL 10 DAY, CURDATE() + INTERVAL 12 DAY, 3, 'Exam coordination at a partner institution.', 'pending', NULL, NULL, NOW() - INTERVAL 4 HOUR),
((SELECT id FROM users WHERE username='amit_reporter'), 1, CURDATE() - INTERVAL 7 DAY, CURDATE() - INTERVAL 6 DAY, 2, 'Recovered from viral fever.', 'approved', @admin_id, 'Approved by admin for recovery period.', NOW() - INTERVAL 9 DAY);

INSERT INTO payroll (user_id, month, year, basic, hra, allowances, deductions, pf, tds, net_salary, paid, paid_at, created_at) VALUES
((SELECT id FROM users WHERE username='vikram_manager'), 3, 2026, 62000.00, 15500.00, 4500.00, 1200.00, 2200.00, 3100.00, 75500.00, 1, NOW() - INTERVAL 5 DAY, NOW() - INTERVAL 6 DAY),
((SELECT id FROM users WHERE username='riya_editor'), 3, 2026, 52000.00, 13000.00, 3200.00, 950.00, 1800.00, 2400.00, 63050.00, 1, NOW() - INTERVAL 5 DAY, NOW() - INTERVAL 6 DAY),
((SELECT id FROM users WHERE username='amit_reporter'), 3, 2026, 39000.00, 9500.00, 2600.00, 850.00, 1450.00, 1800.00, 47000.00, 0, NULL, NOW() - INTERVAL 6 DAY);

UPDATE users u
SET followers_count = (SELECT COUNT(*) FROM follows f WHERE f.following_id=u.id),
    following_count = (SELECT COUNT(*) FROM follows f WHERE f.follower_id=u.id),
    posts_count = (SELECT COUNT(*) FROM posts p WHERE p.user_id=u.id);

UPDATE posts p
SET comments_count = (SELECT COUNT(*) FROM comments c WHERE c.post_id=p.id),
    bookmarks_count = (SELECT COUNT(*) FROM bookmarks b WHERE b.post_id=p.id),
    likes_count = (SELECT COUNT(*) FROM reactions r WHERE r.target_type='post' AND r.target_id=p.id);

UPDATE categories c
SET posts_count = (SELECT COUNT(*) FROM posts p WHERE p.category_id=c.id AND p.status='published');

UPDATE tags t
SET posts_count = (SELECT COUNT(*) FROM post_tags pt WHERE pt.tag_id=t.id);

COMMIT;

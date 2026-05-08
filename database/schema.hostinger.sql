-- ============================================================
-- FatakNews.in â€” Complete Database Schema
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;


-- ============================================================
-- 1. ROLES
-- ============================================================
CREATE TABLE `roles` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(50)  NOT NULL UNIQUE,
  `slug`        VARCHAR(50)  NOT NULL UNIQUE,
  `permissions` JSON         DEFAULT NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

INSERT INTO `roles` (`name`,`slug`,`permissions`) VALUES
('Super Admin',  'super_admin',  '["all"]'),
('Admin',        'admin',        '["manage_news","manage_users","manage_categories","manage_ads","view_reports","manage_employees"]'),
('Manager',      'manager',      '["manage_news","manage_categories","view_reports","approve_posts"]'),
('Editor',       'editor',       '["create_news","edit_news","manage_categories"]'),
('Reporter',     'reporter',     '["create_news","upload_media"]'),
('HR',           'hr',           '["manage_employees","manage_leaves","view_payroll","post_jobs"]'),
('User',         'user',         '["create_post","comment","follow","react"]');

-- ============================================================
-- 2. USERS
-- ============================================================
CREATE TABLE `users` (
  `id`               BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `role_id`          TINYINT UNSIGNED NOT NULL DEFAULT 7,
  `username`         VARCHAR(50)      NOT NULL UNIQUE,
  `email`            VARCHAR(150)     NOT NULL UNIQUE,
  `phone`            VARCHAR(15)      DEFAULT NULL,
  `password_hash`    VARCHAR(255)     NOT NULL,
  `full_name`        VARCHAR(100)     NOT NULL,
  `google_id`        VARCHAR(191)     DEFAULT NULL,
  `auth_provider`    VARCHAR(30)      DEFAULT NULL,
  `avatar`           VARCHAR(255)     DEFAULT 'default.png',
  `cover_photo`      VARCHAR(255)     DEFAULT NULL,
  `bio`              TEXT             DEFAULT NULL,
  `location`         VARCHAR(100)     DEFAULT NULL,
  `website`          VARCHAR(255)     DEFAULT NULL,
  `is_verified`      TINYINT(1)       NOT NULL DEFAULT 0,
  `is_active`        TINYINT(1)       NOT NULL DEFAULT 1,
  `email_verified`   TINYINT(1)       NOT NULL DEFAULT 0,
  `badge_level`      ENUM('bronze','silver','gold','platinum','press') DEFAULT 'bronze',
  `points`           INT UNSIGNED     NOT NULL DEFAULT 0,
  `followers_count`  INT UNSIGNED     NOT NULL DEFAULT 0,
  `following_count`  INT UNSIGNED     NOT NULL DEFAULT 0,
  `posts_count`      INT UNSIGNED     NOT NULL DEFAULT 0,
  `last_login`       TIMESTAMP        DEFAULT NULL,
  `login_ip`         VARCHAR(45)      DEFAULT NULL,
  `reset_token`      VARCHAR(255)     DEFAULT NULL,
  `reset_expires`    TIMESTAMP        DEFAULT NULL,
  `email_token`      VARCHAR(255)     DEFAULT NULL,
  `two_fa_enabled`   TINYINT(1)       NOT NULL DEFAULT 0,
  `two_fa_secret`    VARCHAR(100)     DEFAULT NULL,
  `created_at`       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_role` (`role_id`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  UNIQUE KEY `uniq_google_id` (`google_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- 3. CATEGORIES (Nested: category > subcategory > child)
-- ============================================================
CREATE TABLE `categories` (
  `id`          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id`   SMALLINT UNSIGNED DEFAULT NULL,
  `name`        VARCHAR(100) NOT NULL,
  `slug`        VARCHAR(120) NOT NULL UNIQUE,
  `description` TEXT         DEFAULT NULL,
  `icon`        VARCHAR(50)  DEFAULT NULL,
  `color`       VARCHAR(7)   DEFAULT '#FF2D2D',
  `cover_image` VARCHAR(255) DEFAULT NULL,
  `level`       TINYINT      NOT NULL DEFAULT 1 COMMENT '1=category, 2=subcategory, 3=child',
  `sort_order`  SMALLINT     NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `is_featured` TINYINT(1)   NOT NULL DEFAULT 0,
  `posts_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_slug`   (`slug`),
  FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO `categories` (`parent_id`,`name`,`slug`,`icon`,`color`,`level`,`sort_order`) VALUES
(NULL,'Politics','politics','fa-landmark','#FF2D2D',1,1),
(NULL,'Business','business','fa-briefcase','#FF6B1A',1,2),
(NULL,'Sports','sports','fa-futbol','#00C853',1,3),
(NULL,'Technology','technology','fa-microchip','#2979FF',1,4),
(NULL,'Entertainment','entertainment','fa-film','#AA00FF',1,5),
(NULL,'Health','health','fa-heartbeat','#00BCD4',1,6),
(NULL,'Education','education','fa-graduation-cap','#FFD700',1,7),
(NULL,'International','international','fa-globe','#FF6B6B',1,8),
(NULL,'Crime','crime','fa-gavel','#795548',1,9),
(NULL,'Lifestyle','lifestyle','fa-star','#E91E63',1,10),
-- Politics sub
(1,'National Politics','national-politics','fa-flag','#FF2D2D',2,1),
(1,'State Politics','state-politics','fa-map-marker','#FF2D2D',2,2),
(1,'Elections','elections','fa-vote-yea','#FF2D2D',2,3),
(1,'Parliament','parliament','fa-landmark','#FF2D2D',2,4),
-- Sports sub
(3,'Cricket','cricket','fa-cricket','#00C853',2,1),
(3,'Football','football','fa-futbol','#00C853',2,2),
(3,'Kabaddi','kabaddi','fa-fist-raised','#00C853',2,3),
(3,'Olympics','olympics','fa-medal','#00C853',2,4),
-- Tech sub
(4,'AI & ML','ai-ml','fa-robot','#2979FF',2,1),
(4,'Startups','startups','fa-rocket','#2979FF',2,2),
(4,'Gadgets','gadgets','fa-mobile','#2979FF',2,3),
(4,'Cybersecurity','cybersecurity','fa-shield','#2979FF',2,4);

-- ============================================================
-- 4. TAGS
-- ============================================================
CREATE TABLE `tags` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(80)  NOT NULL UNIQUE,
  `slug`       VARCHAR(100) NOT NULL UNIQUE,
  `posts_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ============================================================
-- 5. NEWS / POSTS (unified table for news + community posts)
-- ============================================================
CREATE TABLE `posts` (
  `id`              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`         BIGINT UNSIGNED  NOT NULL,
  `category_id`     SMALLINT UNSIGNED DEFAULT NULL,
  `type`            ENUM('news','article','community_post','thought','breaking') NOT NULL DEFAULT 'news',
  `title`           VARCHAR(300)     NOT NULL,
  `slug`            VARCHAR(350)     NOT NULL UNIQUE,
  `excerpt`         TEXT             DEFAULT NULL,
  `content`         LONGTEXT         NOT NULL,
  `thumbnail`       VARCHAR(255)     DEFAULT NULL,
  `image_alt`       VARCHAR(255)     DEFAULT NULL,
  `cover_image`     VARCHAR(255)     DEFAULT NULL,
  `media_gallery`   JSON             DEFAULT NULL,
  `video_url`       VARCHAR(500)     DEFAULT NULL,
  `source_name`     VARCHAR(150)     DEFAULT NULL,
  `source_url`      TEXT             DEFAULT NULL,
  `status`          ENUM('draft','pending','approved','published','rejected','archived') NOT NULL DEFAULT 'draft',
  `is_breaking`     TINYINT(1)       NOT NULL DEFAULT 0,
  `is_featured`     TINYINT(1)       NOT NULL DEFAULT 0,
  `is_trending`     TINYINT(1)       NOT NULL DEFAULT 0,
  `is_pinned`       TINYINT(1)       NOT NULL DEFAULT 0,
  `allow_comments`  TINYINT(1)       NOT NULL DEFAULT 1,
  `views_count`     BIGINT UNSIGNED  NOT NULL DEFAULT 0,
  `likes_count`     INT UNSIGNED     NOT NULL DEFAULT 0,
  `comments_count`  INT UNSIGNED     NOT NULL DEFAULT 0,
  `shares_count`    INT UNSIGNED     NOT NULL DEFAULT 0,
  `bookmarks_count` INT UNSIGNED     NOT NULL DEFAULT 0,
  `reading_time`    TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'in minutes',
  `published_at`    TIMESTAMP        DEFAULT NULL,
  `scheduled_at`    TIMESTAMP        DEFAULT NULL,
  `approved_by`     BIGINT UNSIGNED  DEFAULT NULL,
  `rejected_reason` TEXT             DEFAULT NULL,
  `seo_title`       VARCHAR(300)     DEFAULT NULL,
  `seo_description` VARCHAR(500)     DEFAULT NULL,
  `seo_keywords`    VARCHAR(500)     DEFAULT NULL,
  `location`        VARCHAR(150)     DEFAULT NULL COMMENT 'post placement: home, category, both, explore',
  `created_at`      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user`     (`user_id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_status`   (`status`),
  KEY `idx_type`     (`type`),
  KEY `idx_breaking` (`is_breaking`),
  KEY `idx_trending` (`is_trending`),
  KEY `idx_published`(`published_at`),
  FULLTEXT KEY `ft_search` (`title`,`excerpt`,`content`),
  FOREIGN KEY (`user_id`)      REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`)  REFERENCES `categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`approved_by`)  REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 6. POST TAGS (pivot)
-- ============================================================
CREATE TABLE `post_tags` (
  `post_id` BIGINT UNSIGNED NOT NULL,
  `tag_id`  INT UNSIGNED    NOT NULL,
  PRIMARY KEY (`post_id`,`tag_id`),
  FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`)  REFERENCES `tags`(`id`)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 7. COMMENTS
-- ============================================================
CREATE TABLE `comments` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id`     BIGINT UNSIGNED NOT NULL,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `parent_id`   BIGINT UNSIGNED DEFAULT NULL,
  `content`     TEXT            NOT NULL,
  `likes_count` INT UNSIGNED    NOT NULL DEFAULT 0,
  `is_approved` TINYINT(1)      NOT NULL DEFAULT 1,
  `is_pinned`   TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_post`   (`post_id`),
  KEY `idx_user`   (`user_id`),
  KEY `idx_parent` (`parent_id`),
  FOREIGN KEY (`post_id`)   REFERENCES `posts`(`id`)    ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)    ON DELETE CASCADE,
  FOREIGN KEY (`parent_id`) REFERENCES `comments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 8. REACTIONS (like/love/angry/sad/wow)
-- ============================================================
CREATE TABLE `reactions` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      BIGINT UNSIGNED NOT NULL,
  `target_type`  ENUM('post','comment') NOT NULL,
  `target_id`    BIGINT UNSIGNED NOT NULL,
  `reaction_type` ENUM('like','love','angry','sad','wow','fire','clap') NOT NULL DEFAULT 'like',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reaction` (`user_id`,`target_type`,`target_id`),
  KEY `idx_target` (`target_type`,`target_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 9. FOLLOWS
-- ============================================================
CREATE TABLE `follows` (
  `follower_id`  BIGINT UNSIGNED NOT NULL,
  `following_id` BIGINT UNSIGNED NOT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`follower_id`,`following_id`),
  FOREIGN KEY (`follower_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`following_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 10. BOOKMARKS
-- ============================================================
CREATE TABLE `bookmarks` (
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `post_id`    BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`post_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 11. STORIES
-- ============================================================
CREATE TABLE `stories` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`          BIGINT UNSIGNED NOT NULL,
  `media_type`       ENUM('image','text') NOT NULL DEFAULT 'text',
  `media_path`       VARCHAR(255) DEFAULT NULL,
  `caption`          VARCHAR(280) DEFAULT NULL,
  `background_color` VARCHAR(20) NOT NULL DEFAULT '#2D2244',
  `text_color`       VARCHAR(20) NOT NULL DEFAULT '#FFFFFF',
  `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
  `views_count`      INT UNSIGNED NOT NULL DEFAULT 0,
  `expires_at`       TIMESTAMP NOT NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_story_user` (`user_id`),
  KEY `idx_story_expiry` (`expires_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `story_views` (
  `story_id`   BIGINT UNSIGNED NOT NULL,
  `viewer_id`  BIGINT UNSIGNED NOT NULL,
  `viewed_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`story_id`,`viewer_id`),
  KEY `idx_story_viewer` (`viewer_id`),
  FOREIGN KEY (`story_id`) REFERENCES `stories`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`viewer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 11. NOTIFICATIONS
-- ============================================================
CREATE TABLE `notifications` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `actor_id`    BIGINT UNSIGNED DEFAULT NULL,
  `type`        VARCHAR(50) NOT NULL,
  `title`       VARCHAR(200) DEFAULT NULL,
  `message`     TEXT         DEFAULT NULL,
  `link`        VARCHAR(500) DEFAULT NULL,
  `is_read`     TINYINT(1)   NOT NULL DEFAULT 0,
  `data`        JSON         DEFAULT NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`,`is_read`),
  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`actor_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 12. BADGES
-- ============================================================
CREATE TABLE `badges` (
  `id`          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(80)  NOT NULL,
  `slug`        VARCHAR(80)  NOT NULL UNIQUE,
  `description` TEXT         DEFAULT NULL,
  `icon`        VARCHAR(100) DEFAULT NULL,
  `color`       VARCHAR(7)   DEFAULT '#FFD700',
  `points_req`  INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

INSERT INTO `badges` (`name`,`slug`,`description`,`icon`,`color`,`points_req`) VALUES
('Newcomer','newcomer','Just joined FatakNews','fa-seedling','#8BC34A',0),
('Contributor','contributor','Posted 5+ articles','fa-pen','#2196F3',100),
('Trendsetter','trendsetter','Got 100+ likes','fa-fire','#FF5722',500),
('Verified Reporter','verified-reporter','Official press badge','fa-id-badge','#FFD700',1000),
('Community Star','community-star','Top community contributor','fa-star','#AA00FF',2000);

CREATE TABLE `user_badges` (
  `user_id`    BIGINT UNSIGNED   NOT NULL,
  `badge_id`   SMALLINT UNSIGNED NOT NULL,
  `awarded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`badge_id`),
  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`)  ON DELETE CASCADE,
  FOREIGN KEY (`badge_id`) REFERENCES `badges`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 13. ADVERTISEMENTS
-- ============================================================
CREATE TABLE `ads` (
  `id`           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`        VARCHAR(150) NOT NULL,
  `type`         ENUM('banner','sidebar','inline','popup','video') NOT NULL,
  `position`     VARCHAR(50)  NOT NULL,
  `image`        VARCHAR(255) DEFAULT NULL,
  `link`         VARCHAR(500) DEFAULT NULL,
  `code`         TEXT         DEFAULT NULL COMMENT 'Google Ads / custom HTML',
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `start_date`   DATE         DEFAULT NULL,
  `end_date`     DATE         DEFAULT NULL,
  `impressions`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `clicks`       INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ============================================================
-- 14. HR MODULE â€” DEPARTMENTS, EMPLOYEES, LEAVES, PAYROLL
-- ============================================================
CREATE TABLE `departments` (
  `id`          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `head_id`     BIGINT UNSIGNED DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

INSERT INTO `departments` (`name`) VALUES
('Editorial'),('Reporting'),('Technology'),('HR'),('Finance'),('Marketing'),('Legal');

CREATE TABLE `employee_profiles` (
  `user_id`          BIGINT UNSIGNED NOT NULL,
  `department_id`    SMALLINT UNSIGNED DEFAULT NULL,
  `designation`      VARCHAR(100) DEFAULT NULL,
  `employee_code`    VARCHAR(30)  UNIQUE DEFAULT NULL,
  `joining_date`     DATE         DEFAULT NULL,
  `salary`           DECIMAL(10,2) DEFAULT NULL,
  `bank_account`     VARCHAR(30)  DEFAULT NULL,
  `pan_number`       VARCHAR(20)  DEFAULT NULL,
  `aadhar_number`    VARCHAR(20)  DEFAULT NULL,
  `address`          TEXT         DEFAULT NULL,
  `emergency_contact` VARCHAR(15) DEFAULT NULL,
  `reporting_to`     BIGINT UNSIGNED DEFAULT NULL,
  `is_active`        TINYINT(1)  NOT NULL DEFAULT 1,
  PRIMARY KEY (`user_id`),
  FOREIGN KEY (`user_id`)       REFERENCES `users`(`id`)       ON DELETE CASCADE,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`reporting_to`)  REFERENCES `users`(`id`)       ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `leave_types` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(50) NOT NULL,
  `days_allowed` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

INSERT INTO `leave_types` (`name`,`days_allowed`) VALUES
('Sick Leave',12),('Casual Leave',12),('Earned Leave',15),('Maternity Leave',182),('Paternity Leave',15);

CREATE TABLE `leaves` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `leave_type_id` TINYINT UNSIGNED NOT NULL,
  `from_date`   DATE NOT NULL,
  `to_date`     DATE NOT NULL,
  `days`        TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `reason`      TEXT DEFAULT NULL,
  `status`      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` BIGINT UNSIGNED DEFAULT NULL,
  `remarks`     TEXT DEFAULT NULL,
  `applied_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`)      REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`),
  FOREIGN KEY (`approved_by`)  REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `payroll` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       BIGINT UNSIGNED NOT NULL,
  `month`         TINYINT UNSIGNED NOT NULL,
  `year`          SMALLINT UNSIGNED NOT NULL,
  `basic`         DECIMAL(10,2) NOT NULL DEFAULT 0,
  `hra`           DECIMAL(10,2) NOT NULL DEFAULT 0,
  `allowances`    DECIMAL(10,2) NOT NULL DEFAULT 0,
  `deductions`    DECIMAL(10,2) NOT NULL DEFAULT 0,
  `pf`            DECIMAL(10,2) NOT NULL DEFAULT 0,
  `tds`           DECIMAL(10,2) NOT NULL DEFAULT 0,
  `net_salary`    DECIMAL(10,2) NOT NULL DEFAULT 0,
  `paid`          TINYINT(1)  NOT NULL DEFAULT 0,
  `paid_at`       TIMESTAMP DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payroll` (`user_id`,`month`,`year`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `attendance` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      BIGINT UNSIGNED NOT NULL,
  `date`         DATE NOT NULL,
  `check_in`     TIME DEFAULT NULL,
  `check_out`    TIME DEFAULT NULL,
  `status`       ENUM('present','absent','late','half_day','holiday','leave') NOT NULL DEFAULT 'present',
  `work_hours`   DECIMAL(4,2) DEFAULT NULL,
  `notes`        TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_att` (`user_id`,`date`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 15. NEWSLETTER SUBSCRIBERS
-- ============================================================
CREATE TABLE `newsletter_subscribers` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`          VARCHAR(190) NOT NULL,
  `ip_address`     VARCHAR(64) DEFAULT NULL,
  `user_agent`     VARCHAR(255) DEFAULT NULL,
  `context`        VARCHAR(20) DEFAULT NULL,
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `subscribed_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_newsletter_email` (`email`)
) ENGINE=InnoDB;

-- ============================================================
-- 16. SITE SETTINGS
-- ============================================================
CREATE TABLE `settings` (
  `key`        VARCHAR(100) NOT NULL,
  `value`      TEXT         DEFAULT NULL,
  `group`      VARCHAR(50)  NOT NULL DEFAULT 'general',
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB;

INSERT INTO `settings` (`key`,`value`,`group`) VALUES
('site_name','FatakNews.in','general'),
('site_tagline','Fatafat Khabar â€” Breaking News Every Second','general'),
('site_email','info@fataknews.in','general'),
('site_phone','+91-XXXXXXXXXX','general'),
('posts_per_page','20','general'),
('allow_registration','1','general'),
('maintenance_mode','0','general'),
('breaking_news','','ticker'),
('facebook_url','','social'),
('twitter_url','','social'),
('instagram_url','','social'),
('youtube_url','','social'),
('google_analytics','','analytics'),
('smtp_host','','mail'),
('smtp_port','587','mail'),
('smtp_user','','mail'),
('smtp_pass','','mail');

-- ============================================================
-- 17. ACTIVITY LOGS
-- ============================================================
CREATE TABLE `activity_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED DEFAULT NULL,
  `action`      VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `model`       VARCHAR(50)  DEFAULT NULL,
  `model_id`    BIGINT UNSIGNED DEFAULT NULL,
  `ip_address`  VARCHAR(45) DEFAULT NULL,
  `user_agent`  VARCHAR(300) DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

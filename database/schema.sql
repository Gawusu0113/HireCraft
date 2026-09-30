-- HireCraft database schema (MySQL 8 / MariaDB 10.5+), design v0.1
-- Generated from schema_def.py. Engine: InnoDB, charset utf8mb4.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Areas (suburbs/neighbourhoods, tagged with their city/district) across
-- Ghana with approximate coordinates, used for location matching.
CREATE TABLE IF NOT EXISTS `areas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `district` VARCHAR(80) NULL,
  `latitude` DECIMAL(9,6) NOT NULL,
  `longitude` DECIMAL(9,6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_areas_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trades offered on the platform (plumbing, electrical, carpentry, masonry, painting).
CREATE TABLE IF NOT EXISTS `service_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(60) NOT NULL,
  `slug` VARCHAR(60) NOT NULL,
  `description` VARCHAR(255) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_service_categories_name` (`name`),
  UNIQUE KEY `uq_service_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Specific skills within a trade. complexity_weight drives the rule-based job complexity index.
CREATE TABLE IF NOT EXISTS `skills` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(80) NOT NULL,
  `complexity_weight` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_skills_category_id_name` (`category_id`, `name`),
  CONSTRAINT `fk_skills_category_id` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `ck_skills_complexity_weight` CHECK (complexity_weight BETWEEN 1 AND 3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- All accounts. One row per person; role decides access (RBAC).
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role` ENUM('customer','artisan','admin') NOT NULL,
  `full_name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `email_verified_at` DATETIME NULL,
  `phone_verified_at` DATETIME NULL,
  `status` ENUM('active','pending','suspended') NOT NULL DEFAULT 'active',
  `avatar_path` VARCHAR(255) NULL,
  `last_login_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extra details for customers.
CREATE TABLE IF NOT EXISTS `customer_profiles` (
  `user_id` INT UNSIGNED NOT NULL,
  `area_id` INT UNSIGNED NULL,
  `address_note` VARCHAR(255) NULL,
  `avatar_path` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_customer_profiles_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_customer_profiles_area_id` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Professional profile of an artisan, including cached trust and reputation figures used by the engine.
CREATE TABLE IF NOT EXISTS `artisan_profiles` (
  `user_id` INT UNSIGNED NOT NULL,
  `business_name` VARCHAR(120) NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `bio` TEXT NULL,
  `years_experience` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `base_area_id` INT UNSIGNED NOT NULL,
  `travel_radius_km` TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `accepts_emergency` TINYINT(1) NOT NULL DEFAULT 0,
  `avatar_path` VARCHAR(255) NULL,
  `profile_completeness` DECIMAL(4,3) NOT NULL DEFAULT 0,
  `verification_level` ENUM('basic','phone','email','identity','skill','full') NOT NULL DEFAULT 'basic',
  `trust_score` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `approval_status` ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  `rating_mean` DECIMAL(3,2) NOT NULL DEFAULT 0,
  `rating_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `completed_jobs` INT UNSIGNED NOT NULL DEFAULT 0,
  `completion_rate` DECIMAL(4,3) NOT NULL DEFAULT 0,
  `response_rate` DECIMAL(4,3) NOT NULL DEFAULT 0,
  `dispute_free_rate` DECIMAL(4,3) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  KEY `ix_artisan_profiles_category_id_approval_status` (`category_id`, `approval_status`),
  KEY `ix_artisan_profiles_base_area_id` (`base_area_id`),
  CONSTRAINT `fk_artisan_profiles_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_artisan_profiles_category_id` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_artisan_profiles_base_area_id` FOREIGN KEY (`base_area_id`) REFERENCES `areas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Skills an artisan offers (many-to-many).
CREATE TABLE IF NOT EXISTS `artisan_skills` (
  `artisan_id` INT UNSIGNED NOT NULL,
  `skill_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`artisan_id`, `skill_id`),
  CONSTRAINT `fk_artisan_skills_artisan_id` FOREIGN KEY (`artisan_id`) REFERENCES `artisan_profiles` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_artisan_skills_skill_id` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Areas an artisan serves (many-to-many).
CREATE TABLE IF NOT EXISTS `artisan_service_areas` (
  `artisan_id` INT UNSIGNED NOT NULL,
  `area_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`artisan_id`, `area_id`),
  CONSTRAINT `fk_artisan_service_areas_artisan_id` FOREIGN KEY (`artisan_id`) REFERENCES `artisan_profiles` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_artisan_service_areas_area_id` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Weekly working pattern.
CREATE TABLE IF NOT EXISTS `artisan_availability` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `artisan_id` INT UNSIGNED NOT NULL,
  `weekday` TINYINT UNSIGNED NOT NULL,
  `start_time` TIME NOT NULL DEFAULT '08:00:00',
  `end_time` TIME NOT NULL DEFAULT '17:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_artisan_availability_artisan_id_weekday` (`artisan_id`, `weekday`),
  CONSTRAINT `fk_artisan_availability_artisan_id` FOREIGN KEY (`artisan_id`) REFERENCES `artisan_profiles` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ck_artisan_availability_weekday` CHECK (weekday BETWEEN 0 AND 6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dates the artisan is not available.
CREATE TABLE IF NOT EXISTS `artisan_time_off` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `artisan_id` INT UNSIGNED NOT NULL,
  `off_date` DATE NOT NULL,
  `reason` VARCHAR(120) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_artisan_time_off_artisan_id_off_date` (`artisan_id`, `off_date`),
  CONSTRAINT `fk_artisan_time_off_artisan_id` FOREIGN KEY (`artisan_id`) REFERENCES `artisan_profiles` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Typical labour price range per job complexity, entered by the artisan. Used for budget compatibility.
CREATE TABLE IF NOT EXISTS `artisan_price_ranges` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `artisan_id` INT UNSIGNED NOT NULL,
  `complexity` ENUM('simple','medium','complex') NOT NULL,
  `min_price` DECIMAL(10,2) NOT NULL,
  `max_price` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_artisan_price_ranges_artisan_id_complexity` (`artisan_id`, `complexity`),
  CONSTRAINT `fk_artisan_price_ranges_artisan_id` FOREIGN KEY (`artisan_id`) REFERENCES `artisan_profiles` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ck_artisan_price_ranges_rule1` CHECK (min_price >= 0 AND max_price >= min_price)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verification evidence and admin decisions. Documents are demo or consented files only; ID numbers are never stored.
CREATE TABLE IF NOT EXISTS `verifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `artisan_id` INT UNSIGNED NOT NULL,
  `type` ENUM('identity','skill','reference') NOT NULL,
  `document_type` VARCHAR(60) NULL,
  `document_path` VARCHAR(255) NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` INT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `reject_reason` VARCHAR(255) NULL,
  `submitted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_verifications_status_type` (`status`, `type`),
  CONSTRAINT `fk_verifications_artisan_id` FOREIGN KEY (`artisan_id`) REFERENCES `artisan_profiles` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_verifications_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Previous projects an artisan shows to customers.
CREATE TABLE IF NOT EXISTS `portfolios` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `artisan_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(120) NOT NULL,
  `description` TEXT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `completed_on` DATE NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_portfolios_artisan_id` FOREIGN KEY (`artisan_id`) REFERENCES `artisan_profiles` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_portfolios_category_id` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Before, after and other photos for a portfolio item.
CREATE TABLE IF NOT EXISTS `portfolio_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `portfolio_id` INT UNSIGNED NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `kind` ENUM('before','after','other') NOT NULL DEFAULT 'other',
  `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_portfolio_images_portfolio_id` FOREIGN KEY (`portfolio_id`) REFERENCES `portfolios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Skills demonstrated by a portfolio item; enables similar-work matching.
CREATE TABLE IF NOT EXISTS `portfolio_skills` (
  `portfolio_id` INT UNSIGNED NOT NULL,
  `skill_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`portfolio_id`, `skill_id`),
  CONSTRAINT `fk_portfolio_skills_portfolio_id` FOREIGN KEY (`portfolio_id`) REFERENCES `portfolios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_portfolio_skills_skill_id` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit trail of every trust score calculation with its three parts.
CREATE TABLE IF NOT EXISTS `trust_score_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `artisan_id` INT UNSIGNED NOT NULL,
  `score` TINYINT UNSIGNED NOT NULL,
  `verification_part` DECIMAL(5,2) NOT NULL,
  `profile_part` DECIMAL(5,2) NOT NULL,
  `reliability_part` DECIMAL(5,2) NOT NULL,
  `reason` VARCHAR(120) NULL,
  `computed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_trust_score_history_artisan_id_computed_at` (`artisan_id`, `computed_at`),
  CONSTRAINT `fk_trust_score_history_artisan_id` FOREIGN KEY (`artisan_id`) REFERENCES `artisan_profiles` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jobs posted by customers, with budget, location, timing and derived complexity.
CREATE TABLE IF NOT EXISTS `job_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `description` TEXT NOT NULL,
  `area_id` INT UNSIGNED NOT NULL,
  `address_note` VARCHAR(255) NULL,
  `preferred_date` DATE NULL,
  `preferred_time` ENUM('morning','afternoon','evening','any') NOT NULL DEFAULT 'any',
  `urgency` ENUM('low','normal','high','emergency') NOT NULL DEFAULT 'normal',
  `budget_type` ENUM('range','fixed','unknown') NOT NULL DEFAULT 'range',
  `budget_min` DECIMAL(10,2) NULL,
  `budget_max` DECIMAL(10,2) NULL,
  `is_negotiable` TINYINT(1) NOT NULL DEFAULT 0,
  `visibility` ENUM('public','matching_only','private') NOT NULL DEFAULT 'matching_only',
  `special_requirements` TEXT NULL,
  `complexity` ENUM('simple','medium','complex') NULL,
  `is_multi_trade` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('draft','posted','matched','quoted','hired','in_progress','completed','reviewed','cancelled','expired','disputed') NOT NULL DEFAULT 'posted',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_job_requests_customer_id_status` (`customer_id`, `status`),
  KEY `ix_job_requests_category_id_area_id_status` (`category_id`, `area_id`, `status`),
  CONSTRAINT `fk_job_requests_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_job_requests_category_id` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_job_requests_area_id` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `ck_job_requests_rule1` CHECK (budget_max IS NULL OR budget_min IS NULL OR budget_max >= budget_min)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photos of the job site uploaded by the customer.
CREATE TABLE IF NOT EXISTS `job_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_request_id` INT UNSIGNED NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `caption` VARCHAR(120) NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_job_images_job_request_id` FOREIGN KEY (`job_request_id`) REFERENCES `job_requests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Skills the job needs (many-to-many).
CREATE TABLE IF NOT EXISTS `job_required_skills` (
  `job_request_id` INT UNSIGNED NOT NULL,
  `skill_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`job_request_id`, `skill_id`),
  CONSTRAINT `fk_job_required_skills_job_request_id` FOREIGN KEY (`job_request_id`) REFERENCES `job_requests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_job_required_skills_skill_id` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Budget compatibility assessment for a job, with benchmark evidence and confidence.
CREATE TABLE IF NOT EXISTS `budget_analysis_results` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_request_id` INT UNSIGNED NOT NULL,
  `budget_min` DECIMAL(10,2) NULL,
  `budget_max` DECIMAL(10,2) NULL,
  `assessment` ENUM('likely_sufficient','possibly_insufficient','negotiation_recommended','highly_competitive','high_budget','insufficient_data') NOT NULL,
  `confidence_level` ENUM('none','low','medium','high') NOT NULL,
  `benchmark_n` INT UNSIGNED NOT NULL DEFAULT 0,
  `benchmark_p25` DECIMAL(10,2) NULL,
  `benchmark_median` DECIMAL(10,2) NULL,
  `benchmark_p75` DECIMAL(10,2) NULL,
  `reason` TEXT NOT NULL,
  `recommended_action` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_budget_analysis_results_job_request_id_created_at` (`job_request_id`, `created_at`),
  CONSTRAINT `fk_budget_analysis_results_job_request_id` FOREIGN KEY (`job_request_id`) REFERENCES `job_requests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Job feasibility level with counts and reasons.
CREATE TABLE IF NOT EXISTS `job_feasibility_results` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_request_id` INT UNSIGNED NOT NULL,
  `level` ENUM('high','medium','low','requires_more_information') NOT NULL,
  `suitable_artisans` INT UNSIGNED NOT NULL DEFAULT 0,
  `available_on_date` INT UNSIGNED NOT NULL DEFAULT 0,
  `reasons` JSON NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_job_feasibility_results_job_request_id_created_at` (`job_request_id`, `created_at`),
  CONSTRAINT `fk_job_feasibility_results_job_request_id` FOREIGN KEY (`job_request_id`) REFERENCES `job_requests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Applications by artisans and direct requests by customers for a job.
CREATE TABLE IF NOT EXISTS `artisan_job_applications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_request_id` INT UNSIGNED NOT NULL,
  `artisan_id` INT UNSIGNED NOT NULL,
  `origin` ENUM('applied','requested') NOT NULL DEFAULT 'applied',
  `introduction` TEXT NULL,
  `proposed_price` DECIMAL(10,2) NULL,
  `estimated_days` SMALLINT UNSIGNED NULL,
  `portfolio_id` INT UNSIGNED NULL,
  `match_score_at_time` DECIMAL(5,2) NULL,
  `status` ENUM('submitted','shortlisted','accepted','declined','withdrawn') NOT NULL DEFAULT 'submitted',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_artisan_job_applications_job_request_id_artisan_id` (`job_request_id`, `artisan_id`),
  CONSTRAINT `fk_artisan_job_applications_job_request_id` FOREIGN KEY (`job_request_id`) REFERENCES `job_requests` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_artisan_job_applications_artisan_id` FOREIGN KEY (`artisan_id`) REFERENCES `artisan_profiles` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_artisan_job_applications_portfolio_id` FOREIGN KEY (`portfolio_id`) REFERENCES `portfolios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Itemised price offers from artisans.
CREATE TABLE IF NOT EXISTS `quotations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id` INT UNSIGNED NOT NULL,
  `labour_cost` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `material_cost` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `transport_cost` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `additional_charges` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `total_cost` DECIMAL(10,2) NOT NULL,
  `estimated_days` SMALLINT UNSIGNED NULL,
  `notes` VARCHAR(500) NULL,
  `status` ENUM('sent','accepted','rejected','clarification','expired') NOT NULL DEFAULT 'sent',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `responded_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_quotations_application_id` FOREIGN KEY (`application_id`) REFERENCES `artisan_job_applications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The hired job (work order) created when a customer accepts an artisan.
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_request_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `artisan_id` INT UNSIGNED NOT NULL,
  `quotation_id` INT UNSIGNED NULL,
  `agreed_price` DECIMAL(10,2) NULL,
  `scheduled_date` DATE NULL,
  `started_at` DATETIME NULL,
  `completed_at` DATETIME NULL,
  `confirmed_at` DATETIME NULL,
  `status` ENUM('scheduled','in_progress','completed','confirmed','cancelled') NOT NULL DEFAULT 'scheduled',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jobs_job_request_id` (`job_request_id`),
  KEY `ix_jobs_artisan_id_status` (`artisan_id`, `status`),
  KEY `ix_jobs_customer_id_status` (`customer_id`, `status`),
  CONSTRAINT `fk_jobs_job_request_id` FOREIGN KEY (`job_request_id`) REFERENCES `job_requests` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_jobs_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_jobs_artisan_id` FOREIGN KEY (`artisan_id`) REFERENCES `artisan_profiles` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_jobs_quotation_id` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Progress notes and status changes on a hired job.
CREATE TABLE IF NOT EXISTS `job_updates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_id` INT UNSIGNED NOT NULL,
  `updated_by` INT UNSIGNED NOT NULL,
  `status` ENUM('scheduled','in_progress','completed','confirmed','cancelled') NOT NULL,
  `note` VARCHAR(500) NULL,
  `image_path` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_job_updates_job_id` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_job_updates_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One review per confirmed job. Only the customer of a confirmed job may review; no self-reviews.
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `artisan_id` INT UNSIGNED NOT NULL,
  `overall` TINYINT UNSIGNED NOT NULL,
  `quality` TINYINT UNSIGNED NOT NULL,
  `professionalism` TINYINT UNSIGNED NOT NULL,
  `communication` TINYINT UNSIGNED NOT NULL,
  `punctuality` TINYINT UNSIGNED NOT NULL,
  `comment` VARCHAR(1000) NULL,
  `status` ENUM('published','hidden') NOT NULL DEFAULT 'published',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reviews_job_id` (`job_id`),
  KEY `ix_reviews_artisan_id_status` (`artisan_id`, `status`),
  CONSTRAINT `fk_reviews_job_id` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_reviews_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_reviews_artisan_id` FOREIGN KEY (`artisan_id`) REFERENCES `artisan_profiles` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `ck_reviews_overall` CHECK (overall BETWEEN 1 AND 5),
  CONSTRAINT `ck_reviews_quality` CHECK (quality BETWEEN 1 AND 5),
  CONSTRAINT `ck_reviews_professionalism` CHECK (professionalism BETWEEN 1 AND 5),
  CONSTRAINT `ck_reviews_communication` CHECK (communication BETWEEN 1 AND 5),
  CONSTRAINT `ck_reviews_punctuality` CHECK (punctuality BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Artisans saved by customers.
CREATE TABLE IF NOT EXISTS `favorites` (
  `customer_id` INT UNSIGNED NOT NULL,
  `artisan_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`customer_id`, `artisan_id`),
  CONSTRAINT `fk_favorites_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_favorites_artisan_id` FOREIGN KEY (`artisan_id`) REFERENCES `artisan_profiles` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evaluation data: did the recommendations help, and why was an artisan chosen? Supports RQ3.
CREATE TABLE IF NOT EXISTS `recommendation_feedback` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_request_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `chosen_artisan_id` INT UNSIGNED NULL,
  `helpful` ENUM('yes','no','partially') NOT NULL,
  `reason_chosen` ENUM('best_match','lowest_price','highest_rating','most_trusted','most_experienced','recommended_by_hirecraft','other') NULL,
  `understood_explanation` TINYINT UNSIGNED NULL,
  `chosen_rank` TINYINT UNSIGNED NULL,
  `comment` VARCHAR(500) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_recommendation_feedback_job_request_id` FOREIGN KEY (`job_request_id`) REFERENCES `job_requests` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_recommendation_feedback_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_recommendation_feedback_chosen_artisan_id` FOREIGN KEY (`chosen_artisan_id`) REFERENCES `artisan_profiles` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `ck_recommendation_feedback_understood_explanation` CHECK (understood_explanation IS NULL OR understood_explanation BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Versioned matching weights and thresholds set by administrators. Weights are never hard-coded.
CREATE TABLE IF NOT EXISTS `matching_config` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `config_version` INT UNSIGNED NOT NULL,
  `weights` JSON NOT NULL,
  `parameters` JSON NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 0,
  `changed_by` INT UNSIGNED NULL,
  `note` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_matching_config_config_version` (`config_version`),
  CONSTRAINT `fk_matching_config_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Price quartiles per trade and complexity, from platform jobs, survey data or clearly labelled seed data.
CREATE TABLE IF NOT EXISTS `price_benchmarks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `complexity` ENUM('simple','medium','complex') NOT NULL,
  `observations` INT UNSIGNED NOT NULL,
  `p25` DECIMAL(10,2) NOT NULL,
  `median` DECIMAL(10,2) NOT NULL,
  `p75` DECIMAL(10,2) NOT NULL,
  `source` ENUM('platform','survey','seed') NOT NULL DEFAULT 'seed',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_price_benchmarks_category_id_complexity_source` (`category_id`, `complexity`, `source`),
  CONSTRAINT `fk_price_benchmarks_category_id` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- In-app notifications.
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(40) NOT NULL,
  `title` VARCHAR(120) NOT NULL,
  `body` VARCHAR(255) NULL,
  `link` VARCHAR(255) NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_notifications_user_id_is_read_created_at` (`user_id`, `is_read`, `created_at`),
  CONSTRAINT `fk_notifications_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Job-specific private messages between customer and artisan.
CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_request_id` INT UNSIGNED NOT NULL,
  `sender_id` INT UNSIGNED NOT NULL,
  `recipient_id` INT UNSIGNED NOT NULL,
  `body` VARCHAR(1000) NOT NULL,
  `read_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_messages_job_request_id_created_at` (`job_request_id`, `created_at`),
  CONSTRAINT `fk_messages_job_request_id` FOREIGN KEY (`job_request_id`) REFERENCES `job_requests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_messages_sender_id` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_messages_recipient_id` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit log of administrator actions.
CREATE TABLE IF NOT EXISTS `admin_actions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` INT UNSIGNED NOT NULL,
  `action` VARCHAR(60) NOT NULL,
  `target_type` VARCHAR(40) NOT NULL,
  `target_id` INT UNSIGNED NULL,
  `details` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_admin_actions_admin_id_created_at` (`admin_id`, `created_at`),
  CONSTRAINT `fk_admin_actions_admin_id` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stored SmartMatch result for each job-artisan pair: total, eight component points, level and explanation.
CREATE TABLE IF NOT EXISTS `artisan_job_matches` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_request_id` INT UNSIGNED NOT NULL,
  `artisan_id` INT UNSIGNED NOT NULL,
  `config_version` INT UNSIGNED NOT NULL,
  `overall_match_score` DECIMAL(5,2) NOT NULL,
  `skill_match_score` DECIMAL(5,2) NOT NULL,
  `location_match_score` DECIMAL(5,2) NOT NULL,
  `budget_match_score` DECIMAL(5,2) NOT NULL,
  `availability_score` DECIMAL(5,2) NOT NULL,
  `experience_score` DECIMAL(5,2) NOT NULL,
  `trust_score` DECIMAL(5,2) NOT NULL,
  `rating_score` DECIMAL(5,2) NOT NULL,
  `portfolio_relevance_score` DECIMAL(5,2) NOT NULL,
  `recommendation_level` ENUM('highly_recommended','recommended','possible_match','low_compatibility') NOT NULL,
  `capped_reason` VARCHAR(160) NULL,
  `explanation` JSON NOT NULL,
  `weights_snapshot` JSON NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_artisan_job_matches_job_request_id_artisan_id_config_version` (`job_request_id`, `artisan_id`, `config_version`),
  KEY `ix_artisan_job_matches_job_request_id_overall_match_score` (`job_request_id`, `overall_match_score`),
  KEY `ix_artisan_job_matches_artisan_id_overall_match_score` (`artisan_id`, `overall_match_score`),
  CONSTRAINT `fk_artisan_job_matches_job_request_id` FOREIGN KEY (`job_request_id`) REFERENCES `job_requests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_artisan_job_matches_artisan_id` FOREIGN KEY (`artisan_id`) REFERENCES `artisan_profiles` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_artisan_job_matches_config_version` FOREIGN KEY (`config_version`) REFERENCES `matching_config` (`config_version`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Milestones for large/multi-trade jobs, tracked separately from the single completion status on `jobs`.
CREATE TABLE IF NOT EXISTS `job_milestones` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `estimated_cost` DECIMAL(10,2) NULL,
  `estimated_date` DATE NULL,
  `status` ENUM('pending','in_progress','completed') NOT NULL DEFAULT 'pending',
  `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_job_milestones_job_id_sort_order` (`job_id`, `sort_order`),
  CONSTRAINT `fk_job_milestones_job_id` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A dispute opened by a customer or artisan against a hired job.
CREATE TABLE IF NOT EXISTS `disputes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_id` INT UNSIGNED NOT NULL,
  `opened_by` INT UNSIGNED NOT NULL,
  `against_user_id` INT UNSIGNED NULL,
  `issue_type` ENUM('poor_workmanship','job_not_completed','artisan_no_show','pricing_dispute','suspicious_behavior','other') NOT NULL,
  `description` TEXT NOT NULL,
  `status` ENUM('open','under_review','waiting_for_response','resolved','closed') NOT NULL DEFAULT 'open',
  `resolution` TEXT NULL,
  `resolved_by` INT UNSIGNED NULL,
  `resolved_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_disputes_job_id` (`job_id`),
  KEY `ix_disputes_status_created_at` (`status`, `created_at`),
  CONSTRAINT `fk_disputes_job_id` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_disputes_opened_by` FOREIGN KEY (`opened_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_disputes_against_user_id` FOREIGN KEY (`against_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_disputes_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notes/photos either party adds to a dispute.
CREATE TABLE IF NOT EXISTS `dispute_evidence` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dispute_id` INT UNSIGNED NOT NULL,
  `submitted_by` INT UNSIGNED NOT NULL,
  `note` VARCHAR(1000) NOT NULL,
  `image_path` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_dispute_evidence_dispute_id_created_at` (`dispute_id`, `created_at`),
  CONSTRAINT `fk_dispute_evidence_dispute_id` FOREIGN KEY (`dispute_id`) REFERENCES `disputes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dispute_evidence_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User-submitted safety reports: fraud, fake profiles, harassment, suspicious behavior, fake reviews.
CREATE TABLE IF NOT EXISTS `user_reports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reporter_id` INT UNSIGNED NOT NULL,
  `reported_user_id` INT UNSIGNED NULL,
  `reported_review_id` INT UNSIGNED NULL,
  `reason` ENUM('fraud','fake_profile','harassment','suspicious_behavior','inappropriate_messages','fake_review') NOT NULL,
  `details` TEXT NULL,
  `status` ENUM('open','reviewed','dismissed','action_taken') NOT NULL DEFAULT 'open',
  `reviewed_by` INT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `admin_note` VARCHAR(500) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_user_reports_status_created_at` (`status`, `created_at`),
  CONSTRAINT `fk_user_reports_reporter_id` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_user_reports_reported_user_id` FOREIGN KEY (`reported_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_user_reports_reported_review_id` FOREIGN KEY (`reported_review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_user_reports_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

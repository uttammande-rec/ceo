-- StudentOrbit CEO Dashboard — schema
-- Idempotent: safe to run repeatedly.

CREATE DATABASE IF NOT EXISTS `studentorbit`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `studentorbit`;

CREATE TABLE IF NOT EXISTS `phases` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(100) NOT NULL,
  `position`    TINYINT NOT NULL UNIQUE,
  `description` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `verticals` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `name`            VARCHAR(100) NOT NULL,
  `slug`            VARCHAR(50) NOT NULL UNIQUE,
  `revenue_target`  BIGINT NOT NULL DEFAULT 0,
  `revenue_actual`  BIGINT NOT NULL DEFAULT 0,
  `students_target` INT NOT NULL DEFAULT 0,
  `students_actual` INT NOT NULL DEFAULT 0,
  `color`           VARCHAR(7) NULL,
  `sort_order`      TINYINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tasks` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `title`          VARCHAR(200) NOT NULL,
  `phase_id`       INT NOT NULL,
  `vertical_id`    INT NULL,
  `status`         ENUM('todo','in_progress','review','done') NOT NULL DEFAULT 'todo',
  `owner`          VARCHAR(100) NULL,
  `priority`       ENUM('high','medium','low') NOT NULL DEFAULT 'medium',
  `due_date`       DATE NULL,
  `board_position` INT NOT NULL DEFAULT 0,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_tasks_phase` (`phase_id`),
  INDEX `idx_tasks_status` (`status`),
  INDEX `idx_tasks_vertical` (`vertical_id`),
  CONSTRAINT `fk_tasks_phase` FOREIGN KEY (`phase_id`) REFERENCES `phases`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_tasks_vertical` FOREIGN KEY (`vertical_id`) REFERENCES `verticals`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `leads` (
  `id`                INT AUTO_INCREMENT PRIMARY KEY,
  `name`              VARCHAR(120) NOT NULL,
  `phone`             VARCHAR(20) NULL,
  `college`           VARCHAR(150) NULL,
  `vertical_id`       INT NULL,
  `source`            ENUM('workshop','webinar','ambassador','social') NULL,
  `stage`             ENUM('new','contacted','interested','converted','lost') NOT NULL DEFAULT 'new',
  `followup_date`     DATE NULL,
  `notes`             TEXT NULL,
  `pipeline_position` INT NOT NULL DEFAULT 0,
  `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_leads_stage` (`stage`),
  INDEX `idx_leads_followup` (`followup_date`),
  CONSTRAINT `fk_leads_vertical` FOREIGN KEY (`vertical_id`) REFERENCES `verticals`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `todos` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `text`       VARCHAR(255) NOT NULL,
  `done`       TINYINT(1) NOT NULL DEFAULT 0,
  `bucket`     ENUM('today','week') NOT NULL DEFAULT 'today',
  `position`   INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
  `k` VARCHAR(50) PRIMARY KEY,
  `v` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `email`         VARCHAR(190) NOT NULL UNIQUE,
  `name`          VARCHAR(120) NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `email`      VARCHAR(190) NOT NULL,
  `otp_hash`   VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used`       TINYINT(1) NOT NULL DEFAULT 0,
  `attempts`   TINYINT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_pr_email` (`email`),
  INDEX `idx_pr_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

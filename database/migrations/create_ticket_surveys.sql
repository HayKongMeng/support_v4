-- Migration: Create ticket_surveys table
-- Date: 2026-02-09
-- Description: Creates table for customer satisfaction surveys sent after ticket resolution

CREATE TABLE IF NOT EXISTS `ticket_surveys` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `ticket_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `rating` TINYINT UNSIGNED DEFAULT NULL COMMENT '1-5 stars',
    `comment` TEXT DEFAULT NULL,
    `survey_sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `rated_at` TIMESTAMP NULL DEFAULT NULL,
    `comment_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ticket` (`ticket_id`),
    INDEX `idx_company` (`company_id`),
    INDEX `idx_rating` (`rating`),
    CONSTRAINT `fk_survey_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_survey_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_survey_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

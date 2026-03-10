-- Mini App persona/profile table (one-time customer/staff selection + hierarchy mapping)

CREATE TABLE IF NOT EXISTS `telegram_miniapp_profiles` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `telegram_user_id` BIGINT NOT NULL,
    `role_type` ENUM('customer', 'staff') NOT NULL,
    `linked_user_id` INT UNSIGNED DEFAULT NULL,
    `matched_name` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_miniapp_profile_company_telegram` (`company_id`, `telegram_user_id`),
    INDEX `idx_miniapp_profile_company` (`company_id`),
    INDEX `idx_miniapp_profile_user` (`linked_user_id`),
    CONSTRAINT `fk_miniapp_profile_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_miniapp_profile_user` FOREIGN KEY (`linked_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

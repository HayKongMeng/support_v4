-- Telegram User States Table
-- Add this to your database

CREATE TABLE IF NOT EXISTS `telegram_user_states` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `chat_id` BIGINT NOT NULL,
    `state` VARCHAR(50) NOT NULL DEFAULT 'none',
    `data` JSON DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_company_chat` (`company_id`, `chat_id`),
    INDEX `idx_chat_id` (`chat_id`),
    CONSTRAINT `fk_telegram_states_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

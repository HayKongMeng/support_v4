-- Telegram core tables required by webhook + mini app

CREATE TABLE IF NOT EXISTS `telegram_configs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `bot_token` VARCHAR(255) NOT NULL,
    `bot_username` VARCHAR(100) DEFAULT NULL,
    `webhook_secret` VARCHAR(100) DEFAULT NULL,
    `welcome_message` TEXT DEFAULT NULL,
    `default_category_id` INT UNSIGNED DEFAULT NULL,
    `notify_assigned_agents` TINYINT(1) NOT NULL DEFAULT 0,
    `alert_group_chat_id` BIGINT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_company` (`company_id`),
    CONSTRAINT `fk_telegram_config_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_telegram_config_category` FOREIGN KEY (`default_category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

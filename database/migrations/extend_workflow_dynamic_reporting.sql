-- Dynamic reporting mappings for hierarchy routing.

CREATE TABLE IF NOT EXISTS `user_reporting` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `supervisor_user_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_reporting_company_user` (`company_id`, `user_id`),
    INDEX `idx_user_reporting_company` (`company_id`),
    INDEX `idx_user_reporting_user` (`user_id`),
    INDEX `idx_user_reporting_supervisor` (`supervisor_user_id`),
    CONSTRAINT `fk_user_reporting_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_reporting_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_reporting_supervisor` FOREIGN KEY (`supervisor_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_account_owners` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `customer_user_id` INT UNSIGNED NOT NULL,
    `owner_user_id` INT UNSIGNED NOT NULL COMMENT 'Sales/account owner',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_customer_owner_company_customer` (`company_id`, `customer_user_id`),
    INDEX `idx_customer_owner_company` (`company_id`),
    INDEX `idx_customer_owner_customer` (`customer_user_id`),
    INDEX `idx_customer_owner_owner` (`owner_user_id`),
    CONSTRAINT `fk_customer_owner_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_customer_owner_customer` FOREIGN KEY (`customer_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_customer_owner_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `workflow_steps`
    MODIFY COLUMN `approver_type` ENUM(
        'user',
        'role',
        'department_manager',
        'customer_owner',
        'customer_owner_supervisor'
    ) NOT NULL DEFAULT 'user';

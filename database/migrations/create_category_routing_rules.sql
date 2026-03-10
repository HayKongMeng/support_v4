-- Category routing rules for workflow hierarchy.
-- This lets each category choose how assignment should happen.

CREATE TABLE IF NOT EXISTS `category_routing_rules` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `route_mode` ENUM('workflow_default', 'staff_supervisor', 'department_queue') NOT NULL DEFAULT 'workflow_default',
    `department_id` INT UNSIGNED DEFAULT NULL,
    `queue_strategy` ENUM('least_open', 'round_robin') NOT NULL DEFAULT 'least_open',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_category_routing_company_category` (`company_id`, `category_id`),
    INDEX `idx_category_routing_company` (`company_id`),
    INDEX `idx_category_routing_category` (`category_id`),
    INDEX `idx_category_routing_department` (`department_id`),
    CONSTRAINT `fk_category_routing_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_category_routing_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_category_routing_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

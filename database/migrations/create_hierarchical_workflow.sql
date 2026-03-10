-- Hierarchical workflow foundation for multi-level routing/escalation

CREATE TABLE IF NOT EXISTS `departments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `parent_id` INT UNSIGNED DEFAULT NULL,
    `name` VARCHAR(255) NOT NULL,
    `manager_user_id` INT UNSIGNED DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_departments_company_name` (`company_id`, `name`),
    INDEX `idx_departments_company` (`company_id`),
    INDEX `idx_departments_parent` (`parent_id`),
    INDEX `idx_departments_manager` (`manager_user_id`),
    CONSTRAINT `fk_departments_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_departments_parent` FOREIGN KEY (`parent_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_departments_manager` FOREIGN KEY (`manager_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `department_users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `department_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_department_users` (`company_id`, `department_id`, `user_id`),
    INDEX `idx_department_users_company` (`company_id`),
    INDEX `idx_department_users_department` (`department_id`),
    INDEX `idx_department_users_user` (`user_id`),
    CONSTRAINT `fk_department_users_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_department_users_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_department_users_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `workflow_templates` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `trigger_source` ENUM('telegram', 'web', 'email', 'all') NOT NULL DEFAULT 'all',
    `trigger_category_id` INT UNSIGNED DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_workflow_templates_company_active` (`company_id`, `is_active`),
    INDEX `idx_workflow_templates_trigger_source` (`trigger_source`),
    INDEX `idx_workflow_templates_trigger_category` (`trigger_category_id`),
    CONSTRAINT `fk_workflow_templates_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_workflow_templates_category` FOREIGN KEY (`trigger_category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_workflow_templates_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `workflow_steps` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `workflow_id` INT UNSIGNED NOT NULL,
    `step_order` INT UNSIGNED NOT NULL,
    `step_name` VARCHAR(255) NOT NULL,
    `approver_type` ENUM('user', 'role', 'department_manager') NOT NULL DEFAULT 'user',
    `approver_user_id` INT UNSIGNED DEFAULT NULL,
    `approver_role` VARCHAR(50) DEFAULT NULL,
    `approver_department_id` INT UNSIGNED DEFAULT NULL,
    `sla_minutes` INT UNSIGNED NOT NULL DEFAULT 120,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_workflow_steps_order` (`workflow_id`, `step_order`),
    INDEX `idx_workflow_steps_workflow` (`workflow_id`),
    INDEX `idx_workflow_steps_department` (`approver_department_id`),
    INDEX `idx_workflow_steps_user` (`approver_user_id`),
    CONSTRAINT `fk_workflow_steps_workflow` FOREIGN KEY (`workflow_id`) REFERENCES `workflow_templates` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_workflow_steps_user` FOREIGN KEY (`approver_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_workflow_steps_department` FOREIGN KEY (`approver_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ticket_workflow_states` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `ticket_id` INT UNSIGNED NOT NULL,
    `workflow_id` INT UNSIGNED NOT NULL,
    `current_step_order` INT UNSIGNED NOT NULL,
    `current_assignee_id` INT UNSIGNED DEFAULT NULL,
    `status` ENUM('in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'in_progress',
    `due_at` DATETIME DEFAULT NULL,
    `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME DEFAULT NULL,
    `last_escalated_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ticket_workflow_state_ticket` (`ticket_id`),
    INDEX `idx_ticket_workflow_states_company_status` (`company_id`, `status`),
    INDEX `idx_ticket_workflow_states_due_at` (`due_at`),
    INDEX `idx_ticket_workflow_states_assignee` (`current_assignee_id`),
    CONSTRAINT `fk_ticket_workflow_states_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ticket_workflow_states_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ticket_workflow_states_workflow` FOREIGN KEY (`workflow_id`) REFERENCES `workflow_templates` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ticket_workflow_states_assignee` FOREIGN KEY (`current_assignee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ticket_workflow_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `ticket_id` INT UNSIGNED NOT NULL,
    `workflow_id` INT UNSIGNED NOT NULL,
    `step_order` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(50) NOT NULL,
    `actor_user_id` INT UNSIGNED DEFAULT NULL,
    `assignee_user_id` INT UNSIGNED DEFAULT NULL,
    `note` VARCHAR(500) DEFAULT NULL,
    `meta` JSON DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ticket_workflow_logs_ticket` (`ticket_id`),
    INDEX `idx_ticket_workflow_logs_company` (`company_id`),
    INDEX `idx_ticket_workflow_logs_action` (`action`),
    CONSTRAINT `fk_ticket_workflow_logs_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ticket_workflow_logs_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ticket_workflow_logs_workflow` FOREIGN KEY (`workflow_id`) REFERENCES `workflow_templates` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ticket_workflow_logs_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_ticket_workflow_logs_assignee` FOREIGN KEY (`assignee_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

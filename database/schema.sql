-- Support Ticket System Database Schema
-- MySQL 8.x

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Create database
CREATE DATABASE IF NOT EXISTS `dpdc318_ticket` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `dpdc318_ticket`;

-- --------------------------------------------------------
-- Companies (Multi-tenant)
-- --------------------------------------------------------
CREATE TABLE `companies` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `logo` VARCHAR(255) DEFAULT NULL,
    `timezone` VARCHAR(50) DEFAULT 'UTC',
    `settings` JSON DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_slug` (`slug`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Users (Agents, Admins, Customers)
-- --------------------------------------------------------
CREATE TABLE `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `avatar` VARCHAR(255) DEFAULT NULL,
    `role` ENUM('super_admin', 'admin', 'agent', 'front_office_agent', 'back_office_agent', 'customer') NOT NULL DEFAULT 'customer',
    `telegram_chat_id` BIGINT DEFAULT NULL,
    `telegram_username` VARCHAR(100) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
    `last_login_at` TIMESTAMP NULL DEFAULT NULL,
    `remember_token` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_company_email` (`company_id`, `email`),
    INDEX `idx_role` (`role`),
    INDEX `idx_telegram` (`telegram_chat_id`),
    CONSTRAINT `fk_users_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Telegram Mini App Profiles (one-time role selection)
-- --------------------------------------------------------
CREATE TABLE `telegram_miniapp_profiles` (
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

-- --------------------------------------------------------
-- Categories
-- --------------------------------------------------------
CREATE TABLE `categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `parent_id` INT UNSIGNED DEFAULT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `color` VARCHAR(7) DEFAULT '#6366f1',
    `icon` VARCHAR(50) DEFAULT 'folder',
    `auto_assign_to` INT UNSIGNED DEFAULT NULL,
    `keywords` JSON DEFAULT NULL COMMENT 'Keywords for AI matching',
    `sla_response_hours` INT DEFAULT 24,
    `sla_resolve_hours` INT DEFAULT 72,
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_company` (`company_id`),
    INDEX `idx_parent` (`parent_id`),
    CONSTRAINT `fk_categories_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_categories_assign` FOREIGN KEY (`auto_assign_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tickets
-- --------------------------------------------------------
CREATE TABLE `tickets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `ticket_number` VARCHAR(20) NOT NULL,
    `subject` VARCHAR(500) NOT NULL,
    `description` TEXT NOT NULL,
    `status` ENUM('open', 'pending', 'in_progress', 'resolved', 'closed') NOT NULL DEFAULT 'open',
    `priority` ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    `source` ENUM('web', 'email', 'telegram', 'api') NOT NULL DEFAULT 'web',
    `category_id` INT UNSIGNED DEFAULT NULL,
    `assigned_to` INT UNSIGNED DEFAULT NULL,
    `requester_id` INT UNSIGNED DEFAULT NULL,
    `requester_email` VARCHAR(255) NOT NULL,
    `requester_name` VARCHAR(255) NOT NULL,
    `telegram_chat_id` BIGINT DEFAULT NULL COMMENT 'Source Telegram chat (private or group)',
    `ai_suggested_category` INT UNSIGNED DEFAULT NULL,
    `ai_suggested_priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT NULL,
    `ai_confidence_score` DECIMAL(5,4) DEFAULT NULL,
    `ai_classification_data` JSON DEFAULT NULL,
    `tags` JSON DEFAULT NULL,
    `custom_fields` JSON DEFAULT NULL,
    `first_response_at` TIMESTAMP NULL DEFAULT NULL,
    `resolved_at` TIMESTAMP NULL DEFAULT NULL,
    `closed_at` TIMESTAMP NULL DEFAULT NULL,
    `sla_response_due_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When first response is due',
    `sla_resolution_due_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When resolution is due',
    `sla_response_breached` TINYINT(1) DEFAULT 0 COMMENT 'Did response SLA breach',
    `sla_resolution_breached` TINYINT(1) DEFAULT 0 COMMENT 'Did resolution SLA breach',
    `response_time_minutes` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Actual response time',
    `resolution_time_minutes` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Actual resolution time',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ticket_number` (`company_id`, `ticket_number`),
    INDEX `idx_status` (`status`),
    INDEX `idx_priority` (`priority`),
    INDEX `idx_source` (`source`),
    INDEX `idx_assigned` (`assigned_to`),
    INDEX `idx_requester` (`requester_id`),
    INDEX `idx_requester_email` (`requester_email`),
    INDEX `idx_created` (`created_at`),
    INDEX `idx_sla_response_due` (`sla_response_due_at`),
    INDEX `idx_sla_resolution_due` (`sla_resolution_due_at`),
    INDEX `idx_sla_breached` (`sla_response_breached`, `sla_resolution_breached`),
    INDEX `idx_company_status` (`company_id`, `status`),
    CONSTRAINT `fk_tickets_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tickets_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tickets_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tickets_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tickets_ai_category` FOREIGN KEY (`ai_suggested_category`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- SLA Policies (ITIL Standard)
-- --------------------------------------------------------
CREATE TABLE `sla_policies` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `priority` ENUM('low', 'medium', 'high', 'urgent') NOT NULL,
    `response_time_minutes` INT UNSIGNED NOT NULL COMMENT 'First response SLA in minutes',
    `resolution_time_minutes` INT UNSIGNED NOT NULL COMMENT 'Resolution SLA in minutes',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `business_hours_only` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Count only business hours (9-5 Mon-Fri)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_company_priority` (`company_id`, `priority`),
    CONSTRAINT `fk_sla_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Analytics Snapshots (Historical Reporting)
-- --------------------------------------------------------
CREATE TABLE `analytics_snapshots` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `snapshot_date` DATE NOT NULL,
    `metric_type` VARCHAR(50) NOT NULL COMMENT 'Type: daily, weekly, monthly',
    `tickets_created` INT UNSIGNED DEFAULT 0,
    `tickets_resolved` INT UNSIGNED DEFAULT 0,
    `tickets_closed` INT UNSIGNED DEFAULT 0,
    `sla_response_met` INT UNSIGNED DEFAULT 0,
    `sla_response_breached` INT UNSIGNED DEFAULT 0,
    `sla_resolution_met` INT UNSIGNED DEFAULT 0,
    `sla_resolution_breached` INT UNSIGNED DEFAULT 0,
    `avg_response_time_minutes` DECIMAL(10,2) DEFAULT 0,
    `avg_resolution_time_minutes` DECIMAL(10,2) DEFAULT 0,
    `avg_customer_satisfaction` DECIMAL(3,2) DEFAULT 0 COMMENT '1-5 scale',
    `total_agents` INT UNSIGNED DEFAULT 0,
    `avg_tickets_per_agent` DECIMAL(10,2) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_snapshot` (`company_id`, `snapshot_date`, `metric_type`),
    INDEX `idx_company_date` (`company_id`, `snapshot_date`),
    CONSTRAINT `fk_analytics_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Ticket Messages (Conversation Thread)
-- --------------------------------------------------------
CREATE TABLE `ticket_messages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ticket_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `message` TEXT NOT NULL,
    `message_html` TEXT DEFAULT NULL,
    `is_internal` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Internal notes hidden from customer',
    `source` ENUM('web', 'email', 'telegram', 'api', 'system') NOT NULL DEFAULT 'web',
    `attachments` JSON DEFAULT NULL,
    `email_message_id` VARCHAR(255) DEFAULT NULL COMMENT 'Original email Message-ID',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ticket` (`ticket_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_internal` (`is_internal`),
    INDEX `idx_email_msg` (`email_message_id`),
    CONSTRAINT `fk_messages_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_messages_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Attachments
-- --------------------------------------------------------
CREATE TABLE `attachments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ticket_id` INT UNSIGNED DEFAULT NULL,
    `message_id` INT UNSIGNED DEFAULT NULL,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `size` INT UNSIGNED NOT NULL,
    `path` VARCHAR(500) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ticket` (`ticket_id`),
    INDEX `idx_message` (`message_id`),
    CONSTRAINT `fk_attachments_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_attachments_message` FOREIGN KEY (`message_id`) REFERENCES `ticket_messages` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_attachments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Email Configuration (Per Company)
-- --------------------------------------------------------
CREATE TABLE `email_configs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL DEFAULT 'Default',
    `imap_host` VARCHAR(255) NOT NULL,
    `imap_port` INT NOT NULL DEFAULT 993,
    `imap_encryption` ENUM('ssl', 'tls', 'none') DEFAULT 'ssl',
    `imap_username` VARCHAR(255) NOT NULL,
    `imap_password` VARCHAR(500) NOT NULL,
    `smtp_host` VARCHAR(255) NOT NULL,
    `smtp_port` INT NOT NULL DEFAULT 587,
    `smtp_encryption` ENUM('ssl', 'tls', 'none') DEFAULT 'tls',
    `smtp_username` VARCHAR(255) NOT NULL,
    `smtp_password` VARCHAR(500) NOT NULL,
    `from_email` VARCHAR(255) NOT NULL,
    `from_name` VARCHAR(255) NOT NULL,
    `default_category_id` INT UNSIGNED DEFAULT NULL,
    `last_checked_at` TIMESTAMP NULL DEFAULT NULL,
    `last_error` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_company` (`company_id`),
    INDEX `idx_active` (`is_active`),
    CONSTRAINT `fk_email_config_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_email_config_category` FOREIGN KEY (`default_category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Telegram Configuration (Per Company)
-- --------------------------------------------------------
CREATE TABLE `telegram_configs` (
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

-- --------------------------------------------------------
-- Canned Responses
-- --------------------------------------------------------
CREATE TABLE `canned_responses` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `shortcut` VARCHAR(50) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `usage_count` INT UNSIGNED DEFAULT 0,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_company` (`company_id`),
    INDEX `idx_category` (`category_id`),
    INDEX `idx_shortcut` (`shortcut`),
    CONSTRAINT `fk_canned_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_canned_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_canned_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Activity Logs
-- --------------------------------------------------------
CREATE TABLE `activity_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `ticket_id` INT UNSIGNED DEFAULT NULL,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `old_value` JSON DEFAULT NULL,
    `new_value` JSON DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_company` (`company_id`),
    INDEX `idx_ticket` (`ticket_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created` (`created_at`),
    CONSTRAINT `fk_activity_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_activity_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- User Sessions / API Tokens
-- --------------------------------------------------------
CREATE TABLE `user_tokens` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(64) NOT NULL,
    `name` VARCHAR(100) DEFAULT 'API Token',
    `abilities` JSON DEFAULT NULL,
    `last_used_at` TIMESTAMP NULL DEFAULT NULL,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_token` (`token_hash`),
    INDEX `idx_user` (`user_id`),
    CONSTRAINT `fk_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Notifications Queue
-- --------------------------------------------------------
CREATE TABLE `notifications` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `ticket_id` INT UNSIGNED DEFAULT NULL,
    `type` VARCHAR(100) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `data` JSON DEFAULT NULL,
    `read_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_read` (`read_at`),
    INDEX `idx_created` (`created_at`),
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_notifications_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Email Queue (for sending emails)
-- --------------------------------------------------------
CREATE TABLE `email_queue` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `to_email` VARCHAR(255) NOT NULL,
    `to_name` VARCHAR(255) DEFAULT NULL,
    `subject` VARCHAR(500) NOT NULL,
    `body_html` TEXT NOT NULL,
    `body_text` TEXT DEFAULT NULL,
    `attachments` JSON DEFAULT NULL,
    `attempts` TINYINT UNSIGNED DEFAULT 0,
    `last_error` TEXT DEFAULT NULL,
    `sent_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_company` (`company_id`),
    INDEX `idx_sent` (`sent_at`),
    INDEX `idx_attempts` (`attempts`),
    CONSTRAINT `fk_email_queue_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Telegram User States (for conversation flow)
-- --------------------------------------------------------
CREATE TABLE `telegram_user_states` (
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

-- --------------------------------------------------------
-- Ticket Surveys (Customer Satisfaction)
-- --------------------------------------------------------
CREATE TABLE `ticket_surveys` (
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

-- --------------------------------------------------------
-- Knowledge Base Articles
-- --------------------------------------------------------
CREATE TABLE `knowledge_base` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `author_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `excerpt` VARCHAR(500) DEFAULT NULL,
    `tags` JSON DEFAULT NULL,
    `views` INT UNSIGNED DEFAULT 0,
    `is_published` TINYINT(1) NOT NULL DEFAULT 1,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `published_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`company_id`, `slug`),
    INDEX `idx_company` (`company_id`),
    INDEX `idx_author` (`author_id`),
    INDEX `idx_category` (`category_id`),
    INDEX `idx_published` (`is_published`),
    INDEX `idx_featured` (`is_featured`),
    FULLTEXT INDEX `ft_search` (`title`, `content`),
    CONSTRAINT `fk_kb_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_kb_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_kb_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Telegram Groups (for group chat support)
-- --------------------------------------------------------
CREATE TABLE `telegram_groups` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `chat_id` BIGINT NOT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `type` VARCHAR(50) DEFAULT 'group' COMMENT 'group, supergroup, channel',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_chat` (`company_id`, `chat_id`),
    INDEX `idx_company` (`company_id`),
    CONSTRAINT `fk_tg_group_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Departments (Hierarchical Organization)
-- --------------------------------------------------------
CREATE TABLE `departments` (
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

CREATE TABLE `department_users` (
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

-- --------------------------------------------------------
-- Workflow Templates / Steps (Hierarchical Routing)
-- --------------------------------------------------------
CREATE TABLE `workflow_templates` (
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

CREATE TABLE `workflow_steps` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `workflow_id` INT UNSIGNED NOT NULL,
    `step_order` INT UNSIGNED NOT NULL,
    `step_name` VARCHAR(255) NOT NULL,
    `approver_type` ENUM('user', 'role', 'department_manager', 'customer_owner', 'customer_owner_supervisor') NOT NULL DEFAULT 'user',
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

CREATE TABLE `ticket_workflow_states` (
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

CREATE TABLE `ticket_workflow_logs` (
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

-- --------------------------------------------------------
-- Dynamic Reporting / Customer Ownership
-- --------------------------------------------------------
CREATE TABLE `user_reporting` (
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

CREATE TABLE `customer_account_owners` (
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

-- --------------------------------------------------------
-- Category Routing Rules (Supervisor / Department Queue)
-- --------------------------------------------------------
CREATE TABLE `category_routing_rules` (
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

SET FOREIGN_KEY_CHECKS = 1;

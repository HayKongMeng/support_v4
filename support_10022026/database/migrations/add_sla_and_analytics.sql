-- Migration: Add SLA and Analytics Features
-- Date: 2026-02-09
-- Description: Adds SLA tracking based on ITIL international standards and analytics support

-- =====================
-- SLA Configuration Table (ITIL Standard)
-- =====================
CREATE TABLE IF NOT EXISTS `sla_policies` (
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

-- Insert default ITIL-based SLA policies for each company
INSERT INTO `sla_policies` (`company_id`, `name`, `priority`, `response_time_minutes`, `resolution_time_minutes`, `is_active`)
SELECT
    c.id as company_id,
    CASE p.priority
        WHEN 'urgent' THEN 'Critical - Urgent'
        WHEN 'high' THEN 'High Priority'
        WHEN 'medium' THEN 'Medium Priority'
        WHEN 'low' THEN 'Low Priority'
    END as name,
    p.priority,
    CASE p.priority
        WHEN 'urgent' THEN 60          -- 1 hour response
        WHEN 'high' THEN 240           -- 4 hours response
        WHEN 'medium' THEN 480         -- 8 hours response
        WHEN 'low' THEN 1440           -- 24 hours response
    END as response_time_minutes,
    CASE p.priority
        WHEN 'urgent' THEN 240         -- 4 hours resolution
        WHEN 'high' THEN 480           -- 8 hours resolution
        WHEN 'medium' THEN 1440        -- 24 hours resolution
        WHEN 'low' THEN 4320           -- 72 hours resolution
    END as resolution_time_minutes,
    1 as is_active
FROM companies c
CROSS JOIN (
    SELECT 'urgent' as priority UNION ALL
    SELECT 'high' UNION ALL
    SELECT 'medium' UNION ALL
    SELECT 'low'
) p;

-- =====================
-- Add SLA tracking fields to tickets table
-- =====================
-- Note: Skip this section if columns already exist (you'll see "Duplicate column" errors - that's okay!)

-- Add columns one by one (ignore errors if they already exist)
ALTER TABLE `tickets` ADD COLUMN `sla_response_due_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When first response is due' AFTER `resolved_at`;
ALTER TABLE `tickets` ADD COLUMN `sla_resolution_due_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When resolution is due' AFTER `sla_response_due_at`;
ALTER TABLE `tickets` ADD COLUMN `sla_response_breached` TINYINT(1) DEFAULT 0 COMMENT 'Did response SLA breach' AFTER `sla_resolution_due_at`;
ALTER TABLE `tickets` ADD COLUMN `sla_resolution_breached` TINYINT(1) DEFAULT 0 COMMENT 'Did resolution SLA breach' AFTER `sla_response_breached`;
ALTER TABLE `tickets` ADD COLUMN `response_time_minutes` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Actual response time' AFTER `sla_resolution_breached`;
ALTER TABLE `tickets` ADD COLUMN `resolution_time_minutes` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Actual resolution time' AFTER `response_time_minutes`;

-- Add indexes (ignore errors if they already exist)
ALTER TABLE `tickets` ADD INDEX `idx_sla_response_due` (`sla_response_due_at`);
ALTER TABLE `tickets` ADD INDEX `idx_sla_resolution_due` (`sla_resolution_due_at`);
ALTER TABLE `tickets` ADD INDEX `idx_sla_breached` (`sla_response_breached`, `sla_resolution_breached`);

-- =====================
-- Analytics Snapshots Table (for historical reporting)
-- =====================
CREATE TABLE IF NOT EXISTS `analytics_snapshots` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `snapshot_date` DATE NOT NULL,
    `metric_type` VARCHAR(50) NOT NULL COMMENT 'Type: daily, weekly, monthly',

    -- Volume Metrics
    `tickets_created` INT UNSIGNED DEFAULT 0,
    `tickets_resolved` INT UNSIGNED DEFAULT 0,
    `tickets_closed` INT UNSIGNED DEFAULT 0,

    -- SLA Metrics (ITIL Standard)
    `sla_response_met` INT UNSIGNED DEFAULT 0,
    `sla_response_breached` INT UNSIGNED DEFAULT 0,
    `sla_resolution_met` INT UNSIGNED DEFAULT 0,
    `sla_resolution_breached` INT UNSIGNED DEFAULT 0,

    -- Performance Metrics (ISO 20000 Standard)
    `avg_response_time_minutes` DECIMAL(10,2) DEFAULT 0,
    `avg_resolution_time_minutes` DECIMAL(10,2) DEFAULT 0,
    `avg_customer_satisfaction` DECIMAL(3,2) DEFAULT 0 COMMENT '1-5 scale',

    -- Agent Metrics
    `total_agents` INT UNSIGNED DEFAULT 0,
    `avg_tickets_per_agent` DECIMAL(10,2) DEFAULT 0,

    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_snapshot` (`company_id`, `snapshot_date`, `metric_type`),
    INDEX `idx_company_date` (`company_id`, `snapshot_date`),
    CONSTRAINT `fk_analytics_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================
-- Update existing tickets with SLA due dates
-- =====================
-- This will calculate SLA for existing tickets based on their creation time and priority
UPDATE tickets t
INNER JOIN sla_policies s ON t.company_id = s.company_id AND t.priority = s.priority AND s.is_active = 1
SET
    t.sla_response_due_at = DATE_ADD(t.created_at, INTERVAL s.response_time_minutes MINUTE),
    t.sla_resolution_due_at = DATE_ADD(t.created_at, INTERVAL s.resolution_time_minutes MINUTE),
    t.sla_response_breached = CASE
        WHEN t.first_response_at IS NOT NULL AND t.first_response_at > DATE_ADD(t.created_at, INTERVAL s.response_time_minutes MINUTE) THEN 1
        WHEN t.first_response_at IS NULL AND NOW() > DATE_ADD(t.created_at, INTERVAL s.response_time_minutes MINUTE) THEN 1
        ELSE 0
    END,
    t.sla_resolution_breached = CASE
        WHEN t.resolved_at IS NOT NULL AND t.resolved_at > DATE_ADD(t.created_at, INTERVAL s.resolution_time_minutes MINUTE) THEN 1
        WHEN t.resolved_at IS NULL AND t.status NOT IN ('resolved', 'closed') AND NOW() > DATE_ADD(t.created_at, INTERVAL s.resolution_time_minutes MINUTE) THEN 1
        ELSE 0
    END,
    t.response_time_minutes = CASE
        WHEN t.first_response_at IS NOT NULL AND t.first_response_at >= t.created_at
        THEN GREATEST(0, TIMESTAMPDIFF(MINUTE, t.created_at, t.first_response_at))
        ELSE NULL
    END,
    t.resolution_time_minutes = CASE
        WHEN t.resolved_at IS NOT NULL AND t.resolved_at >= t.created_at
        THEN GREATEST(0, TIMESTAMPDIFF(MINUTE, t.created_at, t.resolved_at))
        ELSE NULL
    END
WHERE t.sla_response_due_at IS NULL;

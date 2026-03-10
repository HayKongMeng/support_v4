-- =====================
-- Complete SLA Setup (Run this if you already have the SLA columns)
-- =====================

-- Step 1: Ensure SLA policies table exists
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

-- Step 2: Insert default SLA policies (skip if already exist)
INSERT IGNORE INTO `sla_policies` (`company_id`, `name`, `priority`, `response_time_minutes`, `resolution_time_minutes`, `is_active`)
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
        WHEN 'urgent' THEN 60
        WHEN 'high' THEN 240
        WHEN 'medium' THEN 480
        WHEN 'low' THEN 1440
    END as response_time_minutes,
    CASE p.priority
        WHEN 'urgent' THEN 240
        WHEN 'high' THEN 480
        WHEN 'medium' THEN 1440
        WHEN 'low' THEN 4320
    END as resolution_time_minutes,
    1 as is_active
FROM companies c
CROSS JOIN (
    SELECT 'urgent' as priority UNION ALL
    SELECT 'high' UNION ALL
    SELECT 'medium' UNION ALL
    SELECT 'low'
) p;

-- Step 3: Create analytics table
CREATE TABLE IF NOT EXISTS `analytics_snapshots` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `snapshot_date` DATE NOT NULL,
    `metric_type` VARCHAR(50) NOT NULL,
    `tickets_created` INT UNSIGNED DEFAULT 0,
    `tickets_resolved` INT UNSIGNED DEFAULT 0,
    `tickets_closed` INT UNSIGNED DEFAULT 0,
    `sla_response_met` INT UNSIGNED DEFAULT 0,
    `sla_response_breached` INT UNSIGNED DEFAULT 0,
    `sla_resolution_met` INT UNSIGNED DEFAULT 0,
    `sla_resolution_breached` INT UNSIGNED DEFAULT 0,
    `avg_response_time_minutes` DECIMAL(10,2) DEFAULT 0,
    `avg_resolution_time_minutes` DECIMAL(10,2) DEFAULT 0,
    `avg_customer_satisfaction` DECIMAL(3,2) DEFAULT 0,
    `total_agents` INT UNSIGNED DEFAULT 0,
    `avg_tickets_per_agent` DECIMAL(10,2) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_snapshot` (`company_id`, `snapshot_date`, `metric_type`),
    INDEX `idx_company_date` (`company_id`, `snapshot_date`),
    CONSTRAINT `fk_analytics_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 4: Update existing tickets with SLA data
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

SELECT 'Migration completed successfully!' as status;

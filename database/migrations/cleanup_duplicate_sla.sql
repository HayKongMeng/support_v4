-- =====================
-- Cleanup Duplicate SLA Policies
-- =====================
-- This removes duplicate SLA policies, keeping only the first one for each company+priority

-- Step 1: Delete duplicates (keep the lowest ID for each company+priority combination)
DELETE s1 FROM sla_policies s1
INNER JOIN sla_policies s2
WHERE s1.company_id = s2.company_id
  AND s1.priority = s2.priority
  AND s1.id > s2.id;

-- Step 2: Verify - Show remaining policies
SELECT
    company_id,
    priority,
    response_time_minutes,
    resolution_time_minutes,
    COUNT(*) as count
FROM sla_policies
GROUP BY company_id, priority, response_time_minutes, resolution_time_minutes
ORDER BY company_id,
    CASE priority
        WHEN 'urgent' THEN 1
        WHEN 'high' THEN 2
        WHEN 'medium' THEN 3
        WHEN 'low' THEN 4
    END;

SELECT 'Duplicate SLA policies removed successfully!' as status;

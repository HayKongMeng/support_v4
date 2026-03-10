-- Migration: Add front_office_agent and back_office_agent roles
-- Date: 2026-02-09
-- Description: Adds new agent role types for role-based ticket filtering

-- Update the users table role enum to include new agent types
ALTER TABLE `users`
MODIFY COLUMN `role` ENUM('super_admin', 'admin', 'agent', 'front_office_agent', 'back_office_agent', 'customer') NOT NULL DEFAULT 'customer';

-- Note: After running this migration, agents can be assigned these new roles.
-- Ticket visibility will be filtered based on assignment and ownership for agent roles.

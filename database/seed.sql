-- Seed Data for Support Ticket System
USE `dpdc318_ticket`;

-- --------------------------------------------------------
-- Default Company
-- --------------------------------------------------------
INSERT INTO `companies` (`id`, `name`, `slug`, `email`, `settings`) VALUES
(1, 'Demo Company', 'demo', 'support@demo.com', JSON_OBJECT(
    'ticket_prefix', 'TKT',
    'auto_assign_enabled', true,
    'ai_categorization_enabled', true,
    'customer_portal_enabled', true
));

-- --------------------------------------------------------
-- Default Admin User (password: admin123)
-- --------------------------------------------------------
INSERT INTO `users` (`id`, `company_id`, `email`, `password_hash`, `name`, `role`) VALUES
(1, 1, 'admin@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', 'admin');

-- Default Agent User (password: agent123)
INSERT INTO `users` (`id`, `company_id`, `email`, `password_hash`, `name`, `role`) VALUES
(2, 1, 'agent@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Support Agent', 'agent');

-- Default Customer User (password: customer123)
INSERT INTO `users` (`id`, `company_id`, `email`, `password_hash`, `name`, `role`) VALUES
(3, 1, 'customer@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Customer', 'customer');

-- --------------------------------------------------------
-- Default SLA Policies (ITIL Standard)
-- --------------------------------------------------------
INSERT INTO `sla_policies` (`company_id`, `name`, `priority`, `response_time_minutes`, `resolution_time_minutes`, `is_active`, `business_hours_only`) VALUES
(1, 'Critical - Urgent', 'urgent', 60, 240, 1, 0),
(1, 'High Priority', 'high', 240, 480, 1, 0),
(1, 'Medium Priority', 'medium', 480, 1440, 1, 0),
(1, 'Low Priority', 'low', 1440, 4320, 1, 0);

-- --------------------------------------------------------
-- Default Categories with Keywords for AI
-- --------------------------------------------------------
INSERT INTO `categories` (`id`, `company_id`, `name`, `description`, `color`, `icon`, `keywords`, `sla_response_hours`, `sla_resolve_hours`) VALUES
(1, 1, 'Technical Support', 'Technical issues and troubleshooting', '#ef4444', 'wrench',
    JSON_ARRAY('error', 'bug', 'crash', 'not working', 'broken', 'fix', 'issue', 'problem', 'technical', 'help'), 4, 24),
(2, 1, 'Billing', 'Payment and billing inquiries', '#22c55e', 'credit-card',
    JSON_ARRAY('invoice', 'payment', 'bill', 'charge', 'refund', 'subscription', 'pricing', 'cost', 'money'), 8, 48),
(3, 1, 'Sales', 'Pre-sales questions and quotes', '#3b82f6', 'shopping-cart',
    JSON_ARRAY('buy', 'purchase', 'quote', 'demo', 'trial', 'pricing', 'discount', 'sales', 'interested'), 4, 24),
(4, 1, 'General Inquiry', 'General questions and information', '#8b5cf6', 'question-mark-circle',
    JSON_ARRAY('question', 'information', 'info', 'how to', 'what is', 'where', 'when', 'general'), 24, 72),
(5, 1, 'Feature Request', 'New feature suggestions', '#f59e0b', 'light-bulb',
    JSON_ARRAY('feature', 'suggestion', 'idea', 'request', 'would like', 'wish', 'add', 'new', 'improve'), 48, 168);

-- --------------------------------------------------------
-- Sample Canned Responses
-- --------------------------------------------------------
INSERT INTO `canned_responses` (`company_id`, `category_id`, `title`, `content`, `shortcut`, `created_by`) VALUES
(1, 1, 'Request More Information', 'Thank you for contacting us. To better assist you, could you please provide the following information:\n\n1. What steps led to this issue?\n2. What error message (if any) are you seeing?\n3. What browser/device are you using?\n\nThis will help us investigate and resolve your issue faster.', '#moreinfo', 1),
(1, 1, 'Issue Resolved', 'Great news! We have resolved the issue you reported. Please try again and let us know if you experience any further problems.\n\nThank you for your patience.', '#resolved', 1),
(1, 2, 'Refund Processing', 'We have processed your refund request. Please allow 5-7 business days for the refund to appear in your account.\n\nIf you have any questions, please don\'t hesitate to ask.', '#refund', 1),
(1, NULL, 'Thank You', 'Thank you for contacting our support team. Is there anything else we can help you with?\n\nIf not, feel free to close this ticket. We appreciate your business!', '#thanks', 1);

-- --------------------------------------------------------
-- Sample Tickets
-- --------------------------------------------------------
INSERT INTO `tickets` (`id`, `company_id`, `ticket_number`, `subject`, `description`, `status`, `priority`, `source`, `category_id`, `assigned_to`, `requester_id`, `requester_email`, `requester_name`) VALUES
(1, 1, 'TKT-000001', 'Cannot login to my account', 'I have been trying to login to my account but keep getting an error message saying "Invalid credentials". I am sure my password is correct. Please help!', 'open', 'high', 'web', 1, 2, 3, 'customer@demo.com', 'John Customer'),
(2, 1, 'TKT-000002', 'Question about billing cycle', 'Hi, I would like to know when my next billing date is and if I can change to annual billing to get a discount.', 'pending', 'medium', 'email', 2, NULL, 3, 'customer@demo.com', 'John Customer'),
(3, 1, 'TKT-000003', 'Feature suggestion: Dark mode', 'It would be great if you could add a dark mode option to the application. Many users prefer dark mode for reduced eye strain.', 'open', 'low', 'web', 5, NULL, 3, 'customer@demo.com', 'John Customer');

-- --------------------------------------------------------
-- Sample Ticket Messages
-- --------------------------------------------------------
INSERT INTO `ticket_messages` (`ticket_id`, `user_id`, `message`, `is_internal`, `source`) VALUES
(1, 3, 'I have been trying to login to my account but keep getting an error message saying "Invalid credentials". I am sure my password is correct. Please help!', 0, 'web'),
(1, 2, 'Hi John, I\'m sorry to hear you\'re having trouble logging in. Let me check your account status.', 0, 'web'),
(1, 2, 'Checked the logs - seems like there were multiple failed attempts. Account might be temporarily locked.', 1, 'web'),
(2, 3, 'Hi, I would like to know when my next billing date is and if I can change to annual billing to get a discount.', 0, 'email');

-- --------------------------------------------------------
-- Sample Activity Logs
-- --------------------------------------------------------
INSERT INTO `activity_logs` (`company_id`, `ticket_id`, `user_id`, `action`, `description`) VALUES
(1, 1, 3, 'ticket_created', 'Ticket created via web portal'),
(1, 1, 2, 'ticket_assigned', 'Ticket assigned to Support Agent'),
(1, 1, 2, 'reply_added', 'Agent replied to ticket'),
(1, 2, 3, 'ticket_created', 'Ticket created from email');

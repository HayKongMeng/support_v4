To deploy:

Create the table (run on production database):

CREATE TABLE `ticket_surveys` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `ticket_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `rating` TINYINT UNSIGNED DEFAULT NULL,
    `comment` TEXT DEFAULT NULL,
    `survey_sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `rated_at` TIMESTAMP NULL DEFAULT NULL,
    `comment_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ticket` (`ticket_id`),
    INDEX `idx_company` (`company_id`),
    INDEX `idx_rating` (`rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
Upload updated files to production:

public/telegram_webhook_direct.php
src/Controllers/TicketController.php
views/tickets/show.php
Test:

Create a ticket via Telegram
Mark it as "Resolved" in the web interface
Check Telegram - you should see rating buttons
Click a rating, optionally add comment
View ticket in web - rating should appear in sidebar
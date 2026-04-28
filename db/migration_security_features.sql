-- ============================================
-- SECURITY & DELIVERABILITY FEATURES MIGRATION
-- Date: April 28, 2026
-- ============================================

USE `email_dispatcher`;

-- ============================================
-- 1. SUPPRESSION LIST (Unsubscribes, Bounces, Complaints)
-- ============================================
CREATE TABLE IF NOT EXISTS `suppression_list` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL,
    `type` ENUM('unsubscribe', 'hard_bounce', 'soft_bounce', 'spam_complaint', 'manual') NOT NULL,
    `reason` VARCHAR(500) NULL,
    `source` VARCHAR(100) NULL COMMENT 'Job ID or source that caused suppression',
    `metadata` JSON NULL COMMENT 'Additional bounce/complaint data',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` INT NULL,
    `expires_at` DATETIME NULL COMMENT 'For soft bounces - auto-expire after period',
    UNIQUE KEY `uk_suppression_email` (`email`),
    INDEX `idx_type` (`type`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. EMAIL DELIVERABILITY CONFIG (DKIM/SPF/DMARC)
-- ============================================
CREATE TABLE IF NOT EXISTS `email_deliverability_config` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `domain` VARCHAR(255) NOT NULL,
    `dkim_enabled` TINYINT(1) DEFAULT 0,
    `dkim_selector` VARCHAR(100) DEFAULT 'default',
    `dkim_private_key` TEXT NULL,
    `dkim_public_key` TEXT NULL,
    `spf_record` VARCHAR(500) DEFAULT 'v=spf1 include:_spf.google.com ~all',
    `dmarc_policy` ENUM('none', 'quarantine', 'reject') DEFAULT 'none',
    `dmarc_percentage` INT DEFAULT 100,
    `dmarc_rua` VARCHAR(255) NULL COMMENT 'Aggregate report email',
    `dmarc_ruf` VARCHAR(255) NULL COMMENT 'Forensic report email',
    `is_verified` TINYINT(1) DEFAULT 0,
    `verified_at` DATETIME NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_domain` (`domain`),
    INDEX `idx_verified` (`is_verified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. BOUNCE LOG (Detailed bounce tracking)
-- ============================================
CREATE TABLE IF NOT EXISTS `bounce_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `mail_job_id` INT NOT NULL,
    `mail_job_item_id` INT NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `bounce_type` ENUM('hard', 'soft', 'complaint', 'block') NOT NULL,
    `bounce_code` VARCHAR(50) NULL COMMENT 'SMTP bounce code',
    `bounce_reason` VARCHAR(500) NULL,
    `remote_mta` VARCHAR(255) NULL COMMENT 'Receiving mail server',
    `diagnostic_code` VARCHAR(500) NULL,
    `raw_bounce_data` TEXT NULL COMMENT 'Full bounce message',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_job_id` (`mail_job_id`),
    INDEX `idx_item_id` (`mail_job_item_id`),
    INDEX `idx_email` (`email`),
    INDEX `idx_bounce_type` (`bounce_type`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`mail_job_id`) REFERENCES `mail_jobs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`mail_job_item_id`) REFERENCES `mail_job_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. WEBHOOK ENDPOINTS
-- ============================================
CREATE TABLE IF NOT EXISTS `webhook_endpoints` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `url` VARCHAR(500) NOT NULL,
    `secret` VARCHAR(255) NOT NULL COMMENT 'For HMAC signature verification',
    `events` JSON NOT NULL COMMENT 'Array of event types to send',
    `is_active` TINYINT(1) DEFAULT 1,
    `last_response_code` INT NULL,
    `last_response_body` TEXT NULL,
    `last_called_at` DATETIME NULL,
    `success_count` INT DEFAULT 0,
    `failure_count` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` INT NULL,
    INDEX `idx_active` (`is_active`),
    INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. WEBHOOK EVENT LOG
-- ============================================
CREATE TABLE IF NOT EXISTS `webhook_event_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `webhook_id` INT NOT NULL,
    `event_type` VARCHAR(50) NOT NULL,
    `payload` JSON NOT NULL,
    `response_code` INT NULL,
    `response_body` TEXT NULL,
    `duration_ms` INT NULL,
    `is_success` TINYINT(1) DEFAULT 0,
    `error_message` VARCHAR(500) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_webhook_id` (`webhook_id`),
    INDEX `idx_event_type` (`event_type`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_success` (`is_success`),
    FOREIGN KEY (`webhook_id`) REFERENCES `webhook_endpoints`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. EMAIL ANALYTICS (Open/Click tracking)
-- ============================================
CREATE TABLE IF NOT EXISTS `email_analytics` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `mail_job_id` INT NOT NULL,
    `mail_job_item_id` INT NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `event_type` ENUM('sent', 'delivered', 'open', 'click', 'bounce', 'spam', 'unsubscribe') NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(500) NULL,
    `referer` VARCHAR(500) NULL,
    `country` VARCHAR(2) NULL,
    `city` VARCHAR(100) NULL,
    `device_type` ENUM('desktop', 'mobile', 'tablet', 'unknown') NULL,
    `browser` VARCHAR(100) NULL,
    `os` VARCHAR(100) NULL,
    `link_url` VARCHAR(500) NULL COMMENT 'For click events',
    `metadata` JSON NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_job_id` (`mail_job_id`),
    INDEX `idx_item_id` (`mail_job_item_id`),
    INDEX `idx_email` (`email`),
    INDEX `idx_event_type` (`event_type`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_composite` (`mail_job_id`, `event_type`, `created_at`),
    FOREIGN KEY (`mail_job_id`) REFERENCES `mail_jobs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`mail_job_item_id`) REFERENCES `mail_job_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. TRACKING PIXELS (For open tracking)
-- ============================================
CREATE TABLE IF NOT EXISTS `tracking_pixels` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pixel_code` VARCHAR(32) NOT NULL UNIQUE COMMENT 'Unique tracking code',
    `mail_job_id` INT NOT NULL,
    `mail_job_item_id` INT NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `is_opened` TINYINT(1) DEFAULT 0,
    `open_count` INT DEFAULT 0,
    `first_opened_at` DATETIME NULL,
    `last_opened_at` DATETIME NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_code` (`pixel_code`),
    INDEX `idx_job_id` (`mail_job_id`),
    INDEX `idx_item_id` (`mail_job_item_id`),
    FOREIGN KEY (`mail_job_id`) REFERENCES `mail_jobs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`mail_job_item_id`) REFERENCES `mail_job_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. TRACKING LINKS (For click tracking)
-- ============================================
CREATE TABLE IF NOT EXISTS `tracking_links` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `link_code` VARCHAR(32) NOT NULL UNIQUE COMMENT 'Unique tracking code',
    `mail_job_id` INT NOT NULL,
    `mail_job_item_id` INT NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `original_url` VARCHAR(1000) NOT NULL,
    `click_count` INT DEFAULT 0,
    `first_clicked_at` DATETIME NULL,
    `last_clicked_at` DATETIME NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_code` (`link_code`),
    INDEX `idx_job_id` (`mail_job_id`),
    INDEX `idx_item_id` (`mail_job_item_id`),
    FOREIGN KEY (`mail_job_id`) REFERENCES `mail_jobs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`mail_job_item_id`) REFERENCES `mail_job_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9. RATE LIMIT LOG (For monitoring)
-- ============================================
CREATE TABLE IF NOT EXISTS `rate_limit_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `is_blocked` TINYINT(1) DEFAULT 0,
    `requests_count` INT DEFAULT 1,
    `window_start` DATETIME NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_ip_action` (`ip_address`, `action`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_blocked` (`is_blocked`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 10. SYSTEM SETTINGS (For configuration)
-- ============================================
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    `value` TEXT NULL,
    `type` ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    `description` VARCHAR(500) NULL,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT NULL,
    INDEX `idx_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO `system_settings` (`key`, `value`, `type`, `description`) VALUES
('csrf_enabled', '1', 'boolean', 'Enable CSRF protection'),
('rate_limit_enabled', '1', 'boolean', 'Enable rate limiting'),
('max_emails_per_hour', '100', 'integer', 'Maximum emails per user per hour'),
('max_emails_per_day', '1000', 'integer', 'Maximum emails per user per day'),
('bounce_handling_enabled', '1', 'boolean', 'Enable automatic bounce handling'),
('open_tracking_enabled', '1', 'boolean', 'Enable email open tracking'),
('click_tracking_enabled', '1', 'boolean', 'Enable link click tracking'),
('webhook_retries', '3', 'integer', 'Number of webhook retry attempts'),
('webhook_timeout', '5000', 'integer', 'Webhook timeout in milliseconds'),
('dkim_signing_enabled', '0', 'boolean', 'Enable DKIM email signing')
ON DUPLICATE KEY UPDATE `key` = `key`;

-- ============================================
-- SEED DATA
-- ============================================

-- Add default suppression list entries (example common bounces)
INSERT IGNORE INTO `suppression_list` (`email`, `type`, `reason`, `source`) VALUES
('postmaster@localhost', 'hard_bounce', 'Localhost addresses are not deliverable', 'system'),
('noreply@example.com', 'hard_bounce', 'No-reply addresses should not receive email', 'system');

-- ============================================
-- STORED PROCEDURES
-- ============================================

DELIMITER //

-- Procedure to check if email is suppressed
CREATE PROCEDURE IF NOT EXISTS `check_email_suppressed`(
    IN p_email VARCHAR(255)
)
BEGIN
    SELECT 
        sl.type,
        sl.reason,
        sl.created_at,
        CASE 
            WHEN sl.expires_at IS NOT NULL AND sl.expires_at < NOW() THEN 0
            ELSE 1
        END as is_active
    FROM suppression_list sl
    WHERE sl.email = p_email
    LIMIT 1;
END //

-- Procedure to add bounce and auto-suppress
CREATE PROCEDURE IF NOT EXISTS `process_bounce`(
    IN p_job_id INT,
    IN p_item_id INT,
    IN p_email VARCHAR(255),
    IN p_bounce_type VARCHAR(20),
    IN p_bounce_code VARCHAR(50),
    IN p_bounce_reason VARCHAR(500),
    IN p_raw_data TEXT
)
BEGIN
    -- Log the bounce
    INSERT INTO bounce_log (
        mail_job_id, mail_job_item_id, email, bounce_type, 
        bounce_code, bounce_reason, raw_bounce_data
    ) VALUES (
        p_job_id, p_item_id, p_email, p_bounce_type,
        p_bounce_code, p_bounce_reason, p_raw_data
    );
    
    -- Auto-suppress hard bounces
    IF p_bounce_type = 'hard' THEN
        INSERT INTO suppression_list (email, type, reason, source)
        VALUES (p_email, 'hard_bounce', p_bounce_reason, CONCAT('job_', p_job_id))
        ON DUPLICATE KEY UPDATE 
            type = 'hard_bounce',
            reason = p_bounce_reason,
            updated_at = NOW();
    END IF;
    
    -- Update job item status
    UPDATE mail_job_items 
    SET status = 'failed', 
        status_message = CONCAT('Bounced: ', p_bounce_reason)
    WHERE id = p_item_id;
END //

-- Procedure to get analytics summary
CREATE PROCEDURE IF NOT EXISTS `get_job_analytics`(
    IN p_job_id INT
)
BEGIN
    SELECT 
        event_type,
        COUNT(*) as count,
        COUNT(DISTINCT email) as unique_count
    FROM email_analytics
    WHERE mail_job_id = p_job_id
    GROUP BY event_type
    ORDER BY count DESC;
END //

DELIMITER ;

-- ============================================
-- ADD COLUMNS TO EXISTING TABLES
-- ============================================

-- Add tracking columns to mail_job_items (using separate statements for MySQL 5.7 compatibility)
ALTER TABLE `mail_job_items` ADD COLUMN `is_opened` TINYINT(1) DEFAULT 0 AFTER `sent_at`;
ALTER TABLE `mail_job_items` ADD COLUMN `open_count` INT DEFAULT 0 AFTER `is_opened`;
ALTER TABLE `mail_job_items` ADD COLUMN `first_opened_at` DATETIME NULL AFTER `open_count`;
ALTER TABLE `mail_job_items` ADD COLUMN `click_count` INT DEFAULT 0 AFTER `first_opened_at`;
ALTER TABLE `mail_job_items` ADD COLUMN `tracking_pixel_code` VARCHAR(32) NULL AFTER `click_count`;
ALTER TABLE `mail_job_items` ADD INDEX `idx_opened` (`is_opened`);
ALTER TABLE `mail_job_items` ADD INDEX `idx_tracking_code` (`tracking_pixel_code`);

-- Add bounce processing columns to mail_jobs (using separate statements for MySQL 5.7 compatibility)
ALTER TABLE `mail_jobs` ADD COLUMN `bounce_count` INT DEFAULT 0 AFTER `status`;
ALTER TABLE `mail_jobs` ADD COLUMN `complaint_count` INT DEFAULT 0 AFTER `bounce_count`;
ALTER TABLE `mail_jobs` ADD COLUMN `open_count` INT DEFAULT 0 AFTER `complaint_count`;
ALTER TABLE `mail_jobs` ADD COLUMN `click_count` INT DEFAULT 0 AFTER `open_count`;

-- ============================================
-- END MIGRATION
-- ============================================

-- Database schema for Client Engine Sender (Alternative 4)
-- This schema supports Python-based local Outlook sender with token authentication

-- Table for user API tokens (for client engine authentication)
CREATE TABLE IF NOT EXISTS `user_api_tokens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `token` VARCHAR(255) NOT NULL UNIQUE,
    `token_name` VARCHAR(100) DEFAULT 'Default',
    `description` VARCHAR(255) NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `last_used_at` DATETIME NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_token` (`token`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for email queue (for client engine to fetch)
CREATE TABLE IF NOT EXISTS `email_queue` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `job_id` INT NULL,
    `status` ENUM('pending', 'processing', 'sent', 'failed', 'cancelled') DEFAULT 'pending',
    `priority` INT DEFAULT 5,
    `from_email` VARCHAR(255) NULL,
    `from_name` VARCHAR(255) NULL,
    `to_email` TEXT NOT NULL,
    `cc_email` TEXT NULL,
    `bcc_email` TEXT NULL,
    `subject` VARCHAR(500) NOT NULL,
    `body_html` LONGTEXT NULL,
    `body_text` LONGTEXT NULL,
    `attachments_json` TEXT NULL,
    `reply_to` VARCHAR(255) NULL,
    `headers_json` TEXT NULL,
    `error_message` TEXT NULL,
    `retry_count` INT DEFAULT 0,
    `max_retries` INT DEFAULT 3,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `scheduled_at` DATETIME NULL,
    `processed_at` DATETIME NULL,
    `sent_at` DATETIME NULL,
    `client_engine_id` VARCHAR(100) NULL,
    `client_ip` VARCHAR(45) NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`job_id`) REFERENCES `mail_jobs`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_status` (`user_id`, `status`),
    INDEX `idx_status_scheduled` (`status`, `scheduled_at`),
    INDEX `idx_priority` (`priority`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for client engine sender logs
CREATE TABLE IF NOT EXISTS `client_engine_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `token_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `engine_id` VARCHAR(100) NULL,
    `engine_version` VARCHAR(20) NULL,
    `action` VARCHAR(50) NOT NULL,
    `email_queue_id` INT NULL,
    `status` VARCHAR(20) NULL,
    `message` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`token_id`) REFERENCES `user_api_tokens`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`email_queue_id`) REFERENCES `email_queue`(`id`) ON DELETE SET NULL,
    INDEX `idx_token_id` (`token_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for client engine settings per user
CREATE TABLE IF NOT EXISTS `client_engine_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `outlook_account` VARCHAR(255) NULL,
    `send_delay_ms` INT DEFAULT 1000,
    `max_batch_size` INT DEFAULT 10,
    `auto_start` TINYINT(1) DEFAULT 0,
    `auto_check_interval_sec` INT DEFAULT 60,
    `last_sync_at` DATETIME NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add email_sending_mode 'client_engine' if not exists in system_settings
INSERT INTO `system_settings` (`key`, `value`, `type`, `description`)
VALUES ('email_sending_mode', 'client_engine', 'string', 'Email sending mode: outlook_com, graph_api, smtp, or client_engine')
ON DUPLICATE KEY UPDATE `description` = 'Email sending mode: outlook_com, graph_api, smtp, or client_engine';

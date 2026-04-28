-- Security Enhancements for Email Dispatcher Suite
-- Adds tables and columns for security features

-- Add security-related columns to users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS locked_until DATETIME NULL COMMENT 'Account locked until this time';
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login DATETIME NULL COMMENT 'Last successful login';
ALTER TABLE users ADD COLUMN IF NOT EXISTS failed_login_attempts INT DEFAULT 0 COMMENT 'Failed login attempts counter';
ALTER TABLE users ADD COLUMN IF NOT EXISTS requires_password_change BOOLEAN DEFAULT FALSE COMMENT 'Force password change on next login';
ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_enabled BOOLEAN DEFAULT FALSE COMMENT '2FA enabled';
ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(255) NULL COMMENT '2FA secret';

-- Security logs table for tracking suspicious activities
CREATE TABLE IF NOT EXISTS security_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(50) NOT NULL COMMENT 'login_attempt, failed_login, suspicious, ip_change, etc',
  action_key VARCHAR(255) NOT NULL COMMENT 'User ID, IP, Email, etc',
  details TEXT NULL COMMENT 'Additional details',
  ip_address VARCHAR(45) NULL COMMENT 'IPv4 or IPv6',
  user_agent TEXT NULL,
  timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_event_type (event_type),
  INDEX idx_action_key (action_key),
  INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

-- API access logs for rate limiting
CREATE TABLE IF NOT EXISTS api_rate_limits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  endpoint VARCHAR(255) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  user_id INT NULL,
  request_count INT DEFAULT 1,
  first_request DATETIME DEFAULT CURRENT_TIMESTAMP,
  last_request DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_endpoint_ip (endpoint, ip_address),
  INDEX idx_user_id (user_id),
  INDEX idx_last_request (last_request)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Password history to prevent reuse
CREATE TABLE IF NOT EXISTS password_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Audit log untuk tracking changes
CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(100) NOT NULL COMMENT 'create_user, delete_user, send_email, change_settings, etc',
  table_name VARCHAR(100) NULL,
  record_id INT NULL,
  before_data JSON NULL COMMENT 'Data sebelum perubahan',
  after_data JSON NULL COMMENT 'Data setelah perubahan',
  ip_address VARCHAR(45) NULL,
  timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_user_id (user_id),
  INDEX idx_action (action),
  INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Encryption keys table for sensitive data
CREATE TABLE IF NOT EXISTS encryption_keys (
  id INT AUTO_INCREMENT PRIMARY KEY,
  key_name VARCHAR(100) NOT NULL UNIQUE,
  key_value BLOB NOT NULL COMMENT 'Encrypted key',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Session whitelist untuk IP tracking
CREATE TABLE IF NOT EXISTS session_whitelist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  user_agent VARCHAR(255) NOT NULL,
  device_fingerprint VARCHAR(255) NOT NULL,
  trusted BOOLEAN DEFAULT FALSE,
  last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_trusted (trusted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create stored procedure untuk cleanup old logs
DELIMITER //

DROP PROCEDURE IF EXISTS cleanup_old_logs //
CREATE PROCEDURE cleanup_old_logs()
BEGIN
  -- Keep last 90 days of security logs
  DELETE FROM security_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY);
  
  -- Keep last 30 days of audit logs
  DELETE FROM audit_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY);
  
  -- Keep last 7 days of API rate limits
  DELETE FROM api_rate_limits WHERE last_request < DATE_SUB(NOW(), INTERVAL 7 DAY);
END //

DELIMITER ;

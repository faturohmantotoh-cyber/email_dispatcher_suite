-- Create user_sessions table for real-time active user monitoring
USE email_dispatcher;

CREATE TABLE IF NOT EXISTS user_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  session_id VARCHAR(255) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  hostname VARCHAR(255) DEFAULT NULL,
  user_agent TEXT,
  login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  last_activity DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_session (session_id),
  INDEX idx_user_id (user_id),
  INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

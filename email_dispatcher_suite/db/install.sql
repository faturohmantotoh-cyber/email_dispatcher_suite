-- MySQL schema for email dispatcher
CREATE DATABASE IF NOT EXISTS `email_dispatcher` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `email_dispatcher`;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  email VARCHAR(200) NOT NULL,
  display_name VARCHAR(200),
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_username (username),
  INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  display_name VARCHAR(200) NOT NULL,
  email VARCHAR(200) NOT NULL UNIQUE,
  source ENUM('Outlook','Manual') DEFAULT 'Outlook',
  last_synced DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_name (display_name),
  INDEX idx_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(255) NOT NULL,
  path VARCHAR(500) NOT NULL,
  sha1 CHAR(40) NOT NULL,
  size BIGINT NOT NULL,
  uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sha1 (sha1),
  INDEX idx_filename (filename)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mail_jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  subject VARCHAR(255) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  cc VARCHAR(500) NULL,
  mode ENUM('by_similarity','manual') DEFAULT 'by_similarity',
  status ENUM('pending','processing','completed','failed') DEFAULT 'pending'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mail_job_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mail_job_id INT NOT NULL,
  recipient_email VARCHAR(200) NOT NULL,
  recipient_name VARCHAR(200) NULL,
  attachment_path VARCHAR(500) NULL,
  similarity_score DECIMAL(5,2) NULL,
  status ENUM('pending','sent','failed','skipped') DEFAULT 'pending',
  status_message TEXT NULL,
  sent_at DATETIME NULL,
  FOREIGN KEY (mail_job_id) REFERENCES mail_jobs(id) ON DELETE CASCADE,
  INDEX idx_job (mail_job_id),
  INDEX idx_status (status)
) ENGINE=InnoDB;

-- Seed minimal data
INSERT IGNORE INTO users(username, email, display_name, password_hash) VALUES
('admin@local', 'admin@local', 'Administrator', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36ZyWsSe');

INSERT IGNORE INTO contacts(display_name, email, source) VALUES
('Contoh User 1', 'user1@example.com', 'Manual'),
('Contoh User 2', 'user2@example.com', 'Manual');

-- ICCT Queue Thesis System
-- Import this file in phpMyAdmin, then open http://localhost/icct-queue-thesis/

CREATE DATABASE IF NOT EXISTS icct_queue_thesis
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE icct_queue_thesis;

CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_no VARCHAR(50) NOT NULL UNIQUE,
  fullname VARCHAR(150) NOT NULL,
  mobile VARCHAR(30) NULL,
  rfid_uid VARCHAR(64) NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(16) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  window_no INT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS recommendation_nodes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  prompt VARCHAR(255) NOT NULL,
  parent_id INT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  service_id INT NULL,
  CONSTRAINT fk_rec_parent FOREIGN KEY (parent_id) REFERENCES recommendation_nodes(id) ON DELETE CASCADE,
  CONSTRAINT fk_rec_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS queue_tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_no VARCHAR(32) NOT NULL UNIQUE,
  service_id INT NOT NULL,
  student_id INT NULL,
  source ENUM('online','rfid') NOT NULL,
  status ENUM('waiting','called','serving','completed','cancelled','expired') NOT NULL DEFAULT 'waiting',
  booked_for DATETIME NULL,
  created_at DATETIME NOT NULL,
  called_at DATETIME NULL,
  served_at DATETIME NULL,
  completed_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  CONSTRAINT fk_ticket_service FOREIGN KEY (service_id) REFERENCES services(id),
  CONSTRAINT fk_ticket_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
  INDEX idx_ticket_status (status),
  INDEX idx_ticket_service_status (service_id, status),
  INDEX idx_ticket_created (created_at),
  INDEX idx_ticket_booked_for (booked_for)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sms_outbox (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NULL,
  mobile VARCHAR(30) NOT NULL,
  message TEXT NOT NULL,
  status VARCHAR(30) NOT NULL,
  created_at DATETIME NOT NULL,
  sent_at DATETIME NULL,
  error_text TEXT NULL,
  CONSTRAINT fk_sms_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Seed admin user (password is set by install.php)
INSERT INTO admins (username, password_hash, created_at)
VALUES ('admin', 'CHANGE_ME_RUN_INSTALL', NOW())
ON DUPLICATE KEY UPDATE username=username;

-- Seed services / windows (paper-aligned)
INSERT INTO services (code, name, window_no, is_active) VALUES
('REG', 'Registrar Documents (TOR / Grades / Certifications)', 1, 1),
('PROS', 'Prospectus Evaluation / Advising', 2, 1),
('ENR', 'Enrollment / Adding / Dropping', 3, 1)
ON DUPLICATE KEY UPDATE name=VALUES(name), window_no=VALUES(window_no), is_active=VALUES(is_active);

-- Seed a basic decision-tree recommendation
-- Root prompt
INSERT INTO recommendation_nodes (prompt, parent_id, sort_order, service_id)
SELECT 'What do you need help with?', NULL, 0, NULL
WHERE NOT EXISTS (SELECT 1 FROM recommendation_nodes WHERE parent_id IS NULL);

SET @root_id = (SELECT id FROM recommendation_nodes WHERE parent_id IS NULL ORDER BY id ASC LIMIT 1);
SET @svc_reg = (SELECT id FROM services WHERE code='REG' LIMIT 1);
SET @svc_pros = (SELECT id FROM services WHERE code='PROS' LIMIT 1);
SET @svc_enr = (SELECT id FROM services WHERE code='ENR' LIMIT 1);

INSERT INTO recommendation_nodes (prompt, parent_id, sort_order, service_id)
SELECT 'I need my grades / TOR / certifications', @root_id, 1, @svc_reg
WHERE NOT EXISTS (SELECT 1 FROM recommendation_nodes WHERE parent_id=@root_id AND prompt='I need my grades / TOR / certifications');

INSERT INTO recommendation_nodes (prompt, parent_id, sort_order, service_id)
SELECT 'I need to evaluate my prospectus', @root_id, 2, @svc_pros
WHERE NOT EXISTS (SELECT 1 FROM recommendation_nodes WHERE parent_id=@root_id AND prompt='I need to evaluate my prospectus');

INSERT INTO recommendation_nodes (prompt, parent_id, sort_order, service_id)
SELECT 'I have enrollment / adding / dropping concerns', @root_id, 3, @svc_enr
WHERE NOT EXISTS (SELECT 1 FROM recommendation_nodes WHERE parent_id=@root_id AND prompt='I have enrollment / adding / dropping concerns');


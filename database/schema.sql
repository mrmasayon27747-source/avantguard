-- Avantguard Payroll System Database Schema
-- Run this script to create the database and tables

CREATE DATABASE IF NOT EXISTS avantguard_payroll
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

USE avantguard_payroll;

-- ========================================
-- USERS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin', 'worker') NOT NULL DEFAULT 'worker',
  employee_id INT NULL,
  must_change_password TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_username (username),
  INDEX idx_employee_id (employee_id)
) ENGINE=InnoDB;

-- ========================================
-- EMPLOYEES TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS employees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  position VARCHAR(100) NULL,
  pay_type ENUM('hourly', 'fixed') NOT NULL DEFAULT 'hourly',
  hourly_rate DECIMAL(10,2) DEFAULT 0.00,
  fixed_default_hours INT DEFAULT 0,
  fixed_daily_rate DECIMAL(10,2) DEFAULT 0.00,
  schedule_start TIME DEFAULT '07:00:00',
  schedule_end TIME DEFAULT '20:00:00',
  schedule_days VARCHAR(100) DEFAULT 'Mon,Tue,Wed,Thu,Fri',
  schedules TEXT NULL COMMENT 'JSON array of schedule blocks: [{days:[], start:, end:}]',
  active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_employee_code (employee_code),
  INDEX idx_active (active)
) ENGINE=InnoDB;

-- ========================================
-- PROFILES TABLE (Worker Extended Info)
-- ========================================
CREATE TABLE IF NOT EXISTS profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL UNIQUE,
  name VARCHAR(150) NULL,
  contact_number VARCHAR(50) NULL,
  home_address TEXT NULL,
  email_address VARCHAR(150) NULL,
  birthdate DATE NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ========================================
-- ATTENDANCE TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS attendance (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  date DATE NOT NULL,
  mode ENUM('hourly', 'fixed') NOT NULL DEFAULT 'hourly',
  time_in TIME NULL,
  time_out TIME NULL,
  fixed_hours INT DEFAULT 0,
  hours DECIMAL(5,2) DEFAULT 0.00,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  created_by VARCHAR(50) NULL,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by VARCHAR(50) NULL,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  INDEX idx_employee_date (employee_id, date),
  INDEX idx_date (date),
  UNIQUE KEY unique_employee_date (employee_id, date)
) ENGINE=InnoDB;

-- ========================================
-- PAYSLIPS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS payslips (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  employee_name VARCHAR(200) NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  pay_type ENUM('hourly', 'fixed') DEFAULT 'hourly',
  rate DECIMAL(10,2) DEFAULT 0.00,
  days_present INT DEFAULT 0,
  total_hours DECIMAL(8,2) DEFAULT 0.00,
  overtime_hours DECIMAL(8,2) DEFAULT 0.00,
  gross_pay DECIMAL(12,2) DEFAULT 0.00,
  overtime_pay DECIMAL(12,2) DEFAULT 0.00,
  manual_overtime_bonus DECIMAL(12,2) DEFAULT 0.00,
  total_deductions DECIMAL(12,2) DEFAULT 0.00,
  net_pay DECIMAL(12,2) DEFAULT 0.00,
  status ENUM('draft', 'released') DEFAULT 'draft',
  calculated_by VARCHAR(200) NULL DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  released_at DATETIME NULL,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  INDEX idx_employee_id (employee_id),
  INDEX idx_period (period_start, period_end),
  INDEX idx_status (status)
) ENGINE=InnoDB;

-- ========================================
-- DEDUCTIONS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS deductions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  date DATE NOT NULL,
  reason VARCHAR(100) NOT NULL,
  reason_type VARCHAR(50) NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  notes TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  created_by INT NULL,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  INDEX idx_employee_date (employee_id, date)
) ENGINE=InnoDB;

-- ========================================
-- TASKS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS tasks (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
  due_date DATE NULL,
  status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  created_by INT NULL,
  completed_at DATETIME NULL,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  INDEX idx_employee_id (employee_id),
  INDEX idx_status (status),
  INDEX idx_due_date (due_date)
) ENGINE=InnoDB;

-- ========================================
-- EOD REPORTS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS eod_reports (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  date DATE NOT NULL,
  tasks_completed TEXT NULL,
  pending_concerns TEXT NULL,
  notes_status VARCHAR(50) NULL DEFAULT NULL,
  photo VARCHAR(255) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  INDEX idx_employee_date (employee_id, date),
  UNIQUE KEY unique_employee_date (employee_id, date)
) ENGINE=InnoDB;

-- ========================================
-- DEVOTIONALS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS devotionals (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  date DATE NOT NULL,
  photo VARCHAR(255) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  INDEX idx_employee_date (employee_id, date),
  UNIQUE KEY unique_employee_date (employee_id, date)
) ENGINE=InnoDB;

-- ========================================
-- INSERT DEFAULT ADMIN USER
-- ========================================
INSERT INTO users (username, password_hash, role) 
VALUES ('admin', '$2y$10$AHTUCGjpBZTEfcvRnXEZE.AJWQGcL7fdN4a/8lg8gO8q15qc9f.sW', 'admin')
ON DUPLICATE KEY UPDATE username = username;

-- Smart Classroom & Timetable Scheduler
-- MySQL dump for phpMyAdmin import
-- Uses InnoDB, utf8mb4

SET FOREIGN_KEY_CHECKS=0;
DROP DATABASE IF EXISTS smart_scheduler;
CREATE DATABASE smart_scheduler CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smart_scheduler;
SET FOREIGN_KEY_CHECKS=1;

-- Roles / Users
CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  description VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_id INT NOT NULL,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(150),
  email VARCHAR(150) UNIQUE,
  phone VARCHAR(30),
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE departments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE batches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  department_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  shift ENUM('Morning','Afternoon','Evening') DEFAULT 'Morning',
  year INT NULL,
  max_classes_per_day TINYINT DEFAULT 8,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE classrooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  capacity INT DEFAULT 30,
  location VARCHAR(255),
  features VARCHAR(255),
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  department_id INT NOT NULL,
  code VARCHAR(50) NOT NULL,
  name VARCHAR(200) NOT NULL,
  lecture_hours_per_week TINYINT DEFAULT 0,
  practical_hours_per_week TINYINT DEFAULT 0,
  required_classrooms VARCHAR(255),
  is_elective TINYINT(1) DEFAULT 0,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
  UNIQUE (department_id, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE faculties (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  employee_code VARCHAR(100) UNIQUE,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) UNIQUE,
  phone VARCHAR(30),
  max_classes_per_day TINYINT DEFAULT 6,
  avg_leaves_per_month DECIMAL(4,2) DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE faculty_subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  faculty_id INT NOT NULL,
  subject_id INT NOT NULL,
  preference_score TINYINT DEFAULT 5,
  UNIQUE(faculty_id, subject_id),
  FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE timetable_slots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slot_code VARCHAR(50) NOT NULL UNIQUE,
  day ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  slot_order TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fixed_classes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  batch_id INT NOT NULL,
  subject_id INT NOT NULL,
  slot_id INT NOT NULL,
  classroom_id INT NULL,
  faculty_id INT NULL,
  notes VARCHAR(255),
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (slot_id) REFERENCES timetable_slots(id) ON DELETE CASCADE,
  FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE SET NULL,
  FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE faculty_leaves (
  id INT AUTO_INCREMENT PRIMARY KEY,
  faculty_id INT NOT NULL,
  leave_date DATE NOT NULL,
  leave_type VARCHAR(100),
  approved_by INT NULL,
  status ENUM('Pending','Approved','Rejected') DEFAULT 'Approved',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON DELETE CASCADE,
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE timetable_options (
  id INT AUTO_INCREMENT PRIMARY KEY,
  batch_id INT NOT NULL,
  generated_by INT NULL,
  score DECIMAL(6,3) DEFAULT 0.0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  notes TEXT,
  FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
  FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE timetable_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  option_id INT NOT NULL,
  day ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  slot_id INT NOT NULL,
  batch_id INT NOT NULL,
  subject_id INT NOT NULL,
  faculty_id INT NOT NULL,
  classroom_id INT NULL,
  duration_slots TINYINT DEFAULT 1,
  is_fixed TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(option_id, batch_id, slot_id),
  FOREIGN KEY (option_id) REFERENCES timetable_options(id) ON DELETE CASCADE,
  FOREIGN KEY (slot_id) REFERENCES timetable_slots(id) ON DELETE CASCADE,
  FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT,
  FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON DELETE RESTRICT,
  FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE timetable_approvals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  option_id INT NOT NULL,
  reviewer_id INT NOT NULL,
  status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
  comments TEXT,
  reviewed_at TIMESTAMP NULL,
  FOREIGN KEY (option_id) REFERENCES timetable_options(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE suggestions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  option_id INT NULL,
  entry_id INT NULL,
  suggested_action VARCHAR(255) NOT NULL,
  reason TEXT,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  applied TINYINT(1) DEFAULT 0,
  FOREIGN KEY (option_id) REFERENCES timetable_options(id) ON DELETE SET NULL,
  FOREIGN KEY (entry_id) REFERENCES timetable_entries(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  scope ENUM('global','department','batch') DEFAULT 'global',
  scope_id INT NULL,
  key_name VARCHAR(150) NOT NULL,
  value_text TEXT,
  UNIQUE(scope, scope_id, key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO roles (name, description) VALUES
  ('admin','System administrator'),
  ('timetabler','Person who generates timetables'),
  ('faculty','Teaching faculty'),
  ('reviewer','Approving authority');

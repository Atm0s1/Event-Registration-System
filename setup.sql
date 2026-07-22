-- ============================================================
-- Event Management System — Database Schema
-- Run via phpMyAdmin or: mysql -u root < setup.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS dbevent
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE dbevent;

-- Drop old tables (order matters for FKs)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS registration_answers;
DROP TABLE IF EXISTS registrations;
DROP TABLE IF EXISTS event_requirements;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS admins;
SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------
-- Admin accounts
-- -----------------------------------------------
CREATE TABLE admins (
    admin_id    INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------
-- Customer / user accounts
-- -----------------------------------------------
CREATE TABLE users (
    user_id        INT AUTO_INCREMENT PRIMARY KEY,
    fname          VARCHAR(100) NOT NULL,
    lname          VARCHAR(100) NOT NULL,
    email          VARCHAR(255) NOT NULL UNIQUE,
    password       VARCHAR(255) NOT NULL,
    contact_number VARCHAR(20)  DEFAULT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------
-- Events
-- -----------------------------------------------
CREATE TABLE events (
    event_id    INT AUTO_INCREMENT PRIMARY KEY,
    event_name  VARCHAR(255) NOT NULL,
    description TEXT,
    event_date  DATE         DEFAULT NULL,
    event_time  TIME         DEFAULT NULL,
    venue       VARCHAR(255) DEFAULT NULL,
    latitude    DECIMAL(10,8) DEFAULT NULL,
    longitude   DECIMAL(11,8) DEFAULT NULL,
    icon        VARCHAR(50)  DEFAULT '📋',
    color       VARCHAR(20)  DEFAULT '#667eea',
    min_age     INT          DEFAULT 0,
    max_capacity INT         DEFAULT NULL,
    is_active   TINYINT(1)   DEFAULT 1,
    is_archived TINYINT(1)   DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------
-- 3 requirement questions per event
-- -----------------------------------------------
CREATE TABLE event_requirements (
    req_id           INT AUTO_INCREMENT PRIMARY KEY,
    event_id         INT          NOT NULL,
    requirement_text VARCHAR(500) NOT NULL,
    sort_order       INT          DEFAULT 1,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------
-- User ↔ Event registrations
-- -----------------------------------------------
CREATE TABLE registrations (
    reg_id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    event_id        INT NOT NULL,
    status          ENUM('pending','approved','rejected') DEFAULT 'pending',
    qr_token        VARCHAR(64) UNIQUE DEFAULT NULL,
    attendance_status ENUM('pending','present') DEFAULT 'pending',
    registered_date DATE    DEFAULT NULL,
    registered_time TIME    DEFAULT NULL,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)  REFERENCES users(user_id)   ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    UNIQUE KEY unique_reg (user_id, event_id)
) ENGINE=InnoDB;

-- -----------------------------------------------
-- Answers to event requirements
-- -----------------------------------------------
CREATE TABLE registration_answers (
    answer_id   INT AUTO_INCREMENT PRIMARY KEY,
    reg_id      INT  NOT NULL,
    req_id      INT  NOT NULL,
    answer_text TEXT,
    FOREIGN KEY (reg_id) REFERENCES registrations(reg_id)       ON DELETE CASCADE,
    FOREIGN KEY (req_id) REFERENCES event_requirements(req_id)  ON DELETE CASCADE
) ENGINE=InnoDB;

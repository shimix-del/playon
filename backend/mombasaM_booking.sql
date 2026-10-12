-- ============================================================================
-- Play-On Mombasa Booking System Database Schema
-- Database Name: mombasaM_booking
-- Compatible with MySQL 5.7+, MySQL 8.0+, MariaDB 10.3+, and phpMyAdmin
-- Generated for cPanel Hosting
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+03:00"; -- East Africa Time (EAT)

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Table structure for table `admins`
--
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'admin',
  `failed_login_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` DATETIME NULL DEFAULT NULL,
  `last_login_at` DATETIME NULL DEFAULT NULL,
  `last_login_ip` VARCHAR(45) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_admin_username` (`username`),
  KEY `idx_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping default administrator account
-- Default Credentials:
-- Username: admin
-- Password: PlayOn@Admin2026!
--
INSERT INTO `admins` (`id`, `username`, `email`, `password_hash`, `role`, `created_at`) 
VALUES (
  1,
  'admin',
  'playonkenya@gmail.com',
  '$2y$10$Se7bty9QLibkS2Q2d5ubger6wer2SH9hSBAoDW7lbehYzvaQenIo6',
  'superadmin',
  NOW()
) ON DUPLICATE KEY UPDATE `id`=`id`;

--
-- Table structure for table `bookings`
--
CREATE TABLE IF NOT EXISTS `bookings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_ref` VARCHAR(30) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(35) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `booking_type` VARCHAR(50) NOT NULL DEFAULT 'session',
  `booking_type_label` VARCHAR(100) NOT NULL DEFAULT 'Standard Session Pass',
  `booking_date` DATE NOT NULL,
  `session_time` VARCHAR(100) NOT NULL,
  `children_count` INT UNSIGNED NOT NULL DEFAULT 1,
  `adults_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `estimated_total` VARCHAR(50) NOT NULL DEFAULT 'KES 1,000',
  `notes` TEXT NULL DEFAULT NULL,
  `status` ENUM('pending', 'confirmed', 'cancelled') NOT NULL DEFAULT 'pending',
  `admin_notes` TEXT NULL DEFAULT NULL,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `user_agent` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_booking_ref` (`booking_ref`),
  KEY `idx_booking_status` (`status`),
  KEY `idx_booking_date` (`booking_date`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_booking_email` (`email`),
  KEY `idx_booking_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `email_log`
--
CREATE TABLE IF NOT EXISTS `email_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NULL DEFAULT NULL,
  `recipient` VARCHAR(150) NOT NULL,
  `recipient_name` VARCHAR(150) NULL DEFAULT NULL,
  `email_type` ENUM('admin_notification', 'customer_confirmation', 'status_update') NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `status` ENUM('sent', 'failed') NOT NULL DEFAULT 'sent',
  `error_message` TEXT NULL DEFAULT NULL,
  `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_booking_id` (`booking_id`),
  KEY `idx_email_status` (`status`),
  KEY `idx_email_sent_at` (`sent_at`),
  CONSTRAINT `fk_email_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `rate_limits`
--
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` VARCHAR(45) NOT NULL,
  `endpoint` VARCHAR(50) NOT NULL,
  `request_time` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rate_ip_time` (`ip_address`, `request_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Optional sample booking to verify layout immediately after import
--
INSERT INTO `bookings` (
  `booking_ref`, `full_name`, `phone`, `email`, 
  `booking_type`, `booking_type_label`, `booking_date`, 
  `session_time`, `children_count`, `adults_count`, 
  `estimated_total`, `notes`, `status`, `created_at`
) VALUES (
  'PO-SAMPLE-001',
  'Amina Hassan',
  '+254 712 345 678',
  'amina.sample@example.com',
  'birthday',
  'Birthday Party Package',
  CURDATE() + INTERVAL 3 DAY,
  'Session 2 (2:00 PM - 5:00 PM)',
  5,
  2,
  'KES 8,500',
  'Celebrating Tariq 7th birthday! Would love a Mickey Mouse balloon welcome setup.',
  'pending',
  NOW()
) ON DUPLICATE KEY UPDATE `id`=`id`;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

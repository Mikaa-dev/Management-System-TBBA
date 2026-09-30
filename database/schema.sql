-- Skema Pangkalan Data Sistem Mini ERP
-- Syarikat: The Bridge Business Alliance (TBBA)
-- Tarikh: 2026

CREATE DATABASE IF NOT EXISTS `tbba_erp` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `tbba_erp`;

-- 1. Jadual Pengguna (Users)
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `position` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT 'default.png',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Jadual Kehadiran GPS (Attendance)
CREATE TABLE IF NOT EXISTS `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `clock_in` datetime DEFAULT NULL,
  `clock_out` datetime DEFAULT NULL,
  `clock_in_lat` decimal(10,8) DEFAULT NULL,
  `clock_in_lng` decimal(11,8) DEFAULT NULL,
  `clock_out_lat` decimal(10,8) DEFAULT NULL,
  `clock_out_lng` decimal(11,8) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'present',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `date_idx` (`date`),
  CONSTRAINT `fk_att_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Jadual Tender & KPI Tracker (Tenders)
CREATE TABLE IF NOT EXISTS `tenders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `project_value` decimal(15,2) NOT NULL,
  `indicative_price` decimal(15,2) DEFAULT NULL,
  `cost` decimal(15,2) DEFAULT NULL,
  `selling_price` decimal(15,2) DEFAULT NULL,
  `pricing_attachment` varchar(255) DEFAULT NULL,
  `pricing_attachment_name` varchar(255) DEFAULT NULL,
  `pricing_updated_at` datetime DEFAULT NULL,
  `closing_date` date NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `status` enum('in_progress','submitted','won','lost') DEFAULT 'submitted',
  `month_year` varchar(7) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `month_year_idx` (`month_year`),
  CONSTRAINT `fk_ten_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Jadual Pengurusan Surat (Letters)
CREATE TABLE IF NOT EXISTS `letters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('IN','OUT') NOT NULL,
  `ref_no` varchar(100) NOT NULL UNIQUE,
  `title` varchar(255) NOT NULL,
  `sender_receiver` varchar(255) NOT NULL,
  `letter_date` date NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','in_progress','replied','completed','read') DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status_idx` (`status`),
  CONSTRAINT `fk_let_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SQL Migration: PWA Web Push Notification System (Module 11)
-- Company: The Bridge Business Alliance (TBBA)

USE `tbba_erp`;

-- 1. Table for storing user device push subscriptions
CREATE TABLE IF NOT EXISTS `push_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `endpoint` varchar(500) NOT NULL UNIQUE,
  `p256dh` varchar(255) NOT NULL,
  `auth` varchar(255) NOT NULL,
  `content_encoding` varchar(50) DEFAULT 'aes128gcm',
  `device_name` varchar(100) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_used_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id_active_idx` (`user_id`, `is_active`),
  KEY `endpoint_idx` (`endpoint`(250)),
  CONSTRAINT `fk_push_sub_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table for user notification category preferences
CREATE TABLE IF NOT EXISTS `user_notification_preferences` (
  `user_id` int(11) NOT NULL,
  `leave_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `approval_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `expense_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `purchase_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `announcement_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `system_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_notif_pref_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

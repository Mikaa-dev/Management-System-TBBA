-- ============================================================
-- TBBA ERP — Module: Org Chart & Positions
-- ============================================================

USE `tbba_erp`;

CREATE TABLE IF NOT EXISTS `positions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `department_id` INT NULL,
  `reports_to_position_id` INT NULL,
  `level` INT DEFAULT 1,
  `max_headcount` INT DEFAULT 1,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pos_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pos_reports` FOREIGN KEY (`reports_to_position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add position_id to users safely
ALTER TABLE `users` ADD COLUMN `position_id` INT NULL;

-- If FK already exists this might fail, but let's try to add it. A safer way is checking if it exists, but for now we'll just try to add it. 
-- In MySQL we can't easily do ADD CONSTRAINT IF NOT EXISTS on older versions, so I'll write a PHP script that catches the error.
ALTER TABLE `users` ADD CONSTRAINT `fk_user_pos` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL;

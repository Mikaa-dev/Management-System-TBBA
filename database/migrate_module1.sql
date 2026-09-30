-- ============================================================
-- TBBA ERP — Module 1: User Roles & Permissions Migration
-- Run once. Safe to re-run (uses IF NOT EXISTS / IF EXISTS).
-- ============================================================

USE `tbba_erp`;

-- ============================================================
-- STEP 1: Alter users table — expand role + add new columns
-- ============================================================
ALTER TABLE `users`
  MODIFY COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'staff';

-- Add columns safely (ignore error if already exists via stored procedure approach)
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `department_id` INT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `branch_id` INT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `status` ENUM('active','suspended') NOT NULL DEFAULT 'active';
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `phone` VARCHAR(30) NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `employee_id` VARCHAR(50) NULL;

-- ============================================================
-- STEP 2: roles table
-- ============================================================
CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `display_name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `color` VARCHAR(20) DEFAULT '#64748B',
  `icon` VARCHAR(80) DEFAULT 'fa-user',
  `level` INT NOT NULL DEFAULT 5 COMMENT 'Lower = higher authority (1=super_admin, 9=lowest)',
  `is_system` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STEP 3: permissions table (module × action catalogue)
-- ============================================================
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `module` VARCHAR(100) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `label` VARCHAR(150) NULL,
  UNIQUE KEY `uq_perm` (`module`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STEP 4: role_permissions table (the configurable matrix)
-- ============================================================
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role_name` VARCHAR(50) NOT NULL,
  `module` VARCHAR(100) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `allowed` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_rp` (`role_name`, `module`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STEP 5: user_permission_overrides table
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_permission_overrides` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `module` VARCHAR(100) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `allowed` TINYINT(1) NOT NULL DEFAULT 0,
  `set_by` INT NULL COMMENT 'Admin user ID who set this override',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_upo` (`user_id`, `module`, `action`),
  CONSTRAINT `fk_upo_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STEP 6: departments table
-- ============================================================
CREATE TABLE IF NOT EXISTS `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(20) NULL UNIQUE,
  `head_user_id` INT NULL,
  `description` TEXT NULL,
  `contact_email` VARCHAR(150) NULL,
  `contact_phone` VARCHAR(30) NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STEP 7: branches table
-- ============================================================
CREATE TABLE IF NOT EXISTS `branches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(20) NULL UNIQUE,
  `address` TEXT NULL,
  `city` VARCHAR(100) NULL,
  `state` VARCHAR(100) NULL,
  `phone` VARCHAR(30) NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STEP 8: login_history table
-- ============================================================
CREATE TABLE IF NOT EXISTS `login_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `ip_address` VARCHAR(50) NULL,
  `user_agent` TEXT NULL,
  `method` VARCHAR(30) DEFAULT 'email' COMMENT 'email / google',
  `status` ENUM('success','failed') DEFAULT 'success',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_lh_user` (`user_id`),
  CONSTRAINT `fk_lh_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STEP 9: Seed default roles
-- ============================================================
INSERT IGNORE INTO `roles` (`name`, `display_name`, `description`, `color`, `icon`, `level`) VALUES
('super_admin', 'Super Administrator', 'Full unrestricted system access. Can configure roles, permissions, and all modules.', '#7C3AED', 'fa-crown', 1),
('admin',       'Administrator',       'System-wide administrative access. Manages staff, settings, and core modules.',         '#1D4ED8', 'fa-user-shield', 2),
('hr',          'HR Manager',          'Manages leave, attendance, staff profiles, and employee records.',                       '#0891B2', 'fa-people-roof', 3),
('manager',     'Manager',             'Approves requests, manages projects and teams under their scope.',                        '#059669', 'fa-user-tie', 4),
('dept_head',   'Department Head',     'Oversees department operations, approves department-level requests.',                    '#D97706', 'fa-sitemap', 5),
('finance',     'Finance Officer',     'Manages expense claims, purchase requests, and financial reporting.',                    '#BE185D', 'fa-money-bill-trend-up', 6),
('auditor',     'Auditor',             'Read-only access to all records for audit and compliance review.',                       '#475569', 'fa-magnifying-glass-chart', 7),
('staff',       'Staff',               'Standard employee access. Can submit requests and view personal records.',              '#64748B', 'fa-user', 8);

-- ============================================================
-- STEP 10: Seed permissions catalogue (all module × actions)
-- ============================================================
INSERT IGNORE INTO `permissions` (`module`, `action`, `label`) VALUES
-- Dashboard
('dashboard', 'view', 'View Dashboard'),
-- Staff Management
('staff', 'view', 'View Staff List'),
('staff', 'create', 'Add New Staff'),
('staff', 'edit', 'Edit Staff Profile'),
('staff', 'delete', 'Delete Staff Account'),
-- Attendance
('attendance', 'view', 'View Attendance Records'),
('attendance', 'create', 'Clock In / Clock Out'),
('attendance', 'edit', 'Edit Attendance (Manual)'),
('attendance', 'delete', 'Delete Attendance Record'),
-- Tenders
('tenders', 'view', 'View Tenders'),
('tenders', 'create', 'Add Tender'),
('tenders', 'edit', 'Edit Tender'),
('tenders', 'delete', 'Delete Tender'),
('tenders', 'approve', 'Approve/Update Tender Status'),
-- Documents
('documents', 'view', 'View Documents'),
('documents', 'create', 'Upload Documents'),
('documents', 'edit', 'Edit Document Info'),
('documents', 'delete', 'Delete Documents'),
('documents', 'approve', 'Approve Documents'),
-- Letters / Inquiries
('letters', 'view', 'View Letters & Inquiries'),
('letters', 'create', 'Add Letter / Inquiry'),
('letters', 'edit', 'Edit Letter'),
('letters', 'delete', 'Delete Letter'),
-- Leave
('leave', 'view', 'View Leave Records'),
('leave', 'create', 'Submit Leave Request'),
('leave', 'edit', 'Edit Own Leave Request'),
('leave', 'delete', 'Cancel Leave Request'),
('leave', 'approve', 'Approve / Reject Leave'),
-- Expense Claims
('expense', 'view', 'View Expense Claims'),
('expense', 'create', 'Submit Expense Claim'),
('expense', 'edit', 'Edit Own Expense Claim'),
('expense', 'delete', 'Cancel Expense Claim'),
('expense', 'approve', 'Approve / Reject Expense'),
-- Purchase Requests
('purchase', 'view', 'View Purchase Requests'),
('purchase', 'create', 'Create Purchase Request'),
('purchase', 'edit', 'Edit Purchase Request'),
('purchase', 'delete', 'Cancel Purchase Request'),
('purchase', 'approve', 'Approve Purchase Request'),
-- Projects & Tasks
('projects', 'view', 'View Projects & Tasks'),
('projects', 'create', 'Create Project / Task'),
('projects', 'edit', 'Edit Project / Task'),
('projects', 'delete', 'Delete Project / Task'),
('projects', 'approve', 'Approve Project Milestone'),
-- Organization & Departments
('organization', 'view', 'View Organization Structure'),
('organization', 'create', 'Create Department / Branch'),
('organization', 'edit', 'Edit Department / Branch'),
('organization', 'delete', 'Delete Department / Branch'),
-- Announcements
('announcements', 'view', 'View Announcements'),
('announcements', 'create', 'Create Announcement'),
('announcements', 'edit', 'Edit Announcement'),
('announcements', 'delete', 'Delete Announcement'),
-- Calendar & Events
('calendar', 'view', 'View Calendar & Events'),
('calendar', 'create', 'Create Event'),
('calendar', 'edit', 'Edit Event'),
('calendar', 'delete', 'Delete Event'),
-- Notifications
('notifications', 'view', 'View Notifications'),
-- Roles & Permissions
('roles', 'view', 'View Roles & Permission Matrix'),
('roles', 'edit', 'Edit Role Permissions'),
-- Audit Logs
('audit_logs', 'view', 'View Audit Logs'),
-- System Health
('system', 'view', 'View System Health'),
('system', 'edit', 'Manage System & Backups');

-- ============================================================
-- STEP 11: Seed role_permissions matrix
-- super_admin: ALL permissions
-- ============================================================
INSERT IGNORE INTO `role_permissions` (`role_name`, `module`, `action`, `allowed`)
SELECT 'super_admin', `module`, `action`, 1 FROM `permissions`;

-- admin: almost all, except roles.edit
INSERT IGNORE INTO `role_permissions` (`role_name`, `module`, `action`, `allowed`)
SELECT 'admin', `module`, `action`,
  CASE WHEN `module` = 'roles' AND `action` = 'edit' THEN 0 ELSE 1 END
FROM `permissions`;

-- hr: staff(view,create,edit), attendance(all), leave(all), docs(view,create), org(view), announcements(view,create,edit), calendar, notifications, dashboard
INSERT IGNORE INTO `role_permissions` (`role_name`, `module`, `action`, `allowed`) VALUES
('hr','dashboard','view',1),
('hr','staff','view',1),('hr','staff','create',1),('hr','staff','edit',1),('hr','staff','delete',0),
('hr','attendance','view',1),('hr','attendance','create',1),('hr','attendance','edit',1),('hr','attendance','delete',0),
('hr','leave','view',1),('hr','leave','create',1),('hr','leave','edit',1),('hr','leave','delete',1),('hr','leave','approve',1),
('hr','expense','view',1),('hr','expense','create',1),('hr','expense','edit',0),('hr','expense','delete',0),('hr','expense','approve',0),
('hr','documents','view',1),('hr','documents','create',1),('hr','documents','edit',0),('hr','documents','delete',0),('hr','documents','approve',0),
('hr','letters','view',1),('hr','letters','create',1),('hr','letters','edit',0),('hr','letters','delete',0),
('hr','organization','view',1),('hr','organization','create',0),('hr','organization','edit',0),('hr','organization','delete',0),
('hr','announcements','view',1),('hr','announcements','create',1),('hr','announcements','edit',1),('hr','announcements','delete',0),
('hr','calendar','view',1),('hr','calendar','create',1),('hr','calendar','edit',1),('hr','calendar','delete',0),
('hr','notifications','view',1),
('hr','projects','view',1),('hr','projects','create',0),('hr','projects','edit',0),('hr','projects','delete',0),('hr','projects','approve',0),
('hr','tenders','view',1),('hr','tenders','create',0),('hr','tenders','edit',0),('hr','tenders','delete',0),('hr','tenders','approve',0),
('hr','purchase','view',1),('hr','purchase','create',0),('hr','purchase','edit',0),('hr','purchase','delete',0),('hr','purchase','approve',0),
('hr','roles','view',0),('hr','roles','edit',0),
('hr','audit_logs','view',0),
('hr','system','view',0),('hr','system','edit',0);

-- manager: approve leave/expense/purchase/projects, view all, create projects
INSERT IGNORE INTO `role_permissions` (`role_name`, `module`, `action`, `allowed`) VALUES
('manager','dashboard','view',1),
('manager','staff','view',1),('manager','staff','create',0),('manager','staff','edit',0),('manager','staff','delete',0),
('manager','attendance','view',1),('manager','attendance','create',1),('manager','attendance','edit',0),('manager','attendance','delete',0),
('manager','leave','view',1),('manager','leave','create',1),('manager','leave','edit',1),('manager','leave','delete',0),('manager','leave','approve',1),
('manager','expense','view',1),('manager','expense','create',1),('manager','expense','edit',1),('manager','expense','delete',0),('manager','expense','approve',1),
('manager','purchase','view',1),('manager','purchase','create',1),('manager','purchase','edit',1),('manager','purchase','delete',0),('manager','purchase','approve',1),
('manager','projects','view',1),('manager','projects','create',1),('manager','projects','edit',1),('manager','projects','delete',0),('manager','projects','approve',1),
('manager','tenders','view',1),('manager','tenders','create',1),('manager','tenders','edit',1),('manager','tenders','delete',0),('manager','tenders','approve',1),
('manager','documents','view',1),('manager','documents','create',1),('manager','documents','edit',1),('manager','documents','delete',0),('manager','documents','approve',1),
('manager','letters','view',1),('manager','letters','create',1),('manager','letters','edit',1),('manager','letters','delete',0),
('manager','organization','view',1),('manager','organization','create',0),('manager','organization','edit',0),('manager','organization','delete',0),
('manager','announcements','view',1),('manager','announcements','create',1),('manager','announcements','edit',1),('manager','announcements','delete',0),
('manager','calendar','view',1),('manager','calendar','create',1),('manager','calendar','edit',1),('manager','calendar','delete',0),
('manager','notifications','view',1),
('manager','roles','view',0),('manager','roles','edit',0),
('manager','audit_logs','view',0),
('manager','system','view',0),('manager','system','edit',0);

-- dept_head: same as manager but limited to department scope; approve leave for their dept
INSERT IGNORE INTO `role_permissions` (`role_name`, `module`, `action`, `allowed`) VALUES
('dept_head','dashboard','view',1),
('dept_head','staff','view',1),('dept_head','staff','create',0),('dept_head','staff','edit',0),('dept_head','staff','delete',0),
('dept_head','attendance','view',1),('dept_head','attendance','create',1),('dept_head','attendance','edit',0),('dept_head','attendance','delete',0),
('dept_head','leave','view',1),('dept_head','leave','create',1),('dept_head','leave','edit',1),('dept_head','leave','delete',0),('dept_head','leave','approve',1),
('dept_head','expense','view',1),('dept_head','expense','create',1),('dept_head','expense','edit',1),('dept_head','expense','delete',0),('dept_head','expense','approve',0),
('dept_head','purchase','view',1),('dept_head','purchase','create',1),('dept_head','purchase','edit',1),('dept_head','purchase','delete',0),('dept_head','purchase','approve',0),
('dept_head','projects','view',1),('dept_head','projects','create',1),('dept_head','projects','edit',1),('dept_head','projects','delete',0),('dept_head','projects','approve',0),
('dept_head','tenders','view',1),('dept_head','tenders','create',1),('dept_head','tenders','edit',0),('dept_head','tenders','delete',0),('dept_head','tenders','approve',0),
('dept_head','documents','view',1),('dept_head','documents','create',1),('dept_head','documents','edit',0),('dept_head','documents','delete',0),('dept_head','documents','approve',0),
('dept_head','letters','view',1),('dept_head','letters','create',1),('dept_head','letters','edit',0),('dept_head','letters','delete',0),
('dept_head','organization','view',1),('dept_head','organization','create',0),('dept_head','organization','edit',0),('dept_head','organization','delete',0),
('dept_head','announcements','view',1),('dept_head','announcements','create',0),('dept_head','announcements','edit',0),('dept_head','announcements','delete',0),
('dept_head','calendar','view',1),('dept_head','calendar','create',1),('dept_head','calendar','edit',1),('dept_head','calendar','delete',0),
('dept_head','notifications','view',1),
('dept_head','roles','view',0),('dept_head','roles','edit',0),
('dept_head','audit_logs','view',0),
('dept_head','system','view',0),('dept_head','system','edit',0);

-- finance: view all, approve expense + purchase only
INSERT IGNORE INTO `role_permissions` (`role_name`, `module`, `action`, `allowed`) VALUES
('finance','dashboard','view',1),
('finance','staff','view',1),('finance','staff','create',0),('finance','staff','edit',0),('finance','staff','delete',0),
('finance','attendance','view',1),('finance','attendance','create',1),('finance','attendance','edit',0),('finance','attendance','delete',0),
('finance','leave','view',1),('finance','leave','create',1),('finance','leave','edit',1),('finance','leave','delete',0),('finance','leave','approve',0),
('finance','expense','view',1),('finance','expense','create',1),('finance','expense','edit',1),('finance','expense','delete',0),('finance','expense','approve',1),
('finance','purchase','view',1),('finance','purchase','create',1),('finance','purchase','edit',1),('finance','purchase','delete',0),('finance','purchase','approve',1),
('finance','projects','view',1),('finance','projects','create',0),('finance','projects','edit',0),('finance','projects','delete',0),('finance','projects','approve',0),
('finance','tenders','view',1),('finance','tenders','create',0),('finance','tenders','edit',0),('finance','tenders','delete',0),('finance','tenders','approve',0),
('finance','documents','view',1),('finance','documents','create',1),('finance','documents','edit',0),('finance','documents','delete',0),('finance','documents','approve',0),
('finance','letters','view',1),('finance','letters','create',0),('finance','letters','edit',0),('finance','letters','delete',0),
('finance','organization','view',1),('finance','organization','create',0),('finance','organization','edit',0),('finance','organization','delete',0),
('finance','announcements','view',1),('finance','announcements','create',0),('finance','announcements','edit',0),('finance','announcements','delete',0),
('finance','calendar','view',1),('finance','calendar','create',1),('finance','calendar','edit',1),('finance','calendar','delete',0),
('finance','notifications','view',1),
('finance','roles','view',0),('finance','roles','edit',0),
('finance','audit_logs','view',1),
('finance','system','view',0),('finance','system','edit',0);

-- auditor: view ONLY everywhere
INSERT IGNORE INTO `role_permissions` (`role_name`, `module`, `action`, `allowed`) VALUES
('auditor','dashboard','view',1),
('auditor','staff','view',1),('auditor','staff','create',0),('auditor','staff','edit',0),('auditor','staff','delete',0),
('auditor','attendance','view',1),('auditor','attendance','create',0),('auditor','attendance','edit',0),('auditor','attendance','delete',0),
('auditor','leave','view',1),('auditor','leave','create',0),('auditor','leave','edit',0),('auditor','leave','delete',0),('auditor','leave','approve',0),
('auditor','expense','view',1),('auditor','expense','create',0),('auditor','expense','edit',0),('auditor','expense','delete',0),('auditor','expense','approve',0),
('auditor','purchase','view',1),('auditor','purchase','create',0),('auditor','purchase','edit',0),('auditor','purchase','delete',0),('auditor','purchase','approve',0),
('auditor','projects','view',1),('auditor','projects','create',0),('auditor','projects','edit',0),('auditor','projects','delete',0),('auditor','projects','approve',0),
('auditor','tenders','view',1),('auditor','tenders','create',0),('auditor','tenders','edit',0),('auditor','tenders','delete',0),('auditor','tenders','approve',0),
('auditor','documents','view',1),('auditor','documents','create',0),('auditor','documents','edit',0),('auditor','documents','delete',0),('auditor','documents','approve',0),
('auditor','letters','view',1),('auditor','letters','create',0),('auditor','letters','edit',0),('auditor','letters','delete',0),
('auditor','organization','view',1),('auditor','organization','create',0),('auditor','organization','edit',0),('auditor','organization','delete',0),
('auditor','announcements','view',1),('auditor','announcements','create',0),('auditor','announcements','edit',0),('auditor','announcements','delete',0),
('auditor','calendar','view',1),('auditor','calendar','create',0),('auditor','calendar','edit',0),('auditor','calendar','delete',0),
('auditor','notifications','view',1),
('auditor','roles','view',1),('auditor','roles','edit',0),
('auditor','audit_logs','view',1),
('auditor','system','view',1),('auditor','system','edit',0);

-- staff: basic self-service access
INSERT IGNORE INTO `role_permissions` (`role_name`, `module`, `action`, `allowed`) VALUES
('staff','dashboard','view',1),
('staff','staff','view',0),('staff','staff','create',0),('staff','staff','edit',0),('staff','staff','delete',0),
('staff','attendance','view',1),('staff','attendance','create',1),('staff','attendance','edit',0),('staff','attendance','delete',0),
('staff','leave','view',1),('staff','leave','create',1),('staff','leave','edit',1),('staff','leave','delete',1),('staff','leave','approve',0),
('staff','expense','view',1),('staff','expense','create',1),('staff','expense','edit',1),('staff','expense','delete',1),('staff','expense','approve',0),
('staff','purchase','view',1),('staff','purchase','create',1),('staff','purchase','edit',1),('staff','purchase','delete',1),('staff','purchase','approve',0),
('staff','projects','view',1),('staff','projects','create',0),('staff','projects','edit',0),('staff','projects','delete',0),('staff','projects','approve',0),
('staff','tenders','view',1),('staff','tenders','create',0),('staff','tenders','edit',0),('staff','tenders','delete',0),('staff','tenders','approve',0),
('staff','documents','view',1),('staff','documents','create',1),('staff','documents','edit',0),('staff','documents','delete',0),('staff','documents','approve',0),
('staff','letters','view',1),('staff','letters','create',0),('staff','letters','edit',0),('staff','letters','delete',0),
('staff','organization','view',1),('staff','organization','create',0),('staff','organization','edit',0),('staff','organization','delete',0),
('staff','announcements','view',1),('staff','announcements','create',0),('staff','announcements','edit',0),('staff','announcements','delete',0),
('staff','calendar','view',1),('staff','calendar','create',0),('staff','calendar','edit',0),('staff','calendar','delete',0),
('staff','notifications','view',1),
('staff','roles','view',0),('staff','roles','edit',0),
('staff','audit_logs','view',0),
('staff','system','view',0),('staff','system','edit',0);

-- ============================================================
-- STEP 12: Seed default branch and department placeholders
-- ============================================================
INSERT IGNORE INTO `branches` (`id`, `name`, `code`, `city`, `status`) VALUES
(1, 'Headquarters — Kuala Lumpur', 'HQ-KL', 'Kuala Lumpur', 'active');

INSERT IGNORE INTO `departments` (`id`, `name`, `code`, `status`) VALUES
(1, 'Executive Management', 'EXEC', 'active'),
(2, 'Human Resources', 'HR', 'active'),
(3, 'Finance & Accounts', 'FIN', 'active'),
(4, 'Operations', 'OPS', 'active'),
(5, 'Information Technology', 'IT', 'active'),
(6, 'Business Development', 'BD', 'active');

-- Update existing admin users to super_admin role (they had 'admin')
UPDATE `users` SET `role` = 'super_admin' WHERE `role` = 'admin';

-- ============================================================
-- STEP 13: Split organization permissions by responsibility
-- ============================================================
INSERT IGNORE INTO `permissions` (`module`, `action`, `label`) VALUES
('departments','view','View Departments Directory'),
('departments','create','Create Department'),
('departments','edit','Edit Department'),
('departments','delete','Delete Department'),
('branches','view','View Branch Locations'),
('branches','create','Create Branch'),
('branches','edit','Edit Branch'),
('branches','delete','Delete Branch'),
('positions','view','View Positions & Hierarchy'),
('positions','create','Create Position'),
('positions','edit','Edit Position & Reporting Line'),
('positions','delete','Delete Position'),
('org_chart','view','View Organization Chart');

INSERT IGNORE INTO `role_permissions` (`role_name`,`module`,`action`,`allowed`)
SELECT rp.role_name, mapped.module, rp.action, rp.allowed
FROM `role_permissions` rp
JOIN (
    SELECT 'departments' AS module UNION ALL
    SELECT 'branches' UNION ALL
    SELECT 'positions'
) mapped
WHERE rp.module='organization';

INSERT IGNORE INTO `role_permissions` (`role_name`,`module`,`action`,`allowed`)
SELECT rp.role_name, 'org_chart', 'view', rp.allowed
FROM `role_permissions` rp
WHERE rp.module='organization' AND rp.action='view';

DELETE FROM `role_permissions` WHERE `module`='organization';
DELETE FROM `permissions` WHERE `module`='organization';

<?php
/**
 * Modules 2–10 Migration Runner — TBBA ERP
 * Run once via: php database/run_migration_modules2_10.php
 * Delete after running.
 */
require_once __DIR__ . '/../config/database.php';
header('Content-Type: text/html; charset=utf-8');
$pdo = Database::getInstance();
$errors = []; $success = [];

function run(PDO $pdo, string $label, string $sql): void {
    global $errors, $success;
    try { $pdo->exec($sql); $success[] = "✅ $label"; }
    catch (PDOException $e) { $errors[] = "⚠️ $label: " . $e->getMessage(); }
}

// ─── MODULE 2: Leave & Permission ─────────────────────────────────────────────
run($pdo, "Create leave_types", "CREATE TABLE IF NOT EXISTS `leave_types` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(20) UNIQUE,
  `days_allowed` INT NOT NULL DEFAULT 14,
  `carry_forward` TINYINT(1) DEFAULT 0,
  `is_paid` TINYINT(1) DEFAULT 1,
  `color` VARCHAR(20) DEFAULT '#2563EB',
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

run($pdo, "Create leave_requests", "CREATE TABLE IF NOT EXISTS `leave_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `leave_type_id` INT NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `total_days` DECIMAL(4,1) NOT NULL DEFAULT 1,
  `half_day` TINYINT(1) DEFAULT 0,
  `reason` TEXT NULL,
  `status` ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `approved_by` INT NULL,
  `approved_at` TIMESTAMP NULL,
  `rejection_reason` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_lr_user` (`user_id`),
  CONSTRAINT `fk_lr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lr_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Seed default leave types
$leaveTypes = [
    ['Annual Leave','AL',14,1,1,'#2563EB'],
    ['Medical Leave','ML',14,0,1,'#DC2626'],
    ['Emergency Leave','EL',3,0,1,'#D97706'],
    ['Unpaid Leave','UL',0,0,0,'#64748B'],
    ['Maternity Leave','MAT',90,0,1,'#BE185D'],
    ['Paternity Leave','PAT',7,0,1,'#0891B2'],
    ['Replacement Leave','RL',0,1,1,'#059669'],
    ['Official Duty / Outstation','OD',365,0,1,'#8B5CF6'],
    ['Permission to Leave Office','PL',365,0,1,'#0EA5E9'],
];
foreach ($leaveTypes as $lt) {
    run($pdo, "Seed leave type: {$lt[0]}", "INSERT IGNORE INTO `leave_types` (`name`,`code`,`days_allowed`,`carry_forward`,`is_paid`,`color`) VALUES ('{$lt[0]}','{$lt[1]}',{$lt[2]},{$lt[3]},{$lt[4]},'{$lt[5]}')");
}

// ─── MODULE 3: Expense Claims ─────────────────────────────────────────────────
run($pdo, "Create expense_claims", "CREATE TABLE IF NOT EXISTS `expense_claims` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `claim_no` VARCHAR(30) NULL UNIQUE,
  `user_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(10) DEFAULT 'MYR',
  `expense_date` DATE NOT NULL,
  `project_ref` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `receipt_path` VARCHAR(500) NULL,
  `status` ENUM('draft','pending','approved','rejected','paid') DEFAULT 'draft',
  `approved_by` INT NULL,
  `approved_at` TIMESTAMP NULL,
  `rejection_reason` TEXT NULL,
  `paid_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_ec_user` (`user_id`),
  CONSTRAINT `fk_ec_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

run($pdo, "Create expense_items", "CREATE TABLE IF NOT EXISTS `expense_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `claim_id` INT NOT NULL,
  `description` VARCHAR(300) NOT NULL,
  `category` VARCHAR(100) DEFAULT 'Others',
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `receipt_path` VARCHAR(500) NULL,
  CONSTRAINT `fk_ei_claim` FOREIGN KEY (`claim_id`) REFERENCES `expense_claims`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ─── MODULE 4: Purchase Requests ──────────────────────────────────────────────
run($pdo, "Create purchase_requests", "CREATE TABLE IF NOT EXISTS `purchase_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pr_no` VARCHAR(30) NULL UNIQUE,
  `user_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `total_amount` DECIMAL(12,2) DEFAULT 0.00,
  `currency` VARCHAR(10) DEFAULT 'MYR',
  `vendor` VARCHAR(200) NULL,
  `required_date` DATE NULL,
  `department_id` INT NULL,
  `project_ref` VARCHAR(100) NULL,
  `justification` TEXT NULL,
  `status` ENUM('draft','pending','approved','rejected','ordered','received') DEFAULT 'draft',
  `approved_by` INT NULL,
  `approved_at` TIMESTAMP NULL,
  `rejection_reason` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_pr_user` (`user_id`),
  CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

run($pdo, "Create purchase_items", "CREATE TABLE IF NOT EXISTS `purchase_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pr_id` INT NOT NULL,
  `description` VARCHAR(300) NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1,
  `unit` VARCHAR(50) DEFAULT 'unit',
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT `fk_pi_pr` FOREIGN KEY (`pr_id`) REFERENCES `purchase_requests`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ─── MODULE 5: Approval Center ────────────────────────────────────────────────
run($pdo, "Create approvals", "CREATE TABLE IF NOT EXISTS `approvals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `module` VARCHAR(50) NOT NULL COMMENT 'leave / expense / purchase',
  `record_id` INT NOT NULL COMMENT 'ID of the leave_request / expense_claim / purchase_request',
  `requester_id` INT NOT NULL,
  `approver_id` INT NULL,
  `status` ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `comment` TEXT NULL,
  `actioned_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_ap_module_record` (`module`,`record_id`),
  KEY `idx_ap_approver` (`approver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ─── MODULE 6: Projects & Tasks ───────────────────────────────────────────────
run($pdo, "Create projects", "CREATE TABLE IF NOT EXISTS `projects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT NULL,
  `project_code` VARCHAR(50) NULL UNIQUE,
  `status` ENUM('planning','active','on_hold','completed','cancelled') DEFAULT 'planning',
  `priority` ENUM('low','medium','high','critical') DEFAULT 'medium',
  `start_date` DATE NULL,
  `end_date` DATE NULL,
  `budget` DECIMAL(14,2) NULL,
  `owner_id` INT NOT NULL,
  `department_id` INT NULL,
  `color` VARCHAR(20) DEFAULT '#2563EB',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_proj_owner` FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

run($pdo, "Create project_tasks", "CREATE TABLE IF NOT EXISTS `project_tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `title` VARCHAR(300) NOT NULL,
  `description` TEXT NULL,
  `status` ENUM('todo','in_progress','review','done','cancelled') DEFAULT 'todo',
  `priority` ENUM('low','medium','high','critical') DEFAULT 'medium',
  `assigned_to` INT NULL,
  `due_date` DATE NULL,
  `sort_order` INT DEFAULT 0,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_pt_project` (`project_id`),
  CONSTRAINT `fk_pt_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pt_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

run($pdo, "Create project_members", "CREATE TABLE IF NOT EXISTS `project_members` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `role` VARCHAR(50) DEFAULT 'member',
  `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_pm` (`project_id`,`user_id`),
  CONSTRAINT `fk_pm_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pm_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ─── MODULE 8: Announcements ──────────────────────────────────────────────────
run($pdo, "Create announcements", "CREATE TABLE IF NOT EXISTS `announcements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(300) NOT NULL,
  `content` TEXT NOT NULL,
  `type` ENUM('general','urgent','event','policy') DEFAULT 'general',
  `audience` ENUM('all','department','role') DEFAULT 'all',
  `audience_target` VARCHAR(100) NULL COMMENT 'dept ID or role name if audience is specific',
  `is_pinned` TINYINT(1) DEFAULT 0,
  `author_id` INT NOT NULL,
  `published_at` TIMESTAMP NULL,
  `expires_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_ann_author` FOREIGN KEY (`author_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ─── MODULE 9: Calendar & Events ──────────────────────────────────────────────
run($pdo, "Create events", "CREATE TABLE IF NOT EXISTS `events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(300) NOT NULL,
  `description` TEXT NULL,
  `type` ENUM('meeting','holiday','deadline','training','social','other') DEFAULT 'meeting',
  `start_datetime` DATETIME NOT NULL,
  `end_datetime` DATETIME NOT NULL,
  `all_day` TINYINT(1) DEFAULT 0,
  `location` VARCHAR(300) NULL,
  `color` VARCHAR(20) DEFAULT '#2563EB',
  `is_public` TINYINT(1) DEFAULT 1,
  `department_id` INT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_ev_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Seed public holidays (Malaysia)
$holidays = [
    ['New Year\'s Day','2026-01-01','2026-01-01','#64748B','holiday',1],
    ['Chinese New Year','2026-01-29','2026-01-30','#DC2626','holiday',1],
    ['Thaipusam','2026-02-12','2026-02-12','#D97706','holiday',1],
    ['Labour Day','2026-05-01','2026-05-01','#059669','holiday',1],
    ['Wesak Day','2026-05-11','2026-05-11','#7C3AED','holiday',1],
    ['Hari Raya Aidilfitri','2026-03-20','2026-03-21','#F59E0B','holiday',1],
    ['Hari Raya Aidiladha','2026-05-27','2026-05-27','#F59E0B','holiday',1],
    ['National Day','2026-08-31','2026-08-31','#1D4ED8','holiday',1],
    ['Malaysia Day','2026-09-16','2026-09-16','#1D4ED8','holiday',1],
    ['Deepavali','2026-10-29','2026-10-29','#D97706','holiday',1],
    ['Christmas Day','2026-12-25','2026-12-25','#059669','holiday',1],
];
// Get first super_admin id for created_by
try {
    $adminId = $pdo->query("SELECT id FROM users WHERE role='super_admin' LIMIT 1")->fetchColumn() ?: 1;
} catch (Exception $e) { $adminId = 1; }
foreach ($holidays as $h) {
    run($pdo, "Seed event: {$h[0]}", "INSERT IGNORE INTO `events` (`title`,`start_datetime`,`end_datetime`,`color`,`type`,`is_public`,`all_day`,`created_by`) VALUES ('{$h[0]}','{$h[1]} 00:00:00','{$h[2]} 23:59:59','{$h[3]}','{$h[4]}',{$h[5]},1,{$adminId})");
}

// ─── MODULE 10: Notifications ─────────────────────────────────────────────────
run($pdo, "Create notifications", "CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `type` VARCHAR(80) NOT NULL COMMENT 'leave_approved, expense_rejected, pr_approved, etc.',
  `title` VARCHAR(300) NOT NULL,
  `body` TEXT NOT NULL,
  `icon` VARCHAR(80) DEFAULT 'fa-bell',
  `color` VARCHAR(20) DEFAULT '#2563EB',
  `link` VARCHAR(500) NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_notif_user` (`user_id`),
  KEY `idx_notif_read` (`is_read`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ─── Output ────────────────────────────────────────────────────────────────────
?><!DOCTYPE html><html><head><meta charset="UTF-8"><title>Migration M2-10</title>
<style>body{font-family:monospace;background:#0F172A;color:#E2E8F0;padding:30px;max-width:900px;margin:0 auto;}
.ok{color:#10B981;}.err{color:#F87171;}.box{background:#1E293B;border-radius:10px;padding:20px;margin:16px 0;}
.sum{background:#1E3A5F;border-radius:10px;padding:20px;margin:20px 0;font-size:15px;}
a{color:#38BDF8;}</style></head><body>
<h1>🚀 TBBA ERP — Modules 2–10 Migration</h1>
<div class="sum"><strong>✅ <?= count($success) ?> succeeded</strong> &nbsp;|&nbsp; <strong style="color:#F87171;">⚠️ <?= count($errors) ?> errors</strong></div>
<?php if($errors): ?><div class="box"><?php foreach($errors as $e): ?><div class="err"><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div><?php endif; ?>
<div class="box"><?php foreach($success as $s): ?><div class="ok"><?= htmlspecialchars($s) ?></div><?php endforeach; ?></div>
<div class="sum"><strong>DELETE</strong> this file after migration: <code>database/run_migration_modules2_10.php</code></div>
</body></html>

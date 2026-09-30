<?php
/**
 * TBBA ERP — Finance workflow and account security migration.
 *
 * Safe to run more than once. Run from CLI:
 *   php database/run_migration_finance_security.php
 */

require_once __DIR__ . '/../config/database.php';

$pdo = Database::connect();
$messages = [];
$errors = [];

function runMigrationStep(PDO $pdo, string $label, string $sql, array &$messages, array &$errors): void
{
    try {
        $pdo->exec($sql);
        $messages[] = $label;
    } catch (Throwable $e) {
        $errors[] = $label . ': ' . $e->getMessage();
    }
}

function addColumnIfMissing(PDO $pdo, string $table, string $column, string $definition, array &$messages, array &$errors): void
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    if ((int)$stmt->fetchColumn() === 0) {
        runMigrationStep($pdo, "Add {$table}.{$column}", "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}", $messages, $errors);
    }
}

// Finance master data and controls.
runMigrationStep($pdo, 'Create finance_cost_centers', "CREATE TABLE IF NOT EXISTS `finance_cost_centers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(30) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `department_id` INT NULL,
    `annual_budget` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_fcc_department` (`department_id`),
    CONSTRAINT `fk_fcc_department` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_fcc_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $messages, $errors);

runMigrationStep($pdo, 'Create finance_budgets', "CREATE TABLE IF NOT EXISTS `finance_budgets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `fiscal_year` SMALLINT NOT NULL,
    `cost_center_id` INT NOT NULL,
    `project_ref` VARCHAR(100) NOT NULL DEFAULT '',
    `allocated_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `notes` VARCHAR(500) NULL,
    `created_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_finance_budget_scope` (`fiscal_year`,`cost_center_id`,`project_ref`),
    CONSTRAINT `fk_fb_cost_center` FOREIGN KEY (`cost_center_id`) REFERENCES `finance_cost_centers`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fb_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $messages, $errors);

addColumnIfMissing($pdo, 'finance_records', 'parent_record_id', 'INT NULL AFTER `id`', $messages, $errors);
addColumnIfMissing($pdo, 'finance_records', 'source_request_id', 'INT NULL AFTER `parent_record_id`', $messages, $errors);
addColumnIfMissing($pdo, 'finance_records', 'cost_center_id', 'INT NULL AFTER `source_request_id`', $messages, $errors);
addColumnIfMissing($pdo, 'finance_records', 'project_ref', "VARCHAR(100) NULL AFTER `cost_center_id`", $messages, $errors);
addColumnIfMissing($pdo, 'finance_records', 'currency', "VARCHAR(10) NOT NULL DEFAULT 'MYR' AFTER `project_ref`", $messages, $errors);
addColumnIfMissing($pdo, 'finance_records', 'tax_rate', 'DECIMAL(6,3) NOT NULL DEFAULT 0.000 AFTER `tax_amount`', $messages, $errors);
addColumnIfMissing($pdo, 'finance_records', 'paid_amount', 'DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `total_amount`', $messages, $errors);
addColumnIfMissing($pdo, 'finance_records', 'balance_due', 'DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `paid_amount`', $messages, $errors);
addColumnIfMissing($pdo, 'finance_records', 'recurring_rule_id', 'INT NULL AFTER `balance_due`', $messages, $errors);

runMigrationStep($pdo, 'Create finance_payments', "CREATE TABLE IF NOT EXISTS `finance_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `finance_record_id` INT NOT NULL,
    `payment_no` VARCHAR(60) NOT NULL UNIQUE,
    `direction` ENUM('in','out') NOT NULL,
    `payment_date` DATE NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `method` VARCHAR(50) NOT NULL DEFAULT 'bank_transfer',
    `bank_account` VARCHAR(120) NULL,
    `bank_reference` VARCHAR(120) NULL,
    `notes` VARCHAR(500) NULL,
    `status` ENUM('pending','cleared','reconciled','void') NOT NULL DEFAULT 'cleared',
    `reconciled_by` INT NULL,
    `reconciled_at` DATETIME NULL,
    `created_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_fp_record` (`finance_record_id`),
    INDEX `idx_fp_date_direction` (`payment_date`,`direction`),
    INDEX `idx_fp_reconcile` (`status`),
    CONSTRAINT `fk_fp_record` FOREIGN KEY (`finance_record_id`) REFERENCES `finance_records`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fp_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
    CONSTRAINT `fk_fp_reconciler` FOREIGN KEY (`reconciled_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $messages, $errors);

runMigrationStep($pdo, 'Create finance_bank_transactions', "CREATE TABLE IF NOT EXISTS `finance_bank_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transaction_date` DATE NOT NULL,
    `bank_account` VARCHAR(120) NOT NULL,
    `bank_reference` VARCHAR(120) NULL,
    `description` VARCHAR(255) NOT NULL,
    `direction` ENUM('in','out') NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `matched_payment_id` INT NULL,
    `status` ENUM('unmatched','matched','ignored') NOT NULL DEFAULT 'unmatched',
    `created_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_fbt_status` (`status`),
    CONSTRAINT `fk_fbt_payment` FOREIGN KEY (`matched_payment_id`) REFERENCES `finance_payments`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_fbt_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $messages, $errors);

runMigrationStep($pdo, 'Create finance_recurring_rules', "CREATE TABLE IF NOT EXISTS `finance_recurring_rules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `party_name` VARCHAR(150) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `tax_rate` DECIMAL(6,3) NOT NULL DEFAULT 0.000,
    `frequency` ENUM('monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
    `next_run_date` DATE NOT NULL,
    `due_days` SMALLINT NOT NULL DEFAULT 30,
    `cost_center_id` INT NULL,
    `project_ref` VARCHAR(100) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_frr_cost_center` FOREIGN KEY (`cost_center_id`) REFERENCES `finance_cost_centers`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_frr_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $messages, $errors);

runMigrationStep($pdo, 'Create finance_settings', "CREATE TABLE IF NOT EXISTS `finance_settings` (
    `setting_key` VARCHAR(80) PRIMARY KEY,
    `setting_value` VARCHAR(255) NOT NULL,
    `updated_by` INT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $messages, $errors);

runMigrationStep($pdo, 'Seed finance settings', "INSERT INTO `finance_settings` (`setting_key`,`setting_value`) VALUES
    ('default_currency','MYR'),('sst_rate','8.00'),('invoice_terms_days','30'),('fiscal_year_start_month','1')
    ON DUPLICATE KEY UPDATE `setting_value`=`setting_value`", $messages, $errors);

// Backfill balances without altering the original totals.
runMigrationStep($pdo, 'Backfill finance balances', "UPDATE `finance_records`
    SET `paid_amount` = CASE WHEN `status` IN ('paid','completed') AND `document_type` IN ('invoices','bills') THEN `total_amount` ELSE `paid_amount` END,
        `balance_due` = CASE
            WHEN `document_type` IN ('invoices','bills') AND `status` IN ('paid','completed') THEN 0
            WHEN `document_type` IN ('invoices','bills') AND `balance_due` = 0 AND `paid_amount` = 0 THEN `total_amount`
            ELSE `balance_due` END", $messages, $errors);

// Account security.
addColumnIfMissing($pdo, 'users', 'password_changed_at', 'DATETIME NULL AFTER `password`', $messages, $errors);
addColumnIfMissing($pdo, 'users', 'password_expires_at', 'DATETIME NULL AFTER `password_changed_at`', $messages, $errors);
addColumnIfMissing($pdo, 'users', 'two_factor_enabled', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `password_expires_at`', $messages, $errors);
addColumnIfMissing($pdo, 'users', 'two_factor_secret', 'VARCHAR(255) NULL AFTER `two_factor_enabled`', $messages, $errors);

runMigrationStep($pdo, 'Create password_reset_tokens', "CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `token_hash` CHAR(64) NOT NULL UNIQUE,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL,
    `requested_ip` VARCHAR(50) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_prt_user_expiry` (`user_id`,`expires_at`),
    CONSTRAINT `fk_prt_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $messages, $errors);

runMigrationStep($pdo, 'Create user_sessions', "CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `session_token_hash` CHAR(64) NOT NULL UNIQUE,
    `session_id_hash` CHAR(64) NOT NULL,
    `ip_address` VARCHAR(50) NULL,
    `user_agent` VARCHAR(500) NULL,
    `last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL,
    `revoked_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_us_user_active` (`user_id`,`revoked_at`,`expires_at`),
    CONSTRAINT `fk_us_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $messages, $errors);

runMigrationStep($pdo, 'Create trusted_devices', "CREATE TABLE IF NOT EXISTS `trusted_devices` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `token_hash` CHAR(64) NOT NULL UNIQUE,
    `user_agent_hash` CHAR(64) NOT NULL,
    `user_agent` VARCHAR(500) NULL,
    `ip_address` VARCHAR(50) NULL,
    `last_used_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL,
    `revoked_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_td_user_active` (`user_id`,`revoked_at`,`expires_at`),
    CONSTRAINT `fk_td_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $messages, $errors);

runMigrationStep($pdo, 'Create security_events', "CREATE TABLE IF NOT EXISTS `security_events` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `event_type` VARCHAR(80) NOT NULL,
    `severity` ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
    `ip_address` VARCHAR(50) NULL,
    `user_agent` VARCHAR(500) NULL,
    `details` VARCHAR(1000) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_se_user_time` (`user_id`,`created_at`),
    INDEX `idx_se_type_time` (`event_type`,`created_at`),
    CONSTRAINT `fk_se_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $messages, $errors);

runMigrationStep($pdo, 'Create security_settings', "CREATE TABLE IF NOT EXISTS `security_settings` (
    `setting_key` VARCHAR(80) PRIMARY KEY,
    `setting_value` VARCHAR(255) NOT NULL,
    `updated_by` INT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $messages, $errors);

runMigrationStep($pdo, 'Seed security settings', "INSERT INTO `security_settings` (`setting_key`,`setting_value`) VALUES
    ('password_min_length','6'),('password_expiry_days','90'),('session_lifetime_days','30'),
    ('require_2fa_admin','1'),('login_alerts','1'),('backup_retention_days','30')
    ON DUPLICATE KEY UPDATE `setting_value`=`setting_value`", $messages, $errors);

runMigrationStep($pdo, 'Backfill password dates', "UPDATE `users` SET
    `password_changed_at` = COALESCE(`password_changed_at`, `created_at`, NOW()),
    `password_expires_at` = COALESCE(`password_expires_at`, DATE_ADD(COALESCE(`created_at`, NOW()), INTERVAL 90 DAY))", $messages, $errors);

// RBAC catalogue.
$financePermissions = [
    'view' => 'View Finance Control Center',
    'create' => 'Create Finance Workflow Records',
    'edit' => 'Edit Finance Workflow Records',
    'delete' => 'Delete Finance Workflow Records',
    'reconcile' => 'Reconcile Bank Transactions',
    'settings' => 'Manage Finance Settings and Budgets',
];
foreach ($financePermissions as $action => $label) {
    $stmt = $pdo->prepare("INSERT INTO `permissions` (`module`,`action`,`label`) VALUES ('finance_control',?,?) ON DUPLICATE KEY UPDATE `label`=VALUES(`label`)");
    $stmt->execute([$action, $label]);
    foreach (['super_admin', 'admin', 'finance'] as $role) {
        $stmt = $pdo->prepare("INSERT INTO `role_permissions` (`role_name`,`module`,`action`,`allowed`) VALUES (?,'finance_control',?,1) ON DUPLICATE KEY UPDATE `allowed`=1");
        $stmt->execute([$role, $action]);
    }
    if ($action === 'view') {
        $stmt = $pdo->prepare("INSERT INTO `role_permissions` (`role_name`,`module`,`action`,`allowed`) VALUES ('auditor','finance_control',?,1) ON DUPLICATE KEY UPDATE `allowed`=1");
        $stmt->execute([$action]);
    }
}

foreach (['view' => 'View Account Security', 'manage' => 'Manage Account Security'] as $action => $label) {
    $stmt = $pdo->prepare("INSERT INTO `permissions` (`module`,`action`,`label`) VALUES ('security',?,?) ON DUPLICATE KEY UPDATE `label`=VALUES(`label`)");
    $stmt->execute([$action, $label]);
    foreach (['super_admin', 'admin'] as $role) {
        $stmt = $pdo->prepare("INSERT INTO `role_permissions` (`role_name`,`module`,`action`,`allowed`) VALUES (?,'security',?,1) ON DUPLICATE KEY UPDATE `allowed`=1");
        $stmt->execute([$role, $action]);
    }
}

$summary = [
    'status' => $errors ? 'warning' : 'success',
    'completed' => count($messages),
    'errors' => $errors,
    'message' => $errors ? 'Migration completed with warnings.' : 'Finance and security migration completed successfully.',
];

if (PHP_SAPI === 'cli') {
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($errors ? 1 : 0);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

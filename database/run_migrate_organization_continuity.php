<?php
/**
 * Split legacy organization permissions and normalize organization hierarchy.
 * Safe to run more than once.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Position.php';

$catalogue = [
    'departments' => [
        'view' => 'View Departments Directory',
        'create' => 'Create Department',
        'edit' => 'Edit Department',
        'delete' => 'Delete Department',
    ],
    'branches' => [
        'view' => 'View Branch Locations',
        'create' => 'Create Branch',
        'edit' => 'Edit Branch',
        'delete' => 'Delete Branch',
    ],
    'positions' => [
        'view' => 'View Positions & Hierarchy',
        'create' => 'Create Position',
        'edit' => 'Edit Position & Reporting Line',
        'delete' => 'Delete Position',
    ],
    'org_chart' => [
        'view' => 'View Organization Chart',
    ],
];

$pdo = Database::connect();
Database::query(
    "CREATE TABLE IF NOT EXISTS `user_permission_overrides` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `module` VARCHAR(100) NOT NULL,
        `action` VARCHAR(50) NOT NULL,
        `allowed` TINYINT(1) NOT NULL DEFAULT 0,
        `set_by` INT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_upo` (`user_id`,`module`,`action`),
        CONSTRAINT `fk_upo_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
$pdo->beginTransaction();
try {
    foreach ($catalogue as $module => $actions) {
        foreach ($actions as $action => $label) {
            Database::query(
                "INSERT INTO `permissions` (`module`,`action`,`label`) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE `label`=VALUES(`label`)",
                [$module, $action, $label]
            );
        }
    }

    $legacyRolePermissions = Database::query(
        "SELECT `role_name`,`action`,`allowed` FROM `role_permissions` WHERE `module`='organization'"
    )->fetchAll();
    foreach ($legacyRolePermissions as $permission) {
        foreach ($catalogue as $module => $actions) {
            if (!array_key_exists($permission['action'], $actions)) continue;
            Database::query(
                "INSERT INTO `role_permissions` (`role_name`,`module`,`action`,`allowed`) VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE `allowed`=VALUES(`allowed`)",
                [$permission['role_name'], $module, $permission['action'], $permission['allowed']]
            );
        }
    }

    $legacyOverrides = Database::query(
        "SELECT `user_id`,`action`,`allowed`,`set_by` FROM `user_permission_overrides` WHERE `module`='organization'"
    )->fetchAll();
    foreach ($legacyOverrides as $override) {
        foreach ($catalogue as $module => $actions) {
            if (!array_key_exists($override['action'], $actions)) continue;
            Database::query(
                "INSERT INTO `user_permission_overrides` (`user_id`,`module`,`action`,`allowed`,`set_by`) VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE `allowed`=VALUES(`allowed`), `set_by`=VALUES(`set_by`)",
                [$override['user_id'], $module, $override['action'], $override['allowed'], $override['set_by']]
            );
        }
    }

    Database::query("DELETE FROM `role_permissions` WHERE `module`='organization'");
    Database::query("DELETE FROM `user_permission_overrides` WHERE `module`='organization'");
    Database::query("DELETE FROM `permissions` WHERE `module`='organization'");

    Position::rebuildHierarchyLevels();
    Position::synchronizeUserDepartments();
    $pdo->commit();
    echo "Organization continuity migration completed.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Migration failed: {$e->getMessage()}\n");
    exit(1);
}

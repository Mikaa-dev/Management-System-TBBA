<?php
/**
 * Create the Project Delivery & Demo Item Tracker module and its RBAC catalogue.
 * Safe to run more than once.
 */
require_once __DIR__ . '/../config/database.php';

$permissions = [
    'view'   => 'View Project Delivery & Demo Item Tracker',
    'create' => 'Create Project Delivery / Demo Item Record',
    'edit'   => 'Edit Logistics Record & Update Status',
    'delete' => 'Delete Project Delivery / Demo Item Record',
];

$roleDefaults = [
    'super_admin' => ['view', 'create', 'edit', 'delete'],
    'admin'       => ['view', 'create', 'edit', 'delete'],
    'manager'     => ['view', 'create', 'edit', 'delete'],
    'dept_head'   => ['view', 'create', 'edit', 'delete'],
    'hr'          => ['view', 'create', 'edit'],
    'finance'     => ['view', 'create', 'edit'],
    'auditor'     => ['view'],
    'staff'       => ['view', 'create', 'edit'],
];

$pdo = Database::connect();

try {
    Database::query(
        "CREATE TABLE IF NOT EXISTS `logistics_records` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `reference_no` VARCHAR(40) NOT NULL,
            `record_type` ENUM('project_delivery','demo_item') NOT NULL,
            `item_name` VARCHAR(255) NOT NULL,
            `description` TEXT NULL,
            `quantity` DECIMAL(12,2) NOT NULL DEFAULT 1,
            `unit` VARCHAR(50) NOT NULL DEFAULT 'unit',
            `supplier_name` VARCHAR(255) NULL,
            `client_name` VARCHAR(255) NULL COMMENT 'Required for project delivery records',
            `contact_person` VARCHAR(150) NULL,
            `contact_phone` VARCHAR(50) NULL,
            `received_date` DATE NULL COMMENT 'Date the item was received from the supplier',
            `delivery_date` DATE NULL COMMENT 'Required for project delivery records',
            `shipping_type` ENUM('air_freight','ground_freight','sea_freight') NULL,
            `responsible_user_id` INT NOT NULL,
            `status` ENUM('scheduled','in_transit','delivered','received','cancelled') NOT NULL,
            `notes` TEXT NULL,
            `completed_at` DATETIME NULL,
            `reminder_sent_at` DATETIME NULL,
            `created_by` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_logistics_reference` (`reference_no`),
            KEY `idx_logistics_type_status` (`record_type`,`status`),
            KEY `idx_logistics_due_date` (`delivery_date`),
            KEY `idx_logistics_responsible` (`responsible_user_id`),
            CONSTRAINT `fk_logistics_responsible` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `fk_logistics_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    foreach ($permissions as $action => $label) {
        Database::query(
            "INSERT INTO `permissions` (`module`,`action`,`label`) VALUES ('logistics',?,?)
             ON DUPLICATE KEY UPDATE `label`=VALUES(`label`)",
            [$action, $label]
        );
    }

    $roles = Database::query("SELECT `name` FROM `roles`")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($roles as $role) {
        foreach (array_keys($permissions) as $action) {
            $allowed = in_array($action, $roleDefaults[$role] ?? [], true) ? 1 : 0;
            Database::query(
                "INSERT IGNORE INTO `role_permissions` (`role_name`,`module`,`action`,`allowed`) VALUES (?,'logistics',?,?)",
                [$role, $action, $allowed]
            );
        }
    }

    echo "Project Delivery & Demo Item Tracker migration completed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Migration failed: {$e->getMessage()}\n");
    exit(1);
}

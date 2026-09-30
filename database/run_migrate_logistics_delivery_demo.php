<?php
/**
 * Upgrade the logistics tracker from Delivery / Loan to
 * Project Delivery / Demo Item records. Safe to run more than once.
 */
require_once __DIR__ . '/../config/database.php';

function logisticsColumnExists(string $column): bool
{
    return (bool) Database::query(
        "SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=? AND TABLE_NAME='logistics_records' AND COLUMN_NAME=? LIMIT 1",
        [DB_NAME, $column]
    )->fetchColumn();
}

try {
    if (!logisticsColumnExists('id')) {
        throw new RuntimeException('The logistics_records table does not exist. Run run_migrate_logistics_tracker.php first.');
    }

    // Allow both generations temporarily while existing rows are converted.
    Database::query(
        "ALTER TABLE `logistics_records`
         MODIFY `record_type` ENUM('delivery','loan','project_delivery','demo_item') NOT NULL"
    );
    Database::query(
        "ALTER TABLE `logistics_records`
         MODIFY `status` ENUM('scheduled','in_transit','delivered','borrowed','returned','received','cancelled') NOT NULL"
    );

    if (!logisticsColumnExists('supplier_name')) {
        Database::query(
            "ALTER TABLE `logistics_records`
             ADD `supplier_name` VARCHAR(255) NULL AFTER `unit`"
        );
    }
    if (logisticsColumnExists('party_name') && !logisticsColumnExists('client_name')) {
        Database::query(
            "ALTER TABLE `logistics_records`
             CHANGE `party_name` `client_name` VARCHAR(255) NULL COMMENT 'Required for project delivery records'"
        );
    }
    if (logisticsColumnExists('start_date') && !logisticsColumnExists('received_date')) {
        Database::query(
            "ALTER TABLE `logistics_records`
             CHANGE `start_date` `received_date` DATE NULL COMMENT 'Date the item was received from the supplier'"
        );
    }
    if (logisticsColumnExists('due_date') && !logisticsColumnExists('delivery_date')) {
        Database::query(
            "ALTER TABLE `logistics_records`
             CHANGE `due_date` `delivery_date` DATE NULL COMMENT 'Required for project delivery records'"
        );
    }
    if (!logisticsColumnExists('shipping_type')) {
        Database::query(
            "ALTER TABLE `logistics_records`
             ADD `shipping_type` ENUM('air_freight','ground_freight','sea_freight') NULL AFTER `delivery_date`"
        );
    }

    Database::query("UPDATE `logistics_records` SET `record_type`='project_delivery' WHERE `record_type`='delivery'");
    Database::query("UPDATE `logistics_records` SET `record_type`='demo_item' WHERE `record_type`='loan'");
    Database::query(
        "UPDATE `logistics_records`
         SET `status`='received', `client_name`=NULL, `delivery_date`=NULL, `shipping_type`=NULL
         WHERE `record_type`='demo_item'"
    );

    Database::query(
        "ALTER TABLE `logistics_records`
         MODIFY `record_type` ENUM('project_delivery','demo_item') NOT NULL,
         MODIFY `status` ENUM('scheduled','in_transit','delivered','received','cancelled') NOT NULL"
    );

    $permissionLabels = [
        'view' => 'View Project Delivery & Demo Item Tracker',
        'create' => 'Create Project Delivery / Demo Item Record',
        'edit' => 'Edit Logistics Record & Update Status',
        'delete' => 'Delete Project Delivery / Demo Item Record',
    ];
    foreach ($permissionLabels as $action => $label) {
        Database::query(
            "UPDATE `permissions` SET `label`=? WHERE `module`='logistics' AND `action`=?",
            [$label, $action]
        );
    }

    echo "Project Delivery & Demo Item migration completed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Migration failed: {$e->getMessage()}\n");
    exit(1);
}

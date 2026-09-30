<?php
/** Create the New Tender Board tables and default permissions. Safe to re-run. */
require_once __DIR__ . '/../config/database.php';

try {
    Database::query(
        "CREATE TABLE IF NOT EXISTS `tender_opportunities` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `qt_number` VARCHAR(100) NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `tender_date` DATE NOT NULL,
            `closing_date` DATE NOT NULL,
            `created_by` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_tender_opportunity_qt` (`qt_number`),
            KEY `idx_tender_opportunity_date` (`tender_date`),
            CONSTRAINT `fk_tender_opportunity_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    Database::query(
        "CREATE TABLE IF NOT EXISTS `tender_opportunity_participants` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `tender_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_tender_opportunity_participant` (`tender_id`),
            KEY `idx_tender_participant_user` (`user_id`),
            CONSTRAINT `fk_tender_participant_tender` FOREIGN KEY (`tender_id`) REFERENCES `tender_opportunities` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_tender_participant_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // A tender can be assigned to one staff member only.
    $participantUniqueIndex = Database::query(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tender_opportunity_participants'
           AND INDEX_NAME='uq_tender_opportunity_participant'"
    )->fetchColumn();
    if ((int)$participantUniqueIndex !== 1) {
        $duplicateAssignments = (int)Database::query(
            "SELECT COUNT(*) FROM (
                SELECT `tender_id` FROM `tender_opportunity_participants`
                GROUP BY `tender_id` HAVING COUNT(*) > 1
             ) duplicate_tenders"
        )->fetchColumn();
        if ($duplicateAssignments > 0) {
            throw new RuntimeException(
                'Cannot enable one-staff-per-tender because existing tenders have multiple staff assignments.'
            );
        }
        $dropExistingIndex = (int)$participantUniqueIndex > 0
            ? "DROP INDEX `uq_tender_opportunity_participant`,"
            : '';
        Database::query(
            "ALTER TABLE `tender_opportunity_participants`
             {$dropExistingIndex}
             ADD UNIQUE KEY `uq_tender_opportunity_participant` (`tender_id`)"
        );
    }

    // Upgrade installations created before closing dates and KPI linking existed.
    $closingColumn = Database::query("SHOW COLUMNS FROM `tender_opportunities` LIKE 'closing_date'")->fetch();
    if (!$closingColumn) {
        Database::query("ALTER TABLE `tender_opportunities` ADD COLUMN `closing_date` DATE NULL AFTER `tender_date`");
        Database::query("UPDATE `tender_opportunities` SET `closing_date`=`tender_date` WHERE `closing_date` IS NULL");
        Database::query("ALTER TABLE `tender_opportunities` MODIFY COLUMN `closing_date` DATE NOT NULL");
    }

    $kpiLinkColumn = Database::query("SHOW COLUMNS FROM `tenders` LIKE 'tender_opportunity_id'")->fetch();
    if (!$kpiLinkColumn) {
        Database::query("ALTER TABLE `tenders` ADD COLUMN `tender_opportunity_id` INT NULL AFTER `user_id`");
    }

    $kpiLinkIndex = Database::query("SHOW INDEX FROM `tenders` WHERE `Key_name`='uq_tender_kpi_opportunity_user'")->fetch();
    if (!$kpiLinkIndex) {
        Database::query("ALTER TABLE `tenders` ADD UNIQUE KEY `uq_tender_kpi_opportunity_user` (`user_id`,`tender_opportunity_id`)");
    }

    $kpiLinkForeignKey = Database::query(
        "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='tenders'
           AND CONSTRAINT_NAME='fk_tender_kpi_opportunity' AND CONSTRAINT_TYPE='FOREIGN KEY'"
    )->fetchColumn();
    if (!$kpiLinkForeignKey) {
        Database::query(
            "ALTER TABLE `tenders` ADD CONSTRAINT `fk_tender_kpi_opportunity`
             FOREIGN KEY (`tender_opportunity_id`) REFERENCES `tender_opportunities` (`id`) ON DELETE SET NULL"
        );
    }

    // Per-staff pricing details for tenders joined from the Tender Board.
    $pricingColumns = [
        'indicative_price' => "DECIMAL(15,2) NULL AFTER `project_value`",
        'cost' => "DECIMAL(15,2) NULL AFTER `indicative_price`",
        'selling_price' => "DECIMAL(15,2) NULL AFTER `cost`",
        'pricing_attachment' => "VARCHAR(255) NULL AFTER `selling_price`",
        'pricing_attachment_name' => "VARCHAR(255) NULL AFTER `pricing_attachment`",
        'pricing_updated_at' => "DATETIME NULL AFTER `pricing_attachment_name`",
    ];
    foreach ($pricingColumns as $column => $definition) {
        if (!Database::query("SHOW COLUMNS FROM `tenders` LIKE '{$column}'")->fetch()) {
            Database::query("ALTER TABLE `tenders` ADD COLUMN `{$column}` {$definition}");
        }
    }

    // Preserve values entered through the former single-value form.
    Database::query(
        "UPDATE `tenders`
         SET `selling_price`=`project_value`
         WHERE `tender_opportunity_id` IS NOT NULL
           AND `selling_price` IS NULL
           AND `project_value` > 0"
    );

    // Under Review is no longer part of the fixed workflow. Convert legacy rows
    // before narrowing the enum and ensure new assignments can use In Progress.
    Database::query("UPDATE `tenders` SET `status`='submitted' WHERE `status`='under_review'");
    Database::query(
        "ALTER TABLE `tenders`
         MODIFY COLUMN `status` ENUM('in_progress','submitted','won','lost')
         NULL DEFAULT 'submitted'"
    );

    $permissions = [
        'view' => 'View Tender Board',
        'create' => 'Add Tender Opportunity',
        'edit' => 'Edit Tender Opportunity',
        'delete' => 'Delete Tender Opportunity',
        'join' => 'Join / Leave Tender',
        'report' => 'Generate Personal Monthly Tender Report',
    ];
    foreach ($permissions as $action => $label) {
        Database::query(
            "INSERT INTO `permissions` (`module`,`action`,`label`) VALUES ('tender_board',?,?)
             ON DUPLICATE KEY UPDATE `label`=VALUES(`label`)",
            [$action, $label]
        );
    }

    $roles = Database::query("SELECT `name` FROM `roles`")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($roles as $role) {
        foreach (array_keys($permissions) as $action) {
            $allowed = in_array($action, ['view', 'join', 'report'], true)
                || in_array($role, ['super_admin', 'admin'], true);
            Database::query(
                "INSERT IGNORE INTO `role_permissions` (`role_name`,`module`,`action`,`allowed`) VALUES (?,'tender_board',?,?)",
                [$role, $action, $allowed ? 1 : 0]
            );
        }
    }

    echo "Tender Board migration completed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Migration failed: {$e->getMessage()}\n");
    exit(1);
}

<?php
require_once __DIR__ . '/../config/database.php';

try {
    $sql = file_get_contents(__DIR__ . '/migrate_org_chart.sql');
    
    // Disable FK checks temporarily for schema changes
    Database::query("SET FOREIGN_KEY_CHECKS = 0;");
    
    $statements = explode(';', $sql);
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        try {
            Database::query($statement);
            echo "Executed: " . substr($statement, 0, 50) . "...\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') !== false || 
                strpos($e->getMessage(), 'Duplicate column name') !== false ||
                strpos($e->getMessage(), 'already exists') !== false) {
                echo "Skipped (already exists): " . substr($statement, 0, 50) . "...\n";
            } else {
                echo "Error on: " . substr($statement, 0, 50) . "...\n";
                echo "Message: " . $e->getMessage() . "\n";
            }
        }
    }
    
    Database::query("SET FOREIGN_KEY_CHECKS = 1;");
    echo "\nMigration completed.\n";
    
} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
}

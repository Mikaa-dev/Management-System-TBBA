<?php
/**
 * Migration Runner for PWA Push Notifications (Module 11)
 */
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = Database::connect();
    $sql = file_get_contents(__DIR__ . '/migrate_push_subscriptions.sql');
    $pdo->exec($sql);
    echo "PWA Push Notifications migration executed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>

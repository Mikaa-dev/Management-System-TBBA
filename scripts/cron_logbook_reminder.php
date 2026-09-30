<?php
/**
 * Cron Job: Weekly Logbook Reminder
 * Run every Friday at 16:00 (4:00 PM)
 * cron entry: 0 16 * * 5 php /path/to/scripts/cron_logbook_reminder.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/NotificationService.php';

$ts = time();
$weekStart = date('Y-m-d', strtotime('monday this week', $ts));

// Find active staff who haven't submitted a report for this week
$sql = "
    SELECT u.`id`, u.`name`
    FROM `users` u
    LEFT JOIN `logbook_reports` r ON r.`user_id` = u.`id` AND r.`week_start` = ?
    WHERE u.`status` = 'active'
      AND u.`role` != 'super_admin'
      AND (r.`id` IS NULL OR r.`status` = 'draft')
";

try {
    $pendingUsers = Database::query($sql, [$weekStart])->fetchAll();
    $count = 0;

    foreach ($pendingUsers as $user) {
        $userId = (int)$user['id'];
        
        NotificationService::notifyAndPush(
            $userId,
            'logbook_reminder',
            'Logbook Reminder',
            'Hello ' . $user['name'] . ', please submit your Weekly Logbook before 5:30 PM today!',
            'fa-clock',
            '#EF4444',
            'index.php?page=logbook',
            'logbook_reminder'
        );
        $count++;
    }

    echo "Successfully sent reminders to $count users.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

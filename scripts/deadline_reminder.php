<?php
/**
 * Deadline Reminder Cron Job — TBBA ERP
 * =======================================
 * Script ini perlu dijalankan setiap hari (biasanya pagi) untuk
 * menghantar notification H-1 dan H-7 sebelum deadline.
 *
 * SETUP untuk Laragon (Windows Task Scheduler):
 *   1. Buka Task Scheduler (cari dalam Start Menu)
 *   2. Create Basic Task → beri nama "TBBA ERP Deadline Reminders"
 *   3. Trigger: Daily → jam 8:00 AM
 *   4. Action: Start a program
 *      Program: C:\laragon\bin\php\php8.x.x\php.exe
 *      Arguments: C:\laragon\www\Bridge\scripts\deadline_reminder.php
 *   5. Klik Finish
 *
 * ATAU jalankan manual dari command line untuk testing:
 *   php C:\laragon\www\Bridge\scripts\deadline_reminder.php
 *
 * OUTPUT: Script ini akan print log ke console dan error_log.
 */

// ─── Bootstrap ────────────────────────────────────────────────────────────────
// Tetapkan working directory ke root projek
chdir(dirname(__DIR__));

// Load dependencies
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../core/NotificationService.php';
require_once __DIR__ . '/../models/NotificationToken.php';
require_once __DIR__ . '/../models/NotificationPushLog.php';
require_once __DIR__ . '/../models/LogisticsRecord.php';

// Loader composer (untuk phpdotenv)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

date_default_timezone_set('Asia/Kuala_Lumpur');

// Log function
function logMsg(string $msg): void {
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] {$msg}";
    echo $line . PHP_EOL;
    error_log('[DeadlineReminder] ' . $msg);
}

logMsg("=== TBBA ERP Deadline Reminder Started ===");
logMsg("Current date: " . date('Y-m-d H:i:s T'));

$totalSent = 0;
$errors    = 0;

// ─── 1. Leave Request — H-1 Reminder ─────────────────────────────────────────
logMsg("--- Checking leave requests that start tomorrow ---");
try {
    // Cari leave requests yang APPROVED dan start_date = ESOK
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $leaveRequests = Database::query(
        "SELECT lr.*, u.name as user_name, lt.name as leave_type_name
         FROM `leave_requests` lr
         JOIN `users` u ON u.id = lr.user_id
         JOIN `leave_types` lt ON lt.id = lr.leave_type_id
         WHERE lr.start_date = ?
           AND lr.status = 'approved'
         ORDER BY lr.user_id",
        [$tomorrow]
    )->fetchAll();

    logMsg("Found " . count($leaveRequests) . " leave request(s) starting tomorrow ({$tomorrow})");

    foreach ($leaveRequests as $lr) {
        $userId    = (int)$lr['user_id'];
        $userName  = $lr['user_name'];
        $leaveType = $lr['leave_type_name'];
        $days      = $lr['total_days'];

        $title = "⏰ Reminder: Leave Starts Tomorrow";
        $body  = "Hi {$userName}, your {$leaveType} leave ({$days} day(s)) starts tomorrow ({$tomorrow}). Please ensure your tasks have been handed over.";
        $link  = 'index.php?page=leave';

        $result = NotificationService::sendPush($userId, $title, $body, $link, 'leave_reminder');

        if ($result) {
            logMsg("✓ Reminder sent to User #{$userId} ({$userName}) — {$leaveType}");
            $totalSent++;
        } else {
            logMsg("- User #{$userId} ({$userName}) has no active subscription (skipped).");
        }
    }
} catch (Exception $e) {
    logMsg("ERROR (Leave): " . $e->getMessage());
    $errors++;
}

// ─── 2. Calendar Events — H-1 Reminder ───────────────────────────────────────
logMsg("--- Checking tomorrow's calendar events ---");
try {
    $tomorrow = date('Y-m-d', strtotime('+1 day'));

    // Cari events yang berlaku esok
    $events = Database::query(
        "SELECT e.*, u.name as creator_name
         FROM `events` e
         LEFT JOIN `users` u ON u.id = e.created_by
         WHERE DATE(e.start_datetime) = ?
         ORDER BY e.start_datetime",
        [$tomorrow]
    )->fetchAll();

    logMsg("Found " . count($events) . " event(s) tomorrow ({$tomorrow})");

    foreach ($events as $event) {
        $eventTitle = $event['title'];
        $startTime  = date('h:i A', strtotime($event['start_datetime']));
        $eventId    = $event['id'];

        // Dapatkan semua user yang ada dalam sistem (untuk broadcast reminder)
        // Dalam sistem anda, event mungkin ada attendees — adjust jika perlu
        $users = Database::query(
            "SELECT DISTINCT id FROM `users` WHERE `status` = 'active'"
        )->fetchAll();

        $notifTitle = "📅 Reminder: Event Tomorrow";
        $notifBody  = "The event '{$eventTitle}' takes place tomorrow at {$startTime}. Do not forget to attend.";
        $notifLink  = 'index.php?page=calendar';

        $eventSent = 0;
        foreach ($users as $user) {
            $result = NotificationService::sendPush(
                (int)$user['id'], $notifTitle, $notifBody, $notifLink, 'calendar_reminder'
            );
            if ($result) $eventSent++;
        }

        logMsg("✓ Event reminder '{$eventTitle}' sent to {$eventSent} user(s).");
        $totalSent += $eventSent;
    }
} catch (Exception $e) {
    logMsg("ERROR (Calendar): " . $e->getMessage());
    $errors++;
}

// ─── 3. Expense Claims — Overdue Reminder (> 7 hari pending) ─────────────────
logMsg("--- Checking expense claims pending for more than 7 days ---");
try {
    $overdueClaims = Database::query(
        "SELECT ec.*, u.name as user_name
         FROM `expense_claims` ec
         JOIN `users` u ON u.id = ec.user_id
         WHERE ec.status = 'pending'
           AND ec.created_at <= DATE_SUB(NOW(), INTERVAL 7 DAY)
         ORDER BY ec.created_at ASC
         LIMIT 50"
    )->fetchAll();

    logMsg("Found " . count($overdueClaims) . " expense claim(s) pending for more than 7 days");

    foreach ($overdueClaims as $claim) {
        $userId    = (int)$claim['user_id'];
        $userName  = $claim['user_name'];
        $claimTitle= $claim['title'];
        $amount    = 'MYR ' . number_format($claim['total_amount'], 2);
        $daysPast  = (int)floor((time() - strtotime($claim['created_at'])) / 86400);

        $title = "⚠️ Expense Claim Awaiting Approval";
        $body  = "Your expense claim '{$claimTitle}' ({$amount}) has been awaiting approval for {$daysPast} day(s). Please follow up with your manager.";
        $link  = 'index.php?page=expense';

        $result = NotificationService::sendPush($userId, $title, $body, $link, 'expense_overdue');

        if ($result) {
            logMsg("✓ Expense reminder sent to User #{$userId} ({$userName})");
            $totalSent++;
        }
    }
} catch (Exception $e) {
    logMsg("ERROR (Expense Overdue): " . $e->getMessage());
    $errors++;
}

// ─── 4. Project Delivery Tracker — H-7 Reminder ─────────────────────────────
logMsg("--- Checking project deliveries due within 7 days ---");
try {
    $reminderDate = date('Y-m-d', strtotime('+7 days'));
    $records = LogisticsRecord::dueForReminder($reminderDate);
    logMsg("Found " . count($records) . " record(s) due by {$reminderDate}");

    foreach ($records as $record) {
        $title = '🚚 Project Delivery Due Within 7 Days';
        $body = "'{$record['item_name']}' must be delivered to {$record['client_name']} by {$record['delivery_date']}.";
        $userId = (int)$record['responsible_user_id'];

        $appUrl = rtrim((string)($_ENV['APP_URL'] ?? getenv('APP_URL') ?: ''), '/');
        $link = ($appUrl !== '' ? $appUrl . '/' : '') . 'index.php?page=logistics';

        NotificationService::notifyAndPush(
            $userId,
            'delivery_due_reminder',
            $title,
            $body,
            'fa-truck-fast',
            '#0F766E',
            $link,
            'delivery_due_reminder'
        );
        LogisticsRecord::markReminderSent((int)$record['id']);
        logMsg("✓ Reminder {$record['reference_no']} sent to User #{$userId} ({$record['responsible_name']})");
        $totalSent++;
    }
} catch (Exception $e) {
    logMsg("ERROR (Project Delivery): " . $e->getMessage());
    $errors++;
}

// ─── 5. Tender Closing Date — H-3 and H-1 Reminder ───────────────────────────
logMsg("--- Checking tenders closing in 3 days and 1 day ---");
try {
    require_once __DIR__ . '/../models/Tender.php';
    
    $daysToCheck = [
        3 => date('Y-m-d', strtotime('+3 days')),
        1 => date('Y-m-d', strtotime('+1 day'))
    ];

    foreach ($daysToCheck as $daysLeft => $dateToCheck) {
        $tenders = Tender::getClosingSoon($dateToCheck);
        logMsg("Found " . count($tenders) . " tender(s) closing in {$daysLeft} day(s) ({$dateToCheck})");

        foreach ($tenders as $tender) {
            $userId = (int)$tender['user_id'];
            $projectName = $tender['project_name'];
            
            $title = "⏳ Tender Closing in {$daysLeft} Day(s)";
            $body = "The tender '{$projectName}' is closing on {$dateToCheck}. Please ensure all documents are submitted.";
            $link = 'index.php?page=tender';

            NotificationService::notifyAndPush(
                $userId,
                'tender_reminder',
                $title,
                $body,
                'fa-file-signature',
                '#EAB308', // Yellow
                $link,
                'tender_reminder'
            );
            
            logMsg("✓ Tender reminder '{$projectName}' sent to User #{$userId}");
            $totalSent++;
        }
    }
} catch (Exception $e) {
    logMsg("ERROR (Tender Reminder): " . $e->getMessage());
    $errors++;
}

// ─── Summary ──────────────────────────────────────────────────────────────────
logMsg("=== Summary ===");
logMsg("Total notifications / push messages sent: {$totalSent}");
logMsg("Errors: {$errors}");
logMsg("=== Script Completed ===");

exit($errors > 0 ? 1 : 0);
?>

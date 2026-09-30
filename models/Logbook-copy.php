<?php
/**
 * Logbook Model v2.4 — view-only staff reports
 * Handles weekly reports, daily logs, activities, actions, photos and PDF output.
 */

require_once __DIR__ . '/../config/database.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class Logbook
{
    public static function getOrCreateReport(int $userId, ?string $date = null): array
    {
        $date = $date ?: date('Y-m-d');
        $ts = strtotime($date);

        if ($ts === false) {
            $ts = time();
        }

        $weekStart = date('Y-m-d', strtotime('monday this week', $ts));
        $weekEnd = date('Y-m-d', strtotime('sunday this week', $ts));

        $stmt = Database::query(
            "SELECT * FROM `logbook_reports` WHERE `user_id` = ? AND `week_start` = ? LIMIT 1",
            [$userId, $weekStart]
        );
        $report = $stmt->fetch();

        if ($report) {
            return $report;
        }

        Database::query(
            "INSERT INTO `logbook_reports` (`user_id`, `week_start`, `week_end`, `status`)
             VALUES (?, ?, ?, 'draft')",
            [$userId, $weekStart, $weekEnd]
        );

        return self::getReportById((int) Database::lastInsertId()) ?? [];
    }

    public static function getReportById(int $reportId): ?array
    {
        return Database::query(
            "SELECT * FROM `logbook_reports` WHERE `id` = ? LIMIT 1",
            [$reportId]
        )->fetch() ?: null;
    }

    public static function getUserProfile(int $userId): array
    {
        $user = Database::query(
            "SELECT
                u.`name`,
                u.`department_id`,
                COALESCE(d.`name`, 'N/A') AS `department`
             FROM `users` u
             LEFT JOIN `departments` d ON d.`id` = u.`department_id`
             WHERE u.`id` = ?
             LIMIT 1",
            [$userId]
        )->fetch();

        return $user ?: [
            'name' => 'Unknown User',
            'department_id' => null,
            'department' => 'N/A'
        ];
    }

    /**
     * Completely structured weekly data:
     * daily log -> activities -> actions + photos.
     */
    public static function getStructuredWeeklyLogs(int $reportId): array
    {
        $logs = Database::query(
            "SELECT * FROM `logbook_daily_logs`
             WHERE `report_id` = ?
             ORDER BY `activity_date` ASC",
            [$reportId]
        )->fetchAll();

        $structured = [];

        foreach ($logs as $log) {
            $log['activities'] = self::getActivitiesForLog((int) $log['id']);
            $structured[$log['activity_date']] = $log;
        }

        return $structured;
    }

    public static function getActivitiesForLog(int $dailyLogId): array
    {
        $activities = Database::query(
            "SELECT * FROM `logbook_activities`
             WHERE `daily_log_id` = ?
             ORDER BY `order_num` ASC, `id` ASC",
            [$dailyLogId]
        )->fetchAll();

        foreach ($activities as &$activity) {
            $activityId = (int) $activity['id'];

            $activity['actions'] = Database::query(
                "SELECT * FROM `logbook_actions`
                 WHERE `activity_id` = ?
                 ORDER BY `order_num` ASC, `id` ASC",
                [$activityId]
            )->fetchAll();

            $photos = Database::query(
                "SELECT * FROM `logbook_photos`
                 WHERE `activity_id` = ?
                 ORDER BY `id` ASC",
                [$activityId]
            )->fetchAll();

            foreach ($photos as &$photo) {
                $photo['url'] = 'index.php?action=logbook_photo&id=' . (int) $photo['id'];
            }
            unset($photo);

            $activity['photos'] = $photos;
        }
        unset($activity);

        return $activities;
    }

    public static function saveDailyLog(int $reportId, string $date, array $data): void
    {
        // database.php exposes getInstance(); there is no getConnection().
        $db = Database::getInstance();

        $allowedDayTypes = ['Working Day', 'Leave', 'Public Holiday', 'Medical Leave'];
        $allowedStatuses = ['Not Started', 'Draft', 'Completed'];

        $location = trim((string) ($data['location'] ?? ''));
        $location = $location !== '' ? $location : null;

        $startTime = self::normalizeTime($data['start_time'] ?? null);
        $endTime = self::normalizeTime($data['end_time'] ?? null);

        $dayType = (string) ($data['day_type'] ?? 'Working Day');
        if (!in_array($dayType, $allowedDayTypes, true)) {
            $dayType = 'Working Day';
        }

        $status = (string) ($data['status'] ?? 'Draft');
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'Draft';
        }

        try {
            $db->beginTransaction();

            $stmt = Database::query(
                "SELECT `id` FROM `logbook_daily_logs`
                 WHERE `report_id` = ? AND `activity_date` = ?
                 LIMIT 1",
                [$reportId, $date]
            );
            $log = $stmt->fetch();

            if ($log) {
                $dailyLogId = (int) $log['id'];

                Database::query(
                    "UPDATE `logbook_daily_logs`
                     SET `location` = ?, `start_time` = ?, `end_time` = ?,
                         `day_type` = ?, `status` = ?
                     WHERE `id` = ?",
                    [$location, $startTime, $endTime, $dayType, $status, $dailyLogId]
                );
            } else {
                Database::query(
                    "INSERT INTO `logbook_daily_logs`
                        (`report_id`, `activity_date`, `location`, `start_time`, `end_time`, `day_type`, `status`)
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$reportId, $date, $location, $startTime, $endTime, $dayType, $status]
                );
                $dailyLogId = (int) Database::lastInsertId();
            }

            // Only replace activities if the client actually supplied the field.
            if (array_key_exists('activities', $data) && is_array($data['activities'])) {
                $existingActivities = Database::query(
                    "SELECT `id` FROM `logbook_activities`
                     WHERE `daily_log_id` = ?",
                    [$dailyLogId]
                )->fetchAll(PDO::FETCH_COLUMN);

                $existingActivities = array_map('intval', $existingActivities);
                $newActivityIds = [];

                foreach ($data['activities'] as $index => $actData) {
                    if (!is_array($actData)) {
                        continue;
                    }

                    $actId = !empty($actData['id']) ? (int) $actData['id'] : 0;
                    $title = trim((string) ($actData['title'] ?? ''));

                    if ($actId > 0 && in_array($actId, $existingActivities, true)) {
                        Database::query(
                            "UPDATE `logbook_activities`
                             SET `title` = ?, `order_num` = ?
                             WHERE `id` = ? AND `daily_log_id` = ?",
                            [$title, (int) $index, $actId, $dailyLogId]
                        );
                    } else {
                        Database::query(
                            "INSERT INTO `logbook_activities`
                                (`daily_log_id`, `title`, `order_num`)
                             VALUES (?, ?, ?)",
                            [$dailyLogId, $title, (int) $index]
                        );
                        $actId = (int) Database::lastInsertId();
                    }

                    $newActivityIds[] = $actId;

                    // Re-create textual actions. The JS sends objects:
                    // { description: "..." }, while older clients may send strings.
                    Database::query(
                        "DELETE FROM `logbook_actions` WHERE `activity_id` = ?",
                        [$actId]
                    );

                    if (isset($actData['actions']) && is_array($actData['actions'])) {
                        foreach ($actData['actions'] as $actionIndex => $actionData) {
                            $description = is_array($actionData)
                                ? (string) ($actionData['description'] ?? '')
                                : (string) $actionData;

                            $description = trim($description);

                            if ($description !== '') {
                                Database::query(
                                    "INSERT INTO `logbook_actions`
                                        (`activity_id`, `description`, `order_num`)
                                     VALUES (?, ?, ?)",
                                    [$actId, $description, (int) $actionIndex]
                                );
                            }
                        }
                    }
                }

                // Explicitly remove child records because the supplied schema does
                // not define ON DELETE CASCADE foreign keys.
                $toDelete = array_values(array_diff($existingActivities, $newActivityIds));

                if ($toDelete) {
                    $placeholders = implode(',', array_fill(0, count($toDelete), '?'));

                    $photoRows = Database::query(
                        "SELECT `id`, `photo_path`
                         FROM `logbook_photos`
                         WHERE `activity_id` IN ($placeholders)",
                        $toDelete
                    )->fetchAll();

                    foreach ($photoRows as $photo) {
                        self::deletePhysicalPhoto((string) $photo['photo_path']);
                    }

                    Database::query(
                        "DELETE FROM `logbook_photos`
                         WHERE `activity_id` IN ($placeholders)",
                        $toDelete
                    );

                    Database::query(
                        "DELETE FROM `logbook_actions`
                         WHERE `activity_id` IN ($placeholders)",
                        $toDelete
                    );

                    Database::query(
                        "DELETE FROM `logbook_activities`
                         WHERE `id` IN ($placeholders)",
                        $toDelete
                    );
                }
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function submitReport(int $reportId, int $userId): bool
    {
        $stmt = Database::query(
            "SELECT `id` FROM `logbook_reports`
             WHERE `id` = ? AND `user_id` = ? AND `status` = 'draft'
             LIMIT 1",
            [$reportId, $userId]
        );

        if (!$stmt->fetch()) {
            return false;
        }

        $update = Database::query(
            "UPDATE `logbook_reports`
             SET `status` = 'submitted', `submitted_at` = NOW()
             WHERE `id` = ? AND `user_id` = ? AND `status` = 'draft'",
            [$reportId, $userId]
        );

        return $update->rowCount() === 1;
    }

    public static function getTeamReports(
        string $weekStart,
        ?int $scopeDepartmentId = null,
        array $filters = []
    ): array {
        $where = ["u.`status` = 'active'"];
        $params = [$weekStart];

        if ($scopeDepartmentId !== null && $scopeDepartmentId > 0) {
            $where[] = "u.`department_id` = ?";
            $params[] = $scopeDepartmentId;
        } else {
            $departmentId = (int) ($filters['department_id'] ?? 0);
            if ($departmentId > 0) {
                $where[] = "u.`department_id` = ?";
                $params[] = $departmentId;
            }
        }

        $status = strtolower((string) ($filters['status'] ?? 'all'));
        if ($status === 'not_started') {
            $where[] = "r.`id` IS NULL";
        } elseif (in_array($status, ['draft', 'submitted'], true)) {
            $where[] = "r.`status` = ?";
            $params[] = $status;
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = "(u.`name` LIKE ? OR u.`email` LIKE ? OR u.`employee_id` LIKE ?)";
            array_push($params, $like, $like, $like);
        }

        $sql = "
            SELECT
                r.`id`, r.`user_id`, r.`week_start`, r.`week_end`, r.`status`,
                r.`submitted_at`, r.`created_at`, r.`updated_at`,
                u.`id` AS `staff_user_id`, u.`name` AS `user_name`, u.`email`,
                u.`employee_id`, u.`position`, u.`department_id`,
                COALESCE(d.`name`, 'N/A') AS `department`
            FROM `users` u
            LEFT JOIN `departments` d ON d.`id` = u.`department_id`
            LEFT JOIN `logbook_reports` r
                ON r.`user_id` = u.`id`
               AND r.`week_start` = ?
            WHERE " . implode(' AND ', $where) . "
            ORDER BY d.`name` ASC, u.`name` ASC
        ";

        return Database::query($sql, $params)->fetchAll();
    }

    public static function getTeamSummary(string $weekStart, ?int $scopeDepartmentId = null): array
    {
        $where = ["u.`status` = 'active'"];
        $params = [$weekStart];

        if ($scopeDepartmentId !== null && $scopeDepartmentId > 0) {
            $where[] = "u.`department_id` = ?";
            $params[] = $scopeDepartmentId;
        }

        $sql = "
            SELECT
                COUNT(u.`id`) AS `total_staff`,
                SUM(CASE WHEN r.`id` IS NULL THEN 1 ELSE 0 END) AS `not_started`,
                SUM(CASE WHEN r.`status` = 'draft' THEN 1 ELSE 0 END) AS `draft`,
                SUM(CASE WHEN r.`status` = 'submitted' THEN 1 ELSE 0 END) AS `submitted`
            FROM `users` u
            LEFT JOIN `logbook_reports` r
                ON r.`user_id` = u.`id`
               AND r.`week_start` = ?
            WHERE " . implode(' AND ', $where);

        $row = Database::query($sql, $params)->fetch() ?: [];
        return [
            'total_staff' => (int) ($row['total_staff'] ?? 0),
            'not_started' => (int) ($row['not_started'] ?? 0),
            'draft' => (int) ($row['draft'] ?? 0),
            'submitted' => (int) ($row['submitted'] ?? 0),
        ];
    }

    public static function getDepartmentsForFilter(?int $scopeDepartmentId = null): array
    {
        if ($scopeDepartmentId !== null && $scopeDepartmentId > 0) {
            return Database::query(
                "SELECT `id`, `name` FROM `departments` WHERE `id` = ? ORDER BY `name` ASC",
                [$scopeDepartmentId]
            )->fetchAll();
        }

        return Database::query(
            "SELECT `id`, `name` FROM `departments` ORDER BY `name` ASC"
        )->fetchAll();
    }

    public static function getReportWithUser(int $reportId): ?array
    {
        $row = Database::query(
            "SELECT
                r.*,
                u.`name` AS `user_name`, u.`email`, u.`employee_id`, u.`position`, u.`department_id`,
                COALESCE(d.`name`, 'N/A') AS `department`
             FROM `logbook_reports` r
             JOIN `users` u ON u.`id` = r.`user_id`
             LEFT JOIN `departments` d ON d.`id` = u.`department_id`
             WHERE r.`id` = ?
             LIMIT 1",
            [$reportId]
        )->fetch();

        return $row ?: null;
    }

    public static function getUserDepartmentId(int $userId): ?int
    {
        $row = Database::query(
            "SELECT `department_id` FROM `users` WHERE `id` = ? LIMIT 1",
            [$userId]
        )->fetch();
        if (!$row) return null;
        $id = (int) ($row['department_id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    public static function getActivityContext(int $activityId): ?array
    {
        $row = Database::query(
            "SELECT
                a.`id` AS `activity_id`,
                dl.`id` AS `daily_log_id`,
                dl.`report_id`,
                r.`user_id`,
                r.`status` AS `report_status`
             FROM `logbook_activities` a
             JOIN `logbook_daily_logs` dl ON dl.`id` = a.`daily_log_id`
             JOIN `logbook_reports` r ON r.`id` = dl.`report_id`
             WHERE a.`id` = ?
             LIMIT 1",
            [$activityId]
        )->fetch();

        return $row ?: null;
    }

    public static function getPhotoContext(int $photoId): ?array
    {
        $row = Database::query(
            "SELECT
                p.`id`,
                p.`activity_id`,
                p.`photo_path`,
                p.`caption`,
                dl.`report_id`,
                r.`user_id`,
                r.`status` AS `report_status`
             FROM `logbook_photos` p
             JOIN `logbook_activities` a ON a.`id` = p.`activity_id`
             JOIN `logbook_daily_logs` dl ON dl.`id` = a.`daily_log_id`
             JOIN `logbook_reports` r ON r.`id` = dl.`report_id`
             WHERE p.`id` = ?
             LIMIT 1",
            [$photoId]
        )->fetch();

        return $row ?: null;
    }

    public static function addPhoto(int $activityId, string $path): int
    {
        Database::query(
            "INSERT INTO `logbook_photos` (`activity_id`, `photo_path`)
             VALUES (?, ?)",
            [$activityId, $path]
        );

        return (int) Database::lastInsertId();
    }

    public static function removePhotoForUser(int $photoId, int $userId): bool
    {
        $photo = self::getPhotoContext($photoId);

        if (
            !$photo ||
            (int) $photo['user_id'] !== $userId ||
            (string) ($photo['report_status'] ?? '') !== 'draft'
        ) {
            return false;
        }

        self::deletePhysicalPhoto((string) $photo['photo_path']);

        Database::query(
            "DELETE FROM `logbook_photos` WHERE `id` = ?",
            [$photoId]
        );

        return true;
    }

    /**
     * Generate A4 PDF using Dompdf.
     */
    public static function generatePdf(int $reportId): void
    {
        $autoload = __DIR__ . '/../vendor/autoload.php';

        if (!is_file($autoload)) {
            throw new RuntimeException('Composer dependencies are missing. vendor/autoload.php was not found.');
        }

        require_once $autoload;

        if (!class_exists(Dompdf::class)) {
            throw new RuntimeException('Dompdf is not installed.');
        }

        $report = self::getReportById($reportId);
        if (!$report) {
            throw new RuntimeException('Report not found.');
        }

        $user = self::getUserProfile((int) $report['user_id']);
        $structuredData = self::getStructuredWeeklyLogs($reportId);
        $html = self::buildPdfHtml($report, $user, $structuredData);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($user['name'] ?? 'User'));
        $fileName = 'Weekly_Report_' . trim($safeName, '_') . '_' . $report['week_start'] . '.pdf';

        $dompdf->stream($fileName, ['Attachment' => true]);
        exit;
    }

    private static function normalizeTime($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            return $value . ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
            return $value;
        }

        return null;
    }

    private static function deletePhysicalPhoto(string $relativePath): void
    {
        $baseDir = realpath(__DIR__ . '/../storage/private/logbooks');
        $absolutePath = realpath(__DIR__ . '/../' . $relativePath);

        if (
            $baseDir &&
            $absolutePath &&
            is_file($absolutePath) &&
            str_starts_with($absolutePath, $baseDir . DIRECTORY_SEPARATOR)
        ) {
            @unlink($absolutePath);
        }
    }

    private static function buildPdfHtml(array $report, array $user, array $logs): string
    {
        $logoPath = __DIR__ . '/../assets/images/pdflogo.png';
        $logoData = '';

        if (is_file($logoPath)) {
            $type = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
            if ($type === 'jpg') {
                $type = 'jpeg';
            }
            $logoData = 'data:image/' . $type . ';base64,' . base64_encode((string) file_get_contents($logoPath));
        }

        $weekStartText = date('d M', strtotime($report['week_start']));
        $weekEndText = date('d M Y', strtotime($report['week_end']));
        $department = htmlspecialchars((string) ($user['department'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars((string) ($user['name'] ?? 'Unknown User'), ENT_QUOTES, 'UTF-8');

        $html = '
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Helvetica, Arial, sans-serif; font-size: 11pt; color: #000; line-height: 1.4; margin: 0; padding: 0; }
                .header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; text-align: center; }
                .logo { max-height: 60px; float: left; }
                .header-title { font-size: 14pt; font-weight: bold; padding-top: 15px; }
                .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 10pt; }
                .info-table th { text-align: left; background-color: #555; color: #fff; padding: 5px; width: 25%; border: 1px solid #000; }
                .info-table td { padding: 5px; border: 1px solid #000; }
                .day-block { margin-bottom: 30px; page-break-inside: avoid; }
                .day-header { background-color: #E2E8F0; padding: 5px; font-weight: bold; border: 1px solid #000; text-transform: uppercase; font-size: 10pt; }
                .activity-table { width: 100%; border-collapse: collapse; margin-top: -1px; font-size: 10pt; }
                .activity-table td { border: 1px solid #000; padding: 8px; vertical-align: top; }
                .act-num { width: 5%; text-align: center; font-weight: bold; }
                .act-title { font-weight: bold; margin-bottom: 5px; text-transform: uppercase; }
                .act-actions { margin: 5px 0 10px 20px; padding: 0; list-style-type: disc; color: #D92B2B; font-weight: bold; }
                .act-actions li { margin-bottom: 3px; }
                .act-actions li span { color: #000; font-weight: normal; }
                .photo-grid { display: block; width: 100%; margin-top: 10px; }
                .photo-item { display: inline-block; width: 45%; margin: 5px 2%; text-align: center; }
                .photo-item img { max-width: 100%; max-height: 200px; border: 1px solid #ccc; }
            </style>
        </head>
        <body>
            <div class="header">
                ' . ($logoData ? '<img src="' . $logoData . '" class="logo">' : '') . '
                <div class="header-title">WEEKLY REPORT<br>' . $weekStartText . ' - ' . $weekEndText . '</div>
                <div style="clear: both;"></div>
            </div>

            <table class="info-table">
                <tr>
                    <th>DEPARTMENT</th>
                    <td style="text-transform: uppercase;">' . $department . '</td>
                    <th>DATE</th>
                    <td style="text-transform: uppercase;">' . $weekStartText . ' - ' . $weekEndText . '</td>
                </tr>
                <tr>
                    <th>NAME</th>
                    <td colspan="3" style="text-transform: uppercase;">' . $name . '</td>
                </tr>';

        $mondayLog = $logs[$report['week_start']] ?? null;
        if ($mondayLog) {
            $office = htmlspecialchars((string) ($mondayLog['location'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
            $start = !empty($mondayLog['start_time']) ? date('h:i A', strtotime($mondayLog['start_time'])) : '';
            $end = !empty($mondayLog['end_time']) ? date('h:i A', strtotime($mondayLog['end_time'])) : '';

            $html .= '
                <tr>
                    <th>OFFICE LOCATION</th>
                    <td colspan="3" style="text-transform: uppercase;">' . ($office !== '' ? $office : 'N/A') . '</td>
                </tr>
                <tr>
                    <th>WORKING HOURS</th>
                    <td colspan="3" style="text-transform: uppercase;">' . htmlspecialchars($start . ' - ' . $end, ENT_QUOTES, 'UTF-8') . '</td>
                </tr>';
        }

        $html .= '</table>';

        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

        for ($i = 0; $i < 5; $i++) {
            $currDate = date('Y-m-d', strtotime("+{$i} days", strtotime($report['week_start'])));
            $log = $logs[$currDate] ?? null;

            if (!$log || empty($log['activities'])) {
                continue;
            }

            $html .= '<div class="day-block">';
            $html .= '<div class="day-header">' . strtoupper($days[$i]) . ', ' . date('d M Y', strtotime($currDate)) . '</div>';
            $html .= '<table class="activity-table">';

            $counter = 1;
            foreach ($log['activities'] as $activity) {
                $title = htmlspecialchars((string) ($activity['title'] ?? ''), ENT_QUOTES, 'UTF-8');

                $html .= '<tr>';
                $html .= '<td class="act-num">' . $counter++ . '</td>';
                $html .= '<td>';
                $html .= '<div class="act-title">' . $title . '</div>';

                if (!empty($activity['actions'])) {
                    $html .= '<div style="color:#D92B2B;font-weight:bold;font-size:9pt;">ACTION LIST:</div>';
                    $html .= '<ul class="act-actions">';

                    foreach ($activity['actions'] as $action) {
                        $description = htmlspecialchars((string) ($action['description'] ?? ''), ENT_QUOTES, 'UTF-8');
                        if ($description !== '') {
                            $html .= '<li><span>' . $description . '</span></li>';
                        }
                    }

                    $html .= '</ul>';
                }

                if (!empty($activity['photos'])) {
                    $html .= '<div class="photo-grid">';

                    foreach ($activity['photos'] as $photo) {
                        $photoPath = __DIR__ . '/../' . ($photo['photo_path'] ?? '');

                        if (is_file($photoPath)) {
                            $extension = strtolower(pathinfo($photoPath, PATHINFO_EXTENSION));
                            $mimeExtension = $extension === 'jpg' ? 'jpeg' : $extension;
                            $photoData = base64_encode((string) file_get_contents($photoPath));

                            $html .= '<div class="photo-item"><img src="data:image/' .
                                htmlspecialchars($mimeExtension, ENT_QUOTES, 'UTF-8') .
                                ';base64,' . $photoData . '"></div>';
                        }
                    }

                    $html .= '</div>';
                }

                $html .= '</td></tr>';
            }

            $html .= '</table></div>';
        }

        $html .= '</body></html>';

        return $html;
    }
}

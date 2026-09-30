<?php
/**
 * Notification Model — TBBA ERP Module 10
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Permission.php';

class Notification {
    public static function send(int $userId, string $type, string $title, string $body, string $icon = 'fa-bell', string $color = '#2563EB', string $link = ''): void {
        try {
            Database::query(
                "INSERT INTO `notifications` (`user_id`,`type`,`title`,`body`,`icon`,`color`,`link`) VALUES (?,?,?,?,?,?,?)",
                [$userId, $type, $title, $body, $icon, $color, $link ?: null]
            );
        } catch (Exception $e) {
            error_log("[Notification] DB insert error: " . $e->getMessage());
        }
    }

    /** Return active users who can approve this module and can access the requester's department. */
    public static function approverUserIds(string $module, int $requesterUserId): array {
        try {
            $requesterDepartmentId = (int)Database::query(
                "SELECT COALESCE(`department_id`, 0) FROM `users` WHERE `id`=? LIMIT 1",
                [$requesterUserId]
            )->fetchColumn();

            $users = Database::query(
                "SELECT u.`id`,u.`role`,COALESCE(u.`department_id`,0) AS department_id,
                        EXISTS(
                            SELECT 1 FROM `departments` d
                            WHERE d.`head_user_id`=u.`id` AND d.`status`='active'
                              AND (UPPER(COALESCE(d.`code`,'')) IN ('HR','HRD')
                                   OR LOWER(COALESCE(d.`name`,'')) LIKE '%human resource%')
                        ) AS is_hr_head
                 FROM `users` u
                 WHERE u.`status`='active' AND u.`id`!=?",
                [$requesterUserId]
            )->fetchAll();

            $approverIds = [];
            foreach ($users as $user) {
                $userId = (int)$user['id'];
                $canApprove = $user['role'] === 'super_admin' || Permission::can($userId, $module, 'approve');
                if (!$canApprove) continue;

                // Non-HR department heads only receive requests from their own department.
                if ($user['role'] === 'dept_head' && empty($user['is_hr_head'])) {
                    if ($requesterDepartmentId <= 0 || (int)$user['department_id'] !== $requesterDepartmentId) continue;
                }
                $approverIds[] = $userId;
            }
            return array_values(array_unique($approverIds));
        } catch (Throwable $e) {
            error_log('[Notification] Approver lookup error: ' . $e->getMessage());
            return [];
        }
    }

    /** Send an Approval Center bell notification to every eligible approver. */
    public static function sendToApprovers(
        string $module,
        int $requesterUserId,
        string $type,
        string $title,
        string $body,
        string $icon,
        string $color
    ): int {
        $approverIds = self::approverUserIds($module, $requesterUserId);
        
        // Include NotificationService if not already included
        require_once __DIR__ . '/../core/NotificationService.php';
        
        foreach ($approverIds as $approverId) {
            \NotificationService::notifyAndPush(
                $approverId, 
                $type, 
                $title, 
                $body, 
                $icon, 
                $color, 
                'index.php?page=approvals',
                $type // use same type for push classification
            );
        }
        return count($approverIds);
    }

    public static function getForUser(int $userId, int $limit = 30, bool $unreadOnly = false): array {
        $sql = "SELECT * FROM `notifications` WHERE `user_id`=?";
        if ($unreadOnly) $sql .= " AND `is_read`=0";
        $sql .= " ORDER BY `created_at` DESC LIMIT " . intval($limit);
        try {
            return Database::query($sql, [$userId])->fetchAll();
        } catch (Exception $e) { return []; }
    }

    public static function countUnread(int $userId): int {
        try {
            return (int)Database::query(
                "SELECT COUNT(*) FROM `notifications` WHERE `user_id`=? AND `is_read`=0", [$userId]
            )->fetchColumn();
        } catch (Exception $e) { return 0; }
    }

    public static function markRead(int $id, int $userId): void {
        Database::query("UPDATE `notifications` SET `is_read`=1 WHERE `id`=? AND `user_id`=?", [$id, $userId]);
    }

    public static function markAllRead(int $userId): void {
        Database::query("UPDATE `notifications` SET `is_read`=1 WHERE `user_id`=?", [$userId]);
    }

    public static function delete(int $id, int $userId): void {
        Database::query("DELETE FROM `notifications` WHERE `id`=? AND `user_id`=?", [$id, $userId]);
    }

    public static function clearAll(int $userId): void {
        Database::query("DELETE FROM `notifications` WHERE `user_id`=?", [$userId]);
    }
}
?>

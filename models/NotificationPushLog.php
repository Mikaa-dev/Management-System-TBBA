<?php
/**
 * NotificationPushLog Model — TBBA ERP
 * Simpan log semua push notification yang dihantar.
 * Digunakan untuk audit trail dan "Notification Center" dalam app.
 */
require_once __DIR__ . '/../config/database.php';

class NotificationPushLog {

    /**
     * Rekod log push notification yang dihantar.
     *
     * @param int    $userId       ID penerima
     * @param string $title        Tajuk notification
     * @param string $body         Isi notification
     * @param string $link         URL destinasi bila diklik (optional)
     * @param string $type         Jenis event (leave_approved, expense_approved, etc.)
     * @param string $status       'sent', 'failed', atau 'partial'
     * @param int    $tokensSent   Bilangan token berjaya dihantar
     * @param int    $tokensFailed Bilangan token gagal/expired
     * @return int|false           ID log yang dibuat, atau false jika gagal
     */
    public static function record(
        int $userId,
        string $title,
        string $body,
        string $link = '',
        string $type = 'general',
        string $status = 'sent',
        int $tokensSent = 0,
        int $tokensFailed = 0
    ): int|false {
        try {
            Database::query(
                "INSERT INTO `push_notification_logs`
                    (`user_id`, `title`, `body`, `link`, `type`, `status`, `tokens_sent`, `tokens_failed`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$userId, $title, $body, $link ?: null, $type, $status, $tokensSent, $tokensFailed]
            );
            return (int) Database::lastInsertId();
        } catch (Exception $e) {
            error_log('[NotificationPushLog] record error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Dapatkan semua log push notification untuk seorang user (untuk Notification Center).
     *
     * @param int $userId
     * @param int $limit   Bilangan rekod maksimum
     * @return array
     */
    public static function getForUser(int $userId, int $limit = 50): array {
        try {
            return Database::query(
                "SELECT * FROM `push_notification_logs`
                 WHERE `user_id` = ?
                 ORDER BY `sent_at` DESC
                 LIMIT " . intval($limit),
                [$userId]
            )->fetchAll();
        } catch (Exception $e) {
            error_log('[NotificationPushLog] getForUser error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Dapatkan statistik push notification (untuk admin dashboard).
     *
     * @param int $days  Tempoh dalam hari (default: 30 hari)
     * @return array
     */
    public static function getStats(int $days = 30): array {
        try {
            return Database::query(
                "SELECT
                    COUNT(*) as total_sent,
                    SUM(`tokens_sent`) as tokens_delivered,
                    SUM(`tokens_failed`) as tokens_failed,
                    COUNT(DISTINCT `user_id`) as unique_users,
                    `type`,
                    COUNT(*) as count_by_type
                 FROM `push_notification_logs`
                 WHERE `sent_at` >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY `type`
                 ORDER BY count_by_type DESC",
                [$days]
            )->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
}
?>

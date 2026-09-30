<?php
/**
 * NotificationToken Model — TBBA ERP
 * Menguruskan FCM tokens untuk Web Push Notification.
 * Setiap user boleh ada multiple tokens (multi-device support).
 */
require_once __DIR__ . '/../config/database.php';

class NotificationToken {

    /**
     * Simpan atau kemaskini FCM token untuk user.
     * Menggunakan INSERT ... ON DUPLICATE KEY UPDATE supaya:
     * - Token baru → insert
     * - Token sama → kemaskini user_id & device_info (dalam kes token dikongsi)
     *
     * @param int    $userId     ID user yang log masuk
     * @param string $token      FCM registration token dari browser
     * @param string $deviceInfo Info browser/OS (optional, untuk debugging)
     * @return bool
     */
    public static function saveToken(int $userId, string $token, string $deviceInfo = ''): bool {
        if (empty($token) || empty($userId)) return false;

        try {
            Database::query(
                "INSERT INTO `notification_tokens`
                    (`user_id`, `fcm_token`, `device_info`, `platform`, `is_active`, `last_used_at`)
                VALUES
                    (?, ?, ?, 'web', 1, NOW())
                ON DUPLICATE KEY UPDATE
                    `user_id`      = VALUES(`user_id`),
                    `device_info`  = VALUES(`device_info`),
                    `is_active`    = 1,
                    `last_used_at` = NOW()",
                [$userId, $token, $deviceInfo]
            );
            return true;
        } catch (Exception $e) {
            error_log('[NotificationToken] saveToken error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Dapatkan semua FCM token aktif untuk seorang user.
     * Digunakan sebelum menghantar push notification.
     *
     * @param int $userId
     * @return array  Array of ['id' => ..., 'fcm_token' => ...]
     */
    public static function getActiveByUser(int $userId): array {
        try {
            return Database::query(
                "SELECT `id`, `fcm_token`, `device_info`
                 FROM `notification_tokens`
                 WHERE `user_id` = ? AND `is_active` = 1
                 ORDER BY `last_used_at` DESC",
                [$userId]
            )->fetchAll();
        } catch (Exception $e) {
            error_log('[NotificationToken] getActiveByUser error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Buang token yang expired atau invalid dari database.
     * Dipanggil secara automatik bila FCM API return error token.
     *
     * @param string $token  Token yang tidak sah
     * @return bool
     */
    public static function removeToken(string $token): bool {
        if (empty($token)) return false;
        try {
            Database::query(
                "DELETE FROM `notification_tokens` WHERE `fcm_token` = ?",
                [$token]
            );
            return true;
        } catch (Exception $e) {
            error_log('[NotificationToken] removeToken error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Buang semua token untuk user tertentu (e.g., bila user logout dari semua device).
     *
     * @param int $userId
     * @return bool
     */
    public static function removeAllForUser(int $userId): bool {
        try {
            Database::query(
                "DELETE FROM `notification_tokens` WHERE `user_id` = ?",
                [$userId]
            );
            return true;
        } catch (Exception $e) {
            error_log('[NotificationToken] removeAllForUser error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Buang token spesifik milik user (untuk unsubscribe satu device sahaja).
     *
     * @param int    $userId
     * @param string $token
     * @return bool
     */
    public static function removeTokenForUser(int $userId, string $token): bool {
        if (empty($token)) return false;
        try {
            Database::query(
                "DELETE FROM `notification_tokens` WHERE `user_id` = ? AND `fcm_token` = ?",
                [$userId, $token]
            );
            return true;
        } catch (Exception $e) {
            error_log('[NotificationToken] removeTokenForUser error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Semak jika user ada sekurang-kurangnya satu token aktif (ada subscribe push).
     *
     * @param int $userId
     * @return bool
     */
    public static function userHasTokens(int $userId): bool {
        try {
            $count = (int) Database::query(
                "SELECT COUNT(*) FROM `notification_tokens` WHERE `user_id` = ? AND `is_active` = 1",
                [$userId]
            )->fetchColumn();
            return $count > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Tandakan token sebagai tidak aktif (soft deactivate) tanpa delete.
     * Berguna jika nak track historical data.
     *
     * @param string $token
     * @return bool
     */
    public static function deactivateToken(string $token): bool {
        if (empty($token)) return false;
        try {
            Database::query(
                "UPDATE `notification_tokens` SET `is_active` = 0 WHERE `fcm_token` = ?",
                [$token]
            );
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>

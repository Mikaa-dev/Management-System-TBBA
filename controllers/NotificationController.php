<?php
/**
 * Notifications Controller — TBBA ERP Module 10
 * Dikemaskini dengan FCM Push Notification support.
 */
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/NotificationToken.php';
require_once __DIR__ . '/../models/NotificationPushLog.php';

class NotificationController {

    public static function index(): void {
        Auth::requireLogin();
        $pageTitle     = 'Notifications';
        $userId        = (int)Auth::id();
        $notifications = Notification::getForUser($userId, 100);
        $pushLogs      = NotificationPushLog::getForUser($userId, 50);
        include __DIR__ . '/../views/notifications/index.php';
    }

    /** AJAX: Get recent notifications and unread count for top navbar bell */
    public static function getDropdown(): void {
        Auth::requireLogin();
        $userId = (int)Auth::id();
        $unread = Notification::countUnread($userId);
        $items  = Notification::getForUser($userId, 10, true);
        Helper::json('success', 'OK', [
            'unread' => $unread,
            'items'  => $items
        ]);
    }

    /** AJAX: Mark single notification as read */
    public static function markRead(): void {
        Auth::requireLogin();
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');
        $id = (int)($_POST['id'] ?? 0);
        Notification::markRead($id, (int)Auth::id());
        Helper::json('success', 'Marked as read.');
    }

    /** AJAX: Mark all notifications as read */
    public static function markAllRead(): void {
        Auth::requireLogin();
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');
        Notification::markAllRead((int)Auth::id());
        Helper::json('success', 'All notifications marked as read.');
    }

    /** AJAX: Delete single notification */
    public static function delete(): void {
        Auth::requireLogin();
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');
        $id = (int)($_POST['id'] ?? 0);
        Notification::delete($id, (int)Auth::id());
        Helper::json('success', 'Notification deleted.');
    }

    /** AJAX: Clear all notifications */
    public static function clearAll(): void {
        Auth::requireLogin();
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');
        Notification::clearAll((int)Auth::id());
        Helper::json('success', 'All notifications cleared.');
    }

    /** AJAX: Mark dynamic notification receipt as read */
    public static function markReceiptRead(): void {
        Auth::requireLogin();
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');
        $type   = $_POST['type'] ?? '';
        $id     = (int)($_POST['id'] ?? 0);
        $userId = (int)Auth::id();

        if ($type && $id) {
            try {
                Database::query(
                    "INSERT IGNORE INTO `user_read_receipts` (`user_id`, `item_type`, `item_id`) VALUES (?, ?, ?)",
                    [$userId, $type, $id]
                );
            } catch (Exception $e) {}
        }
        Helper::json('success', 'Marked as read.');
    }

    // =========================================================================
    // ─── FCM PUSH NOTIFICATION ENDPOINTS (BAHARU) ─────────────────────────────
    // =========================================================================

    /**
     * AJAX: Simpan FCM token yang dihantar dari browser frontend.
     * Dipanggil selepas user grant notification permission dan SDK jana token.
     *
     * POST params:
     *   - csrf_token  : CSRF token
     *   - fcm_token   : FCM registration token dari Firebase SDK
     *   - device_info : (optional) info browser/OS
     */
    public static function saveFcmToken(): void {
        Auth::requireLogin();

        // Validate CSRF
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) {
            Helper::json('error', 'Invalid CSRF token.');
        }

        $fcmToken   = trim($_POST['fcm_token']   ?? '');
        $deviceInfo = trim($_POST['device_info'] ?? '');
        $userId     = (int)Auth::id();

        // Validate token
        if (empty($fcmToken)) {
            Helper::json('error', 'The FCM token cannot be empty.');
        }

        // Validate panjang token (FCM token biasanya 140-200+ chars)
        if (strlen($fcmToken) < 50 || strlen($fcmToken) > 512) {
            Helper::json('error', 'Invalid FCM token length.');
        }

        // Sanitize device info
        $deviceInfo = substr($deviceInfo, 0, 255);

        $saved = NotificationToken::saveToken($userId, $fcmToken, $deviceInfo);

        if ($saved) {
            Helper::json('success', 'Push notification token saved.', [
                'user_id' => $userId,
                'subscribed' => true
            ]);
        } else {
            Helper::json('error', 'Failed to save the token. Please try again.');
        }
    }

    /**
     * AJAX: Buang FCM token dari database.
     * Dipanggil bila user manually disable notification atau logout.
     *
     * POST params:
     *   - csrf_token : CSRF token
     *   - fcm_token  : Token yang nak dibuang (optional — jika kosong, buang semua)
     */
    public static function removeFcmToken(): void {
        Auth::requireLogin();

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) {
            Helper::json('error', 'Invalid CSRF token.');
        }

        $fcmToken = trim($_POST['fcm_token'] ?? '');
        $userId   = (int)Auth::id();

        if (!empty($fcmToken)) {
            // Buang token spesifik sahaja (unsubscribe dari device ini)
            NotificationToken::removeTokenForUser($userId, $fcmToken);
            Helper::json('success', 'The token was removed from this device.', ['subscribed' => false]);
        } else {
            // Buang semua token (unsubscribe dari semua device)
            NotificationToken::removeAllForUser($userId);
            Helper::json('success', 'All push notification tokens were removed.', ['subscribed' => false]);
        }
    }

    /**
     * AJAX: Semak status subscription push notification user semasa.
     * Frontend boleh guna ini untuk update UI toggle.
     */
    public static function checkPushStatus(): void {
        Auth::requireLogin();
        $userId      = (int)Auth::id();
        $hasTokens   = NotificationToken::userHasTokens($userId);
        Helper::json('success', 'OK', [
            'subscribed' => $hasTokens,
            'user_id'    => $userId,
        ]);
    }

    /**
     * AJAX: [ADMIN] Hantar test push notification kepada diri sendiri.
     * Berguna untuk test sama ada setup FCM berfungsi.
     */
    public static function sendTestPush(): void {
        Auth::requireLogin();

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) {
            Helper::json('error', 'Invalid CSRF token.');
        }

        // Load NotificationService
        require_once __DIR__ . '/../core/NotificationService.php';

        $userId = (int)Auth::id();
        $targetToken = trim((string)($_POST['fcm_token'] ?? ''));
        if ($targetToken === '') {
            Helper::json('error', 'This device has not registered a push token. Enable notifications and retry.');
        }
        if (!NotificationToken::userHasTokens($userId)) {
            Helper::json('error', 'No active push subscription was found for this device. Click Enable Notifications first and allow browser notifications.');
        }
        if (!is_file(FIREBASE_CREDENTIALS_PATH)) {
            Helper::json('error', 'Firebase service credentials could not be loaded. Contact the system administrator.');
        }
        $result = NotificationService::sendPush(
            $userId,
            '🔔 Test Push Notification',
            'Push notifications are working correctly. Your TBBA ERP system is ready.',
            'index.php?page=notifications',
            'test',
            $targetToken
        );

        if ($result) {
            Helper::json('success', 'Test push notification sent successfully.');
        } else {
            Helper::json('error', NotificationService::getLastPushError() ?: 'The test notification could not be sent. Please try again.');
        }
    }
}
?>

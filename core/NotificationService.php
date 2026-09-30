<?php
/**
 * NotificationService Core — TBBA ERP
 * Centralized service untuk menghantar:
 *   1. In-App Notification (simpan dalam DB, papar dalam navbar bell)
 *   2. FCM Web Push Notification (real-time browser push)
 *
 * Cara guna dari mana-mana controller:
 *   NotificationService::notify($userId, 'leave', 'Leave Approved', 'Your request...', 'fa-check', '#10B981', '/leave');
 *   NotificationService::sendPush($userId, 'Leave Approved', 'Your request has been approved', '/leave', 'leave_approved');
 *   // Atau gabungan kedua-dua sekaligus:
 *   NotificationService::notifyAndPush($userId, 'leave', 'Leave Approved', 'Your request has been approved', 'fa-check', '#10B981', '/leave', 'leave_approved');
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/NotificationToken.php';
require_once __DIR__ . '/../models/NotificationPushLog.php';

class NotificationService {
    private static string $lastPushError = '';

    public static function getLastPushError(): string {
        return self::$lastPushError;
    }

    // =========================================================================
    // ─── IN-APP NOTIFICATIONS (sedia ada, tidak diubah) ──────────────────────
    // =========================================================================

    /**
     * Hantar in-app notification kepada seorang user (simpan dalam DB).
     */
    public static function notify(int $userId, string $type, string $title, string $body, string $icon = 'fa-bell', string $color = '#2563EB', string $link = '', array $extraData = []): void {
        Notification::send($userId, $type, $title, $body, $icon, $color, $link);
    }

    /**
     * Hantar in-app notification kepada senarai user.
     */
    public static function notifyUsers(array $userIds, string $type, string $title, string $body, string $icon = 'fa-bell', string $color = '#2563EB', string $link = '', array $extraData = []): void {
        foreach ($userIds as $uid) {
            self::notify((int)$uid, $type, $title, $body, $icon, $color, $link, $extraData);
        }
    }

    /**
     * Hantar in-app notification kepada semua user dengan role tertentu.
     */
    public static function notifyRole(string $role, string $type, string $title, string $body, string $icon = 'fa-bell', string $color = '#2563EB', string $link = '', array $extraData = []): void {
        try {
            $users = Database::query("SELECT `id` FROM `users` WHERE `role` = ? AND `status` = 'active'", [$role])->fetchAll();
            foreach ($users as $u) {
                self::notify((int)$u['id'], $type, $title, $body, $icon, $color, $link, $extraData);
            }
        } catch (Exception $e) {
            error_log("[NotificationService] notifyRole error: " . $e->getMessage());
        }
    }

    /**
     * Hantar in-app notification kepada semua user aktif.
     */
    public static function notifyAll(string $type, string $title, string $body, string $icon = 'fa-bell', string $color = '#2563EB', string $link = '', array $extraData = []): void {
        try {
            $users = Database::query("SELECT `id` FROM `users` WHERE `status` = 'active'")->fetchAll();
            foreach ($users as $u) {
                self::notify((int)$u['id'], $type, $title, $body, $icon, $color, $link, $extraData);
            }
        } catch (Exception $e) {
            error_log("[NotificationService] notifyAll error: " . $e->getMessage());
        }
    }

    // =========================================================================
    // ─── FCM PUSH NOTIFICATIONS (BAHARU) ─────────────────────────────────────
    // =========================================================================

    /**
     * [UTAMA] Hantar FCM push notification kepada seorang user.
     * Akan cuba semua token aktif user tersebut (multi-device).
     * Token yang expired/invalid akan dibuang secara automatik.
     *
     * @param int    $userId   ID user penerima
     * @param string $title    Tajuk notification (max ~50 char)
     * @param string $body     Isi teks notification (max ~100 char)
     * @param string $link     URL untuk navigate bila notification diklik
     * @param string $type     Jenis event (untuk log & analytics)
     * @return bool            true jika sekurang-kurangnya satu token berjaya
     */
    public static function sendPush(int $userId, string $title, string $body, string $link = '', string $type = 'general', ?string $targetToken = null): bool {
        self::$lastPushError = '';
        // Dapatkan semua token aktif user ini
        $tokens = NotificationToken::getActiveByUser($userId);
        if ($targetToken !== null) {
            $tokens = array_values(array_filter($tokens, static fn(array $row): bool => hash_equals($row['fcm_token'], $targetToken)));
        }

        if (empty($tokens)) {
            self::$lastPushError = 'No active subscription was found for this device. Enable notifications again.';
            return false;
        }

        // Dapatkan OAuth2 access token untuk FCM HTTP v1 API
        $accessToken = self::getGoogleAccessToken();
        if (!$accessToken) {
            self::$lastPushError = 'The server could not authenticate with Firebase. Check the service account and the server connection to Google.';
            error_log("[NotificationService] sendPush: Failed to obtain a Google OAuth2 access token.");
            NotificationPushLog::record($userId, $title, $body, $link, $type, 'failed', 0, count($tokens));
            return false;
        }

        $tokensSent   = 0;
        $tokensFailed = 0;

        foreach ($tokens as $tokenRow) {
            $fcmToken = $tokenRow['fcm_token'];
            $result   = self::sendToToken($accessToken, $fcmToken, $title, $body, $link, $type);

            if ($result === true) {
                $tokensSent++;
            } elseif ($result === 'invalid_token') {
                self::$lastPushError = 'This device subscription has expired. Enable notifications again and retry.';
                // Token expired atau tidak sah — buang dari DB
                NotificationToken::removeToken($fcmToken);
                $tokensFailed++;
                error_log("[NotificationService] Removed expired/invalid FCM token for user #{$userId}");
            } else {
                // Error lain (network, dll)
                $tokensFailed++;
            }
        }

        // Log hasil pengiriman
        $status = ($tokensSent > 0 && $tokensFailed > 0) ? 'partial'
                : ($tokensSent > 0 ? 'sent' : 'failed');

        NotificationPushLog::record($userId, $title, $body, $link, $type, $status, $tokensSent, $tokensFailed);

        return $tokensSent > 0;
    }

    /**
     * [GABUNGAN] Hantar in-app notification DAN push notification sekaligus.
     * Ini cara paling mudah untuk trigger dari controller.
     *
     * Contoh penggunaan:
     *   NotificationService::notifyAndPush(
     *       $userId,
     *       'leave', 'Leave Approved', 'Your request has been approved by your manager.',
     *       'fa-check-circle', '#10B981',
     *       'index.php?page=leave',
     *       'leave_approved'
     *   );
     */
    public static function notifyAndPush(
        int    $userId,
        string $inAppType,
        string $title,
        string $body,
        string $icon  = 'fa-bell',
        string $color = '#2563EB',
        string $link  = '',
        string $pushType = 'general'
    ): void {
        // 1. Simpan in-app notification dalam DB (sedia ada)
        self::notify($userId, $inAppType, $title, $body, $icon, $color, $link);

        // 2. Hantar push notification ke browser/device
        self::sendPush($userId, $title, $body, $link, $pushType);
    }

    /**
     * Hantar push notification kepada senarai user.
     * (Bulk send, e.g., untuk announcement baru)
     *
     * @param array  $userIds  Array of user IDs
     * @param string $title
     * @param string $body
     * @param string $link
     * @param string $type
     * @return array ['sent' => N, 'failed' => M]
     */
    public static function sendPushToUsers(array $userIds, string $title, string $body, string $link = '', string $type = 'general'): array {
        $sent = 0; $failed = 0;
        foreach ($userIds as $uid) {
            if (self::sendPush((int)$uid, $title, $body, $link, $type)) {
                $sent++;
            } else {
                $failed++;
            }
        }
        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Hantar push notification kepada semua user dengan role tertentu.
     */
    public static function sendPushToRole(string $role, string $title, string $body, string $link = '', string $type = 'general'): array {
        try {
            $users = Database::query("SELECT `id` FROM `users` WHERE `role` = ? AND `status` = 'active'", [$role])->fetchAll();
            $userIds = array_column($users, 'id');
            return self::sendPushToUsers($userIds, $title, $body, $link, $type);
        } catch (Exception $e) {
            error_log("[NotificationService] sendPushToRole error: " . $e->getMessage());
            return ['sent' => 0, 'failed' => 0];
        }
    }

    // =========================================================================
    // ─── PRIVATE HELPERS — FCM HTTP v1 API ───────────────────────────────────
    // =========================================================================

    /**
     * Hantar notification ke satu FCM token menggunakan FCM HTTP v1 API.
     *
     * @param string $accessToken  OAuth2 access token dari Google
     * @param string $fcmToken     FCM registration token (device)
     * @param string $title
     * @param string $body
     * @param string $link         URL untuk navigate bila klik notification
     * @param string $type         Jenis notification (untuk data payload)
     * @return true|string         true = berjaya, 'invalid_token' = token tak sah, 'error' = error lain
     */
    private static function sendToToken(
        string $accessToken,
        string $fcmToken,
        string $title,
        string $body,
        string $link,
        string $type
    ): bool|string {
        // Bina URL full untuk link (pastikan absolute URL)
        $appUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
        if ($appUrl === '') {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $appUrl = $protocol . '://' . $host;
        }
        $payload = self::buildPushPayload($fcmToken, $title, $body, $link, $type, $appUrl);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => FCM_API_ENDPOINT,
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($response === false || $curlError) {
            self::$lastPushError = 'The server could not connect to Firebase. Check the hosting outbound HTTPS connection.';
            error_log('[NotificationService] cURL error: ' . $curlError);
            return 'error';
        }
        if ($httpCode === 200) return true;

        $responseData = json_decode($response, true);
        $responseData = is_array($responseData) ? $responseData : [];
        if (self::isUnregisteredToken($responseData)) return 'invalid_token';

        $status = $responseData['error']['status'] ?? '';
        self::$lastPushError = match ($status) {
            'INVALID_ARGUMENT' => 'Firebase rejected the notification request. The device subscription has been preserved; check the server push log.',
            'PERMISSION_DENIED', 'UNAUTHENTICATED' => 'Firebase rejected the server credentials or project permissions. Check the service account and Cloud Messaging API settings.',
            'RESOURCE_EXHAUSTED' => 'Firebase push quota was exceeded. Please try again later.',
            default => 'Firebase could not accept the notification (HTTP ' . $httpCode . '). Check the server push log.',
        };
        error_log('[NotificationService] FCM API error [' . $httpCode . ']: ' . $response);
        return 'error';
    }

    /** Only a confirmed expired registration may be removed automatically. */
    private static function isUnregisteredToken(array $responseData): bool {
        foreach (($responseData['error']['details'] ?? []) as $detail) {
            if (($detail['@type'] ?? '') === 'type.googleapis.com/google.firebase.fcm.v1.FcmError'
                && ($detail['errorCode'] ?? '') === 'UNREGISTERED') {
                return true;
            }
        }
        // INVALID_ARGUMENT can describe an invalid URL, oversized message or
        // other payload problem. It must never delete a valid device token.
        return false;
    }

    private static function buildPushPayload(string $fcmToken, string $title, string $body, string $link, string $type, string $appUrl): array {
        $fullLink = $link;
        if (!empty($link) && !str_starts_with($link, 'http')) {
            $fullLink = $appUrl . '/' . ltrim($link, '/');
        }
        $appIcon = $appUrl . '/assets/images/app_icon_v2.png';

        // FCM Message payload
        $payload = [
            'message' => [
                'token' => $fcmToken,
                // Notification payload — papar oleh OS/browser secara automatik
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                // Web-specific notification options
                'webpush' => [
                    'notification' => [
                        'title'              => $title,
                        'body'               => $body,
                        'icon'               => $appIcon,
                        'badge'              => $appIcon,
                        'requireInteraction' => false,  // Auto-dismiss selepas beberapa saat
                        'tag'                => $type,  // Groupkan notification sejenis
                        'renotify'           => true,   // Tunjuk walaupun ada tag sama
                    ],
                ],
                // Data payload — boleh diakses oleh service worker (untuk custom handling)
                'data' => [
                    'type'  => $type,
                    'link'  => $fullLink,
                    'time'  => date('Y-m-d H:i:s'),
                ],
            ]
        ];

        // FCM only accepts absolute HTTPS click URLs. HTTP localhost and an
        // empty/default link must not make the entire push request invalid.
        $clickLink = $fullLink !== '' ? $fullLink : $appUrl . '/index.php?page=notifications';
        if (filter_var($clickLink, FILTER_VALIDATE_URL)
            && strtolower((string)parse_url($clickLink, PHP_URL_SCHEME)) === 'https') {
            $payload['message']['webpush']['fcm_options'] = ['link' => $clickLink];
        }
        return $payload;
    }

    /**
     * Dapatkan Google OAuth2 Access Token menggunakan Service Account JSON.
     * Token ini diperlukan untuk FCM HTTP v1 API.
     *
     * Token dicache dalam session/memory untuk 50 minit (token valid 1 jam).
     *
     * @return string|null  Access token, atau null jika gagal
     */
    private static function getGoogleAccessToken(): ?string {
        // Semak cache session dulu (elak request berulang dalam satu session)
        if (session_status() === PHP_SESSION_NONE) session_start();

        $cached      = $_SESSION['_fcm_access_token']      ?? null;
        $cachedExpiry= $_SESSION['_fcm_token_expiry']       ?? 0;

        // Guna token yang cached jika masih valid (ada lebih 5 minit)
        if ($cached && time() < ($cachedExpiry - 300)) {
            return $cached;
        }

        // Baca Service Account JSON
        $credPath = FIREBASE_CREDENTIALS_PATH;
        if (!file_exists($credPath)) {
            error_log("[NotificationService] Service account file not found: {$credPath}");
            return null;
        }

        $credentials = json_decode(file_get_contents($credPath), true);
        if (!$credentials || empty($credentials['private_key']) || empty($credentials['client_email'])) {
            error_log("[NotificationService] The service account JSON is invalid or corrupted.");
            return null;
        }

        // Bina JWT (JSON Web Token) untuk OAuth2 request
        $now = time();
        $jwtHeader  = self::base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $jwtClaims  = self::base64UrlEncode(json_encode([
            'iss'   => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => GOOGLE_TOKEN_ENDPOINT,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));

        $jwtUnsigned = $jwtHeader . '.' . $jwtClaims;

        // Sign JWT dengan private key dari service account
        $privateKey = openssl_pkey_get_private($credentials['private_key']);
        if (!$privateKey) {
            error_log("[NotificationService] Failed to load the private key from the service account.");
            return null;
        }

        $signature = '';
        if (!openssl_sign($jwtUnsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            error_log("[NotificationService] Failed to sign the JWT.");
            return null;
        }

        $jwt = $jwtUnsigned . '.' . self::base64UrlEncode($signature);

        // Hantar JWT ke Google untuk dapatkan access token
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => GOOGLE_TOKEN_ENDPOINT,
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode !== 200) {
            error_log("[NotificationService] Google OAuth2 error [{$httpCode}]: " . $response);
            return null;
        }

        $tokenData = json_decode($response, true);
        $accessToken = $tokenData['access_token'] ?? null;

        if ($accessToken) {
            // Cache dalam session untuk 50 minit
            $_SESSION['_fcm_access_token'] = $accessToken;
            $_SESSION['_fcm_token_expiry'] = $now + 3600;
        }

        return $accessToken;
    }

    /**
     * Encode data kepada URL-safe base64 (tanpa padding =).
     * Diperlukan untuk format JWT.
     */
    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
?>

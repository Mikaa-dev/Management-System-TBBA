<?php
/**
 * Firebase Configuration — TBBA ERP
 * Baca credentials dari .env file menggunakan vlucas/phpdotenv
 *
 * SETUP:
 * 1. Salin .env.example kepada .env
 * 2. Isi semua nilai dari Firebase Console
 * 3. Download service-account.json dan letak dalam config/
 */

// Load Composer autoload jika belum diload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Load .env file
if (class_exists('Dotenv\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    try {
        $dotenv->load();
        $dotenv->required([
            'FCM_API_KEY',
            'FCM_PROJECT_ID',
            'FCM_MESSAGING_SENDER_ID',
            'FCM_APP_ID',
            'FCM_VAPID_KEY',
        ])->notEmpty();
    } catch (Exception $e) {
        // .env belum disetup — log warning tapi jangan crash app
        error_log('[Firebase Config] .env file missing or incomplete: ' . $e->getMessage());
    }
}

/**
 * Firebase Web App Configuration (untuk JavaScript/Frontend)
 * Ini selamat untuk diexpose ke frontend (public keys)
 */
define('FCM_API_KEY',            $_ENV['FCM_API_KEY']            ?? '');
define('FCM_AUTH_DOMAIN',        $_ENV['FCM_AUTH_DOMAIN']        ?? '');
define('FCM_PROJECT_ID',         $_ENV['FCM_PROJECT_ID']         ?? '');
define('FCM_STORAGE_BUCKET',     $_ENV['FCM_STORAGE_BUCKET']     ?? '');
define('FCM_MESSAGING_SENDER_ID',$_ENV['FCM_MESSAGING_SENDER_ID']?? '');
define('FCM_APP_ID',             $_ENV['FCM_APP_ID']             ?? '');

/**
 * VAPID Key — untuk Web Push dalam Firebase SDK
 * Boleh diexpose ke frontend (ini public key)
 */
define('FCM_VAPID_KEY',          $_ENV['FCM_VAPID_KEY']          ?? '');

/**
 * Service Account JSON path — RAHSIA, jangan expose ke frontend!
 * Digunakan oleh backend PHP untuk hantar push notification via FCM HTTP v1 API
 */
$firebaseCredentialsValue = trim((string)($_ENV['FIREBASE_CREDENTIALS_PATH'] ?? 'config/service-account.json'));
if ($firebaseCredentialsValue === '') {
    $firebaseCredentialsValue = 'config/service-account.json';
}

// Accept an absolute path, a path relative to the project root (recommended),
// or the legacy filename relative to this config directory.
$isAbsoluteCredentialsPath = str_starts_with($firebaseCredentialsValue, '/')
    || preg_match('/^[A-Za-z]:[\\\\\/]/', $firebaseCredentialsValue) === 1;
if ($isAbsoluteCredentialsPath) {
    $firebaseCredentialsPath = $firebaseCredentialsValue;
} else {
    $projectRelativePath = dirname(__DIR__) . '/' . ltrim($firebaseCredentialsValue, '/\\');
    $configRelativePath = __DIR__ . '/' . ltrim($firebaseCredentialsValue, '/\\');
    $firebaseCredentialsPath = file_exists($projectRelativePath) ? $projectRelativePath : $configRelativePath;
}
define('FIREBASE_CREDENTIALS_PATH', $firebaseCredentialsPath);

/**
 * FCM HTTP v1 API endpoint
 */
define('FCM_API_ENDPOINT', 'https://fcm.googleapis.com/v1/projects/' . FCM_PROJECT_ID . '/messages:send');

/**
 * Google OAuth2 Token endpoint — untuk dapatkan access token dari service account
 */
define('GOOGLE_TOKEN_ENDPOINT', 'https://oauth2.googleapis.com/token');

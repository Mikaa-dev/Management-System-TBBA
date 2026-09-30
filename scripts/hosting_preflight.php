<?php
/**
 * DirectAdmin production preflight for TBBA ERP.
 *
 * Usage:
 *   php scripts/hosting_preflight.php
 *   php scripts/hosting_preflight.php --prepare
 *
 * --prepare creates the writable application directories with mode 0750.
 * The script never prints passwords, API keys, tokens, or credential contents.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not Found');
}

$root = dirname(__DIR__);
$prepare = in_array('--prepare', $argv, true);
$passes = [];
$warnings = [];
$failures = [];

$pass = static function (string $message) use (&$passes): void {
    $passes[] = $message;
    echo "[PASS] {$message}\n";
};
$warn = static function (string $message) use (&$warnings): void {
    $warnings[] = $message;
    echo "[WARN] {$message}\n";
};
$fail = static function (string $message) use (&$failures): void {
    $failures[] = $message;
    echo "[FAIL] {$message}\n";
};
$isTruthy = static fn(string $value): bool => in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
$bytes = static function (string $value): int {
    $value = trim($value);
    if ($value === '' || $value === '-1') return PHP_INT_MAX;
    $number = (float)$value;
    return (int)round($number * match (strtolower(substr($value, -1))) {
        'g' => 1024 ** 3,
        'm' => 1024 ** 2,
        'k' => 1024,
        default => 1,
    });
};
$resolvePath = static function (string $value) use ($root): string {
    $value = trim($value);
    if ($value === '') return '';
    $absolute = str_starts_with($value, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $value) === 1;
    return $absolute ? $value : $root . '/' . ltrim($value, '/\\');
};

echo "TBBA ERP DirectAdmin preflight\n";
echo "Application root: {$root}\n";
echo "PHP SAPI: " . PHP_SAPI . "\n\n";

if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
    $pass('PHP ' . PHP_VERSION . ' is supported (minimum 8.1).');
} else {
    $fail('PHP 8.1 or newer is required; select PHP 8.3 in DirectAdmin.');
}

$requiredExtensions = ['curl', 'fileinfo', 'gd', 'json', 'mbstring', 'openssl', 'pdo', 'pdo_mysql', 'session', 'zip'];
foreach ($requiredExtensions as $extension) {
    extension_loaded($extension)
        ? $pass("PHP extension {$extension} is loaded.")
        : $fail("PHP extension {$extension} is missing.");
}

$autoload = $root . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
    $pass('Composer dependencies are installed.');
} else {
    $fail('vendor/autoload.php is missing; run composer install --no-dev --optimize-autoloader.');
}

require_once $root . '/config/app.php';

$environment = tbba_env('APP_ENV');
$appUrl = rtrim(tbba_env('APP_URL'), '/');
$debug = tbba_env('APP_DEBUG', 'false');
if ($environment === 'production') $pass('APP_ENV is production.');
else $fail('APP_ENV must be production on the live server.');

if (filter_var($appUrl, FILTER_VALIDATE_URL) && parse_url($appUrl, PHP_URL_SCHEME) === 'https') {
    $pass('APP_URL is a valid HTTPS URL.');
} else {
    $fail('APP_URL must contain the final HTTPS application URL.');
}

if (!$isTruthy($debug)) $pass('APP_DEBUG is disabled.');
else $fail('APP_DEBUG must be false in production.');

$dbHost = tbba_env('DB_HOST');
$dbPort = tbba_env('DB_PORT', '3306');
$dbName = tbba_env('DB_NAME');
$dbUser = tbba_env('DB_USER');
$dbPass = tbba_env('DB_PASS');
foreach (['DB_HOST' => $dbHost, 'DB_NAME' => $dbName, 'DB_USER' => $dbUser, 'DB_PASS' => $dbPass] as $key => $value) {
    $value !== '' ? $pass("{$key} is configured.") : $fail("{$key} is empty.");
}
if (strcasecmp($dbUser, 'root') === 0) $fail('DB_USER must be a dedicated DirectAdmin database user, not root.');
elseif ($dbUser !== '') $pass('DB_USER is not root.');
if (!ctype_digit($dbPort) || (int)$dbPort < 1 || (int)$dbPort > 65535) $fail('DB_PORT is invalid.');

$encryptionKey = tbba_env('SECURITY_ENCRYPTION_KEY');
if (strlen($encryptionKey) === 64 && ctype_xdigit($encryptionKey)) {
    $pass('SECURITY_ENCRYPTION_KEY has the expected 64-character hexadecimal format.');
} else {
    $fail('SECURITY_ENCRYPTION_KEY must be generated with: openssl rand -hex 32');
}

$integrationKeys = [
    'Google sign-in' => ['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET'],
    'SMTP security email' => ['MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS'],
    'Firebase web push' => ['FCM_API_KEY', 'FCM_PROJECT_ID', 'FCM_MESSAGING_SENDER_ID', 'FCM_APP_ID', 'FCM_VAPID_KEY'],
    'Google Drive gallery' => ['GDRIVE_MASTER_FOLDER_ID'],
];
foreach ($integrationKeys as $feature => $keys) {
    $missing = array_values(array_filter($keys, static fn(string $key): bool => trim(tbba_env($key)) === ''));
    $missing
        ? $fail($feature . ' is incomplete: ' . implode(', ', $missing) . '.')
        : $pass($feature . ' environment values are configured.');
}

$credentialVariables = [
    'FIREBASE_CREDENTIALS_PATH' => tbba_env('FIREBASE_CREDENTIALS_PATH'),
    'GOOGLE_DRIVE_CREDENTIALS_PATH' => tbba_env('GOOGLE_DRIVE_CREDENTIALS_PATH'),
];
foreach ($credentialVariables as $key => $configuredPath) {
    $path = $resolvePath($configuredPath);
    if ($path === '' || !is_readable($path)) {
        $fail("{$key} does not point to a readable file.");
        continue;
    }
    $pass("{$key} points to a readable file.");
    $real = realpath($path);
    if ($environment === 'production' && $real !== false && str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
        $fail("{$key} must be stored outside public_html in production.");
    }
}

$writableDirectories = [
    'storage/backups', 'storage/logs', 'storage/logs/ddos_ratelimit',
    'storage/logs/login_throttle', 'storage/logs/inquiry_throttle',
    'storage/private/documents', 'storage/private/expenses', 'storage/private/finance',
    'storage/private/leaves', 'storage/private/letters', 'storage/private/signatures',
    'storage/private/signed_pdfs', 'storage/private/tender_pricing', 'storage/private/tenders',
    'storage/quarantine', 'uploads/avatars',
];
foreach ($writableDirectories as $relative) {
    $directory = $root . '/' . $relative;
    if ($prepare && !is_dir($directory)) {
        @mkdir($directory, 0750, true);
    }
    if (is_dir($directory) && is_writable($directory)) {
        $pass("{$relative} is writable.");
    } else {
        $fail("{$relative} must exist and be writable by PHP-FPM.");
    }
}

if (is_file($root . '/.htaccess')) $pass('Root .htaccess is present.');
else $fail('Root .htaccess is missing.');
if (is_file($root . '/storage/private/.htaccess')) $pass('Private storage deny rule is present.');
else $fail('storage/private/.htaccess is missing.');
if (is_file($root . '/.user.ini')) $pass('DirectAdmin PHP-FPM .user.ini is present.');
else $warn('.user.ini is missing; configure upload and error settings in DirectAdmin.');

$declaredIni = is_file($root . '/.user.ini') ? parse_ini_file($root . '/.user.ini') : [];
$uploadLimit = $bytes((string)($declaredIni['upload_max_filesize'] ?? ini_get('upload_max_filesize')));
$postLimit = $bytes((string)($declaredIni['post_max_size'] ?? ini_get('post_max_size')));
if ($uploadLimit >= 25 * 1024 * 1024 && $postLimit > $uploadLimit) {
    $pass('Declared upload limits support the largest 25 MB application upload.');
} else {
    $fail('Set upload_max_filesize to at least 25M and post_max_size above that value.');
}

$requiredTables = [
    'announcements', 'approvals', 'attendance', 'audit_logs', 'branches', 'company_documents',
    'departments', 'events', 'expense_claims', 'expense_items', 'finance_bank_transactions',
    'finance_budgets', 'finance_cost_centers', 'finance_payments', 'finance_records',
    'finance_recurring_rules', 'finance_settings', 'inquiries', 'leave_requests', 'leave_types',
    'letters', 'login_history', 'logistics_records', 'notification_tokens', 'notifications',
    'password_reset_tokens', 'permissions', 'positions', 'project_members', 'project_tasks',
    'projects', 'purchase_items', 'purchase_requests', 'push_notification_logs', 'role_permissions',
    'roles', 'security_events', 'security_settings', 'signatures', 'tender_opportunities',
    'tender_opportunity_participants', 'tenders', 'trusted_devices', 'user_permission_overrides',
    'user_read_receipts', 'user_sessions', 'users',
];

if ($dbHost !== '' && $dbName !== '' && $dbUser !== '' && $dbPass !== '') {
    try {
        $pdo = new PDO(
            "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
            $dbUser,
            $dbPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
        );
        $pass('Database connection succeeded.');
        $stmt = $pdo->query('SHOW TABLES');
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $missingTables = array_values(array_diff($requiredTables, $tables));
        if ($missingTables) $fail('Database schema is incomplete: ' . implode(', ', $missingTables) . '.');
        else $pass('All ' . count($requiredTables) . ' required database tables exist.');
    } catch (Throwable $exception) {
        $fail('Database connection/schema check failed: ' . $exception->getMessage());
    }
} else {
    $warn('Database schema check skipped until all DB values are filled.');
}

echo "\nSummary: " . count($passes) . ' passed, ' . count($warnings) . ' warning(s), ' . count($failures) . " failed.\n";
exit($failures ? 1 : 0);


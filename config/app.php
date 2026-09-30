<?php
/** Shared environment-backed application configuration. */
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

if (class_exists('Dotenv\Dotenv') && empty($_ENV['TBBA_ENV_LOADED'])) {
    try {
        Dotenv\Dotenv::createImmutable(__DIR__ . '/../')->safeLoad();
        $_ENV['TBBA_ENV_LOADED'] = '1';
    } catch (Throwable $e) {
        $_ENV['TBBA_ENV_ERROR'] = '1';
        error_log('[App Config] Unable to load .env: ' . $e->getMessage());
    }
}

if (!function_exists('tbba_env')) {
    /** Read a value loaded by phpdotenv, with server/process fallbacks. */
    function tbba_env(string $key, string $default = ''): string
    {
        if (array_key_exists($key, $_ENV)) {
            return (string) $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER)) {
            return (string) $_SERVER[$key];
        }
        $value = getenv($key);
        return $value === false ? $default : (string) $value;
    }
}

define('APP_ENV', tbba_env('APP_ENV', 'production'));
define('APP_URL', rtrim(tbba_env('APP_URL'), '/'));
define('GOOGLE_OAUTH_CLIENT_ID', tbba_env('GOOGLE_CLIENT_ID'));
define('GOOGLE_OAUTH_CLIENT_SECRET', tbba_env('GOOGLE_CLIENT_SECRET'));
define('GOOGLE_OAUTH_REDIRECT_URI', tbba_env('GOOGLE_REDIRECT_URI'));
define('SECURITY_MAIL_FROM', tbba_env('SECURITY_MAIL_FROM', 'no-reply@tbba.local'));

if (!function_exists('tbba_is_https')) {
    function tbba_is_https(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
        return APP_URL !== '' && strtolower((string)parse_url(APP_URL, PHP_URL_SCHEME)) === 'https';
    }
}

if (APP_ENV === 'production' && (APP_URL === '' || parse_url(APP_URL, PHP_URL_SCHEME) !== 'https')) {
    error_log('[App Config] Production requires an HTTPS APP_URL.');
    http_response_code(503);
    exit('Application URL configuration is not ready for production.');
}

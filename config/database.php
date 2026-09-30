<?php
/**
 * Fail Konfigurasi Pangkalan Data MySQL (PDO Wrapper)
 * Syarikat: The Bridge Business Alliance (TBBA)
 */

// Tetapkan Zon Masa Rasmi Malaysia (+08:00 Kuala Lumpur)
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once __DIR__ . '/app.php';

define('DB_HOST', tbba_env('DB_HOST', '127.0.0.1'));
define('DB_USER', tbba_env('DB_USER', 'root'));
define('DB_PASS', tbba_env('DB_PASS', ''));
define('DB_NAME', tbba_env('DB_NAME', 'tbba_erp'));
define('DB_PORT', tbba_env('DB_PORT', '3306'));

if (APP_ENV === 'production' && (DB_USER === 'root' || DB_PASS === '')) {
    error_log('[Database] Refusing insecure production database credentials.');
    http_response_code(503);
    exit('Database configuration is not ready for production.');
}

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $this->pdo->exec("SET time_zone = '+08:00'");
        } catch (PDOException $e) {
            // Only the installer may fall back to a server-level connection, and
            // only when MySQL explicitly reports that the database is missing.
            $driverCode = (int)($e->errorInfo[1] ?? 0);
            if ($driverCode !== 1049 || PHP_SAPI !== 'cli') {
                error_log('[Database] Connection failed: ' . $e->getMessage());
                http_response_code(503);
                die(json_encode(['status' => 'error', 'message' => 'The database service is temporarily unavailable.']));
            }
            try {
                $dsnNoDb = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
                $this->pdo = new PDO($dsnNoDb, DB_USER, DB_PASS, $options);
            } catch (PDOException $ex) {
                error_log('[Database] Connection failed: ' . $ex->getMessage());
                http_response_code(503);
                die(json_encode([
                    'status' => 'error',
                    'message' => 'The database service is temporarily unavailable.'
                ]));
            }
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->pdo;
    }

    public static function connect() {
        return self::getInstance();
    }

    // Fungsi bantuan untuk query terus
    public static function query($sql, $params = []) {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    // Fungsi untuk mendapatkan ID terakhir yang dimasukkan
    public static function lastInsertId() {
        return self::getInstance()->lastInsertId();
    }
}
?>

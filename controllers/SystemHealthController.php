<?php
/**
 * Controller Pemantauan Kesihatan Sistem & Auto-Backup
 * Syarikat: The Bridge Business Alliance (TBBA)
 * Nota: Hanya Admin / CEO yang dibenarkan mengakses modul ini.
 */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../core/SecurityFirewall.php';
require_once __DIR__ . '/../models/SystemHealth.php';

class SystemHealthController {

    // Paparkan halaman dashboard kesihatan sistem
    public static function index() {
        Auth::requireLogin();
        
        // Hanya admin yang boleh akses modul ini mengikut spesifikasi
        if (!Auth::hasPermission('system', 'view')) {
            Helper::redirect('index.php?page=dashboard&error=unauthorized');
            return;
        }

        $user = Auth::user();
        
        // Dapatkan data analitik secara masa nyata (real-time)
        $diskUsage  = SystemHealth::getDiskUsage();
        $mysqlPerf  = SystemHealth::getMySqlPerformance();
        $serverLoad = SystemHealth::getServerLoad();
        $backups    = SystemHealth::getBackupsList();
        $wafStats   = SecurityFirewall::getStats();
        $securitySettingsRows = Database::query("SELECT setting_key,setting_value FROM security_settings")->fetchAll();
        $securitySettings = array_column($securitySettingsRows, 'setting_value', 'setting_key');
        $securityStats = Database::query("SELECT
            (SELECT COUNT(*) FROM user_sessions WHERE revoked_at IS NULL AND expires_at>NOW()) active_sessions,
            (SELECT COUNT(*) FROM users WHERE two_factor_enabled=1) two_factor_users,
            (SELECT COUNT(*) FROM security_events WHERE severity IN ('warning','critical') AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)) recent_alerts")->fetch();
        $securityEvents = Database::query("SELECT se.*,u.name user_name FROM security_events se LEFT JOIN users u ON u.id=se.user_id ORDER BY se.created_at DESC LIMIT 12")->fetchAll();

        include __DIR__ . '/../views/system/health.php';
    }

    // Jalankan skrip Auto-Backup (AJAX POST)
    public static function runBackup() {
        Auth::requireLogin();

        if (!Auth::hasPermission('system', 'edit')) {
            Helper::json('error', 'Access Denied: Only Admin is authorized to execute backup scripts.');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::json('error', 'Invalid request method.');
            return;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) {
            Helper::json('error', 'Invalid CSRF token security.');
            return;
        }

        $result = SystemHealth::runAutoBackup();
        if ($result['status'] === 'success') {
            Helper::json('success', $result['message'], $result);
        } else {
            Helper::json('error', $result['message']);
        }
    }

    // Padam fail backup yang disimpan (AJAX POST)
    public static function deleteBackup() {
        Auth::requireLogin();

        if (!Auth::hasPermission('system', 'edit')) {
            Helper::json('error', 'Access Denied.');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::json('error', 'Invalid request method.');
            return;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) {
            Helper::json('error', 'Invalid CSRF token security.');
            return;
        }

        $filename = $_POST['filename'] ?? '';
        if (empty($filename)) {
            Helper::json('error', 'Please specify the backup archive to delete.');
            return;
        }

        if (SystemHealth::deleteBackup($filename)) {
            Helper::json('success', "Backup archive '$filename' successfully deleted from server.");
        } else {
            Helper::json('error', 'Backup archive not found or failed to delete.');
        }
    }

    // Muat turun fail backup .zip
    public static function downloadBackup() {
        Auth::requireLogin();

        if (!Auth::hasPermission('system', 'edit')) {
            die("Access Denied: Only Admin is authorized to download backup archives.");
        }

        $filename = basename($_GET['file'] ?? '');
        if (!preg_match('/^TBBA_System_Backup_[0-9_-]+\.zip$/', $filename)) {
            http_response_code(400);
            exit('Invalid backup archive name.');
        }
        $filePath = __DIR__ . '/../storage/backups/' . $filename;

        if (file_exists($filePath) && is_file($filePath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        } else {
            die("Error: Backup archive not found in server directory.");
        }
    }

    public static function testBackup() {
        Auth::requireLogin();
        if (!Auth::hasPermission('system', 'edit')) Helper::json('error', 'Access Denied.');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid request method.');
        if (!Helper::verifyCsrf($_POST['csrf_token'] ?? '')) Helper::json('error', 'Invalid CSRF token.');
        $result = SystemHealth::testBackup((string)($_POST['filename'] ?? ''));
        if ($result['status'] === 'success') Helper::json('success', $result['message'], $result['data'] ?? []);
        Helper::json('error', $result['message']);
    }

    // Ujian kelajuan pertanyaan MySQL (AJAX GET)
    public static function benchmarkDb() {
        Auth::requireLogin();

        if (!Auth::hasPermission('system', 'edit')) {
            Helper::json('error', 'Access Denied.');
            return;
        }

        $result = SystemHealth::benchmarkDb();
        if ($result['status'] === 'ERROR') {
            Helper::json('error', $result['rating'], $result);
            return;
        }
        Helper::json('success', "Query speed test completed successfully: {$result['speed_ms']}ms", $result);
    }

    // Dapatkan statistik WAF & Anti-DDoS secara AJAX
    public static function getWafStats() {
        Auth::requireLogin();
        if (!Auth::hasPermission('system', 'edit')) {
            Helper::json('error', 'Access Denied.');
            return;
        }
        $stats = SecurityFirewall::getStats();
        Helper::json('success', 'WAF statistics retrieved.', $stats);
    }

    // Kosongkan log WAF audit (AJAX POST)
    public static function clearWafLogs() {
        Auth::requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::json('error', 'Invalid request method.');
        }
        if (!Helper::verifyCsrf($_POST['csrf_token'] ?? '')) {
            Helper::json('error', 'Invalid CSRF token security.');
        }
        SecurityFirewall::clearLogs();
        Helper::json('success', 'Security audit logs cleared successfully.');
    }
}
?>

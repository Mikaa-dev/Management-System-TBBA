<?php
/**
 * Audit Trail & Activity Log Model (PDO)
 * Company: The Bridge Business Alliance (TBBA)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Auth.php';

class AuditLog {
    private static bool $schemaChecked = false;

    // Verify migrations without changing schema during a web request.
    public static function ensureTable() {
        if (self::$schemaChecked) return;
        try {
            Database::query("SELECT id, user_id, user_name, user_role, action_type, description, ip_address, created_at FROM audit_logs LIMIT 0");
            self::$schemaChecked = true;
        } catch (Throwable $e) {
            throw new RuntimeException('Audit log schema is unavailable. Run the database migrations.', 0, $e);
        }
    }

    // Record a silent audit log entry
    public static function record($actionType, $description, $userId = null, $userName = null, $userRole = null) {
        self::ensureTable();

        if ($userId === null) {
            $userId = Auth::id();
        }
        if ($userName === null || $userRole === null) {
            $u = Auth::user();
            if ($u) {
                $userName = $userName ?? $u['name'];
                $userRole = $userRole ?? $u['role'];
            } else {
                $userName = $userName ?? 'System / Guest';
                $userRole = $userRole ?? 'Guest';
            }
        }

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Format description cleanly if timestamp or name included
        $timeStr = date('g:i A');
        $formattedDesc = $description;

        Database::query("INSERT INTO audit_logs (user_id, user_name, user_role, action_type, description, ip_address) 
                         VALUES (?, ?, ?, ?, ?, ?)", [
            $userId,
            $userName,
            $userRole,
            $actionType,
            $formattedDesc,
            $ipAddress
        ]);
    }

    // Get all audit logs with optional action type filter
    public static function getAll($filterAction = null, $limit = 500) {
        self::ensureTable();
        $sql = "SELECT * FROM audit_logs ";
        $params = [];

        if (!empty($filterAction) && $filterAction !== 'ALL') {
            $sql .= "WHERE action_type = ? ";
            $params[] = $filterAction;
        }

        $sql .= "ORDER BY created_at DESC LIMIT " . intval($limit);
        $stmt = Database::query($sql, $params);
        return $stmt->fetchAll();
    }

    // Get recent logs for dashboard widget
    public static function getRecent($limit = 10) {
        self::ensureTable();
        $stmt = Database::query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT " . intval($limit));
        return $stmt->fetchAll();
    }
}
?>

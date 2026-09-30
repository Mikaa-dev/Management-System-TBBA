<?php
/**
 * Audit Trail & Activity Log Controller
 * Company: The Bridge Business Alliance (TBBA)
 */

require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';

class AuditLogController {
    public static function index() {
        Auth::requireLogin();
        Auth::requirePermission('audit_logs', 'view');

        $selectedAction = $_GET['action_type'] ?? 'ALL';
        $auditLogs = AuditLog::getAll($selectedAction, 500);
        $pageTitle = "System Audit Trail & Activity Log";

        include __DIR__ . '/../views/system/audit_logs.php';
    }
}
?>

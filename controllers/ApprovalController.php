<?php
/**
 * Approval Center Controller — TBBA ERP Module 5
 * Unified inbox for Leave, Finance-verified Expense, and Purchase approvals.
 */
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../models/LeaveRequest.php';
require_once __DIR__ . '/../models/ExpenseClaim.php';
require_once __DIR__ . '/../models/PurchaseRequest.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../core/NotificationService.php';
require_once __DIR__ . '/../models/AuditLog.php';

class ApprovalController {
    public static function index(): void {
        Auth::requireLogin();
        $pageTitle = 'Approval Center';
        $currentUser = Auth::user();

        $deptScope = Auth::getScopeDepartmentId();

        $pendingLeave = Auth::hasPermission('leave', 'approve')
            ? LeaveRequest::getAll('pending', $deptScope)
            : [];

        // Expense claims enter Approval Center ONLY after Finance verification.
        $pendingExpense = Auth::hasPermission('expense', 'approve')
            ? ExpenseClaim::getAwaitingApproval($deptScope)
            : [];

        $pendingPurchase = Auth::hasPermission('purchase', 'approve')
            ? PurchaseRequest::getAll('pending', null, $deptScope)
            : [];

        $totalPending = count($pendingLeave) + count($pendingExpense) + count($pendingPurchase);
        include __DIR__ . '/../views/approvals/index.php';
    }

    /** AJAX: Get approval summary counts */
    public static function getSummary(): void {
        Auth::requireLogin();
        $deptScope = Auth::getScopeDepartmentId();

        Helper::json('success', 'OK', [
            'leave' => Auth::hasPermission('leave', 'approve')
                ? count(LeaveRequest::getAll('pending', $deptScope))
                : 0,
            'expense' => Auth::hasPermission('expense', 'approve')
                ? count(ExpenseClaim::getAwaitingApproval($deptScope))
                : 0,
            'purchase' => Auth::hasPermission('purchase', 'approve')
                ? count(PurchaseRequest::getAll('pending', null, $deptScope))
                : 0,
        ]);
    }

    /** AJAX: Universal approve/reject handler */
    public static function decide(): void {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');

        $module = trim((string)($_POST['module'] ?? ''));
        $id = (int)($_POST['id'] ?? 0);
        $decision = trim((string)($_POST['decision'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));

        if ($id <= 0 || !in_array($decision, ['approved', 'rejected'], true)) {
            Helper::json('error', 'Invalid decision.');
        }

        switch ($module) {
            case 'leave':
                Auth::requirePermission('leave', 'approve');
                $req = LeaveRequest::findById($id);

                if (!$req || $req['status'] !== 'pending') {
                    Helper::json('error', 'Request not available for action.');
                }

                self::requireRequestScope((int)$req['user_id']);

                if (!LeaveRequest::updateStatus($id, $decision, (int)Auth::id(), $reason ?: null)) {
                    Helper::json('error', 'This request was already processed by another user.');
                }

                NotificationService::notifyAndPush(
                    (int)$req['user_id'],
                    'leave_' . $decision,
                    'Leave ' . ucfirst($decision),
                    "Your leave request ({$req['total_days']} day(s)) has been {$decision}.",
                    $decision === 'approved' ? 'fa-check-circle' : 'fa-times-circle',
                    $decision === 'approved' ? '#10B981' : '#EF4444',
                    'index.php?page=leave',
                    'leave_' . $decision
                );
                break;

            case 'expense':
                Auth::requirePermission('expense', 'approve');
                $claim = ExpenseClaim::findById($id);

                if (!$claim || $claim['status'] !== 'pending') {
                    Helper::json('error', 'Claim not available for action.');
                }

                self::requireRequestScope((int)$claim['user_id']);

                if (!ExpenseClaim::hasPendingApproval($id)) {
                    Helper::json('error', 'This claim has not been verified by Finance or is no longer in Approval Center.');
                }

                // An approval cannot be completed without the current boss/approver signature.
                if ($decision === 'approved' && !ExpenseClaim::hasSignature($id, 'approver', (int)Auth::id())) {
                    Helper::json('error', 'Please sign the expense claim before approving it.');
                }

                if (!ExpenseClaim::finalizeApproval($id, $decision, (int)Auth::id(), $reason ?: null)) {
                    Helper::json('error', 'This claim was already processed by another user.');
                }

                $body = "Your expense '{$claim['title']}' (MYR " . number_format((float)$claim['total_amount'], 2) . ") has been {$decision}.";
                if ($reason !== '') $body .= " Reason: {$reason}";

                NotificationService::notifyAndPush(
                    (int)$claim['user_id'],
                    'expense_' . $decision,
                    'Expense ' . ucfirst($decision),
                    $body,
                    $decision === 'approved' ? 'fa-check-circle' : 'fa-times-circle',
                    $decision === 'approved' ? '#10B981' : '#EF4444',
                    'index.php?page=expense',
                    'expense_' . $decision
                );
                break;

            case 'purchase':
                Auth::requirePermission('purchase', 'approve');
                $pr = PurchaseRequest::findById($id);

                if (!$pr || $pr['status'] !== 'pending') {
                    Helper::json('error', 'PR not available for action.');
                }

                self::requireRequestScope((int)$pr['user_id']);

                if (!PurchaseRequest::updateStatus($id, $decision, (int)Auth::id(), $reason ?: null)) {
                    Helper::json('error', 'This purchase request was already processed by another user.');
                }

                NotificationService::notifyAndPush(
                    (int)$pr['user_id'],
                    'pr_' . $decision,
                    'Purchase Request ' . ucfirst($decision),
                    "Your PR '{$pr['title']}' has been {$decision}.",
                    $decision === 'approved' ? 'fa-check-circle' : 'fa-times-circle',
                    $decision === 'approved' ? '#10B981' : '#EF4444',
                    'index.php?page=purchase',
                    'pr_' . $decision
                );
                break;

            default:
                Helper::json('error', 'Unknown module: ' . $module);
        }

        AuditLog::record(
            strtoupper($module) . '_' . strtoupper($decision),
            "{$module} #{$id} {$decision}" . ($reason ? ": {$reason}" : '')
        );

        Helper::json('success', ucfirst($module) . ' ' . $decision . ' successfully.');
    }

    private static function requireRequestScope(int $requesterUserId): void {
        if (Auth::getScopeDepartmentId() === null) return;

        $requester = User::findById($requesterUserId);
        if (!$requester || !Auth::canAccessUser($requester)) {
            Helper::json('error', 'Access denied. You can only approve requests from your own department.');
        }
    }
}
?>

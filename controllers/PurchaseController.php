<?php
/**
 * Purchase Request Controller — TBBA ERP Module 4
 */
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../models/PurchaseRequest.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Department.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/AuditLog.php';

class PurchaseController {
    public static function index(): void {
        Auth::requirePermission('purchase', 'view');
        $pageTitle   = 'Purchase Requests';
        $currentUser = Auth::user();
        $canApprove  = Auth::hasPermission('purchase', 'approve');
        $userId      = (int)$currentUser['id'];
        $departments = Department::getAllActive();

        $deptScope = Auth::getScopeDepartmentId();
        if ($canApprove) {
            $requests = PurchaseRequest::getAll(null, null, $deptScope);
            $pending  = PurchaseRequest::getAll('pending', null, $deptScope);
        } else {
            $requests = PurchaseRequest::getAll(null, $userId);
            $pending  = PurchaseRequest::getAll('pending', $userId);
        }
        include __DIR__ . '/../views/purchase/index.php';
    }

    public static function store(): void {
        Auth::requirePermission('purchase', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');
        $userId       = (int)Auth::id();
        $title        = trim($_POST['title'] ?? '');
        $vendor       = trim($_POST['vendor'] ?? '');
        $requiredDate = trim($_POST['required_date'] ?? '');
        $deptId       = (int)($_POST['department_id'] ?? 0) ?: null;
        $projectRef   = trim($_POST['project_ref'] ?? '');
        $justification= trim($_POST['justification'] ?? '');

        if (!$title) Helper::json('error', 'Purchase request title is required.');
        if ($requiredDate !== '' && !self::validDate($requiredDate)) Helper::json('error', 'Please enter a valid required date.');

        $itemsJson = $_POST['items'] ?? '[]';
        $items = json_decode($itemsJson, true) ?: [];
        if (empty($items)) Helper::json('error', 'At least one item is required.');

        $total = 0;
        foreach ($items as &$item) {
            $item['quantity']   = (float)($item['quantity'] ?? 1);
            $item['unit_price'] = (float)($item['unit_price'] ?? 0);
            if (empty($item['description'])) Helper::json('error', 'All items need a description.');
            if ($item['quantity'] <= 0 || $item['unit_price'] < 0) {
                Helper::json('error', 'Item quantity must be positive and unit price cannot be negative.');
            }
            $total += $item['quantity'] * $item['unit_price'];
        }

        $id = PurchaseRequest::create([
            'user_id' => $userId, 'title' => $title, 'vendor' => $vendor ?: null,
            'required_date' => $requiredDate ?: null, 'department_id' => $deptId,
            'project_ref' => $projectRef ?: null, 'justification' => $justification ?: null,
            'total_amount' => $total,
        ], $items);

        $user = Auth::user();
        Notification::sendToApprovers(
            'purchase',
            $userId,
            'approval_purchase_pending',
            'New Purchase Request',
            "{$user['name']} submitted '{$title}' for MYR " . number_format($total, 2) . '.',
            'fa-cart-shopping',
            '#2563EB'
        );
        AuditLog::record('PR_SUBMIT', "Purchase request submitted: '{$title}' — MYR " . number_format($total, 2));
        Helper::json('success', 'Purchase request submitted!', ['id' => $id, 'total' => $total]);
    }

    public static function approve(): void {
        Auth::requirePermission('purchase', 'approve');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');
        $id       = (int)($_POST['id'] ?? 0);
        $decision = trim($_POST['decision'] ?? '');
        $reason   = trim($_POST['reason'] ?? '');
        if (!$id || !in_array($decision, ['approved','rejected'])) Helper::json('error', 'Invalid data.');

        $pr = PurchaseRequest::findById($id);
        if (!$pr) Helper::json('error', 'PR not found.');
        if ($pr['status'] !== 'pending') Helper::json('error', 'PR is no longer pending.');

        if (Auth::getScopeDepartmentId() !== null) {
            $targetUser = User::findById((int)$pr['user_id']);
            if (!$targetUser || !Auth::canAccessUser($targetUser)) {
                Helper::json('error', 'Access Denied: You can only approve purchase requests for staff within your own department.');
            }
        }

        if (!PurchaseRequest::updateStatus($id, $decision, (int)Auth::id(), $reason ?: null)) {
            Helper::json('error', 'This purchase request was already processed by another user.');
        }
        Notification::send(
            $pr['user_id'], 'pr_' . $decision,
            "Purchase Request " . ucfirst($decision),
            "Your PR '{$pr['title']}' (MYR " . number_format($pr['total_amount'],2) . ") has been {$decision}." . ($reason ? " Reason: $reason" : ''),
            $decision === 'approved' ? 'fa-check-circle' : 'fa-times-circle',
            $decision === 'approved' ? '#10B981' : '#EF4444',
            'index.php?page=purchase'
        );
        AuditLog::record('PR_' . strtoupper($decision), "Purchase request #{$id}: {$decision}");
        Helper::json('success', "Purchase request " . ucfirst($decision) . " successfully.");
    }

    public static function updateStatus(): void {
        Auth::requirePermission('purchase', 'approve');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');
        $id     = (int)($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $valid  = ['draft','pending','approved','rejected','ordered','received'];
        if (!$id || !in_array($status, $valid)) Helper::json('error', 'Invalid data.');
        $pr = PurchaseRequest::findById($id);
        if (!$pr) Helper::json('error', 'Not found.');
        if (!Auth::canAccessOwnedResource((int)$pr['user_id'], 'purchase')) Helper::json('error', 'Access denied.');
        $validTransition = ($pr['status'] === 'approved' && $status === 'ordered')
            || ($pr['status'] === 'ordered' && $status === 'received');
        if (!$validTransition) Helper::json('error', 'Invalid purchase workflow transition.');
        if (!PurchaseRequest::updateStatus($id, $status, (int)Auth::id())) {
            Helper::json('error', 'This purchase request status changed before your update was saved.');
        }
        Helper::json('success', "Status updated to " . ucfirst($status) . ".");
    }

    public static function getPR(): void {
        Auth::requirePermission('purchase', 'view');
        $id = (int)($_GET['id'] ?? 0);
        $pr = PurchaseRequest::findById($id);
        if (!$pr) Helper::json('error', 'Not found.');
        if (!Auth::canAccessOwnedResource((int)$pr['user_id'], 'purchase')) {
            Helper::json('error', 'Access Denied: You cannot view this purchase request.');
        }
        $items = PurchaseRequest::getItems($id);
        Helper::json('success', 'OK', ['pr' => $pr, 'items' => $items]);
    }

    public static function delete(): void {
        Auth::requirePermission('purchase', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');
        $id = (int)($_POST['id'] ?? 0);
        $pr = PurchaseRequest::findById($id);
        if (!$pr) Helper::json('error', 'Not found.');
        if ((string)$pr['status'] !== 'pending') Helper::json('error', 'Only a pending purchase request can be deleted.');
        if (!Auth::canAccessOwnedResource((int)$pr['user_id'], 'purchase')) Helper::json('error', 'Access denied.');
        PurchaseRequest::delete($id);
        AuditLog::record('DELETE', "Deleted PR #{$id}: {$pr['title']}");
        Helper::json('success', 'Purchase request deleted.');
    }

    private static function validDate(string $date): bool {
        $parsed = DateTime::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
?>

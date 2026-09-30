<?php
/**
 * Expense Claims Controller — TBBA ERP Module 3
 */
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../models/ExpenseClaim.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../core/NotificationService.php'; // FCM Push Notification

class ExpenseController {
    public static function index(): void {
        Auth::requirePermission('expense', 'view');
        $pageTitle   = 'Expense Claims';
        $currentUser = Auth::user();
        $canApprove  = Auth::hasPermission('expense', 'approve');
        $userId      = (int)$currentUser['id'];

        $deptScope = Auth::getScopeDepartmentId();
        if ($canApprove) {
            $claims  = ExpenseClaim::getAll(null, null, $deptScope);
            $pending = ExpenseClaim::getAll('pending', null, $deptScope);
        } else {
            $claims  = ExpenseClaim::getAll(null, $userId);
            $pending = ExpenseClaim::getAll('pending', $userId);
        }
        $totalClaimed  = ExpenseClaim::totalByStatus($userId, 'approved');
        $totalPending  = ExpenseClaim::totalByStatus($userId, 'pending');
        include __DIR__ . '/../views/expense/index.php';
    }

    public static function store(): void {
        Auth::requirePermission('expense', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');
        $userId       = (int)Auth::id();
        $title        = trim($_POST['title'] ?? '');
        $expenseDate  = trim($_POST['expense_date'] ?? '');
        $projectRef   = trim($_POST['project_ref'] ?? '');
        $notes        = trim($_POST['notes'] ?? '');

        if (!$title || !$expenseDate) Helper::json('error', 'Title and expense date are required.');

        // Parse JSON items
        $itemsJson = $_POST['items'] ?? '[]';
        $items = json_decode($itemsJson, true) ?: [];
        if (empty($items)) Helper::json('error', 'At least one expense item is required.');

        // A receipt is optional. Validate and save it only when the claimant uploads one.
        $mainReceiptPath = null;
        $receiptError = $_FILES['receipt_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($receiptError !== UPLOAD_ERR_NO_FILE) {
            if ($receiptError !== UPLOAD_ERR_OK) {
                Helper::json('error', 'The receipt could not be uploaded. Please try again or submit the claim without it.');
            }

            try {
                Helper::validateUpload($_FILES['receipt_file'], [
                    'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
                    'pdf' => ['application/pdf'],
                    'doc' => ['application/msword', 'application/octet-stream'],
                    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
                    'zip' => ['application/zip', 'application/x-zip-compressed'],
                ], 10 * 1024 * 1024);
            } catch (InvalidArgumentException $e) {
                Helper::json('error', $e->getMessage());
            }

            $uploadDirRelative = 'storage/private/expenses';
            $uploadDirAbsolute = __DIR__ . '/../' . $uploadDirRelative;
            if (!is_dir($uploadDirAbsolute) && !mkdir($uploadDirAbsolute, 0750, true) && !is_dir($uploadDirAbsolute)) {
                Helper::json('error', 'The receipt storage folder is unavailable. Please try again or submit the claim without it.');
            }

            $safeName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($_FILES['receipt_file']['name']));
            $uniqueFilename = 'exp_main_' . time() . '_' . $safeName;
            if (move_uploaded_file($_FILES['receipt_file']['tmp_name'], $uploadDirAbsolute . '/' . $uniqueFilename)) {
                $mainReceiptPath = $uploadDirRelative . '/' . $uniqueFilename;
            } else {
                Helper::json('error', 'Failed to save the uploaded receipt. Please try again or submit the claim without it.');
            }
        }

        $total = 0;
        foreach ($items as &$item) {
            $item['amount'] = (float)($item['amount'] ?? 0);
            $item['description'] = trim((string)($item['description'] ?? ''));
            if ($item['description'] === '') Helper::json('error', 'All expense items need a description.');
            if ($item['amount'] <= 0) Helper::json('error', 'All items must have a positive amount.');
            $total += $item['amount'];
        }

        if (!self::validDate($expenseDate)) Helper::json('error', 'Please enter a valid expense date.');
        try {
            $id = ExpenseClaim::create([
                'user_id' => $userId, 'title' => $title, 'expense_date' => $expenseDate,
                'total_amount' => $total, 'project_ref' => $projectRef ?: null, 'notes' => $notes ?: null,
                'receipt_path' => $mainReceiptPath
            ], $items);
        } catch (Throwable $e) {
            self::deleteNewReceipt($mainReceiptPath);
            throw $e;
        }

        $user = Auth::user();
        Notification::sendToApprovers(
            'expense',
            $userId,
            'approval_expense_pending',
            'New Expense Claim',
            "{$user['name']} submitted '{$title}' for MYR " . number_format($total, 2) . '.',
            'fa-receipt',
            '#BE185D'
        );
        AuditLog::record('EXPENSE_SUBMIT', "Expense claim submitted: '{$title}' — MYR " . number_format($total, 2));
        Helper::json('success', 'Expense claim submitted for approval!', ['id' => $id, 'total' => $total]);
    }

    public static function approve(): void {
        Auth::requirePermission('expense', 'approve');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');
        $id       = (int)($_POST['id'] ?? 0);
        $decision = trim($_POST['decision'] ?? '');
        $reason   = trim($_POST['reason'] ?? '');
        if (!$id || !in_array($decision, ['approved','rejected'])) Helper::json('error', 'Invalid data.');

        $claim = ExpenseClaim::findById($id);
        if (!$claim) Helper::json('error', 'Claim not found.');
        if ($claim['status'] !== 'pending') Helper::json('error', 'Claim is no longer pending.');

        if (Auth::getScopeDepartmentId() !== null) {
            $targetUser = User::findById((int)$claim['user_id']);
            if (!$targetUser || !Auth::canAccessUser($targetUser)) {
                Helper::json('error', 'Access Denied: You can only approve claims for staff within your own department.');
            }
        }

        if (!ExpenseClaim::updateStatus($id, $decision, (int)Auth::id(), $reason ?: null)) {
            Helper::json('error', 'This claim was already processed by another user.');
        }

        // Dual notification: in-app DB + FCM push ke device user
        $notifTitle = "Expense Claim " . ucfirst($decision);
        $notifBody  = "Your claim '{$claim['title']}' (MYR " . number_format($claim['total_amount'],2) . ") has been {$decision}." . ($reason ? " Reason: $reason" : '');
        NotificationService::notifyAndPush(
            (int)$claim['user_id'],
            'expense_' . $decision,
            $notifTitle,
            $notifBody,
            $decision === 'approved' ? 'fa-check-circle' : 'fa-times-circle',
            $decision === 'approved' ? '#10B981' : '#EF4444',
            'index.php?page=expense',
            'expense_' . $decision
        );

        AuditLog::record('EXPENSE_' . strtoupper($decision), "Expense #{$id}: {$decision}");
        Helper::json('success', "Expense claim " . ucfirst($decision) . " successfully.");
    }

    public static function markPaid(): void {
        Auth::requirePermission('expense', 'approve');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');
        $id = (int)($_POST['id'] ?? 0);
        $claim = ExpenseClaim::findById($id);
        if (!$claim || $claim['status'] !== 'approved') Helper::json('error', 'Claim must be approved before marking paid.');
        if (!Auth::canAccessOwnedResource((int)$claim['user_id'], 'expense')) Helper::json('error', 'Access denied.');
        if (!ExpenseClaim::updateStatus($id, 'paid', (int)Auth::id())) {
            Helper::json('error', 'This claim is no longer awaiting payment.');
        }
        // Push notification bila expense ditanda paid
        NotificationService::notifyAndPush(
            (int)$claim['user_id'],
            'expense_paid',
            'Expense Claim Paid 💰',
            "Your expense claim '{$claim['title']}' has been marked as PAID. Please check your account.",
            'fa-money-bill-wave', '#10B981',
            'index.php?page=expense',
            'expense_paid'
        );
        Helper::json('success', 'Expense claim marked as paid.');
    }

    public static function getClaim(): void {
        Auth::requirePermission('expense', 'view');
        $id = (int)($_GET['id'] ?? 0);
        $claim = ExpenseClaim::findById($id);
        if (!$claim) Helper::json('error', 'Not found.');
        if (!Auth::canAccessOwnedResource((int)$claim['user_id'], 'expense')) {
            Helper::json('error', 'Access Denied: You cannot view this expense claim.');
        }
        $items = ExpenseClaim::getItems($id);
        Helper::json('success', 'OK', ['claim' => $claim, 'items' => $items]);
    }

    public static function delete(): void {
        Auth::requirePermission('expense', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');
        $id = (int)($_POST['id'] ?? 0);
        $claim = ExpenseClaim::findById($id);
        if (!$claim) Helper::json('error', 'Not found.');
        if ((string)$claim['status'] !== 'pending') Helper::json('error', 'Only a pending expense claim can be deleted.');
        if (!Auth::canAccessOwnedResource((int)$claim['user_id'], 'expense')) Helper::json('error', 'Access denied.');
        ExpenseClaim::delete($id);
        AuditLog::record('DELETE', "Deleted expense claim #{$id}: {$claim['title']}");
        Helper::json('success', 'Expense claim deleted.');
    }

    private static function validDate(string $date): bool {
        $parsed = DateTime::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private static function deleteNewReceipt(?string $relativePath): void {
        $relativePath = str_replace('\\', '/', ltrim((string)$relativePath, '/'));
        if ($relativePath === '' || str_contains($relativePath, '..')
            || !str_starts_with($relativePath, 'storage/private/expenses/')) return;
        $absolute = realpath(__DIR__ . '/../' . $relativePath);
        $root = realpath(__DIR__ . '/../storage/private/expenses');
        if ($absolute && $root && is_file($absolute) && str_starts_with($absolute, $root . DIRECTORY_SEPARATOR)) @unlink($absolute);
    }
}
?>

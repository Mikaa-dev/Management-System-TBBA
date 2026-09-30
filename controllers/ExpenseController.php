<?php
/**
 * Expense Claims Controller — TBBA ERP Module 3
 * Workflow v3: Staff -> Finance Review -> Approval Center -> Management Approval -> Paid
 */
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../models/ExpenseClaim.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../core/NotificationService.php';

class ExpenseController {
    public static function index(): void {
        Auth::requirePermission('expense', 'view');

        $pageTitle = 'Expense Claims';
        $currentUser = Auth::user();
        $userId = (int)$currentUser['id'];

        $canViewAll = Auth::hasPermission('expense', 'view_all');
        $canFinanceCheck = Auth::hasPermission('expense', 'finance_check');
        $canApprove = Auth::hasPermission('expense', 'approve');
        $canMarkPaid = Auth::hasPermission('expense', 'mark_paid');

        $requestedView = strtolower(trim((string)($_GET['view'] ?? 'my')));
        $showAllStaff = $canViewAll && $requestedView === 'all';

        $deptScope = Auth::getScopeDepartmentId();
        if ($showAllStaff) {
            $claims = ExpenseClaim::getAll(null, null, $deptScope);
        } else {
            $claims = ExpenseClaim::getAll(null, $userId);
        }

        $totalClaimed = ExpenseClaim::totalByStatuses($userId, ['approved', 'paid']);
        $totalPending = ExpenseClaim::totalByStatus($userId, 'pending');
        $expenseCategories = ExpenseClaim::categories();

        include __DIR__ . '/../views/expense/index.php';
    }

    public static function store(): void {
        Auth::requirePermission('expense', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');

        $userId = (int)Auth::id();
        $title = trim((string)($_POST['title'] ?? ''));
        $expenseDate = trim((string)($_POST['expense_date'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($title === '' || $expenseDate === '') {
            Helper::json('error', 'Title and expense date are required.');
        }
        if (!self::validDate($expenseDate)) {
            Helper::json('error', 'Please enter a valid expense date.');
        }

        $itemsJson = $_POST['items'] ?? '[]';
        $items = json_decode($itemsJson, true);
        if (!is_array($items) || empty($items)) {
            Helper::json('error', 'At least one expense item is required.');
        }

        $allowedCategories = ExpenseClaim::categories();
        $total = 0.0;

        foreach ($items as $index => &$item) {
            if (!is_array($item)) Helper::json('error', 'Invalid expense item data.');

            $item['description'] = trim((string)($item['description'] ?? ''));
            $item['category'] = trim((string)($item['category'] ?? ''));
            $item['amount'] = round((float)($item['amount'] ?? 0), 2);
            $item['receipt_path'] = null;

            if ($item['description'] === '') {
                Helper::json('error', 'All expense items need a description.');
            }
            if (!in_array($item['category'], $allowedCategories, true)) {
                Helper::json('error', 'Invalid expense category selected.');
            }
            if ($item['amount'] <= 0) {
                Helper::json('error', 'All items must have a positive amount.');
            }

            $total += $item['amount'];
        }
        unset($item);

        $uploadedPaths = [];

        try {
            foreach ($items as $index => &$item) {
                $field = 'item_receipt_' . $index;
                if (!isset($_FILES[$field])) continue;

                $error = (int)($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($error === UPLOAD_ERR_NO_FILE) continue;
                if ($error !== UPLOAD_ERR_OK) {
                    throw new InvalidArgumentException('One of the receipts could not be uploaded. Please try again.');
                }

                $path = self::storeReceipt($_FILES[$field], $index + 1);
                $item['receipt_path'] = $path;
                $uploadedPaths[] = $path;
            }
            unset($item);

            $id = ExpenseClaim::create([
                'user_id' => $userId,
                'title' => $title,
                'expense_date' => $expenseDate,
                'total_amount' => $total,
                // Keep legacy DB column nullable. It is no longer collected in the form.
                'project_ref' => null,
                'notes' => $notes !== '' ? $notes : null,
                'receipt_path' => null,
            ], $items);
        } catch (InvalidArgumentException $e) {
            foreach ($uploadedPaths as $path) self::deleteNewReceipt($path);
            Helper::json('error', $e->getMessage());
        } catch (Throwable $e) {
            foreach ($uploadedPaths as $path) self::deleteNewReceipt($path);
            throw $e;
        }

        AuditLog::record(
            'EXPENSE_SUBMIT',
            "Expense claim submitted for Finance review: '{$title}' — MYR " . number_format($total, 2)
        );

        Helper::json(
            'success',
            'Expense claim submitted. Please sign the claim before Finance verification.',
            ['id' => $id, 'total' => $total]
        );
    }

    /** Finance Officer: check a claim and send it to Approval Center. */
    public static function financeVerify(): void {
        Auth::requirePermission('expense', 'finance_check');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) Helper::json('error', 'Invalid claim ID.');

        $claim = ExpenseClaim::findById($id);
        if (!$claim) Helper::json('error', 'Expense claim not found.');
        if ($claim['status'] !== 'pending') Helper::json('error', 'Only a pending claim can be verified.');

        self::requireClaimScope($claim);

        if (ExpenseClaim::hasPendingApproval($id)) {
            Helper::json('error', 'This claim has already been sent to Approval Center.');
        }

        if (!ExpenseClaim::hasSignature($id, 'applicant', (int)$claim['user_id'])) {
            Helper::json('error', 'The staff applicant must sign this expense claim before Finance can forward it.');
        }

        if (!ExpenseClaim::forwardToApproval($id, (int)$claim['user_id'])) {
            Helper::json('error', 'This claim was already forwarded or is no longer available for Finance review.');
        }

        $financeUser = Auth::user();
        AuditLog::record(
            'EXPENSE_FINANCE_VERIFIED',
            "Finance verified {$claim['claim_no']} (#{$id}) — {$claim['title']} — MYR " . number_format((float)$claim['total_amount'], 2)
        );

        // Only at this point is the claim announced to final approvers.
        Notification::sendToApprovers(
            'expense',
            (int)$claim['user_id'],
            'approval_expense_pending',
            'Expense Claim Ready for Approval',
            "{$claim['user_name']} — {$claim['claim_no']} — MYR " . number_format((float)$claim['total_amount'], 2) . ' has been checked by Finance.',
            'fa-receipt',
            '#BE185D'
        );

        NotificationService::notifyAndPush(
            (int)$claim['user_id'],
            'expense_finance_verified',
            'Expense Checked by Finance',
            "Your expense claim '{$claim['title']}' has been checked by Finance and sent for management approval.",
            'fa-circle-check',
            '#2563EB',
            'index.php?page=expense',
            'expense_finance_verified'
        );

        $reviewerName = (string)($financeUser['name'] ?? 'Finance');
        Helper::json('success', "Checked by {$reviewerName} and sent to Approval Center.");
    }

    /**
     * Legacy direct approval endpoint retained for compatibility.
     * Final approval is only allowed after Finance forwarding.
     */
    public static function approve(): void {
        Auth::requirePermission('expense', 'approve');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');

        $id = (int)($_POST['id'] ?? 0);
        $decision = trim((string)($_POST['decision'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));

        if ($id <= 0 || !in_array($decision, ['approved', 'rejected'], true)) {
            Helper::json('error', 'Invalid data.');
        }

        $claim = ExpenseClaim::findById($id);
        if (!$claim || $claim['status'] !== 'pending') {
            Helper::json('error', 'Claim not available for action.');
        }

        self::requireClaimScope($claim);

        if (!ExpenseClaim::hasPendingApproval($id)) {
            Helper::json('error', 'Finance must verify and forward this claim before management approval.');
        }

        if ($decision === 'approved' && !ExpenseClaim::hasSignature($id, 'approver', (int)Auth::id())) {
            Helper::json('error', 'Please sign this claim as the approver before approving it.');
        }

        if (!ExpenseClaim::finalizeApproval($id, $decision, (int)Auth::id(), $reason !== '' ? $reason : null)) {
            Helper::json('error', 'This claim was already processed by another user.');
        }

        self::notifyFinalDecision($claim, $decision, $reason);

        AuditLog::record(
            'EXPENSE_' . strtoupper($decision),
            "Expense #{$id}: {$decision}" . ($reason !== '' ? ": {$reason}" : '')
        );

        Helper::json('success', 'Expense claim ' . ucfirst($decision) . ' successfully.');
    }

    public static function checkApproverSignature(): void {
        Auth::requirePermission('expense', 'approve');
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) Helper::json('error', 'Invalid claim ID.');

        $claim = ExpenseClaim::findById($id);
        if (!$claim) Helper::json('error', 'Claim not found.');
        self::requireClaimScope($claim);

        Helper::json('success', 'OK', [
            'signed' => ExpenseClaim::hasSignature($id, 'approver', (int)Auth::id()),
        ]);
    }

    public static function markPaid(): void {
        Auth::requirePermission('expense', 'mark_paid');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');

        $id = (int)($_POST['id'] ?? 0);
        $claim = ExpenseClaim::findById($id);

        if (!$claim || $claim['status'] !== 'approved') {
            Helper::json('error', 'Claim must be approved before marking paid.');
        }

        self::requireClaimScope($claim);

        if (!ExpenseClaim::updateStatus($id, 'paid', (int)Auth::id())) {
            Helper::json('error', 'This claim is no longer awaiting payment.');
        }

        NotificationService::notifyAndPush(
            (int)$claim['user_id'],
            'expense_paid',
            'Expense Claim Paid 💰',
            "Your expense claim '{$claim['title']}' has been marked as PAID. Please check your account.",
            'fa-money-bill-wave',
            '#10B981',
            'index.php?page=expense',
            'expense_paid'
        );

        AuditLog::record('EXPENSE_PAID', "Expense #{$id} marked as paid.");
        Helper::json('success', 'Expense claim marked as paid.');
    }

    public static function getClaim(): void {
        self::requireAnyExpenseAccess();

        $id = (int)($_GET['id'] ?? 0);
        $claim = ExpenseClaim::findById($id);
        if (!$claim) Helper::json('error', 'Not found.');

        if (!self::canAccessClaim($claim)) {
            Helper::json('error', 'Access Denied: You cannot view this expense claim.');
        }

        $items = ExpenseClaim::getItems($id);
        $categoryTotals = ExpenseClaim::getCategoryTotals($id);

        Helper::json('success', 'OK', [
            'claim' => $claim,
            'items' => $items,
            'category_totals' => $categoryTotals,
            'workflow' => [
                'stage' => $claim['workflow_stage'] ?? '',
                'label' => $claim['workflow_label'] ?? '',
                'applicant_signed' => !empty($claim['applicant_signed']),
                'finance_verified' => !empty($claim['approval_id']),
                'approval_status' => $claim['approval_status'] ?? null,
                'finance_forwarded_at' => $claim['finance_forwarded_at'] ?? null,
                'management_actioned_at' => $claim['management_actioned_at'] ?? null,
                'management_approver_name' => $claim['management_approver_name'] ?? null,
                'paid_at' => $claim['paid_at'] ?? null,
            ],
        ]);
    }

    public static function downloadItemReceipt(): void {
        self::requireAnyExpenseAccess();

        $itemId = (int)($_GET['item_id'] ?? 0);
        $item = ExpenseClaim::getItemById($itemId);
        if (!$item || empty($item['receipt_path'])) {
            http_response_code(404);
            exit('Receipt not found.');
        }

        $claim = ExpenseClaim::findById((int)$item['claim_id']);
        if (!$claim || !self::canAccessClaim($claim)) {
            http_response_code(403);
            exit('Access denied.');
        }

        self::streamPrivateReceipt((string)$item['receipt_path']);
    }

    public static function delete(): void {
        Auth::requirePermission('expense', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');

        $id = (int)($_POST['id'] ?? 0);
        $claim = ExpenseClaim::findById($id);

        if (!$claim) Helper::json('error', 'Not found.');
        if ((string)$claim['status'] !== 'pending') {
            Helper::json('error', 'Only a pending expense claim can be deleted.');
        }
        if (ExpenseClaim::hasPendingApproval($id)) {
            Helper::json('error', 'This claim is already in Approval Center and can no longer be deleted.');
        }

        $isOwner = (int)$claim['user_id'] === (int)Auth::id();
        if (!$isOwner && !Auth::hasPermission('expense', 'view_all')) {
            Helper::json('error', 'Access denied.');
        }
        self::requireClaimScope($claim);

        ExpenseClaim::delete($id);
        AuditLog::record('DELETE', "Deleted expense claim #{$id}: {$claim['title']}");
        Helper::json('success', 'Expense claim deleted.');
    }

    private static function validDate(string $date): bool {
        $parsed = DateTime::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private static function requireAnyExpenseAccess(): void {
        Auth::requireLogin();
        if (
            !Auth::hasPermission('expense', 'view') &&
            !Auth::hasPermission('expense', 'view_all') &&
            !Auth::hasPermission('expense', 'finance_check') &&
            !Auth::hasPermission('expense', 'approve') &&
            !Auth::hasPermission('expense', 'mark_paid')
        ) {
            Helper::json('error', 'Access denied.');
        }
    }

    private static function canAccessClaim(array $claim): bool {
        if ((int)$claim['user_id'] === (int)Auth::id()) return true;

        $hasWorkflowAccess =
            Auth::hasPermission('expense', 'view_all') ||
            Auth::hasPermission('expense', 'finance_check') ||
            Auth::hasPermission('expense', 'approve') ||
            Auth::hasPermission('expense', 'mark_paid');

        if (!$hasWorkflowAccess) return false;

        if (Auth::getScopeDepartmentId() === null) return true;
        $targetUser = User::findById((int)$claim['user_id']);
        return $targetUser !== null && Auth::canAccessUser($targetUser);
    }

    private static function requireClaimScope(array $claim): void {
        if (Auth::getScopeDepartmentId() === null) return;
        $targetUser = User::findById((int)$claim['user_id']);
        if (!$targetUser || !Auth::canAccessUser($targetUser)) {
            Helper::json('error', 'Access denied. This claim is outside your department scope.');
        }
    }

    private static function storeReceipt(array $file, int $itemNo): string {
        Helper::validateUpload($file, [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword', 'application/octet-stream'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'zip' => ['application/zip', 'application/x-zip-compressed'],
        ], 10 * 1024 * 1024);

        $uploadDirRelative = 'storage/private/expenses';
        $uploadDirAbsolute = __DIR__ . '/../' . $uploadDirRelative;

        if (!is_dir($uploadDirAbsolute) && !mkdir($uploadDirAbsolute, 0750, true) && !is_dir($uploadDirAbsolute)) {
            throw new RuntimeException('The receipt storage folder is unavailable.');
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename((string)$file['name']));
        if ($safeName === '' || $safeName === null) $safeName = 'receipt';

        $uniqueFilename = 'exp_item_' . $itemNo . '_' . bin2hex(random_bytes(8)) . '_' . $safeName;
        $absolute = $uploadDirAbsolute . '/' . $uniqueFilename;

        if (!move_uploaded_file((string)$file['tmp_name'], $absolute)) {
            throw new RuntimeException('Failed to save an uploaded receipt.');
        }

        return $uploadDirRelative . '/' . $uniqueFilename;
    }

    private static function streamPrivateReceipt(string $relativePath): void {
        $relativePath = str_replace('\\', '/', ltrim($relativePath, '/'));
        if ($relativePath === '' || str_contains($relativePath, '..') || !str_starts_with($relativePath, 'storage/private/expenses/')) {
            http_response_code(403);
            exit('Invalid receipt path.');
        }

        $root = realpath(__DIR__ . '/../storage/private/expenses');
        $absolute = realpath(__DIR__ . '/../' . $relativePath);

        if (!$root || !$absolute || !is_file($absolute) || !str_starts_with($absolute, $root . DIRECTORY_SEPARATOR)) {
            http_response_code(404);
            exit('Receipt not found.');
        }

        $mime = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = finfo_file($finfo, $absolute);
                if (is_string($detected) && $detected !== '') $mime = $detected;
                finfo_close($finfo);
            }
        }

        $inlineMimes = ['application/pdf', 'image/jpeg', 'image/png'];
        $disposition = in_array($mime, $inlineMimes, true) ? 'inline' : 'attachment';
        $filename = basename($absolute);

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($absolute));
        header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($filename) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($absolute);
        exit;
    }

    private static function deleteNewReceipt(?string $relativePath): void {
        $relativePath = str_replace('\\', '/', ltrim((string)$relativePath, '/'));
        if ($relativePath === '' || str_contains($relativePath, '..')
            || !str_starts_with($relativePath, 'storage/private/expenses/')) return;

        $absolute = realpath(__DIR__ . '/../' . $relativePath);
        $root = realpath(__DIR__ . '/../storage/private/expenses');

        if ($absolute && $root && is_file($absolute) && str_starts_with($absolute, $root . DIRECTORY_SEPARATOR)) {
            @unlink($absolute);
        }
    }

    private static function notifyFinalDecision(array $claim, string $decision, string $reason = ''): void {
        $notifTitle = 'Expense Claim ' . ucfirst($decision);
        $notifBody = "Your claim '{$claim['title']}' (MYR " . number_format((float)$claim['total_amount'], 2) . ") has been {$decision}.";
        if ($reason !== '') $notifBody .= " Reason: {$reason}";

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
    }
}
?>

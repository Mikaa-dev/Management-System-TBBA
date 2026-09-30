<?php
/**
 * FinanceRecordController — TBBA ERP
 * Company: The Bridge Business Alliance (TBBA)
 * Handles Sales & Purchases data records for Finance Officer
 */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../models/FinanceRecord.php';
require_once __DIR__ . '/../models/FinanceWorkflow.php';
require_once __DIR__ . '/../models/AuditLog.php';

class FinanceRecordController {
    /** Helper check to ensure user has general Finance view rights */
    private static function checkAccess(string $module = '', string $action = 'view'): void {
        Auth::requireLogin();
        if (!empty($module)) {
            if (!Auth::hasPermission($module, $action)) {
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    Helper::json('error', "Access Denied: You do not have permission ($action) for module ($module).");
                }
                die("Access Denied: Requires '$module ($action)' authorization.");
            }
        } else {
            if (!Auth::hasPermission('sales', 'view') && !Auth::hasPermission('purchases', 'view')) {
                die("Access Denied: Requires Finance Officer or Administrator authorization.");
            }
        }
    }

    /** Render Sales Page */
    public static function sales(): void {
        self::checkAccess('sales', 'view');
        $pageTitle  = 'Sales Operations & Records';
        $canEdit    = Auth::hasPermission('sales', 'create') || Auth::hasPermission('sales', 'edit');
        $docTypes   = FinanceRecord::getSalesTypes();
        $activeTab  = $_GET['tab'] ?? 'all';
        if (!array_key_exists($activeTab, $docTypes) && $activeTab !== 'all') {
            $activeTab = 'all';
        }

        $records = FinanceRecord::getAll('sales', $activeTab);
        $stats   = FinanceRecord::getStats('sales', $activeTab);
        $costCenters = FinanceWorkflow::costCenters();
        $currentTypeInfo = $docTypes[$activeTab] ?? ['label' => 'All Sales Records', 'icon' => 'fa-chart-line', 'color' => '#3B82F6'];

        include __DIR__ . '/../views/finance/sales.php';
    }

    /** Render Purchases Page */
    public static function purchases(): void {
        self::checkAccess('purchases', 'view');
        $pageTitle  = 'Purchases Operations & Records';
        $canEdit    = Auth::hasPermission('purchases', 'create') || Auth::hasPermission('purchases', 'edit');
        $docTypes   = FinanceRecord::getPurchaseTypes();
        $activeTab  = $_GET['tab'] ?? 'all';
        if (!array_key_exists($activeTab, $docTypes) && $activeTab !== 'all') {
            $activeTab = 'all';
        }

        $records = FinanceRecord::getAll('purchases', $activeTab);
        $stats   = FinanceRecord::getStats('purchases', $activeTab);
        $costCenters = FinanceWorkflow::costCenters();
        $currentTypeInfo = $docTypes[$activeTab] ?? ['label' => 'All Purchase Records', 'icon' => 'fa-cart-shopping', 'color' => '#10B981'];

        include __DIR__ . '/../views/finance/purchases.php';
    }

    /** AJAX: Save or Update Record */
    public static function save_record(): void {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::json('error', 'Invalid request method.');
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) {
            Helper::json('error', 'Invalid CSRF token security.');
        }

        $id            = (int)($_POST['id'] ?? 0);
        $requestedCategory = trim($_POST['category'] ?? 'sales');
        if (!in_array($requestedCategory, ['sales', 'purchases'], true)) {
            Helper::json('error', 'Invalid finance record category.');
        }

        $existing = null;
        $category = $requestedCategory;
        if ($id > 0) {
            $existing = FinanceRecord::findById($id);
            if (!$existing) {
                Helper::json('error', 'Financial record not found.');
            }
            $category = (string)$existing['category'];
            if ($requestedCategory !== $category) {
                Helper::json('error', 'The submitted category does not match the existing financial record.');
            }
        }
        $targetModule  = ($category === 'purchases') ? 'purchases' : 'sales';
        $requiredAction= ($id > 0) ? 'edit' : 'create';

        if (!Auth::hasPermission($targetModule, $requiredAction)) {
            Helper::json('error', "Access Denied: You do not have permission to $requiredAction $targetModule records.");
        }

        $document_type = trim($_POST['document_type'] ?? 'quotations');
        $documentTypes = $category === 'purchases'
            ? FinanceRecord::getPurchaseTypes()
            : FinanceRecord::getSalesTypes();
        if (!array_key_exists($document_type, $documentTypes)) {
            Helper::json('error', 'Invalid document type for this finance category.');
        }
        $reference_no  = trim($_POST['reference_no'] ?? '');
        $party_name    = trim($_POST['party_name'] ?? '');
        $document_date = trim($_POST['document_date'] ?? date('Y-m-d'));
        $due_date      = trim($_POST['due_date'] ?? '');
        $title         = trim($_POST['title'] ?? '');
        $amount        = (float)($_POST['amount'] ?? 0);
        $tax_amount    = (float)($_POST['tax_amount'] ?? 0);
        $total_amount  = $amount + $tax_amount;
        $tax_rate      = $amount > 0 ? round(($tax_amount / $amount) * 100, 3) : 0;
        $currency      = strtoupper(trim($_POST['currency'] ?? 'MYR'));
        $projectRef    = trim($_POST['project_ref'] ?? '');
        $costCenterId  = (int)($_POST['cost_center_id'] ?? 0);
        $status        = trim($_POST['status'] ?? 'draft');
        $notes         = trim($_POST['notes'] ?? '');
        $userId        = (int)Auth::id();

        if (empty($reference_no)) {
            Helper::json('error', 'Reference Number is required.');
        }
        if (empty($party_name)) {
            Helper::json('error', 'Customer/Supplier Name is required.');
        }
        if (empty($title)) {
            Helper::json('error', 'Title / Description is required.');
        }
        if (!self::validDate($document_date) || ($due_date !== '' && !self::validDate($due_date))) {
            Helper::json('error', 'Please enter valid document and due dates.');
        }
        if ($due_date !== '' && $due_date < $document_date) {
            Helper::json('error', 'Due date cannot be earlier than the document date.');
        }
        if ($amount <= 0 || $tax_amount < 0) {
            Helper::json('error', 'Amount must be positive and tax cannot be negative.');
        }
        $allowedStatuses = $category === 'sales'
            ? ['draft','sent','confirmed','delivered','in_transit','paid','completed','processed','issued','overdue','cancelled']
            : ['draft','approved','pending','received','pending_payment','paid','completed','overdue','cancelled'];
        if (!in_array($status, $allowedStatuses, true)) Helper::json('error', 'Invalid finance record status.');
        if (FinanceRecord::isReferenceTaken($reference_no, $id > 0 ? $id : null)) {
            Helper::json('error', 'Reference Number is already in use.');
        }
        if ($existing && ((float)($existing['paid_amount'] ?? 0) > 0
            || in_array((string)$existing['status'], ['paid','completed','processed','received','delivered'], true))) {
            Helper::json('error', 'Posted or paid financial records are immutable. Create an adjustment or credit note instead.');
        }

        // Handle Supporting Document upload
        $attachmentPath = null;
        if ($id > 0) {
            $attachmentPath = $existing['attachment'] ?? null;
        }

        $attachmentError = $_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($attachmentError !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['attachment'];
            try {
                $ext = Helper::validateUpload($file, [
                    'pdf' => ['application/pdf'],
                    'doc' => ['application/msword', 'application/octet-stream'],
                    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
                    'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
                    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
                    'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
                    'png' => ['image/png'],
                    'jpg' => ['image/jpeg'],
                    'jpeg' => ['image/jpeg'],
                ], 15 * 1024 * 1024);
            } catch (InvalidArgumentException $e) {
                Helper::json('error', $e->getMessage());
            }
            // Finance evidence is stored outside the public uploads directory and
            // can only be streamed through the permission-checked download action.
            $uploadDir = __DIR__ . '/../storage/private/finance/';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true) && !is_dir($uploadDir)) {
                Helper::json('error', 'The private finance storage folder is unavailable.');
            }
            $newFileName = 'FIN_' . strtoupper(substr($category, 0, 3)) . '_' . time() . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $newFileName)) {
                @chmod($uploadDir . $newFileName, 0640);
                $attachmentPath = 'storage/private/finance/' . $newFileName;
            } else {
                Helper::json('error', 'Failed to save uploaded supporting document to server.');
            }
        }

        $data = [
            'category'      => $category,
            'document_type' => $document_type,
            'reference_no'  => $reference_no,
            'party_name'    => $party_name,
            'document_date' => $document_date,
            'due_date'      => $due_date,
            'title'         => $title,
            'amount'        => $amount,
            'tax_amount'    => $tax_amount,
            'tax_rate'      => $tax_rate,
            'total_amount'  => $total_amount,
            'currency'      => preg_match('/^[A-Z]{3,10}$/', $currency) ? $currency : 'MYR',
            'project_ref'   => $projectRef ?: null,
            'cost_center_id'=> $costCenterId > 0 ? $costCenterId : null,
            'status'        => $status,
            'notes'         => $notes,
            'attachment'    => $attachmentPath,
            'created_by'    => $userId
        ];

        if ($id > 0) {
            try {
                FinanceRecord::update($id, $data);
            } catch (Throwable $e) {
                self::deleteNewAttachment($attachmentPath, $existing['attachment'] ?? null);
                throw $e;
            }
            self::deleteNewAttachment($existing['attachment'] ?? null, $attachmentPath);
            if (class_exists('AuditLog')) {
                AuditLog::record('UPDATE_FINANCE_RECORD', "Updated $category record: $reference_no");
            }
            Helper::json('success', 'Financial record updated successfully.');
        } else {
            try {
                $newId = FinanceRecord::create($data);
            } catch (Throwable $e) {
                self::deleteNewAttachment($attachmentPath, null);
                if ($e instanceof PDOException && $e->getCode() === '23000') {
                    Helper::json('error', 'Reference Number is already in use.');
                }
                throw $e;
            }
            if (class_exists('AuditLog')) {
                AuditLog::record('CREATE_FINANCE_RECORD', "Created $category record: $reference_no");
            }
            Helper::json('success', 'Financial record created successfully.');
        }
    }

    /** AJAX: Delete Record */
    public static function delete_record(): void {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::json('error', 'Invalid request method.');
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) {
            Helper::json('error', 'Invalid CSRF token security.');
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            Helper::json('error', 'Invalid record ID.');
        }

        $record = FinanceRecord::findById($id);
        if (!$record) {
            Helper::json('error', 'Record not found.');
        }

        $targetModule = ($record['category'] === 'purchases') ? 'purchases' : 'sales';
        if (!Auth::hasPermission($targetModule, 'delete')) {
            Helper::json('error', "Access Denied: You do not have permission to delete $targetModule records.");
        }

        if ((string)$record['status'] !== 'draft' || (float)($record['paid_amount'] ?? 0) > 0
            || FinanceRecord::hasFinancialDependencies($id)) {
            Helper::json('error', 'Only an unposted draft without payments or linked documents can be deleted.');
        }

        if (!FinanceRecord::delete($id)) Helper::json('error', 'This record can no longer be deleted.');
        self::deleteNewAttachment($record['attachment'] ?? null, null);
        if (class_exists('AuditLog')) {
            AuditLog::record('DELETE_FINANCE_RECORD', "Deleted record: " . $record['reference_no']);
        }
        Helper::json('success', 'Financial record deleted successfully.');
    }

    /** AJAX: Get single record for edit modal */
    public static function get_record(): void {
        Auth::requireLogin();
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            Helper::json('error', 'Invalid record ID.');
        }
        $record = FinanceRecord::findById($id);
        if (!$record) {
            Helper::json('error', 'Record not found.');
        }
        $targetModule = ($record['category'] === 'purchases') ? 'purchases' : 'sales';
        if (!Auth::hasPermission($targetModule, 'view')) {
            Helper::json('error', "Access Denied: You do not have permission to view $targetModule records.");
        }
        Helper::json('success', 'OK', $record);
    }

    /** Permission-checked finance attachment delivery. */
    public static function downloadAttachment(): void {
        Auth::requireLogin();
        $id = (int)($_GET['id'] ?? 0);
        $record = FinanceRecord::findById($id);
        if (!$record || empty($record['attachment'])) {
            http_response_code(404);
            die('Supporting document not found.');
        }
        $module = $record['category'] === 'purchases' ? 'purchases' : 'sales';
        if (!Auth::hasPermission($module, 'view') && !Auth::hasPermission('finance_control', 'view')) {
            http_response_code(403);
            die('Access denied.');
        }

        $relative = str_replace('\\', '/', ltrim((string)$record['attachment'], '/'));
        $allowedPrefixes = ['storage/private/finance/', 'uploads/finance/'];
        $allowed = false;
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($relative, $prefix)) { $allowed = true; break; }
        }
        if (!$allowed || str_contains($relative, '..')) {
            http_response_code(400);
            die('Invalid file path.');
        }
        $path = realpath(__DIR__ . '/../' . $relative);
        $financePrivate = realpath(__DIR__ . '/../storage/private/finance');
        $financeLegacy = realpath(__DIR__ . '/../uploads/finance');
        if (!$path || !is_file($path) ||
            !(($financePrivate && str_starts_with($path, $financePrivate . DIRECTORY_SEPARATOR)) ||
              ($financeLegacy && str_starts_with($path, $financeLegacy . DIRECTORY_SEPARATOR)))) {
            http_response_code(404);
            die('Supporting document not found.');
        }
        $mime = mime_content_type($path) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    private static function validDate(string $date): bool {
        $parsed = DateTime::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    /** Delete only a replaced/new finance attachment inside an approved storage root. */
    private static function deleteNewAttachment(?string $candidate, ?string $keep): void {
        $candidate = str_replace('\\', '/', ltrim((string)$candidate, '/'));
        $keep = str_replace('\\', '/', ltrim((string)$keep, '/'));
        if ($candidate === '' || $candidate === $keep || str_contains($candidate, '..')) return;
        if (!str_starts_with($candidate, 'storage/private/finance/') && !str_starts_with($candidate, 'uploads/finance/')) return;
        $absolute = realpath(__DIR__ . '/../' . $candidate);
        $privateRoot = realpath(__DIR__ . '/../storage/private/finance');
        $legacyRoot = realpath(__DIR__ . '/../uploads/finance');
        if ($absolute && is_file($absolute)
            && (($privateRoot && str_starts_with($absolute, $privateRoot . DIRECTORY_SEPARATOR))
                || ($legacyRoot && str_starts_with($absolute, $legacyRoot . DIRECTORY_SEPARATOR)))) {
            @unlink($absolute);
        }
    }
}

<?php
/**
 * Controller Pengurusan Tender & KPI Tracker - AJAX
 * Syarikat: The Bridge Business Alliance (TBBA)
 */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../models/Tender.php';
require_once __DIR__ . '/../models/AuditLog.php';

class TenderController {
    // Pengendali Hantar Borang Tender Baharu (AJAX dengan muat naik fail)
    public static function store() {
        Auth::requirePermission('tenders', 'create');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::json('error', 'Invalid request method.');
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) {
            Helper::json('error', 'Invalid CSRF token security.');
        }

        $user = Auth::user();

        $projectName  = trim($_POST['project_name'] ?? '');
        $clientName   = trim($_POST['client_name'] ?? '');
        $projectValue = isset($_POST['project_value']) ? (float)$_POST['project_value'] : 0;
        $closingDate  = trim($_POST['closing_date'] ?? '');

        if (empty($projectName) || empty($clientName) || $projectValue <= 0 || empty($closingDate)) {
            Helper::json('error', 'Please fill in all required tender details (Project Name, Client, RM Value, and Closing Date) validly.');
        }
        $parsedClosingDate = DateTime::createFromFormat('!Y-m-d', $closingDate);
        if (!$parsedClosingDate || $parsedClosingDate->format('Y-m-d') !== $closingDate) {
            Helper::json('error', 'Please enter a valid closing date.');
        }

        // Pengurusan Muat Naik Fail Dokumen Tender
        $attachmentPath = null;
        if (!empty($_FILES['attachment']['name'])) {
            $file = $_FILES['attachment'];
            try {
                $ext = Helper::validateUpload($file, [
                    'pdf' => ['application/pdf'],
                    'doc' => ['application/msword', 'application/octet-stream'],
                    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
                    'zip' => ['application/zip', 'application/x-zip-compressed'],
                    'png' => ['image/png'],
                    'jpg' => ['image/jpeg'],
                    'jpeg' => ['image/jpeg'],
                ], 5 * 1024 * 1024);
            } catch (InvalidArgumentException $e) {
                Helper::json('error', $e->getMessage());
            }

            $uploadDir = __DIR__ . '/../storage/private/tenders/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0750, true);
            }

            $newFileName = 'TENDER_' . time() . '_' . uniqid() . '.' . $ext;
            $destination = $uploadDir . $newFileName;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $attachmentPath = 'storage/private/tenders/' . $newFileName;
            } else {
                Helper::json('error', 'Failed to upload attachment file to server.');
            }
        }

        $monthYear = !empty($_POST['month_year']) && preg_match('/^\d{4}-\d{2}$/', $_POST['month_year'])
            ? trim($_POST['month_year'])
            : date('Y-m');

        $tenderData = [
            'user_id'       => $user['id'],
            'project_name'  => $projectName,
            'client_name'   => $clientName,
            'project_value' => $projectValue,
            'closing_date'  => $closingDate,
            'attachment'    => $attachmentPath,
            'status'        => 'submitted',
            'month_year'    => $monthYear
        ];

        try {
            $newId = Tender::create($tenderData);
        } catch (Throwable $e) {
            self::deleteStoredTenderFile($attachmentPath);
            throw $e;
        }
        $kpi   = Tender::getKpi($user['id'], $monthYear);

        AuditLog::record('CREATE', "Submitted new tender project '{$projectName}' (RM " . number_format((float)$projectValue, 2) . ") for client '{$clientName}'");

        Helper::json('success', "New tender '$projectName' successfully submitted! This month's KPI increased to {$kpi['percentage']}%.", [
            'id'            => $newId,
            'project_name'  => htmlspecialchars($projectName),
            'client_name'   => htmlspecialchars($clientName),
            'project_value' => Helper::rm($projectValue),
            'closing_date'  => Helper::date($closingDate, 'd M Y'),
            'attachment'    => $attachmentPath ? Helper::url('index.php?action=download_attachment&type=tender&id=' . $newId) : null,
            'status'        => 'submitted',
            'staff_name'    => htmlspecialchars($user['name']),
            'kpi'           => $kpi
        ]);
    }

    // Kemas kini Status Tender (AJAX POST - Admin & Pemilik Tender)
    public static function updateStatus() {
        Auth::requirePermission('tenders', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::json('error', 'Invalid request method.');
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) {
            Helper::json('error', 'Invalid CSRF token security.');
        }

        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $summary = isset($_POST['post_mortem_summary']) ? trim($_POST['post_mortem_summary']) : null;
        $factors = isset($_POST['post_mortem_factors']) ? trim($_POST['post_mortem_factors']) : null;
        $swotS = isset($_POST['swot_s']) ? trim($_POST['swot_s']) : null;
        $swotW = isset($_POST['swot_w']) ? trim($_POST['swot_w']) : null;
        $swotO = isset($_POST['swot_o']) ? trim($_POST['swot_o']) : null;
        $swotT = isset($_POST['swot_t']) ? trim($_POST['swot_t']) : null;

        $allowedStatuses = ['in_progress', 'submitted', 'won', 'lost'];
        if (!$id || !in_array($status, $allowedStatuses, true)) {
            Helper::json('error', 'Invalid tender ID or status.');
        }
        if (in_array($status, ['won', 'lost'], true)
            && !Auth::hasPermission('tenders', 'approve')) {
            Helper::json('error', 'You do not have permission to record the final tender result.');
        }

        $tender = Tender::getById($id);
        if (!$tender) {
            Helper::json('error', 'Tender record not found.');
        }

        if (!Auth::hasPermission('tenders', 'edit') && $tender['user_id'] != Auth::id()) {
            Helper::json('error', 'You can only change the status of your own tender records.');
        }

        $currentStatus = (string)$tender['status'];
        $isPostMortemSave = in_array($currentStatus, ['won', 'lost'], true)
            && $status === $currentStatus
            && ($summary !== null || $factors !== null || $swotS !== null || $swotW !== null || $swotO !== null || $swotT !== null);

        if (in_array($currentStatus, ['won', 'lost'], true) && !$isPostMortemSave) {
            Helper::json('error', 'Won and Lost are final statuses and cannot be changed.');
        }

        $validTransition = ($currentStatus === 'in_progress' && $status === 'submitted')
            || ($currentStatus === 'submitted' && in_array($status, ['won', 'lost'], true));
        if (!$validTransition && !$isPostMortemSave) {
            Helper::json('error', 'Invalid tender workflow. Use In Progress, then Submitted, followed by Won or Lost.');
        }
        if ($currentStatus === 'in_progress' && $status === 'submitted'
            && !empty($tender['tender_opportunity_id'])
            && ($tender['indicative_price'] === null || $tender['cost'] === null || $tender['selling_price'] === null)) {
            Helper::json('error', 'Complete Project Pricing before submitting this tender.');
        }

        if (Tender::updateStatus($id, $status, $summary, $factors, $swotS, $swotW, $swotO, $swotT)) {
            $descStr = "Updated status of tender #{$id} ('{$tender['project_name']}') to '" . strtoupper($status) . "'";
            if ($summary || $swotS) $descStr .= " and completed SWOT Post Mortem analysis";
            AuditLog::record('UPDATE', $descStr);

            Helper::json('success', 'Tender status successfully updated!');
        } else {
            Helper::json('error', 'No status change or record not found.');
        }
    }

    // Padam Rekod Tender (AJAX POST - Admin atau Pemilik)
    public static function delete() {
        Auth::requirePermission('tenders', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::json('error', 'Invalid request method.');
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) {
            Helper::json('error', 'Invalid CSRF token security.');
        }

        $id = (int)($_POST['id'] ?? 0);
        $tender = Tender::getById($id);
        if (!$tender) {
            Helper::json('error', 'Tender record not found.');
        }

        if (!Auth::hasPermission('tenders', 'delete') && $tender['user_id'] != Auth::id()) {
            Helper::json('error', 'You can only delete your own tender records.');
        }
        if (in_array((string)$tender['status'], ['won', 'lost'], true)) {
            Helper::json('error', 'Finalized tender records cannot be deleted.');
        }

        if (Tender::delete($id)) {
            self::deleteStoredTenderFile($tender['attachment'] ?? null);
            self::deleteStoredTenderFile($tender['pricing_attachment'] ?? null);
            AuditLog::record('DELETE', "Deleted tender record #{$id} ('{$tender['project_name']}')");
            Helper::json('success', 'Tender record permanently deleted.');
        } else {
            Helper::json('error', 'Failed to delete tender record.');
        }
    }

    private static function deleteStoredTenderFile(?string $relativePath): void {
        $relativePath = str_replace('\\', '/', ltrim((string)$relativePath, '/'));
        if ($relativePath === '' || str_contains($relativePath, '..')) return;
        $prefixes = ['storage/private/tenders/', 'uploads/tenders/'];
        if (!array_filter($prefixes, fn($prefix) => str_starts_with($relativePath, $prefix))) return;
        $absolute = realpath(__DIR__ . '/../' . $relativePath);
        $roots = [realpath(__DIR__ . '/../storage/private/tenders'), realpath(__DIR__ . '/../uploads/tenders')];
        foreach (array_filter($roots) as $root) {
            if ($absolute && is_file($absolute) && str_starts_with($absolute, $root . DIRECTORY_SEPARATOR)) {
                @unlink($absolute);
                break;
            }
        }
    }
}
?>

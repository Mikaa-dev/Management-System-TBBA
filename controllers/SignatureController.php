<?php
/**
 * Signature Controller — TBBA ERP E-Sign Module v3
 * Endpoint AJAX untuk semua operasi digital signature.
 * Expense workflow: Staff sign -> Finance verify -> Boss sign -> final approval.
 */
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../core/SignatureService.php';
require_once __DIR__ . '/../models/Signature.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../models/User.php';

class SignatureController {

    // ─── POST: Simpan Signature ────────────────────────────────────────────────
    /**
     * AJAX endpoint untuk terima dan simpan signature dari canvas.
     *
     * POST params:
     *   - csrf_token      string   Token CSRF
     *   - signature_data  string   Base64 PNG dari signature pad
     *   - document_type   string   'leave_request' atau 'expense_claim'
     *   - document_id     int      ID dokumen
     *   - signer_role     string   'applicant' atau 'approver'
     */
    public static function sign(): void {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token.');

        $signatureData = trim($_POST['signature_data'] ?? '');
        $documentType  = trim($_POST['document_type'] ?? '');
        $documentId    = (int)($_POST['document_id'] ?? 0);
        $signerRole    = trim($_POST['signer_role'] ?? 'approver');

        // ── Validate ──
        if (empty($signatureData))  Helper::json('error', 'Signature data is required.');
        if (empty($documentType))   Helper::json('error', 'Document type is required.');
        if (!$documentId)           Helper::json('error', 'Invalid document ID.');
        if (!in_array($signerRole, ['applicant', 'approver'])) {
            Helper::json('error', 'Invalid signer role.');
        }

        $userId = (int) Auth::id();
        $document = self::getDocumentRecord($documentType, $documentId);
        if (!$document) Helper::json('error', 'Document not found.');

        // ── Authorization check ──
        // Applicant hanya boleh sign dokumen mereka sendiri
        if ($signerRole === 'applicant') {
            $ownerCheck = self::checkDocumentOwner($documentType, $documentId, $userId);
            if (!$ownerCheck) {
                Helper::json('error', 'You may only sign your own document.');
            }
            if ((string)($document['status'] ?? '') !== 'pending') {
                Helper::json('error', 'The applicant may only sign while the document is pending approval.');
            }
        }

        // Approver mesti ada permission untuk approve.
        // Leave kekalkan flow lama: approve dahulu, kemudian sign.
        // Expense v3 guna flow baru: Finance verify -> boss sign -> final approve.
        if ($signerRole === 'approver') {
            $canApprove = match($documentType) {
                'leave_request' => Auth::hasPermission('leave', 'approve'),
                'expense_claim' => Auth::hasPermission('expense', 'approve'),
                default         => false,
            };

            if (!$canApprove || !self::canAccessDocument($documentType, $documentId)) {
                Helper::json('error', 'You do not have permission to sign as an approver.');
            }

            if ($documentType === 'expense_claim') {
                // Expense approval is signature-first. The claim must still be pending,
                // and Finance must already have forwarded it to Approval Center.
                if ((string)($document['status'] ?? '') !== 'pending') {
                    Helper::json('error', 'Only a pending expense claim may be signed for approval.');
                }

                if (!self::expenseIsAwaitingApproval($documentId)) {
                    Helper::json('error', 'Finance must verify and send this expense claim to Approval Center before it can be signed.');
                }
            } else {
                // Preserve the existing Leave workflow.
                if ((string)($document['status'] ?? '') !== 'approved'
                    || (int)($document['approved_by'] ?? 0) !== $userId) {
                    Helper::json('error', 'Only the recorded approver may sign an approved document.');
                }
            }
        }

        // ── Process Signature ──
        $result = SignatureService::processSignature(
            $signatureData,
            $documentType,
            $documentId,
            $userId,
            $signerRole,
            $_SERVER['REMOTE_ADDR'] ?? ''
        );

        if (!$result['success']) {
            Helper::json('error', $result['message']);
        }

        // ── Audit Log ──
        AuditLog::record(
            'ESIGN',
            "E-Sign: User #{$userId} signed '{$documentType}' #{$documentId} as '{$signerRole}' from IP " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
        );

        Helper::json('success', 'Signature saved successfully.', [
            'signature_url' => $result['signature_url'],
            'signed_at'     => date('Y-m-d H:i:s'),
            'signer_role'   => $signerRole,
        ]);
    }

    // ─── GET: Dapatkan Status Signature ───────────────────────────────────────
    /**
     * Dapatkan status signature untuk sesebuah dokumen.
     * Digunakan oleh UI untuk papar badge dan preview.
     *
     * GET params:
     *   - document_type  string
     *   - document_id    int
     */
    public static function getStatus(): void {
        Auth::requireLogin();

        $documentType = trim($_GET['document_type'] ?? '');
        $documentId   = (int)($_GET['document_id'] ?? 0);

        if (!$documentType || !$documentId) {
            Helper::json('error', 'Required parameters are missing.');
        }
        if (!self::canAccessDocument($documentType, $documentId)) {
            Helper::json('error', 'Access denied.');
        }

        $status = Signature::getStatus($documentType, $documentId);

        // Tambah URL penuh untuk setiap signature image
        foreach ($status['signatures'] as &$sig) {
            $sig['signature_full_url'] = Helper::url('index.php?action=download_signature_image&id=' . (int)$sig['id']);
            $sig['signed_at_formatted'] = date('d M Y, h:i A', strtotime($sig['signed_at']));
        }

        Helper::json('success', 'OK', $status);
    }

    /** Stream a signature image only to users who may view its document. */
    public static function downloadImage(): void {
        Auth::requireLogin();
        $id = (int)($_GET['id'] ?? 0);
        $signature = $id > 0
            ? Database::query("SELECT * FROM signatures WHERE id=? LIMIT 1", [$id])->fetch()
            : false;
        if (!$signature || !self::canAccessDocument(
            (string)$signature['document_type'],
            (int)$signature['document_id']
        )) {
            http_response_code(404);
            exit('Signature not found.');
        }

        $absolutePath = realpath(__DIR__ . '/../' . ltrim((string)$signature['signature_url'], '/'));
        $allowedRoots = [
            realpath(__DIR__ . '/../uploads/signatures'),
            realpath(__DIR__ . '/../storage/private/signatures'),
        ];
        $allowed = false;
        foreach (array_filter($allowedRoots) as $root) {
            if ($absolutePath && str_starts_with($absolutePath, $root . DIRECTORY_SEPARATOR)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed || !$absolutePath || !is_file($absolutePath)) {
            http_response_code(404);
            exit('Signature not found.');
        }

        header('Content-Type: image/png');
        header('Content-Length: ' . filesize($absolutePath));
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        readfile($absolutePath);
        exit;
    }

    // ─── POST: Generate PDF ───────────────────────────────────────────────────
    /**
     * Generate PDF rasmi dengan embedded signatures.
     *
     * POST params:
     *   - csrf_token     string
     *   - document_type  string
     *   - document_id    int
     */
    public static function generatePdf(): void {
        Auth::requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token.');

        $documentType = trim($_POST['document_type'] ?? '');
        $documentId   = (int)($_POST['document_id'] ?? 0);

        if (!$documentType || !$documentId) {
            Helper::json('error', 'Required parameters are missing.');
        }
        if (!self::canAccessDocument($documentType, $documentId)) {
            Helper::json('error', 'Access denied.');
        }

        $document = self::getDocumentRecord($documentType, $documentId);
        $allowedStatuses = $documentType === 'expense_claim' ? ['approved', 'paid'] : ['approved'];
        if (!$document || !in_array((string)($document['status'] ?? ''), $allowedStatuses, true)) {
            Helper::json('error', 'Only an approved document can generate an approval PDF.');
        }
        $signatureStatus = Signature::getStatus($documentType, $documentId);
        if (empty($signatureStatus['fully_signed'])) {
            Helper::json('error', 'Both applicant and approver signatures are required.');
        }
        $verification = SignatureService::verifyAll($documentType, $documentId);
        if (!$verification || !array_reduce($verification, fn($valid, $row) => $valid && !empty($row['valid']), true)) {
            Helper::json('error', 'Signature integrity verification failed. The PDF was not generated.');
        }

        $result = SignatureService::generateSignedPdf($documentType, $documentId);

        if (!$result['success']) {
            Helper::json('error', $result['message']);
        }

        AuditLog::record('PDF_GENERATE', "Generated signed PDF for '{$documentType}' #{$documentId}");

        Helper::json('success', 'PDF generated successfully.', [
            'pdf_path'    => $result['pdf_path'],
            'filename'    => $result['filename'],
            'download_url'=> Helper::url('index.php?action=download_signed_pdf&type=' . urlencode($documentType) . '&id=' . $documentId),
        ]);
    }

    // ─── GET: Download PDF ────────────────────────────────────────────────────
    /**
     * Serve PDF untuk download.
     * Semak authorization sebelum serve.
     *
     * GET params:
     *   - type  string  document_type
     *   - id    int     document_id
     */
    public static function downloadPdf(): void {
        Auth::requireLogin();

        $documentType = trim($_GET['type'] ?? '');
        $documentId   = (int)($_GET['id'] ?? 0);

        if (!$documentType || !$documentId) {
            http_response_code(400);
            echo 'Required parameters are missing.';
            exit;
        }

        // Dapatkan path PDF dari database
        $pdfPath = self::getSignedPdfPath($documentType, $documentId);

        if (!$pdfPath) {
            http_response_code(404);
            echo 'The PDF has not been generated. Please generate it first.';
            exit;
        }

        $absolutePath = realpath(__DIR__ . '/../' . ltrim((string)$pdfPath, '/'));
        $allowedRoots = [
            realpath(__DIR__ . '/../uploads/signed_pdfs'),
            realpath(__DIR__ . '/../storage/private/signed_pdfs'),
        ];
        $allowed = false;
        foreach (array_filter($allowedRoots) as $root) {
            if ($absolutePath && str_starts_with($absolutePath, $root . DIRECTORY_SEPARATOR)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed || !$absolutePath || !is_file($absolutePath)) {
            http_response_code(404);
            echo 'PDF file not found.';
            exit;
        }

        if (!self::canAccessDocument($documentType, $documentId)) {
            http_response_code(403);
            echo 'Access denied.';
            exit;
        }

        // Serve PDF
        $filename = basename($absolutePath);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($absolutePath));
        header('Cache-Control: no-cache, must-revalidate');
        readfile($absolutePath);
        exit;
    }

    // ─── POST: Verify Signature ───────────────────────────────────────────────
    /**
     * Verify integriti semua signatures untuk sesebuah dokumen.
     *
     * GET params:
     *   - document_type  string
     *   - document_id    int
     */
    public static function verify(): void {
        Auth::requireLogin();

        $documentType = trim($_GET['document_type'] ?? $_POST['document_type'] ?? '');
        $documentId   = (int)($_GET['document_id'] ?? $_POST['document_id'] ?? 0);

        if (!$documentType || !$documentId) {
            Helper::json('error', 'Required parameters are missing.');
        }
        if (!self::canAccessDocument($documentType, $documentId)) {
            Helper::json('error', 'Access denied.');
        }

        $results = SignatureService::verifyAll($documentType, $documentId);

        if (empty($results)) {
            Helper::json('success', 'This document has no signatures.', [
                'verified' => false,
                'results'  => [],
            ]);
        }

        $allValid = array_reduce($results, fn($carry, $r) => $carry && $r['valid'], true);

        Helper::json('success', $allValid ? 'All signatures are valid ✓' : '⚠️ One or more signatures are invalid.', [
            'verified' => $allValid,
            'results'  => $results,
        ]);
    }

    // ─── Helper: Check Document Owner ─────────────────────────────────────────

    /**
     * Semak sama ada user adalah pemilik dokumen.
     */
    private static function checkDocumentOwner(string $documentType, int $documentId, int $userId): bool {
        try {
            $tableName = match($documentType) {
                'leave_request'  => 'leave_requests',
                'expense_claim'  => 'expense_claims',
                default          => null,
            };

            if (!$tableName) return false;

            $row = Database::query(
                "SELECT user_id FROM `{$tableName}` WHERE id = ? LIMIT 1",
                [$documentId]
            )->fetch();

            return $row && (int)$row['user_id'] === $userId;
        } catch (Exception $e) {
            return false;
        }
    }

    private static function getDocumentRecord(string $documentType, int $documentId): ?array {
        $tableName = match($documentType) {
            'leave_request' => 'leave_requests',
            'expense_claim' => 'expense_claims',
            default => null,
        };
        if (!$tableName || $documentId <= 0) return null;
        $row = Database::query("SELECT * FROM `{$tableName}` WHERE id=? LIMIT 1", [$documentId])->fetch();
        return $row ?: null;
    }

    /**
     * Match document ownership and permission scope before exposing e-sign data.
     *
     * Expense v3 separates "view all", Finance review, final approval and payment
     * permissions, so we cannot rely only on Auth::canAccessOwnedResource(), which
     * historically grants cross-user access only through the module approve permission.
     */
    private static function canAccessDocument(string $documentType, int $documentId): bool {
        try {
            [$tableName, $module] = match($documentType) {
                'leave_request' => ['leave_requests', 'leave'],
                'expense_claim' => ['expense_claims', 'expense'],
                default         => [null, null],
            };

            if (!$tableName || !$module || $documentId <= 0) return false;

            $ownerId = Database::query(
                "SELECT user_id FROM `{$tableName}` WHERE id = ? LIMIT 1",
                [$documentId]
            )->fetchColumn();

            if ($ownerId === false) return false;

            $ownerId = (int)$ownerId;

            // Document owner may always access their own e-sign information.
            if ($ownerId === (int)Auth::id()) return true;

            // Leave keeps the original RBAC behaviour.
            if ($documentType === 'leave_request') {
                return Auth::canAccessOwnedResource($ownerId, 'leave');
            }

            // Expense v3: allow users who are explicitly permitted to view/review/
            // approve/pay other staff claims.
            $hasExpenseAccess =
                Auth::hasPermission('expense', 'view_all')
                || Auth::hasPermission('expense', 'finance_check')
                || Auth::hasPermission('expense', 'approve')
                || Auth::hasPermission('expense', 'mark_paid');

            if (!$hasExpenseAccess) return false;

            // Global-scope users may access all staff records.
            if (Auth::getScopeDepartmentId() === null) return true;

            // Department-scoped users must still be allowed to access the owner.
            $owner = User::findById($ownerId);
            return $owner !== null && Auth::canAccessUser($owner);

        } catch (Throwable $e) {
            return false;
        }
    }

    /** Expense claim is signable by the boss only after Finance forwards it. */
    private static function expenseIsAwaitingApproval(int $documentId): bool {
        if ($documentId <= 0) return false;

        try {
            return (bool)Database::query(
                "SELECT 1
                 FROM approvals
                 WHERE module='expense'
                   AND record_id=?
                   AND status='pending'
                 ORDER BY id DESC
                 LIMIT 1",
                [$documentId]
            )->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Dapatkan path PDF yang telah digenerate dari database.
     */
    private static function getSignedPdfPath(string $documentType, int $documentId): ?string {
        try {
            $tableName = match($documentType) {
                'leave_request'  => 'leave_requests',
                'expense_claim'  => 'expense_claims',
                default          => null,
            };

            if (!$tableName) return null;

            $row = Database::query(
                "SELECT signed_pdf_path FROM `{$tableName}` WHERE id = ? LIMIT 1",
                [$documentId]
            )->fetch();

            return $row['signed_pdf_path'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }
}
?>

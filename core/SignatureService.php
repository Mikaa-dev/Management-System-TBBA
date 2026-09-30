<?php
/**
 * SignatureService — TBBA ERP E-Sign Core Service
 * =================================================
 * Service layer untuk semua operasi e-sign:
 *   1. Process & simpan signature image dari base64
 *   2. Generate PDF rasmi dengan embedded signatures
 *   3. Verify integriti signature
 *
 * Cara guna dari controller:
 *   $result = SignatureService::processSignature($base64, 'leave_request', $leaveId, $userId, 'applicant');
 *   $pdfPath = SignatureService::generateSignedPdf('leave_request', $leaveId);
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Signature.php';
require_once __DIR__ . '/../core/fpdf.php';

class SignatureService {

    // Direktori storage (relatif dari root projek)
    const SIGNATURES_DIR  = 'storage/private/signatures/';
    const SIGNED_PDFS_DIR = 'storage/private/signed_pdfs/';

    // ─── 1. Process & Save Signature ─────────────────────────────────────────

    /**
     * Proses signature dari base64 data URL dan simpan ke storage.
     *
     * @param string $base64DataUrl  Base64 PNG dari canvas: "data:image/png;base64,iVBO..."
     * @param string $documentType   'leave_request' atau 'expense_claim'
     * @param int    $documentId     ID dokumen
     * @param int    $userId         ID user yang sign
     * @param string $signerRole     'applicant' (staff) atau 'approver' (manager)
     * @param string $ip             IP address (optional)
     * @return array ['success'=>bool, 'signature_url'=>string, 'message'=>string]
     */
    public static function processSignature(
        string $base64DataUrl,
        string $documentType,
        int    $documentId,
        int    $userId,
        string $signerRole = 'approver',
        string $ip = ''
    ): array {
        // ─── Validate Input ────────────────────────────────────────────────────
        if (empty($base64DataUrl)) {
            return ['success' => false, 'message' => 'Signature data is empty.'];
        }
        if (strlen($base64DataUrl) > 2 * 1024 * 1024) {
            return ['success' => false, 'message' => 'Signature data exceeds the 2 MB limit.'];
        }

        if (!in_array($signerRole, ['applicant', 'approver'])) {
            return ['success' => false, 'message' => 'Invalid signer role.'];
        }

        // Extract base64 dari data URL (format: "data:image/png;base64,XXXXX")
        if (strpos($base64DataUrl, 'data:image/png;base64,') === 0) {
            $base64Data = substr($base64DataUrl, strlen('data:image/png;base64,'));
        } else {
            return ['success' => false, 'message' => 'Signature must be submitted as a PNG image.'];
        }

        // Decode base64 → binary PNG
        $imageData = base64_decode($base64Data, true);
        if ($imageData === false) {
            return ['success' => false, 'message' => 'Invalid signature data (base64 decoding failed).'];
        }

        // Validate minimum saiz (signature kosong sangat kecil, < 1KB)
        if (strlen($imageData) < 500) {
            return ['success' => false, 'message' => 'The signature is too small. Please draw a clear signature.'];
        }
        $imageInfo = @getimagesizefromstring($imageData);
        if (!$imageInfo || ($imageInfo[2] ?? null) !== IMAGETYPE_PNG) {
            return ['success' => false, 'message' => 'The submitted signature is not a valid PNG image.'];
        }

        // ─── Simpan PNG ke Storage ─────────────────────────────────────────────
        $sigDir = __DIR__ . '/../' . self::SIGNATURES_DIR;
        if (!is_dir($sigDir)) {
            mkdir($sigDir, 0750, true);
        }

        // Generate nama fail yang unik dan tidak boleh diteka
        $filename = sprintf(
            'sig_%s_%d_%d_%s_%s.png',
            $documentType,
            $documentId,
            $userId,
            $signerRole,
            bin2hex(random_bytes(8))  // 16 char random hex
        );

        $absolutePath = $sigDir . $filename;
        $relativePath = self::SIGNATURES_DIR . $filename;

        if (file_put_contents($absolutePath, $imageData) === false) {
            return ['success' => false, 'message' => 'Failed to save the signature to storage.'];
        }

        // ─── Set File Permissions (Read-Only selepas simpan) ──────────────────
        // 0444 = read-only untuk semua (owner, group, others)
        // Ini mencegah sebarang modification melalui PHP file_put_contents
        chmod($absolutePath, 0444);

        // ─── Kira SHA-256 Hash untuk Integrity ────────────────────────────────
        $signatureHash = hash_file('sha256', $absolutePath);

        // ─── Simpan Record dalam DB ───────────────────────────────────────────
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $ipAddress = $ip ?: ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');

        Signature::save(
            $documentType,
            $documentId,
            $userId,
            $signerRole,
            $relativePath,
            $signatureHash,
            $ipAddress,
            $userAgent
        );

        return [
            'success'       => true,
            'signature_url' => $relativePath,
            'message'       => 'Signature saved successfully.',
        ];
    }

    // ─── 2. Generate Signed PDF ───────────────────────────────────────────────

    /**
     * Generate PDF rasmi dengan embedded signatures menggunakan FPDF.
     * PDF akan disimpan dalam uploads/signed_pdfs/ dan path disimpan dalam DB.
     *
     * @param string $documentType  'leave_request' atau 'expense_claim'
     * @param int    $documentId
     * @return array ['success'=>bool, 'pdf_path'=>string, 'message'=>string]
     */
    public static function generateSignedPdf(string $documentType, int $documentId): array {
        $pdfDir = __DIR__ . '/../' . self::SIGNED_PDFS_DIR;
        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0750, true);
        }

        try {
            [$tableName, $allowedStatuses] = match ($documentType) {
                'leave_request' => ['leave_requests', ['approved']],
                'expense_claim' => ['expense_claims', ['approved', 'paid']],
                default => [null, []],
            };
            if (!$tableName) return ['success' => false, 'message' => 'Unsupported document type.'];
            $status = Database::query("SELECT status FROM `{$tableName}` WHERE id=? LIMIT 1", [$documentId])->fetchColumn();
            if ($status === false || !in_array((string)$status, $allowedStatuses, true)) {
                return ['success' => false, 'message' => 'Only an approved document can generate an approval PDF.'];
            }
            if (!Signature::getStatus($documentType, $documentId)['fully_signed']) {
                return ['success' => false, 'message' => 'Both applicant and approver signatures are required.'];
            }
            $verification = self::verifyAll($documentType, $documentId);
            if (!$verification || !array_reduce($verification, fn($valid, $row) => $valid && !empty($row['valid']), true)) {
                return ['success' => false, 'message' => 'Signature integrity verification failed.'];
            }
            switch ($documentType) {
                case 'leave_request':
                    return self::generateLeaveApprovalPdf($documentId, $pdfDir);

                case 'expense_claim':
                    return self::generateExpenseApprovalPdf($documentId, $pdfDir);

                default:
                    return ['success' => false, 'message' => "Document type '{$documentType}' is not supported."];
            }
        } catch (Exception $e) {
            error_log('[SignatureService] generateSignedPdf error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to generate PDF: ' . $e->getMessage()];
        }
    }

    // ─── 3. Leave Approval PDF ───────────────────────────────────────────────

    /**
     * Generate PDF surat kelulusan cuti dengan kedua-dua signatures.
     */
    private static function generateLeaveApprovalPdf(int $leaveId, string $pdfDir): array {
        // Fetch leave request data dengan user info
        $leave = Database::query(
            "SELECT lr.*, u.name AS user_name, u.position AS user_position,
                    u.employee_id AS user_employee_id,
                    d.name AS department_name,
                    lt.name AS leave_type_name,
                    ab.name AS approved_by_name, ab.position AS approver_position
             FROM `leave_requests` lr
             JOIN `users` u ON u.id = lr.user_id
             LEFT JOIN `departments` d ON d.id = u.department_id
             LEFT JOIN `leave_types` lt ON lt.id = lr.leave_type_id
             LEFT JOIN `users` ab ON ab.id = lr.approved_by
             WHERE lr.id = ? LIMIT 1",
            [$leaveId]
        )->fetch();

        if (!$leave) {
            return ['success' => false, 'message' => 'Leave request not found.'];
        }

        // Dapatkan signatures
        $sigStatus = Signature::getStatus('leave_request', $leaveId);
        $signatures = [];
        foreach ($sigStatus['signatures'] as $sig) {
            $signatures[$sig['signer_role']] = $sig;
        }

        // ─── Build PDF dengan FPDF ────────────────────────────────────────────
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 25);

        $rootDir = __DIR__ . '/../';

        // ── Header ──
        // Logo (jika ada)
        $logoPath = $rootDir . 'assets/images/logo.png';
        if (file_exists($logoPath)) {
            // Tetapkan tinggi maksimum 22mm supaya tak langgar teks di bawah. Lebar auto.
            $pdf->Image($logoPath, 20, 10, 0, 22);
        }

        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetTextColor(30, 58, 138); // TBBA dark blue
        $pdf->Cell(0, 8, 'THE BRIDGE BUSINESS ALLIANCE', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 5, 'Leave Approval Letter', 0, 1, 'C');
        $pdf->Cell(0, 4, 'Reference No: TBBA/LEAVE/' . date('Y') . '/' . str_pad($leaveId, 4, '0', STR_PAD_LEFT), 0, 1, 'C');
        $pdf->Ln(4);

        // ── Divider ──
        $pdf->SetDrawColor(30, 58, 138);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
        $pdf->Ln(6);

        // ── Leave Details ──
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(30, 58, 138);
        $pdf->Cell(0, 7, 'LEAVE REQUEST DETAILS', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(40, 40, 40);
        $pdf->Ln(2);

        $details = [
            ['Employee Name',  $leave['user_name']        ?? '-'],
            ['Employee ID',    $leave['user_employee_id'] ?? '-'],
            ['Department',     $leave['department_name']  ?? '-'],
            ['Position',       $leave['user_position']    ?? '-'],
            ['Leave Type',     $leave['leave_type_name']  ?? '-'],
            ['Start Date',     date('d M Y', strtotime($leave['start_date']))],
            ['End Date',       date('d M Y', strtotime($leave['end_date']))],
            ['Total Days',     $leave['total_days'] . ' day(s)'],
            ['Status',         strtoupper($leave['status'] ?? 'PENDING')],
            ['Reason',         $leave['reason'] ?? '-'],
        ];

        foreach ($details as [$label, $value]) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(55, 7, $label . ':', 0, 0);
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(0, 7, $value, 0, 1);
        }

        if (!empty($leave['rejection_reason'])) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(55, 7, 'Rejection Reason:', 0, 0);
            $pdf->SetFont('Arial', '', 10);
            $pdf->MultiCell(0, 7, $leave['rejection_reason'], 0, 'L');
        }

        $pdf->Ln(8);

        // ── Divider ──
        $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
        $pdf->Ln(6);

        // ── Signature Section ──
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(30, 58, 138);
        $pdf->Cell(0, 7, 'DIGITAL SIGNATURES', 0, 1);
        $pdf->SetTextColor(40, 40, 40);
        $pdf->Ln(2);

        $sigY = $pdf->GetY();

        // ── Applicant Signature (kiri) ──
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetXY(20, $sigY);
        $pdf->Cell(80, 6, 'APPLICANT SIGNATURE', 0, 1, 'C');

        if (!empty($signatures['applicant'])) {
            $sig = $signatures['applicant'];
            $sigImgPath = $rootDir . $sig['signature_url'];
            if (file_exists($sigImgPath)) {
                $pdf->Image($sigImgPath, 30, $pdf->GetY(), 60, 0);
            }
            $pdf->SetXY(20, $sigY + 33);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(80, 5, $sig['user_name'] ?? '-', 0, 1, 'C');
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->SetX(20);
            $pdf->Cell(80, 4, $sig['user_position'] ?? 'Staff', 0, 1, 'C');
            $pdf->SetX(20);
            $pdf->Cell(80, 4, date('d M Y, h:i A', strtotime($sig['signed_at'])), 0, 1, 'C');
        } else {
            $pdf->SetXY(20, $sigY + 8);
            $pdf->SetTextColor(180, 180, 180);
            $pdf->Cell(80, 25, '[ Pending Signature ]', 1, 1, 'C');
            $pdf->SetTextColor(40, 40, 40);
        }

        // ── Approver Signature (kanan) ──
        $pdf->SetXY(110, $sigY);
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(40, 40, 40);
        $pdf->Cell(80, 6, 'APPROVER SIGNATURE', 0, 1, 'C');

        if (!empty($signatures['approver'])) {
            $sig = $signatures['approver'];
            $sigImgPath = $rootDir . $sig['signature_url'];
            if (file_exists($sigImgPath)) {
                $pdf->Image($sigImgPath, 120, $sigY + 7, 60, 0);
            }
            $pdf->SetXY(110, $sigY + 33);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(80, 5, $sig['user_name'] ?? '-', 0, 1, 'C');
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->SetX(110);
            $pdf->Cell(80, 4, $sig['user_position'] ?? 'Manager', 0, 1, 'C');
            $pdf->SetX(110);
            $pdf->Cell(80, 4, date('d M Y, h:i A', strtotime($sig['signed_at'])), 0, 1, 'C');
        } else {
            $pdf->SetXY(110, $sigY + 8);
            $pdf->SetTextColor(180, 180, 180);
            $pdf->Cell(80, 25, '[ Pending Signature ]', 1, 1, 'C');
            $pdf->SetTextColor(40, 40, 40);
        }

        // ── Footer ──
        $pdf->SetY(-30);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetTextColor(130, 130, 130);
        $pdf->Cell(0, 4, 'This document was digitally signed via TBBA ERP E-Sign System.', 0, 1, 'C');
        $pdf->Cell(0, 4, 'Generated on: ' . date('d M Y, h:i:s A T'), 0, 1, 'C');
        $pdf->Cell(0, 4, 'Document ID: LEAVE-' . $leaveId . ' | Verify at: ' . (Helper::url('index.php') ?? 'TBBA ERP'), 0, 1, 'C');

        // ── Simpan PDF ────────────────────────────────────────────────────────
        $filename = 'leave_approval_' . $leaveId . '_' . date('Ymd_His') . '.pdf';
        $pdfPath  = self::SIGNED_PDFS_DIR . $filename;
        $pdf->Output('F', $pdfDir . $filename);

        $oldPdfPath = $leave['signed_pdf_path'] ?? null;
        // Kemaskini leave_requests dengan path PDF
        try {
            Database::query(
                "UPDATE `leave_requests` SET `signed_pdf_path` = ? WHERE `id` = ?",
                [$pdfPath, $leaveId]
            );
            self::deleteReplacedPrivateFile($oldPdfPath, $pdfPath, self::SIGNED_PDFS_DIR);
        } catch (Exception $e) {
            // Column mungkin belum ada — skip update, PDF tetap generate
            error_log('[SignatureService] Cannot update signed_pdf_path: ' . $e->getMessage());
        }

        return [
            'success'  => true,
            'pdf_path' => $pdfPath,
            'filename' => $filename,
            'message'  => 'PDF generated successfully.',
        ];
    }

    // ─── 4. Expense Claim PDF ─────────────────────────────────────────────────

    /**
     * Generate PDF surat kelulusan expense claim dengan kedua-dua signatures.
     */
    private static function generateExpenseApprovalPdf(int $expenseId, string $pdfDir): array {
        // Fetch expense claim data
        $claim = Database::query(
            "SELECT ec.*, u.name AS user_name, u.position AS user_position,
                    u.employee_id AS user_employee_id,
                    d.name AS department_name,
                    ab.name AS approved_by_name, ab.position AS approver_position
             FROM `expense_claims` ec
             JOIN `users` u ON u.id = ec.user_id
             LEFT JOIN `departments` d ON d.id = u.department_id
             LEFT JOIN `users` ab ON ab.id = ec.approved_by
             WHERE ec.id = ? LIMIT 1",
            [$expenseId]
        )->fetch();

        if (!$claim) {
            return ['success' => false, 'message' => 'Expense claim not found.'];
        }

        // Fetch expense items. receipt_path is the actual receipt column used by Expense v3.
        $items = Database::query(
            "SELECT * FROM `expense_items` WHERE `claim_id` = ? ORDER BY `id` ASC",
            [$expenseId]
        )->fetchAll();

        // Signatures
        $sigStatus = Signature::getStatus('expense_claim', $expenseId);
        $signatures = [];
        foreach ($sigStatus['signatures'] as $sig) {
            $signatures[$sig['signer_role']] = $sig;
        }

        // Category summary
        $categoryTotals = [];
        $grandTotal = 0.0;
        foreach ($items as $item) {
            $category = trim((string)($item['category'] ?? 'Misc Expenses'));
            if ($category === '') $category = 'Misc Expenses';
            $amount = (float)($item['amount'] ?? 0);
            $categoryTotals[$category] = ($categoryTotals[$category] ?? 0) + $amount;
            $grandTotal += $amount;
        }

        $rootDir = __DIR__ . '/../';
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetMargins(20, 16, 20);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->AddPage();

        // Helpers local to this PDF only.
        $cleanText = static function ($value): string {
            $text = trim(preg_replace('/\s+/u', ' ', (string)$value) ?? '');
            return $text !== '' ? $text : '-';
        };

        $wrapText = static function (FPDF $pdf, string $text, float $maxWidth): array {
            $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
            if ($text === '') return ['-'];

            $words = preg_split('/\s+/', $text) ?: [$text];
            $lines = [];
            $line = '';

            foreach ($words as $word) {
                $candidate = $line === '' ? $word : $line . ' ' . $word;
                if ($pdf->GetStringWidth($candidate) <= $maxWidth) {
                    $line = $candidate;
                    continue;
                }

                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }

                // Handle one very long token safely.
                if ($pdf->GetStringWidth($word) > $maxWidth) {
                    $chunk = '';
                    foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
                        $candidateChunk = $chunk . $char;
                        if ($chunk !== '' && $pdf->GetStringWidth($candidateChunk) > $maxWidth) {
                            $lines[] = $chunk;
                            $chunk = $char;
                        } else {
                            $chunk = $candidateChunk;
                        }
                    }
                    $line = $chunk;
                } else {
                    $line = $word;
                }
            }

            if ($line !== '') $lines[] = $line;
            return $lines ?: ['-'];
        };

        $drawTableHeader = static function (FPDF $pdf): void {
            $pdf->SetFillColor(30, 58, 138);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(8,  7, '#',            1, 0, 'C', true);
            $pdf->Cell(54, 7, 'Description',  1, 0, 'C', true);
            $pdf->Cell(54, 7, 'Category',     1, 0, 'C', true);
            $pdf->Cell(20, 7, 'Receipt',      1, 0, 'C', true);
            $pdf->Cell(34, 7, 'Amount (MYR)', 1, 1, 'C', true);
            $pdf->SetTextColor(40, 40, 40);
        };

        // ── Header ──
        $logoPath = $rootDir . 'assets/images/logo.png';
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 20, 9, 0, 20);
        }

        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetTextColor(30, 58, 138);
        $pdf->Cell(0, 8, 'THE BRIDGE BUSINESS ALLIANCE', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 5, 'Expense Claim Approval', 0, 1, 'C');
        $pdf->Cell(0, 4, 'Ref: TBBA/EXPENSE/' . date('Y') . '/' . str_pad($expenseId, 4, '0', STR_PAD_LEFT), 0, 1, 'C');
        $pdf->Ln(4);

        $pdf->SetDrawColor(30, 58, 138);
        $pdf->SetLineWidth(0.4);
        $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
        $pdf->Ln(5);

        // ── Claimant Details ──
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(30, 58, 138);
        $pdf->Cell(0, 6, 'CLAIMANT DETAILS', 0, 1);
        $pdf->SetTextColor(40, 40, 40);
        $pdf->Ln(1);

        $claimantDetails = [
            ['Name',         $cleanText($claim['user_name'] ?? '-')],
            ['Employee ID',  $cleanText($claim['user_employee_id'] ?? '-')],
            ['Department',   $cleanText($claim['department_name'] ?? '-')],
            ['Claim Title',  $cleanText($claim['title'] ?? '-')],
            ['Expense Date', $cleanText($claim['expense_date'] ?? '-')],
            ['Status',       strtoupper($cleanText($claim['status'] ?? 'PENDING'))],
        ];

        foreach ($claimantDetails as [$label, $value]) {
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(48, 6, $label . ':', 0, 0);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 6, $value, 0, 1);
        }
        $pdf->Ln(3);

        // ── Expense Items Table ──
        if (!empty($items)) {
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->SetTextColor(30, 58, 138);
            $pdf->Cell(0, 6, 'EXPENSE ITEMS', 0, 1);
            $pdf->SetTextColor(40, 40, 40);

            $drawTableHeader($pdf);
            $pdf->SetFont('Arial', '', 8);

            $widths = [8, 54, 54, 20, 34];
            $lineHeight = 4.4;

            foreach ($items as $i => $item) {
                $description = $cleanText($item['description'] ?? '-');
                $category = $cleanText($item['category'] ?? 'Misc Expenses');
                $receiptPath = trim((string)($item['receipt_path'] ?? ''));
                $receiptLabel = $receiptPath !== '' ? 'Attached' : '-';
                $amount = (float)($item['amount'] ?? 0);

                $descLines = $wrapText($pdf, $description, $widths[1] - 3);
                $catLines  = $wrapText($pdf, $category, $widths[2] - 3);
                $maxLines  = max(count($descLines), count($catLines), 1);
                $rowHeight = max(6.2, $maxLines * $lineHeight + 2.0);

                // Keep each row together. If a new page is required, repeat header.
                if ($pdf->GetY() + $rowHeight > 244) {
                    $pdf->AddPage();
                    $pdf->SetFont('Arial', 'B', 10);
                    $pdf->SetTextColor(30, 58, 138);
                    $pdf->Cell(0, 6, 'EXPENSE ITEMS - CONTINUED', 0, 1);
                    $drawTableHeader($pdf);
                    $pdf->SetFont('Arial', '', 8);
                }

                $x = $pdf->GetX();
                $y = $pdf->GetY();

                // Draw row borders.
                $cursorX = $x;
                foreach ($widths as $w) {
                    $pdf->Rect($cursorX, $y, $w, $rowHeight);
                    $cursorX += $w;
                }

                // #
                $pdf->SetXY($x, $y + (($rowHeight - 4) / 2));
                $pdf->Cell($widths[0], 4, (string)($i + 1), 0, 0, 'C');

                // Description
                $pdf->SetXY($x + $widths[0] + 1.5, $y + 1.2);
                foreach ($descLines as $line) {
                    $pdf->Cell($widths[1] - 3, $lineHeight, $line, 0, 2, 'L');
                }

                // Category
                $catX = $x + $widths[0] + $widths[1];
                $pdf->SetXY($catX + 1.5, $y + 1.2);
                foreach ($catLines as $line) {
                    $pdf->Cell($widths[2] - 3, $lineHeight, $line, 0, 2, 'L');
                }

                // Receipt
                $receiptX = $catX + $widths[2];
                $pdf->SetXY($receiptX, $y + (($rowHeight - 4) / 2));
                if ($receiptPath !== '') {
                    $pdf->SetTextColor(5, 150, 105);
                    $pdf->SetFont('Arial', 'B', 7.5);
                } else {
                    $pdf->SetTextColor(100, 100, 100);
                    $pdf->SetFont('Arial', '', 8);
                }
                $pdf->Cell($widths[3], 4, $receiptLabel, 0, 0, 'C');

                // Amount
                $amountX = $receiptX + $widths[3];
                $pdf->SetXY($amountX + 1, $y + (($rowHeight - 4) / 2));
                $pdf->SetTextColor(40, 40, 40);
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell($widths[4] - 2, 4, number_format($amount, 2), 0, 0, 'R');

                $pdf->SetXY($x, $y + $rowHeight);
            }
        }

        // ── Category Summary ──
        if (!empty($categoryTotals)) {
            $pdf->Ln(5);

            if ($pdf->GetY() > 220) {
                $pdf->AddPage();
            }

            $pdf->SetFont('Arial', 'B', 11);
            $pdf->SetTextColor(30, 58, 138);
            $pdf->Cell(0, 6, 'CATEGORY SUMMARY', 0, 1);
            $pdf->SetTextColor(40, 40, 40);

            $pdf->SetFont('Arial', 'B', 8.5);
            $pdf->SetFillColor(241, 245, 249);
            $pdf->Cell(125, 6, 'Category', 1, 0, 'L', true);
            $pdf->Cell(45, 6, 'Total (MYR)', 1, 1, 'R', true);

            $pdf->SetFont('Arial', '', 8.5);
            foreach ($categoryTotals as $category => $amount) {
                $pdf->Cell(125, 6, $category, 1, 0, 'L');
                $pdf->Cell(45, 6, number_format($amount, 2), 1, 1, 'R');
            }

            $pdf->SetFont('Arial', 'B', 9.5);
            $pdf->Cell(125, 7, 'GRAND TOTAL', 1, 0, 'R');
            $pdf->Cell(45, 7, 'MYR ' . number_format($grandTotal, 2), 1, 1, 'R');
        }

        $pdf->Ln(6);

        // ── Signature Section ──
        // Reserve enough room for two signatures + footer.
        if ($pdf->GetY() > 205) {
            $pdf->AddPage();
        }

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(30, 58, 138);
        $pdf->Cell(0, 7, 'DIGITAL SIGNATURES', 0, 1);
        $pdf->SetTextColor(40, 40, 40);
        $pdf->Ln(1);

        $sigY = $pdf->GetY();

        foreach (
            [
                ['applicant', 20,  'CLAIMANT SIGNATURE'],
                ['approver',  110, 'APPROVER SIGNATURE']
            ] as [$role, $xPos, $label]
        ) {
            $pdf->SetXY($xPos, $sigY);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(80, 6, $label, 0, 1, 'C');

            if (!empty($signatures[$role])) {
                $sig = $signatures[$role];
                $sigImgPath = $rootDir . ltrim((string)$sig['signature_url'], '/');

                if (file_exists($sigImgPath)) {
                    $pdf->Image($sigImgPath, $xPos + 14, $sigY + 7, 52, 0);
                }

                $pdf->SetXY($xPos, $sigY + 31);
                $pdf->SetFont('Arial', 'B', 8.5);
                $pdf->Cell(80, 5, $cleanText($sig['user_name'] ?? '-'), 0, 1, 'C');
                $pdf->SetFont('Arial', '', 7.5);
                $pdf->SetTextColor(100, 100, 100);
                $pdf->SetX($xPos);
                $pdf->Cell(80, 4, date('d M Y, h:i A', strtotime((string)$sig['signed_at'])), 0, 1, 'C');
                $pdf->SetTextColor(40, 40, 40);
            } else {
                $pdf->SetXY($xPos, $sigY + 8);
                $pdf->SetTextColor(180, 180, 180);
                $pdf->Cell(80, 22, '[ Pending Signature ]', 1, 1, 'C');
                $pdf->SetTextColor(40, 40, 40);
            }
        }

        // ── Footer ──
        // Important: disable automatic page break here. With a 30 mm bottom
        // margin, SetY(-30) sat exactly on FPDF's page-break trigger and created
        // the unwanted blank second page seen in the old PDF.
        $pdf->SetAutoPageBreak(false);
        $pdf->SetY(-14);
        $pdf->SetFont('Arial', 'I', 7.5);
        $pdf->SetTextColor(130, 130, 130);
        $pdf->Cell(
            0,
            4,
            'Digitally signed via TBBA ERP E-Sign System. Generated: ' . date('d M Y, h:i:s A'),
            0,
            1,
            'C'
        );

        // ── Save PDF ──
        $filename = 'expense_approval_' . $expenseId . '_' . date('Ymd_His') . '.pdf';
        $pdfPath  = self::SIGNED_PDFS_DIR . $filename;
        $pdf->Output('F', $pdfDir . $filename);

        $oldPdfPath = $claim['signed_pdf_path'] ?? null;
        try {
            Database::query(
                "UPDATE `expense_claims` SET `signed_pdf_path` = ? WHERE `id` = ?",
                [$pdfPath, $expenseId]
            );
            self::deleteReplacedPrivateFile($oldPdfPath, $pdfPath, self::SIGNED_PDFS_DIR);
        } catch (Exception $e) {
            error_log('[SignatureService] Cannot update signed_pdf_path: ' . $e->getMessage());
        }

        return [
            'success'  => true,
            'pdf_path' => $pdfPath,
            'filename' => $filename,
            'message'  => 'PDF generated successfully.',
        ];
    }

    // ─── 5. Verify Signature ──────────────────────────────────────────────────

    /**
     * Verify semua signatures untuk sesebuah dokumen.
     * Semak integrity (hash) semua signatures yang ada.
     *
     * @param string $documentType
     * @param int    $documentId
     * @return array Keputusan verify untuk setiap signature
     */
    public static function verifyAll(string $documentType, int $documentId): array {
        $signatures = Signature::getByDocument($documentType, $documentId);
        $results = [];

        foreach ($signatures as $sig) {
            $result = Signature::verify(
                $documentType, $documentId, $sig['user_id'], $sig['signer_role']
            );
            $results[] = array_merge($result, [
                'signer'      => $sig['user_name'],
                'signer_role' => $sig['signer_role'],
                'signed_at'   => $sig['signed_at'],
            ]);
        }

        return $results;
    }

    private static function deleteReplacedPrivateFile(?string $oldPath, string $newPath, string $allowedPrefix): void {
        $oldPath = str_replace('\\', '/', ltrim((string)$oldPath, '/'));
        if ($oldPath === '' || $oldPath === $newPath || !str_starts_with($oldPath, $allowedPrefix) || str_contains($oldPath, '..')) return;
        $absolute = realpath(__DIR__ . '/../' . $oldPath);
        $root = realpath(__DIR__ . '/../' . rtrim($allowedPrefix, '/'));
        if ($absolute && $root && is_file($absolute) && str_starts_with($absolute, $root . DIRECTORY_SEPARATOR)) {
            @unlink($absolute);
        }
    }
}
?>

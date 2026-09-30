<?php
/** New Tender Board controller. */
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../models/TenderOpportunity.php';
require_once __DIR__ . '/../models/Tender.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../core/fpdf.php';

class TenderBoardMonthlyPDF extends FPDF {
    public string $periodLabel = '';
    public string $staffName = '';

    public function Header(): void {
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(15, 23, 42);
        $this->Cell(0, 7, 'THE BRIDGE BUSINESS ALLIANCE (TBBA)', 0, 1, 'C');
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(37, 99, 235);
        $this->Cell(0, 6, 'PERSONAL MONTHLY TENDER REPORT', 0, 1, 'C');
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 5, $this->staffName . ' | ' . $this->periodLabel, 0, 1, 'C');
        $this->Ln(4);
    }

    public function Footer(): void {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 5, 'Generated ' . date('d M Y, h:i A') . ' | Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
    }
}

class TenderBoardController {
    public static function index(): void {
        $tenderManagementSection = ($_GET['section'] ?? 'board') === 'kpi' ? 'kpi' : 'board';
        if ($tenderManagementSection === 'kpi') {
            Auth::requirePermission('tenders', 'view');
            $pageTitle = 'Tender Management';
            $canReport = Auth::hasPermission('tender_board', 'report');
            include __DIR__ . '/../views/tenders/index.php';
            return;
        }

        Auth::requirePermission('tender_board', 'view');
        $pageTitle = 'Tender Management';
        $currentUser = Auth::user();
        $tenders = TenderOpportunity::getAll((int)$currentUser['id']);
        $canCreate = Auth::hasPermission('tender_board', 'create');
        $canEdit = Auth::hasPermission('tender_board', 'edit');
        $canDelete = Auth::hasPermission('tender_board', 'delete');
        $canJoin = Auth::hasPermission('tender_board', 'join');
        include __DIR__ . '/../views/tender_board/index.php';
    }

    public static function store(): void {
        Auth::requirePermission('tender_board', 'create');
        self::requirePostAndCsrf();
        [$qtNumber, $title, $closingDate] = self::validatedInput();
        if (TenderOpportunity::isQtNumberTaken($qtNumber)) {
            Helper::json('error', "QT number '{$qtNumber}' already exists.");
        }
        $id = TenderOpportunity::create($qtNumber, $title, $closingDate, (int)Auth::id());
        AuditLog::record('CREATE', "Added new tender opportunity {$qtNumber}: {$title}");
        Helper::json('success', 'New tender added to the board.', ['id' => $id]);
    }

    public static function update(): void {
        Auth::requirePermission('tender_board', 'edit');
        self::requirePostAndCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || !TenderOpportunity::findById($id)) {
            Helper::json('error', 'Tender record not found.');
        }
        [$qtNumber, $title, $closingDate] = self::validatedInput();
        if (TenderOpportunity::isQtNumberTaken($qtNumber, $id)) {
            Helper::json('error', "QT number '{$qtNumber}' already exists.");
        }
        TenderOpportunity::update($id, $qtNumber, $title, $closingDate);
        AuditLog::record('UPDATE', "Updated tender opportunity #{$id}: {$qtNumber}");
        Helper::json('success', 'Tender details updated.');
    }

    public static function delete(): void {
        Auth::requirePermission('tender_board', 'delete');
        self::requirePostAndCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $tender = TenderOpportunity::findById($id);
        if (!$tender) Helper::json('error', 'Tender record not found.');
        $pricingFiles = Tender::getPricingAttachmentsByOpportunity($id);
        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();
            Tender::deleteByOpportunity($id);
            TenderOpportunity::delete($id);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Tender Board delete error: ' . $e->getMessage());
            Helper::json('error', 'Unable to delete this tender. Please try again.');
        }
        foreach ($pricingFiles as $pricingFile) self::deletePrivatePricingFile($pricingFile);
        AuditLog::record('DELETE', "Deleted tender opportunity {$tender['qt_number']}: {$tender['title']}");
        Helper::json('success', 'Tender removed from the board.');
    }

    public static function submitTender(): void {
        Auth::requirePermission('tender_board', 'join');
        self::requirePostAndCsrf();
        $id     = (int)($_POST['id'] ?? 0);
        $userId = (int)Auth::id();
        $tender = TenderOpportunity::findById($id);
        if (!$tender) Helper::json('error', 'Tender record not found.');

        if (!TenderOpportunity::isParticipant($id, $userId)) {
            Helper::json('error', 'You must be joined to this tender to submit it.');
        }

        $kpi = Tender::getByOpportunityAndUser($id, $userId);
        if (!$kpi) Helper::json('error', 'Your linked tender KPI record was not found.');

        if ($kpi['status'] !== 'in_progress') {
            Helper::json('error', 'This tender has already been submitted and cannot be changed.');
        }

        // Require pricing to be filled before submitting
        if ($kpi['indicative_price'] === null || $kpi['cost'] === null || $kpi['selling_price'] === null) {
            Helper::json('error', 'Please complete the project pricing (Indicative Price, Cost, and Selling Price) before submitting.');
        }

        Database::query(
            "UPDATE `tenders` SET `status`='submitted' WHERE `tender_opportunity_id`=? AND `user_id`=?",
            [$id, $userId]
        );

        AuditLog::record('UPDATE', Auth::user()['name'] . " submitted tender {$tender['qt_number']} for result confirmation");
        Helper::json('success', 'Tender submitted successfully. Record Won or Lost once the result is confirmed.');
    }

    public static function setResult(): void {
        Auth::requirePermission('tender_board', 'join');
        self::requirePostAndCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $result = trim((string)($_POST['result'] ?? ''));
        if ($id <= 0 || !in_array($result, ['won', 'lost'], true)) {
            Helper::json('error', 'Invalid tender result.');
        }

        $userId = (int)Auth::id();
        $tender = TenderOpportunity::findById($id);
        if (!$tender || !TenderOpportunity::isParticipant($id, $userId)) {
            Helper::json('error', 'You can only record the result of a tender assigned to you.');
        }
        $kpi = Tender::getByOpportunityAndUser($id, $userId);
        if (!$kpi) {
            Helper::json('error', 'Your linked tender record was not found.');
        }
        if ((string)$kpi['status'] !== 'submitted') {
            Helper::json('error', 'A result can only be recorded after the tender has been submitted.');
        }

        Tender::updateStatus((int)$kpi['id'], $result);
        AuditLog::record(
            'UPDATE',
            Auth::user()['name'] . " recorded tender {$tender['qt_number']} as " . strtoupper($result)
        );
        Helper::json('success', 'Tender result recorded successfully. This final status is now locked.');
    }

    public static function toggleInterest(): void {
        Auth::requirePermission('tender_board', 'join');
        self::requirePostAndCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $tender = TenderOpportunity::findById($id);
        if (!$tender) Helper::json('error', 'Tender record not found.');
        $userId = (int)Auth::id();
        $wasJoined = TenderOpportunity::isParticipant($id, $userId);
        if (!$wasJoined && $tender['closing_date'] < date('Y-m-d')) {
            Helper::json('error', 'This tender has already closed and can no longer be joined.');
        }

        $pdo = Database::getInstance();
        $removedPricingPath = null;
        try {
            $pdo->beginTransaction();
            $joined = TenderOpportunity::toggleParticipant($id, $userId);
            $kpiId = null;
            $needsValue = false;
            if ($joined) {
                $kpi = Tender::getByOpportunityAndUser($id, $userId);
                $kpiId = $kpi ? (int)$kpi['id'] : Tender::createFromOpportunity($userId, $tender);
                $needsValue = !$kpi
                    || $kpi['indicative_price'] === null
                    || $kpi['cost'] === null
                    || $kpi['selling_price'] === null;
            } else {
                $existingPricing = Tender::getByOpportunityAndUser($id, $userId);
                $removedPricingPath = $existingPricing['pricing_attachment'] ?? null;
                Tender::deleteByOpportunityAndUser($id, $userId);
            }
            $pdo->commit();
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Helper::json('error', $e->getMessage());
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Tender Board join/KPI error: ' . $e->getMessage());
            Helper::json('error', 'Unable to update this tender. Please try again.');
        }
        if ($removedPricingPath) self::deletePrivatePricingFile($removedPricingPath);
        $verb = $joined ? 'joined' : 'left';
        AuditLog::record('UPDATE', Auth::user()['name'] . " {$verb} tender {$tender['qt_number']}");
        Helper::json(
            'success',
            $joined ? 'Tender joined successfully. You can now complete the project pricing.' : 'You left the tender and your linked pricing record was removed.',
            ['joined' => $joined, 'kpi_id' => $kpiId, 'needs_pricing' => $needsValue]
        );
    }

    public static function updateProjectPricing(): void {
        Auth::requirePermission('tender_board', 'join');
        self::requirePostAndCsrf();

        $id = (int)($_POST['id'] ?? 0);
        $userId = (int)Auth::id();
        $tender = TenderOpportunity::findById($id);
        if ($id <= 0 || !$tender) {
            Helper::json('error', 'Tender record not found.');
        }
        if (!TenderOpportunity::isParticipant($id, $userId)) {
            Helper::json('error', 'Join this tender before editing its project pricing.');
        }

        $pricingRecord = Tender::getByOpportunityAndUser($id, $userId);
        if (!$pricingRecord) {
            Helper::json('error', 'Your linked tender pricing record was not found.');
        }
        if ((string)$pricingRecord['status'] !== 'in_progress') {
            Helper::json('error', 'Project pricing is locked after the tender is submitted.');
        }

        $indicativePrice = self::validMoney('indicative_price', 'Indicative price');
        $cost = self::validMoney('cost', 'Cost');
        $sellingPrice = self::validMoney('selling_price', 'Selling price');

        $oldPath = $pricingRecord['pricing_attachment'] ?? null;
        $attachmentPath = $oldPath;
        $attachmentName = $pricingRecord['pricing_attachment_name'] ?? null;
        $newUpload = self::storePricingAttachment($userId, $id);
        if ($newUpload) {
            $attachmentPath = $newUpload['path'];
            $attachmentName = $newUpload['name'];
        } elseif (!empty($_POST['remove_attachment'])) {
            $attachmentPath = null;
            $attachmentName = null;
        }

        try {
            Tender::updateBoardPricing(
                $id,
                $userId,
                $indicativePrice,
                $cost,
                $sellingPrice,
                $attachmentPath,
                $attachmentName
            );
        } catch (Throwable $e) {
            if ($newUpload) self::deletePrivatePricingFile($newUpload['path']);
            error_log('Tender Board pricing update error: ' . $e->getMessage());
            Helper::json('error', 'Unable to save project pricing. Please try again.');
        }

        if ($oldPath && $oldPath !== $attachmentPath) {
            self::deletePrivatePricingFile($oldPath);
        }

        AuditLog::record(
            'UPDATE',
            Auth::user()['name'] . " updated project pricing for tender {$tender['qt_number']}"
        );
        Helper::json('success', 'Project pricing saved successfully.');
    }

    public static function downloadPricingFile(): void {
        Auth::requirePermission('tender_board', 'join');
        $id = (int)($_GET['id'] ?? 0);
        $record = Tender::getByOpportunityAndUser($id, (int)Auth::id());
        if (!$record || empty($record['pricing_attachment'])) {
            http_response_code(404);
            exit('Pricing attachment not found.');
        }

        $relativePath = (string)$record['pricing_attachment'];
        $allowedRoot = realpath(__DIR__ . '/../storage/private/tender_pricing');
        $absolutePath = realpath(__DIR__ . '/../' . ltrim($relativePath, '/'));
        if (!$allowedRoot || !$absolutePath || !is_file($absolutePath)
            || !str_starts_with($absolutePath, $allowedRoot . DIRECTORY_SEPARATOR)) {
            http_response_code(404);
            exit('Pricing attachment not found.');
        }

        $downloadName = self::safeDownloadName(
            (string)($record['pricing_attachment_name'] ?: basename($absolutePath))
        );
        $mime = mime_content_type($absolutePath) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($absolutePath));
        header('Content-Disposition: attachment; filename="' . addcslashes($downloadName, "\"\\") . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($absolutePath);
        exit;
    }

    public static function report(): void {
        Auth::requirePermission('tender_board', 'report');
        $monthYear = trim((string)($_GET['month'] ?? date('Y-m')));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthYear)) {
            $monthYear = date('Y-m');
        }

        $rows = Tender::getBoardMonthlyReport((int)Auth::id(), $monthYear);
        $summary = self::pricingSummary($rows);
        $periodDate = DateTime::createFromFormat('!Y-m', $monthYear);
        $periodLabel = $periodDate ? $periodDate->format('F Y') : $monthYear;
        $user = Auth::user();
        $format = strtolower(trim((string)($_GET['format'] ?? 'print')));

        if ($format === 'pdf') {
            self::exportPersonalReportPdf($rows, $summary, $periodLabel, $monthYear, $user);
        }
        if ($format === 'csv') {
            self::exportPersonalReportCsv($rows, $summary, $periodLabel, $monthYear, $user);
        }

        include __DIR__ . '/../views/tender_board/report.php';
    }

    private static function validMoney(string $field, string $label): float {
        $raw = trim((string)($_POST[$field] ?? ''));
        if ($raw === '' || !is_numeric($raw)) {
            Helper::json('error', "{$label} must be a valid amount.");
        }
        $value = (float)$raw;
        if (!is_finite($value) || $value < 0 || $value > 9999999999999.99) {
            Helper::json('error', "{$label} must be between RM 0.00 and RM 9,999,999,999,999.99.");
        }
        return round($value, 2);
    }

    private static function storePricingAttachment(int $userId, int $opportunityId): ?array {
        $file = $_FILES['pricing_attachment'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Helper::json('error', 'The pricing attachment could not be uploaded.');
        }
        if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > 10 * 1024 * 1024) {
            Helper::json('error', 'Pricing attachment must be smaller than 10 MB.');
        }

        $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        $allowedMimes = [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword', 'application/octet-stream'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
            'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
            'csv' => ['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/octet-stream'],
            'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
        ];
        if (!isset($allowedMimes[$extension])) {
            Helper::json('error', 'Unsupported pricing attachment. Use PDF, Word, Excel, CSV, ZIP, JPG or PNG.');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file((string)$file['tmp_name']);
        if (!in_array($mime, $allowedMimes[$extension], true)) {
            Helper::json('error', 'The pricing attachment content does not match its file type.');
        }

        $relativeDir = 'storage/private/tender_pricing';
        $absoluteDir = __DIR__ . '/../' . $relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0750, true) && !is_dir($absoluteDir)) {
            Helper::json('error', 'The pricing attachment folder is not available.');
        }
        $storedName = sprintf(
            'pricing_%d_%d_%s.%s',
            $userId,
            $opportunityId,
            bin2hex(random_bytes(12)),
            $extension
        );
        if (!move_uploaded_file((string)$file['tmp_name'], $absoluteDir . '/' . $storedName)) {
            Helper::json('error', 'Failed to save the pricing attachment.');
        }

        $originalName = self::safeDownloadName((string)$file['name']);
        return ['path' => $relativeDir . '/' . $storedName, 'name' => $originalName];
    }

    private static function deletePrivatePricingFile(?string $relativePath): void {
        if (!$relativePath || !str_starts_with($relativePath, 'storage/private/tender_pricing/')) return;
        $root = realpath(__DIR__ . '/../storage/private/tender_pricing');
        $path = realpath(__DIR__ . '/../' . ltrim($relativePath, '/'));
        if ($root && $path && is_file($path) && str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            @unlink($path);
        }
    }

    private static function safeDownloadName(string $name): string {
        $name = preg_replace('/[^A-Za-z0-9._ -]/u', '_', basename($name)) ?: 'pricing-attachment';
        return mb_substr($name, 0, 180);
    }

    private static function pricingSummary(array $rows): array {
        $summary = [
            'tender_count' => count($rows),
            'priced_count' => 0,
            'indicative_total' => 0.0,
            'cost_total' => 0.0,
            'selling_total' => 0.0,
            'gross_margin' => 0.0,
            'post_mortem_count' => 0,
        ];
        foreach ($rows as $row) {
            if ($row['indicative_price'] !== null && $row['cost'] !== null && $row['selling_price'] !== null) {
                $summary['priced_count']++;
            }
            $summary['indicative_total'] += (float)($row['indicative_price'] ?? 0);
            $summary['cost_total'] += (float)($row['cost'] ?? 0);
            $summary['selling_total'] += (float)($row['selling_price'] ?? 0);
            if (self::hasPostMortem($row)) $summary['post_mortem_count']++;
        }
        $summary['gross_margin'] = $summary['selling_total'] - $summary['cost_total'];
        return $summary;
    }

    private static function exportPersonalReportCsv(array $rows, array $summary, string $periodLabel, string $monthYear, array $user): void {
        $filename = 'TBBA_My_Tender_Report_' . str_replace('-', '_', $monthYear) . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");
        self::writeCsvRow($output, ['THE BRIDGE BUSINESS ALLIANCE (TBBA)']);
        self::writeCsvRow($output, ['Personal Monthly Tender Report']);
        self::writeCsvRow($output, ['Staff', $user['name'] ?? '']);
        self::writeCsvRow($output, ['Period', $periodLabel]);
        self::writeCsvRow($output, []);
        self::writeCsvRow($output, [
            'QT Number', 'Tender Title', 'Joined Date', 'Closing Date',
            'Indicative Price (RM)', 'Cost (RM)', 'Selling Price (RM)', 'Gross Margin (RM)',
            'Status', 'Pricing File', 'Post-Mortem Summary', 'Key Factors',
            'SWOT - Strengths', 'SWOT - Weaknesses', 'SWOT - Opportunities', 'SWOT - Threats'
        ]);
        foreach ($rows as $row) {
            $selling = $row['selling_price'] !== null ? (float)$row['selling_price'] : null;
            $cost = $row['cost'] !== null ? (float)$row['cost'] : null;
            self::writeCsvRow($output, [
                $row['qt_number'], $row['title'], date('d M Y', strtotime($row['joined_at'])),
                date('d M Y', strtotime($row['closing_date'])),
                $row['indicative_price'] === null ? '' : number_format((float)$row['indicative_price'], 2, '.', ''),
                $cost === null ? '' : number_format($cost, 2, '.', ''),
                $selling === null ? '' : number_format($selling, 2, '.', ''),
                $selling === null || $cost === null ? '' : number_format($selling - $cost, 2, '.', ''),
                ucwords(str_replace('_', ' ', (string)$row['status'])),
                $row['pricing_attachment_name'] ?: '',
                $row['post_mortem_summary'] ?: '',
                $row['post_mortem_factors'] ?: '',
                $row['swot_s'] ?: '',
                $row['swot_w'] ?: '',
                $row['swot_o'] ?: '',
                $row['swot_t'] ?: '',
            ]);
        }
        self::writeCsvRow($output, []);
        self::writeCsvRow($output, ['Summary', 'Joined Tenders', $summary['tender_count']]);
        self::writeCsvRow($output, ['Summary', 'Priced Tenders', $summary['priced_count']]);
        self::writeCsvRow($output, ['Summary', 'Completed Post-Mortems', $summary['post_mortem_count']]);
        self::writeCsvRow($output, ['Summary', 'Total Indicative Price (RM)', number_format($summary['indicative_total'], 2, '.', '')]);
        self::writeCsvRow($output, ['Summary', 'Total Cost (RM)', number_format($summary['cost_total'], 2, '.', '')]);
        self::writeCsvRow($output, ['Summary', 'Total Selling Price (RM)', number_format($summary['selling_total'], 2, '.', '')]);
        self::writeCsvRow($output, ['Summary', 'Gross Margin (RM)', number_format($summary['gross_margin'], 2, '.', '')]);
        fclose($output);
        exit;
    }

    private static function exportPersonalReportPdf(array $rows, array $summary, string $periodLabel, string $monthYear, array $user): void {
        $pdf = new TenderBoardMonthlyPDF('L', 'mm', 'A4');
        $pdf->AliasNbPages();
        $pdf->periodLabel = self::pdfText($periodLabel);
        $pdf->staffName = self::pdfText((string)($user['name'] ?? 'Staff'));
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 16);
        $pdf->AddPage();

        $pdf->SetFillColor(239, 246, 255);
        $pdf->SetDrawColor(191, 219, 254);
        $pdf->SetTextColor(30, 64, 175);
        $pdf->SetFont('Arial', 'B', 8);
        $summaryItems = [
            ['Joined Tenders', (string)$summary['tender_count']],
            ['Priced Tenders', (string)$summary['priced_count']],
            ['Post-Mortems', (string)$summary['post_mortem_count']],
            ['Total Selling', 'RM ' . number_format($summary['selling_total'], 2)],
            ['Gross Margin', 'RM ' . number_format($summary['gross_margin'], 2)],
        ];
        $summaryWidth = 276 / count($summaryItems);
        foreach ($summaryItems as [$label, $value]) {
            $pdf->Cell($summaryWidth, 6, $label, 1, 0, 'C', true);
        }
        $pdf->Ln();
        $pdf->SetTextColor(15, 23, 42);
        $pdf->SetFont('Arial', 'B', 10);
        foreach ($summaryItems as [$label, $value]) {
            $pdf->Cell($summaryWidth, 8, self::pdfText($value), 1, 0, 'C');
        }
        $pdf->Ln(13);

        $headers = ['#', 'QT Number', 'Tender Title', 'Joined', 'Indicative (RM)', 'Cost (RM)', 'Selling (RM)', 'Margin (RM)', 'Status'];
        $widths = [8, 26, 65, 23, 30, 30, 30, 30, 25];
        $pdf->SetFillColor(15, 23, 42);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 7);
        foreach ($headers as $i => $header) $pdf->Cell($widths[$i], 8, $header, 1, 0, 'C', true);
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 7);
        $pdf->SetTextColor(15, 23, 42);
        if (!$rows) {
            $pdf->Cell(array_sum($widths), 12, 'No joined tenders were recorded for this month.', 1, 1, 'C');
        } else {
            foreach ($rows as $index => $row) {
                $selling = $row['selling_price'] !== null ? (float)$row['selling_price'] : null;
                $cost = $row['cost'] !== null ? (float)$row['cost'] : null;
                $values = [
                    (string)($index + 1), self::pdfText((string)$row['qt_number']),
                    self::pdfText(self::truncate((string)$row['title'], 46)),
                    date('d M Y', strtotime($row['joined_at'])),
                    $row['indicative_price'] === null ? '-' : number_format((float)$row['indicative_price'], 2),
                    $cost === null ? '-' : number_format($cost, 2),
                    $selling === null ? '-' : number_format($selling, 2),
                    $selling === null || $cost === null ? '-' : number_format($selling - $cost, 2),
                    self::pdfText(ucwords(str_replace('_', ' ', (string)$row['status']))),
                ];
                foreach ($values as $i => $value) $pdf->Cell($widths[$i], 8, $value, 1, 0, $i === 2 ? 'L' : 'C');
                $pdf->Ln();
            }
        }

        $pdf->Ln(8);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(0, 6, 'POST-MORTEM & SWOT DETAILS', 0, 1, 'L');
        $pdf->Ln(1);
        $postMortemRows = array_values(array_filter($rows, fn($row) => self::hasPostMortem($row)));
        if (!$postMortemRows) {
            $pdf->SetFont('Arial', 'I', 8);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->Cell(0, 8, 'No completed post-mortem or SWOT details were recorded for this month.', 1, 1, 'C');
        } else {
            foreach ($postMortemRows as $row) {
                $pdf->SetFillColor(239, 246, 255);
                $pdf->SetDrawColor(191, 219, 254);
                $pdf->SetTextColor(30, 64, 175);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->MultiCell(0, 6, self::pdfText($row['qt_number'] . ' - ' . $row['title']), 1, 'L', true);
                $pdf->SetTextColor(15, 23, 42);
                $pdf->SetFont('Arial', '', 8);
                $details = [
                    'Status' => ucwords(str_replace('_', ' ', (string)$row['status'])),
                    'Key Factors' => (string)($row['post_mortem_factors'] ?: '-'),
                    'Summary' => (string)($row['post_mortem_summary'] ?: '-'),
                    'Strengths' => (string)($row['swot_s'] ?: '-'),
                    'Weaknesses' => (string)($row['swot_w'] ?: '-'),
                    'Opportunities' => (string)($row['swot_o'] ?: '-'),
                    'Threats' => (string)($row['swot_t'] ?: '-'),
                ];
                foreach ($details as $label => $value) {
                    $pdf->SetFont('Arial', 'B', 8);
                    $pdf->Cell(31, 5, $label . ':', 0, 0, 'L');
                    $pdf->SetFont('Arial', '', 8);
                    $pdf->MultiCell(0, 5, self::pdfText($value), 0, 'L');
                }
                $pdf->Ln(4);
            }
        }

        $filename = 'TBBA_My_Tender_Report_' . str_replace('-', '_', $monthYear) . '.pdf';
        $pdf->Output('D', $filename);
        exit;
    }

    private static function pdfText(string $value): string {
        $converted = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $value);
        return $converted === false ? preg_replace('/[^\x20-\x7E]/', '', $value) : $converted;
    }

    private static function writeCsvRow($output, array $fields): void {
        $safeFields = array_map(static function ($value) {
            if (!is_string($value)) return $value;
            $value = str_replace("\0", '', $value);
            return preg_match('/^[\\x00-\\x20]*[=+\\-@]/', $value) ? "'" . $value : $value;
        }, $fields);
        fputcsv($output, $safeFields, ',', '"', '');
    }

    private static function hasPostMortem(array $row): bool {
        foreach (['post_mortem_summary', 'post_mortem_factors', 'swot_s', 'swot_w', 'swot_o', 'swot_t'] as $field) {
            if (trim((string)($row[$field] ?? '')) !== '') return true;
        }
        return false;
    }

    private static function truncate(string $value, int $length): string {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length - 3) . '...' : $value;
    }

    private static function requirePostAndCsrf(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid request method.');
        if (!Helper::verifyCsrf($_POST['csrf_token'] ?? '')) {
            Helper::json('error', 'Invalid CSRF token security.');
        }
    }

    private static function validatedInput(): array {
        $qtNumber = strtoupper(trim((string)($_POST['qt_number'] ?? '')));
        $title = trim((string)($_POST['title'] ?? ''));
        $closingDate = trim((string)($_POST['closing_date'] ?? ''));
        $date = DateTime::createFromFormat('!Y-m-d', $closingDate);
        if ($qtNumber === '' || $title === '' || !$date || $date->format('Y-m-d') !== $closingDate) {
            Helper::json('error', 'QT number, tender title and a valid closing date are required.');
        }
        if (mb_strlen($qtNumber) > 100 || mb_strlen($title) > 255) {
            Helper::json('error', 'QT number or tender title is too long.');
        }
        return [$qtNumber, $title, $closingDate];
    }
}

<?php
/**
 * FinanceRecord Model — TBBA ERP Module
 * Company: The Bridge Business Alliance (TBBA)
 * Handles Sales & Purchases data for Finance Officer
 */

require_once __DIR__ . '/../config/database.php';

class FinanceRecord {
    private static bool $initialized = false;

    /** Verify the migrated finance table once per request; schema changes belong to migrations. */
    public static function initTable(): void {
        if (self::$initialized) return;
        try {
            Database::query("SELECT 1 FROM finance_records LIMIT 1");
            self::$initialized = true;
        } catch (Exception $e) {
            error_log("[FinanceRecord] initTable error: " . $e->getMessage());
            throw new RuntimeException('Finance schema is unavailable. Run the database migrations.', 0, $e);
        }
    }

    /**
     * Legacy development fixture. It is intentionally never called at runtime;
     * an empty production table must stay empty after users delete its records.
     */
    private static function seedDefaultData(): void {
        $sampleRecords = [
            // ─── SALES ──────────────────────────────────────────────────────────
            // Quotations
            ['sales', 'quotations', 'QT-2026-001', 'Petronas Dagangan Bhd', '2026-07-01', '2026-07-15', 'Enterprise Software Maintenance Proposal', 45000.00, 2700.00, 47700.00, 'sent', 'Awaiting client technical committee review.'],
            ['sales', 'quotations', 'QT-2026-002', 'Sime Darby Plantation Bhd', '2026-07-10', '2026-07-24', 'IoT Sensor Network Deployment Quotation', 120000.00, 7200.00, 127200.00, 'draft', 'Drafting hardware BOM.'],
            // Sale Orders
            ['sales', 'sale_orders', 'SO-2026-001', 'Maybank Berhad', '2026-07-05', '2026-08-05', 'Cloud Infrastructure Migration Order #MB-982', 85000.00, 5100.00, 90100.00, 'confirmed', 'Signed contract received.'],
            ['sales', 'sale_orders', 'SO-2026-002', 'Tenaga Nasional Berhad', '2026-07-12', '2026-08-12', 'Substation Telemetry Integration Order', 210000.00, 12600.00, 222600.00, 'confirmed', 'Batch 1 schedule finalized.'],
            // Delivery Orders
            ['sales', 'delivery_orders', 'DO-2026-001', 'Maybank Berhad', '2026-07-14', '2026-07-14', 'Phase 1 Server Nodes & Router Hardware Delivery', 35000.00, 2100.00, 37100.00, 'delivered', 'Acknowledged by Maybank Data Center IT Head.'],
            ['sales', 'delivery_orders', 'DO-2026-002', 'Tenaga Nasional Berhad', '2026-07-18', '2026-07-18', 'Telemetry Gateway Units Batch #1', 65000.00, 3900.00, 68900.00, 'in_transit', 'Dispatched via secure courier.'],
            // Invoices
            ['sales', 'invoices', 'INV-2026-001', 'Maybank Berhad', '2026-07-15', '2026-08-14', 'Invoice for Cloud Infrastructure Phase 1 Delivery', 35000.00, 2100.00, 37100.00, 'sent', 'Payment terms 30 days.'],
            ['sales', 'invoices', 'INV-2026-002', 'Axiata Group Berhad', '2026-06-20', '2026-07-20', 'Annual Cybersecurity Audit Retainer Q2', 60000.00, 3600.00, 63600.00, 'paid', 'Settled on 18 July 2026.'],
            ['sales', 'invoices', 'INV-2026-003', 'TM Bhd', '2026-06-10', '2026-07-10', 'Broadband Network Analytics Module Support', 48000.00, 2880.00, 50880.00, 'overdue', 'Follow-up sent to TM Finance.'],
            // Credit Notes
            ['sales', 'credit_notes', 'CN-2026-001', 'Maybank Berhad', '2026-07-16', '2026-07-16', 'Rebate for Early Server Delivery Adjustment', 2000.00, 120.00, 2120.00, 'issued', 'Applied against future billing.'],
            // Payments
            ['sales', 'payments', 'REC-2026-001', 'Axiata Group Berhad', '2026-07-18', '2026-07-18', 'Payment Receipt for Invoice INV-2026-002', 60000.00, 3600.00, 63600.00, 'completed', 'Received via IBG Transfer Ref #AX99120.'],
            // Refunds
            ['sales', 'refunds', 'RF-2026-001', 'Maxis Berhad', '2026-07-08', '2026-07-08', 'Refund for overpaid SLA deposit Q1', 5000.00, 0.00, 5000.00, 'processed', 'Credited to client account.'],

            // ─── PURCHASES ──────────────────────────────────────────────────────
            // Purchase Orders
            ['purchases', 'purchase_orders', 'PO-2026-001', 'Dell Malaysia Sdn Bhd', '2026-07-02', '2026-07-16', 'PowerEdge R750 Rack Servers (5 Units)', 115000.00, 6900.00, 121900.00, 'approved', 'Expected delivery 22 July.'],
            ['purchases', 'purchase_orders', 'PO-2026-002', 'Cisco Systems Malaysia', '2026-07-11', '2026-07-25', 'Catalyst 9300 Switches & Transceivers', 74000.00, 4440.00, 78440.00, 'approved', 'Vendor order confirmation #CS-8812.'],
            // Goods Received Notes (GRN)
            ['purchases', 'grn', 'GRN-2026-001', 'Dell Malaysia Sdn Bhd', '2026-07-15', '2026-07-15', 'Received 3x PowerEdge R750 Servers (Partial)', 69000.00, 4140.00, 73140.00, 'received', 'Inspected by warehouse tech team.'],
            ['purchases', 'grn', 'GRN-2026-002', 'Office Supplies Depot', '2026-07-17', '2026-07-17', 'Quarterly HQ Stationery & Printer Cartridges', 4500.00, 270.00, 4770.00, 'received', 'All items verified in good condition.'],
            // Bills
            ['purchases', 'bills', 'BILL-2026-001', 'Dell Malaysia Sdn Bhd', '2026-07-16', '2026-08-15', 'Vendor Bill for Partial Server Delivery (3 Units)', 69000.00, 4140.00, 73140.00, 'pending_payment', 'Verified against GRN-2026-001.'],
            ['purchases', 'bills', 'BILL-2026-002', 'Office Supplies Depot', '2026-07-17', '2026-08-16', 'Stationery Supply Bill #OSD-9901', 4500.00, 270.00, 4770.00, 'paid', 'Settled via petty cash / company card.'],
            ['purchases', 'bills', 'BILL-2026-003', 'Amazon Web Services Inc.', '2026-07-01', '2026-07-15', 'AWS Cloud Hosting Monthly Usage June 2026', 18500.00, 1110.00, 19610.00, 'paid', 'Auto-debited on 14 July 2026.'],
            // Credit Notes
            ['purchases', 'credit_notes', 'PCN-2026-001', 'Dell Malaysia Sdn Bhd', '2026-07-17', '2026-07-17', 'Credit Note for Defective Server Rail Kit', 1200.00, 72.00, 1272.00, 'received', 'To be deducted from BILL-2026-001.'],
            // Payments
            ['purchases', 'payments', 'PAY-2026-001', 'Amazon Web Services Inc.', '2026-07-14', '2026-07-14', 'Disbursement for AWS June Hosting Bill', 18500.00, 1110.00, 19610.00, 'completed', 'Bank transaction id #BK-771239.'],
            ['purchases', 'payments', 'PAY-2026-002', 'Office Supplies Depot', '2026-07-18', '2026-07-18', 'Payment voucher for BILL-2026-002', 4500.00, 270.00, 4770.00, 'completed', 'Cheque #CQ-44819.'],
            // Refunds
            ['purchases', 'refunds', 'PRF-2026-001', 'Global Tech Expo 2026', '2026-07-12', '2026-07-12', 'Refund for cancelled booth reservation', 8000.00, 0.00, 8000.00, 'received', 'Credited back to corporate account.']
        ];

        foreach ($sampleRecords as $r) {
            Database::query(
                "INSERT INTO `finance_records` (`category`, `document_type`, `reference_no`, `party_name`, `document_date`, `due_date`, `title`, `amount`, `tax_amount`, `total_amount`, `status`, `notes`, `created_by`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6], $r[7], $r[8], $r[9], $r[10], $r[11], 1]
            );
        }
    }

    /** Get all records by category (sales or purchases) and optional document_type */
    public static function getAll(string $category, ?string $docType = null): array {
        self::initTable();
        try {
            if ($docType && $docType !== 'all') {
                return Database::query(
                    "SELECT f.*, u.name AS creator_name
                     FROM `finance_records` f
                     LEFT JOIN `users` u ON u.id = f.created_by
                     WHERE f.category = ? AND f.document_type = ?
                     ORDER BY f.document_date DESC, f.id DESC",
                    [$category, $docType]
                )->fetchAll();
            } else {
                return Database::query(
                    "SELECT f.*, u.name AS creator_name
                     FROM `finance_records` f
                     LEFT JOIN `users` u ON u.id = f.created_by
                     WHERE f.category = ?
                     ORDER BY f.document_date DESC, f.id DESC",
                    [$category]
                )->fetchAll();
            }
        } catch (Exception $e) {
            error_log("[FinanceRecord] getAll error: " . $e->getMessage());
            return [];
        }
    }

    /** Find single record by ID */
    public static function findById(int $id): ?array {
        self::initTable();
        try {
            $row = Database::query(
                "SELECT f.*, u.name AS creator_name
                 FROM `finance_records` f
                 LEFT JOIN `users` u ON u.id = f.created_by
                 WHERE f.id = ? LIMIT 1",
                [$id]
            )->fetch();
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    public static function isReferenceTaken(string $referenceNo, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) FROM finance_records WHERE reference_no=?";
        $params = [$referenceNo];
        if ($excludeId !== null) { $sql .= " AND id<>?"; $params[] = $excludeId; }
        return (int)Database::query($sql, $params)->fetchColumn() > 0;
    }

    public static function hasFinancialDependencies(int $id): bool {
        $payments = (int)Database::query("SELECT COUNT(*) FROM finance_payments WHERE finance_record_id=?", [$id])->fetchColumn();
        $children = (int)Database::query("SELECT COUNT(*) FROM finance_records WHERE parent_record_id=?", [$id])->fetchColumn();
        return $payments > 0 || $children > 0;
    }

    /** Calculate summary statistics for a category and optional document_type */
    public static function getStats(string $category, ?string $docType = null): array {
        self::initTable();
        try {
            $sql = "SELECT COUNT(*) AS total_count,
                           SUM(total_amount) AS total_sum,
                           SUM(CASE WHEN status IN ('draft','pending','sent','pending_payment','issued','in_transit') THEN total_amount ELSE 0 END) AS pending_sum,
                           SUM(CASE WHEN status IN ('confirmed','approved','received','delivered') THEN total_amount ELSE 0 END) AS confirmed_sum,
                           SUM(CASE WHEN status IN ('paid','completed','processed') THEN total_amount ELSE 0 END) AS paid_sum
                    FROM `finance_records` WHERE `category` = ?";
            $params = [$category];

            if ($docType && $docType !== 'all') {
                $sql .= " AND `document_type` = ?";
                $params[] = $docType;
            }

            $row = Database::query($sql, $params)->fetch();
            return [
                'total_count'   => (int)($row['total_count'] ?? 0),
                'total_sum'     => (float)($row['total_sum'] ?? 0.00),
                'pending_sum'   => (float)($row['pending_sum'] ?? 0.00),
                'confirmed_sum' => (float)($row['confirmed_sum'] ?? 0.00),
                'paid_sum'      => (float)($row['paid_sum'] ?? 0.00),
            ];
        } catch (Exception $e) {
            return ['total_count' => 0, 'total_sum' => 0.00, 'pending_sum' => 0.00, 'confirmed_sum' => 0.00, 'paid_sum' => 0.00];
        }
    }

    /** Create a new finance record */
    public static function create(array $data): int {
        self::initTable();
        $isPayableDocument = in_array($data['document_type'] ?? '', ['invoices', 'bills'], true);
        $balanceDue = $isPayableDocument ? (float)($data['total_amount'] ?? 0) : 0.0;
        $stmt = Database::query(
            "INSERT INTO `finance_records` (`cost_center_id`, `project_ref`, `currency`, `category`, `document_type`, `reference_no`, `party_name`, `document_date`, `due_date`, `title`, `amount`, `tax_amount`, `tax_rate`, `total_amount`, `paid_amount`, `balance_due`, `status`, `notes`, `attachment`, `created_by`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                !empty($data['cost_center_id']) ? (int)$data['cost_center_id'] : null,
                $data['project_ref'] ?? null,
                $data['currency'] ?? 'MYR',
                $data['category'],
                $data['document_type'],
                $data['reference_no'],
                $data['party_name'],
                $data['document_date'],
                !empty($data['due_date']) ? $data['due_date'] : null,
                $data['title'],
                (float)($data['amount'] ?? 0),
                (float)($data['tax_amount'] ?? 0),
                (float)($data['tax_rate'] ?? 0),
                (float)($data['total_amount'] ?? 0),
                0,
                $balanceDue,
                $data['status'] ?? 'draft',
                $data['notes'] ?? null,
                $data['attachment'] ?? null,
                (int)($data['created_by'] ?? 1)
            ]
        );
        return Database::lastInsertId();
    }

    /** Update existing record */
    public static function update(int $id, array $data): bool {
        self::initTable();
        Database::query(
            "UPDATE `finance_records`
             SET `cost_center_id` = ?, `project_ref` = ?, `currency` = ?, `document_type` = ?, `reference_no` = ?, `party_name` = ?, `document_date` = ?, `due_date` = ?,
                 `title` = ?, `amount` = ?, `tax_amount` = ?, `tax_rate` = ?, `total_amount` = ?,
                 `balance_due` = GREATEST(0, ? - `paid_amount`), `status` = ?, `notes` = ?, `attachment` = ?
             WHERE `id` = ?",
            [
                !empty($data['cost_center_id']) ? (int)$data['cost_center_id'] : null,
                $data['project_ref'] ?? null,
                $data['currency'] ?? 'MYR',
                $data['document_type'],
                $data['reference_no'],
                $data['party_name'],
                $data['document_date'],
                !empty($data['due_date']) ? $data['due_date'] : null,
                $data['title'],
                (float)($data['amount'] ?? 0),
                (float)($data['tax_amount'] ?? 0),
                (float)($data['tax_rate'] ?? 0),
                (float)($data['total_amount'] ?? 0),
                (float)($data['total_amount'] ?? 0),
                $data['status'] ?? 'draft',
                $data['notes'] ?? null,
                $data['attachment'] ?? null,
                $id
            ]
        );
        return true;
    }

    /** Delete record by ID */
    public static function delete(int $id): bool {
        self::initTable();
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $record = Database::query("SELECT status,paid_amount FROM finance_records WHERE id=? FOR UPDATE", [$id])->fetch();
            if (!$record || $record['status'] !== 'draft' || (float)$record['paid_amount'] !== 0.0
                || self::hasFinancialDependencies($id)) {
                $pdo->rollBack();
                return false;
            }
            $stmt = Database::query("DELETE FROM finance_records WHERE id=?", [$id]);
            $pdo->commit();
            return $stmt->rowCount() === 1;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Document types definitions and metadata */
    public static function getSalesTypes(): array {
        return [
            'quotations'      => ['label' => 'Quotations', 'icon' => 'fa-file-signature', 'color' => '#3B82F6'],
            'sale_orders'     => ['label' => 'Sale Orders', 'icon' => 'fa-clipboard-check', 'color' => '#10B981'],
            'delivery_orders' => ['label' => 'Delivery Orders', 'icon' => 'fa-truck-fast', 'color' => '#F59E0B'],
            'invoices'        => ['label' => 'Invoices', 'icon' => 'fa-file-invoice-dollar', 'color' => '#8B5CF6'],
            'credit_notes'    => ['label' => 'Credit Notes', 'icon' => 'fa-receipt', 'color' => '#EC4899'],
            'payments'        => ['label' => 'Payments', 'icon' => 'fa-money-check-dollar', 'color' => '#06B6D4'],
            'refunds'         => ['label' => 'Refunds', 'icon' => 'fa-rotate-left', 'color' => '#EF4444']
        ];
    }

    public static function getPurchaseTypes(): array {
        return [
            'purchase_orders' => ['label' => 'Purchase Orders', 'icon' => 'fa-cart-shopping', 'color' => '#3B82F6'],
            'grn'             => ['label' => 'Goods Received Notes', 'icon' => 'fa-boxes-packing', 'color' => '#10B981'],
            'bills'           => ['label' => 'Bills', 'icon' => 'fa-file-invoice', 'color' => '#F59E0B'],
            'credit_notes'    => ['label' => 'Credit Notes', 'icon' => 'fa-receipt', 'color' => '#8B5CF6'],
            'payments'        => ['label' => 'Payments', 'icon' => 'fa-money-bill-transfer', 'color' => '#06B6D4'],
            'refunds'         => ['label' => 'Refunds', 'icon' => 'fa-rotate-left', 'color' => '#EF4444']
        ];
    }
}

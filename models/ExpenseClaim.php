<?php
/**
 * Expense Claim Model — TBBA ERP Module 3
 * Workflow v3: Staff -> Finance Review -> Approval Center -> Management Approval -> Paid
 */
require_once __DIR__ . '/../config/database.php';

class ExpenseClaim {
    private static function nextClaimNo(): string {
        $y = date('Y');
        $m = date('m');
        $prefix = 'EC-' . $y . $m . '-';

        $last = Database::query(
            "SELECT claim_no FROM expense_claims WHERE claim_no LIKE ? ORDER BY claim_no DESC LIMIT 1 FOR UPDATE",
            [$prefix . '%']
        )->fetchColumn();

        $sequence = $last && preg_match('/(\d+)$/', (string)$last, $match)
            ? (int)$match[1] + 1
            : 1;

        return $prefix . str_pad((string)$sequence, 4, '0', STR_PAD_LEFT);
    }

    public static function categories(): array {
        return [
            'Airfares',
            'Accommodation',
            'Meals & Entertainment - Staff',
            'Meals & Entertainment - Client',
            'Printing & Stationery',
            'Office Refreshment',
            'Gift & Donation (CSR)',
            'Medical',
            'Telephone & Internet',
            'Allowance',
            'Courier & Postage',
            'Petrol, Toll & Parking',
            'Grab',
            'Misc Expenses',
        ];
    }

    private static function baseSelect(): string {
        return "SELECT ec.*,
                       u.name AS user_name,
                       u.department_id,
                       d.name AS department_name,
                       ab.name AS approved_by_name,
                       ap.id AS approval_id,
                       ap.status AS approval_status,
                       ap.approver_id AS management_approver_id,
                       ap.comment AS approval_comment,
                       ap.created_at AS finance_forwarded_at,
                       ap.actioned_at AS management_actioned_at,
                       ma.name AS management_approver_name,
                       EXISTS(
                           SELECT 1
                           FROM signatures s
                           WHERE s.document_type='expense_claim'
                             AND s.document_id=ec.id
                             AND s.signer_role='applicant'
                       ) AS applicant_signed,
                       EXISTS(
                           SELECT 1
                           FROM signatures s2
                           WHERE s2.document_type='expense_claim'
                             AND s2.document_id=ec.id
                             AND s2.signer_role='approver'
                       ) AS approver_signed
                FROM expense_claims ec
                JOIN users u ON u.id = ec.user_id
                LEFT JOIN departments d ON d.id = u.department_id
                LEFT JOIN users ab ON ab.id = ec.approved_by
                LEFT JOIN approvals ap ON ap.id = (
                    SELECT MAX(a2.id)
                    FROM approvals a2
                    WHERE a2.module='expense' AND a2.record_id=ec.id
                )
                LEFT JOIN users ma ON ma.id = ap.approver_id";
    }

    private static function decorate(array $row): array {
        $status = strtolower((string)($row['status'] ?? 'draft'));
        $approvalStatus = strtolower((string)($row['approval_status'] ?? ''));

        if ($status === 'pending') {
            if ($approvalStatus === 'pending') {
                $row['workflow_stage'] = 'management_approval';
                $row['workflow_label'] = 'Awaiting Management Approval';
            } else {
                $row['workflow_stage'] = 'finance_review';
                $row['workflow_label'] = 'Pending Finance Review';
            }
        } elseif ($status === 'approved') {
            $row['workflow_stage'] = 'approved';
            $row['workflow_label'] = 'Approved — Awaiting Payment';
        } elseif ($status === 'rejected') {
            $row['workflow_stage'] = 'rejected';
            $row['workflow_label'] = 'Rejected';
        } elseif ($status === 'paid') {
            $row['workflow_stage'] = 'paid';
            $row['workflow_label'] = 'Paid';
        } else {
            $row['workflow_stage'] = 'draft';
            $row['workflow_label'] = 'Draft';
        }

        $row['applicant_signed'] = !empty($row['applicant_signed']) ? 1 : 0;
        $row['approver_signed'] = !empty($row['approver_signed']) ? 1 : 0;
        return $row;
    }

    private static function decorateAll(array $rows): array {
        return array_map(static fn(array $row): array => self::decorate($row), $rows);
    }

    public static function getAll(?string $status = null, ?int $userId = null, ?int $deptId = null): array {
        $sql = self::baseSelect();
        $params = [];
        $where = [];

        if ($status) {
            $where[] = 'ec.status=?';
            $params[] = $status;
        }
        if ($userId) {
            $where[] = 'ec.user_id=?';
            $params[] = $userId;
        }
        if ($deptId !== null && $deptId > 0) {
            $where[] = 'u.department_id=?';
            $params[] = $deptId;
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY ec.created_at DESC';
        return self::decorateAll(Database::query($sql, $params)->fetchAll());
    }

    /** Pending claims that have NOT yet been forwarded by Finance. */
    public static function getPendingFinance(?int $deptId = null, ?int $userId = null): array {
        $rows = self::getAll('pending', $userId, $deptId);
        return array_values(array_filter($rows, static function (array $row): bool {
            return strtolower((string)($row['approval_status'] ?? '')) !== 'pending';
        }));
    }

    /** Pending claims that Finance has verified and sent to Approval Center. */
    public static function getAwaitingApproval(?int $deptId = null): array {
        $rows = self::getAll('pending', null, $deptId);
        return array_values(array_filter($rows, static function (array $row): bool {
            return strtolower((string)($row['approval_status'] ?? '')) === 'pending';
        }));
    }

    public static function findById(int $id): ?array {
        $sql = self::baseSelect() . ' WHERE ec.id=? LIMIT 1';
        $row = Database::query($sql, [$id])->fetch();
        return $row ? self::decorate($row) : null;
    }

    public static function getItems(int $claimId): array {
        return Database::query(
            'SELECT * FROM expense_items WHERE claim_id=? ORDER BY id',
            [$claimId]
        )->fetchAll();
    }

    public static function getItemById(int $itemId): ?array {
        $row = Database::query(
            "SELECT ei.*, ec.user_id, ec.status AS claim_status, ec.claim_no
             FROM expense_items ei
             JOIN expense_claims ec ON ec.id=ei.claim_id
             WHERE ei.id=? LIMIT 1",
            [$itemId]
        )->fetch();
        return $row ?: null;
    }

    public static function getCategoryTotals(int $claimId): array {
        return Database::query(
            "SELECT category, COALESCE(SUM(amount),0) AS total
             FROM expense_items
             WHERE claim_id=?
             GROUP BY category
             ORDER BY category ASC",
            [$claimId]
        )->fetchAll();
    }

    public static function hasSignature(int $claimId, string $role, ?int $userId = null): bool {
        $sql = "SELECT 1 FROM signatures
                WHERE document_type='expense_claim'
                  AND document_id=?
                  AND signer_role=?";
        $params = [$claimId, $role];

        if ($userId !== null && $userId > 0) {
            $sql .= ' AND user_id=?';
            $params[] = $userId;
        }

        $sql .= ' LIMIT 1';
        return (bool)Database::query($sql, $params)->fetchColumn();
    }

    public static function hasPendingApproval(int $claimId): bool {
        return (bool)Database::query(
            "SELECT 1 FROM approvals
             WHERE module='expense' AND record_id=? AND status='pending'
             ORDER BY id DESC LIMIT 1",
            [$claimId]
        )->fetchColumn();
    }

    public static function getPending(?int $deptId = null): array {
        return self::getAll('pending', null, $deptId);
    }

    public static function countByStatus(int $userId, string $status): int {
        return (int)Database::query(
            'SELECT COUNT(*) FROM expense_claims WHERE user_id=? AND status=?',
            [$userId, $status]
        )->fetchColumn();
    }

    public static function totalByStatus(int $userId, string $status): float {
        return (float)Database::query(
            'SELECT COALESCE(SUM(total_amount),0) FROM expense_claims WHERE user_id=? AND status=?',
            [$userId, $status]
        )->fetchColumn();
    }

    public static function totalByStatuses(int $userId, array $statuses): float {
        $statuses = array_values(array_unique(array_filter(array_map('strval', $statuses))));
        if (!$statuses) return 0.0;

        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $params = array_merge([$userId], $statuses);
        return (float)Database::query(
            "SELECT COALESCE(SUM(total_amount),0)
             FROM expense_claims
             WHERE user_id=? AND status IN ({$placeholders})",
            $params
        )->fetchColumn();
    }

    private static bool $schemaChecked = false;

    public static function ensureColumnsExist(): void {
        if (self::$schemaChecked) return;

        try {
            Database::query('SELECT receipt_path FROM expense_claims LIMIT 0');
            Database::query('SELECT receipt_path FROM expense_items LIMIT 0');
            Database::query('SELECT module,record_id,status FROM approvals LIMIT 0');
            self::$schemaChecked = true;
        } catch (Throwable $e) {
            throw new RuntimeException('Expense claim schema is unavailable. Run the database migrations.', 0, $e);
        }
    }

    public static function create(array $data, array $items): int {
        self::ensureColumnsExist();
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $claimNo = self::nextClaimNo();
            Database::query(
                "INSERT INTO expense_claims
                    (claim_no,user_id,title,total_amount,expense_date,project_ref,notes,receipt_path,status)
                 VALUES (?,?,?,?,?,?,?,?, 'pending')",
                [
                    $claimNo,
                    $data['user_id'],
                    $data['title'],
                    $data['total_amount'],
                    $data['expense_date'],
                    $data['project_ref'] ?? null,
                    $data['notes'] ?? null,
                    $data['receipt_path'] ?? null,
                ]
            );

            $id = (int)Database::lastInsertId();

            foreach ($items as $item) {
                Database::query(
                    "INSERT INTO expense_items
                        (claim_id,description,category,amount,receipt_path)
                     VALUES (?,?,?,?,?)",
                    [
                        $id,
                        $item['description'],
                        $item['category'] ?? 'Misc Expenses',
                        $item['amount'],
                        $item['receipt_path'] ?? null,
                    ]
                );
            }

            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Finance verifies a pending claim and places it in Approval Center. */
    public static function forwardToApproval(int $id, int $requesterId): bool {
        self::ensureColumnsExist();
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $claim = Database::query(
                'SELECT id,user_id,status FROM expense_claims WHERE id=? FOR UPDATE',
                [$id]
            )->fetch();

            if (!$claim || $claim['status'] !== 'pending') {
                $pdo->rollBack();
                return false;
            }

            $existing = Database::query(
                "SELECT id FROM approvals
                 WHERE module='expense' AND record_id=? AND status='pending'
                 ORDER BY id DESC LIMIT 1 FOR UPDATE",
                [$id]
            )->fetchColumn();

            if ($existing) {
                $pdo->rollBack();
                return false;
            }

            Database::query(
                "INSERT INTO approvals (module,record_id,requester_id,status,created_at)
                 VALUES ('expense',?,?, 'pending', NOW())",
                [$id, $requesterId]
            );

            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Final boss decision. Updates the claim and its pending Approval Center row atomically. */
    public static function finalizeApproval(
        int $id,
        string $decision,
        int $approverId,
        ?string $reason = null
    ): bool {
        if (!in_array($decision, ['approved', 'rejected'], true)) return false;

        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $claim = Database::query(
                'SELECT id,status FROM expense_claims WHERE id=? FOR UPDATE',
                [$id]
            )->fetch();

            if (!$claim || $claim['status'] !== 'pending') {
                $pdo->rollBack();
                return false;
            }

            $approval = Database::query(
                "SELECT id FROM approvals
                 WHERE module='expense' AND record_id=? AND status='pending'
                 ORDER BY id DESC LIMIT 1 FOR UPDATE",
                [$id]
            )->fetch();

            if (!$approval) {
                $pdo->rollBack();
                return false;
            }

            $stmt = Database::query(
                "UPDATE expense_claims
                 SET status=?, approved_by=?, approved_at=NOW(), rejection_reason=?
                 WHERE id=? AND status='pending'",
                [$decision, $approverId, $reason, $id]
            );

            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();
                return false;
            }

            Database::query(
                "UPDATE approvals
                 SET status=?, approver_id=?, comment=?, actioned_at=NOW()
                 WHERE id=? AND status='pending'",
                [$decision, $approverId, $reason, (int)$approval['id']]
            );

            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Backward-compatible status updater.
     * Final approval/rejection now goes through the Approval Center record.
     */
    public static function updateStatus(int $id, string $status, int $approverId, ?string $reason = null): bool {
        if (in_array($status, ['approved', 'rejected'], true)) {
            return self::finalizeApproval($id, $status, $approverId, $reason);
        }

        if ($status === 'paid') {
            $stmt = Database::query(
                "UPDATE expense_claims
                 SET status='paid', paid_at=NOW()
                 WHERE id=? AND status='approved'",
                [$id]
            );
            return $stmt->rowCount() === 1;
        }

        return false;
    }

    public static function delete(int $id): void {
        $pdo = Database::connect();
        $pdo->beginTransaction();
        $paths = [];

        try {
            $claim = Database::query(
                'SELECT receipt_path,signed_pdf_path FROM expense_claims WHERE id=? FOR UPDATE',
                [$id]
            )->fetch();

            if (!$claim) throw new RuntimeException('Expense claim not found.');

            $paths[] = $claim['receipt_path'] ?? null;
            $paths[] = $claim['signed_pdf_path'] ?? null;

            $itemPaths = Database::query(
                'SELECT receipt_path FROM expense_items WHERE claim_id=?',
                [$id]
            )->fetchAll(PDO::FETCH_COLUMN);

            $signaturePaths = Database::query(
                "SELECT signature_url FROM signatures
                 WHERE document_type=? AND document_id=?",
                ['expense_claim', $id]
            )->fetchAll(PDO::FETCH_COLUMN);

            $paths = array_merge($paths, $itemPaths, $signaturePaths);

            Database::query(
                "DELETE FROM approvals WHERE module='expense' AND record_id=?",
                [$id]
            );
            Database::query(
                'DELETE FROM signatures WHERE document_type=? AND document_id=?',
                ['expense_claim', $id]
            );
            Database::query('DELETE FROM expense_claims WHERE id=?', [$id]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        foreach (array_filter(array_unique($paths)) as $path) {
            self::deleteStoredFile((string)$path);
        }
    }

    private static function deleteStoredFile(string $relativePath): void {
        $relativePath = str_replace('\\', '/', ltrim($relativePath, '/'));
        if ($relativePath === '' || str_contains($relativePath, '..')) return;

        $allowed = [
            'storage/private/expenses/',
            'uploads/expenses/',
            'storage/private/signatures/',
            'uploads/signatures/',
            'storage/private/signed_pdfs/',
            'uploads/signed_pdfs/',
        ];

        if (!array_filter($allowed, static fn($prefix) => str_starts_with($relativePath, $prefix))) return;

        $absolute = realpath(__DIR__ . '/../' . $relativePath);
        if ($absolute && is_file($absolute)) @unlink($absolute);
    }
}
?>

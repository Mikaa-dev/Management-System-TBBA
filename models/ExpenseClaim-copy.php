<?php
/**
 * Expense Claim Model — TBBA ERP Module 3
 */
require_once __DIR__ . '/../config/database.php';

class ExpenseClaim {
    private static function nextClaimNo(): string {
        $y = date('Y'); $m = date('m');
        $prefix = 'EC-' . $y . $m . '-';
        $last = Database::query(
            "SELECT claim_no FROM expense_claims WHERE claim_no LIKE ? ORDER BY claim_no DESC LIMIT 1 FOR UPDATE",
            [$prefix . '%']
        )->fetchColumn();
        $sequence = $last && preg_match('/(\d+)$/', (string)$last, $match) ? (int)$match[1] + 1 : 1;
        return $prefix . str_pad((string)$sequence, 4, '0', STR_PAD_LEFT);
    }

    public static function getAll(?string $status = null, ?int $userId = null, ?int $deptId = null): array {
        $sql = "SELECT ec.*, u.name AS user_name, u.department_id, d.name AS department_name,
                       ab.name AS approved_by_name
                FROM `expense_claims` ec
                JOIN `users` u ON u.id = ec.user_id
                LEFT JOIN `departments` d ON d.id = u.department_id
                LEFT JOIN `users` ab ON ab.id = ec.approved_by";
        $params = []; $where = [];
        if ($status) { $where[] = "ec.status=?"; $params[] = $status; }
        if ($userId) { $where[] = "ec.user_id=?"; $params[] = $userId; }
        if ($deptId !== null && $deptId > 0) { $where[] = "u.department_id=?"; $params[] = $deptId; }
        if ($where) $sql .= " WHERE " . implode(" AND ", $where);
        $sql .= " ORDER BY ec.created_at DESC";
        return Database::query($sql, $params)->fetchAll();
    }

    public static function findById(int $id): ?array {
        $r = Database::query(
            "SELECT ec.*, u.name AS user_name, ab.name AS approved_by_name
             FROM `expense_claims` ec JOIN `users` u ON u.id=ec.user_id
             LEFT JOIN `users` ab ON ab.id=ec.approved_by
             WHERE ec.id=? LIMIT 1", [$id]
        )->fetch();
        return $r ?: null;
    }

    public static function getItems(int $claimId): array {
        return Database::query("SELECT * FROM `expense_items` WHERE `claim_id`=? ORDER BY `id`", [$claimId])->fetchAll();
    }

    public static function getPending(?int $deptId = null): array { return self::getAll('pending', null, $deptId); }

    public static function countByStatus(int $userId, string $status): int {
        return (int)Database::query(
            "SELECT COUNT(*) FROM `expense_claims` WHERE `user_id`=? AND `status`=?", [$userId, $status]
        )->fetchColumn();
    }

    public static function totalByStatus(int $userId, string $status): float {
        return (float)Database::query(
            "SELECT COALESCE(SUM(total_amount),0) FROM `expense_claims` WHERE `user_id`=? AND `status`=?", [$userId, $status]
        )->fetchColumn();
    }

    private static $schemaChecked = false;
    public static function ensureColumnsExist(): void {
        if (self::$schemaChecked) return;
        try {
            Database::query("SELECT receipt_path FROM expense_claims LIMIT 0");
            Database::query("SELECT receipt_path FROM expense_items LIMIT 0");
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
                "INSERT INTO `expense_claims` (`claim_no`,`user_id`,`title`,`total_amount`,`expense_date`,`project_ref`,`notes`,`receipt_path`,`status`)
                 VALUES (?,?,?,?,?,?,?,?,'pending')",
                [$claimNo,$data['user_id'],$data['title'],$data['total_amount'],
                 $data['expense_date'],$data['project_ref']??null,$data['notes']??null,$data['receipt_path']??null]
            );
            $id = (int)Database::lastInsertId();
            foreach ($items as $item) {
                Database::query(
                    "INSERT INTO `expense_items` (`claim_id`,`description`,`category`,`amount`,`receipt_path`) VALUES (?,?,?,?,?)",
                    [$id, $item['description'], $item['category']??'Others', $item['amount'], $item['receipt_path']??null]
                );
            }
            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function updateStatus(int $id, string $status, int $approverId, ?string $reason = null): bool {
        $sql = "UPDATE `expense_claims` SET `status`=?, `approved_by`=?, `approved_at`=NOW(), `rejection_reason`=?";
        if ($status === 'paid') $sql .= ", `paid_at`=NOW()";
        $expected = $status === 'paid' ? 'approved' : 'pending';
        $sql .= " WHERE `id`=? AND `status`=?";
        $stmt = Database::query($sql, [$status, $approverId, $reason, $id, $expected]);
        return $stmt->rowCount() === 1;
    }

    public static function delete(int $id): void {
        $pdo = Database::connect();
        $pdo->beginTransaction();
        $paths = [];
        try {
            $claim = Database::query("SELECT receipt_path,signed_pdf_path FROM expense_claims WHERE id=? FOR UPDATE", [$id])->fetch();
            if (!$claim) throw new RuntimeException('Expense claim not found.');
            $paths[] = $claim['receipt_path'] ?? null;
            $paths[] = $claim['signed_pdf_path'] ?? null;
            $itemPaths = Database::query("SELECT receipt_path FROM expense_items WHERE claim_id=?", [$id])->fetchAll(PDO::FETCH_COLUMN);
            $signaturePaths = Database::query(
                "SELECT signature_url FROM signatures WHERE document_type=? AND document_id=?",
                ['expense_claim', $id]
            )->fetchAll(PDO::FETCH_COLUMN);
            $paths = array_merge($paths, $itemPaths, $signaturePaths);
            Database::query("DELETE FROM signatures WHERE document_type=? AND document_id=?", ['expense_claim', $id]);
            Database::query("DELETE FROM expense_claims WHERE id=?", [$id]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        foreach (array_filter(array_unique($paths)) as $path) self::deleteStoredFile((string)$path);
    }

    private static function deleteStoredFile(string $relativePath): void {
        $relativePath = str_replace('\\', '/', ltrim($relativePath, '/'));
        if ($relativePath === '' || str_contains($relativePath, '..')) return;
        $allowed = ['storage/private/expenses/', 'uploads/expenses/', 'storage/private/signatures/',
            'uploads/signatures/', 'storage/private/signed_pdfs/', 'uploads/signed_pdfs/'];
        if (!array_filter($allowed, fn($prefix) => str_starts_with($relativePath, $prefix))) return;
        $absolute = realpath(__DIR__ . '/../' . $relativePath);
        if ($absolute && is_file($absolute)) @unlink($absolute);
    }
}
?>

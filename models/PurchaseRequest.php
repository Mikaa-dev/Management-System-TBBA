<?php
/**
 * Purchase Request Model — TBBA ERP Module 4
 */
require_once __DIR__ . '/../config/database.php';

class PurchaseRequest {
    private static function nextPrNo(): string {
        $y = date('Y'); $m = date('m');
        $prefix = 'PR-' . $y . $m . '-';
        $last = Database::query(
            "SELECT pr_no FROM purchase_requests WHERE pr_no LIKE ? ORDER BY pr_no DESC LIMIT 1 FOR UPDATE",
            [$prefix . '%']
        )->fetchColumn();
        $sequence = $last && preg_match('/(\d+)$/', (string)$last, $match) ? (int)$match[1] + 1 : 1;
        return $prefix . str_pad((string)$sequence, 4, '0', STR_PAD_LEFT);
    }

    public static function getAll(?string $status = null, ?int $userId = null, ?int $deptId = null): array {
        $sql = "SELECT pr.*, u.name AS user_name, u.department_id, d.name AS department_name,
                       ab.name AS approved_by_name
                FROM `purchase_requests` pr JOIN `users` u ON u.id=pr.user_id
                LEFT JOIN `departments` d ON d.id=pr.department_id
                LEFT JOIN `users` ab ON ab.id=pr.approved_by";
        $params = []; $where = [];
        if ($status) { $where[] = "pr.status=?"; $params[] = $status; }
        if ($userId) { $where[] = "pr.user_id=?"; $params[] = $userId; }
        if ($deptId !== null && $deptId > 0) { $where[] = "u.department_id=?"; $params[] = $deptId; }
        if ($where) $sql .= " WHERE " . implode(" AND ", $where);
        $sql .= " ORDER BY pr.created_at DESC";
        return Database::query($sql, $params)->fetchAll();
    }

    public static function findById(int $id): ?array {
        $r = Database::query(
            "SELECT pr.*, u.name AS user_name, d.name AS department_name, ab.name AS approved_by_name
             FROM `purchase_requests` pr JOIN `users` u ON u.id=pr.user_id
             LEFT JOIN `departments` d ON d.id=pr.department_id
             LEFT JOIN `users` ab ON ab.id=pr.approved_by
             WHERE pr.id=? LIMIT 1", [$id]
        )->fetch();
        return $r ?: null;
    }

    public static function getItems(int $prId): array {
        return Database::query("SELECT * FROM `purchase_items` WHERE `pr_id`=? ORDER BY `id`", [$prId])->fetchAll();
    }

    public static function getPending(?int $deptId = null): array { return self::getAll('pending', null, $deptId); }

    public static function countByStatus(int $userId, string $status): int {
        return (int)Database::query(
            "SELECT COUNT(*) FROM `purchase_requests` WHERE `user_id`=? AND `status`=?", [$userId,$status]
        )->fetchColumn();
    }

    public static function create(array $data, array $items): int {
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $prNo = self::nextPrNo();
            Database::query(
                "INSERT INTO `purchase_requests` (`pr_no`,`user_id`,`title`,`total_amount`,`vendor`,`required_date`,`department_id`,`project_ref`,`justification`,`status`)
                 VALUES (?,?,?,?,?,?,?,?,?,'pending')",
                [$prNo,$data['user_id'],$data['title'],$data['total_amount'],
                 $data['vendor']??null,$data['required_date']??null,$data['department_id']??null,
                 $data['project_ref']??null,$data['justification']??null]
            );
            $id = (int)Database::lastInsertId();
            foreach ($items as $item) {
                $total = (float)$item['quantity'] * (float)$item['unit_price'];
                Database::query(
                    "INSERT INTO `purchase_items` (`pr_id`,`description`,`quantity`,`unit`,`unit_price`,`total_price`) VALUES (?,?,?,?,?,?)",
                    [$id,$item['description'],$item['quantity']??1,$item['unit']??'unit',$item['unit_price'],$total]
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
        $expected = match ($status) {
            'ordered' => 'approved',
            'received' => 'ordered',
            default => 'pending',
        };
        $stmt = Database::query(
            "UPDATE `purchase_requests` SET `status`=?, `approved_by`=?, `approved_at`=NOW(), `rejection_reason`=? WHERE `id`=? AND `status`=?",
            [$status, $approverId, $reason, $id, $expected]
        );
        return $stmt->rowCount() === 1;
    }

    public static function delete(int $id): void {
        Database::query("DELETE FROM `purchase_requests` WHERE `id`=?", [$id]);
    }
}
?>

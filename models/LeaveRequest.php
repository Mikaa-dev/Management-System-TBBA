<?php
/**
 * Leave Request Model — TBBA ERP Module 2
 */
require_once __DIR__ . '/../config/database.php';

class LeaveRequest {
    // ─── Leave Types ───────────────────────────────────────────────────────────
    public static function getLeaveTypes(): array {
        return Database::query("SELECT * FROM `leave_types` WHERE `status`='active' ORDER BY `name` ASC")->fetchAll();
    }
    public static function getLeaveTypeById(int $id): ?array {
        $r = Database::query("SELECT * FROM `leave_types` WHERE `id`=? LIMIT 1", [$id])->fetch();
        return $r ?: null;
    }

    // ─── Schema Auto-Heal ──────────────────────────────────────────────────────
    private static $schemaChecked = false;
    public static function ensureColumnsExist(): void {
        if (self::$schemaChecked) return;
        try {
            Database::query("SELECT proof_type, attachment_path, gps_coordinates FROM leave_requests LIMIT 0");
            self::$schemaChecked = true;
        } catch (Throwable $e) {
            throw new RuntimeException('Leave request schema is unavailable. Run the database migrations.', 0, $e);
        }
    }

    // ─── Leave Requests ────────────────────────────────────────────────────────
    public static function getAll(?string $status = null, ?int $deptId = null): array {
        self::ensureColumnsExist();
        $sql = "SELECT lr.*, u.name AS user_name, u.department_id, d.name AS department_name,
                       lt.name AS leave_type_name, lt.color AS leave_color,
                       ab.name AS approved_by_name
                FROM `leave_requests` lr
                JOIN `users` u ON u.id = lr.user_id
                LEFT JOIN `departments` d ON d.id = u.department_id
                LEFT JOIN `leave_types` lt ON lt.id = lr.leave_type_id
                LEFT JOIN `users` ab ON ab.id = lr.approved_by";
        $params = [];
        $where = [];
        if ($status) { $where[] = "lr.status=?"; $params[] = $status; }
        if ($deptId) { $where[] = "u.department_id=?"; $params[] = $deptId; }
        if ($where) $sql .= " WHERE " . implode(" AND ", $where);
        $sql .= " ORDER BY lr.created_at DESC";
        return Database::query($sql, $params)->fetchAll();
    }

    public static function getByUser(int $userId, ?string $status = null): array {
        self::ensureColumnsExist();
        $sql = "SELECT lr.*, lt.name AS leave_type_name, lt.color AS leave_color,
                       ab.name AS approved_by_name
                FROM `leave_requests` lr
                LEFT JOIN `leave_types` lt ON lt.id = lr.leave_type_id
                LEFT JOIN `users` ab ON ab.id = lr.approved_by
                WHERE lr.user_id=?";
        $params = [$userId];
        if ($status) { $sql .= " AND lr.status=?"; $params[] = $status; }
        $sql .= " ORDER BY lr.created_at DESC";
        return Database::query($sql, $params)->fetchAll();
    }

    public static function findById(int $id): ?array {
        self::ensureColumnsExist();
        $r = Database::query(
            "SELECT lr.*, u.name AS user_name, u.email AS user_email,
                    lt.name AS leave_type_name, lt.color AS leave_color, lt.days_allowed,
                    ab.name AS approved_by_name
             FROM `leave_requests` lr
             JOIN `users` u ON u.id = lr.user_id
             LEFT JOIN `leave_types` lt ON lt.id = lr.leave_type_id
             LEFT JOIN `users` ab ON ab.id = lr.approved_by
             WHERE lr.id=? LIMIT 1", [$id]
        )->fetch();
        return $r ?: null;
    }

    public static function getPending(): array {
        return self::getAll('pending');
    }

    public static function countByStatus(int $userId, string $status): int {
        return (int)Database::query(
            "SELECT COUNT(*) FROM `leave_requests` WHERE `user_id`=? AND `status`=?", [$userId, $status]
        )->fetchColumn();
    }

    public static function getTotalDaysUsed(int $userId, int $leaveTypeId, int $year): float {
        $v = Database::query(
            "SELECT COALESCE(SUM(total_days),0) FROM `leave_requests`
             WHERE `user_id`=? AND `leave_type_id`=? AND YEAR(start_date)=? AND `status`='approved'",
            [$userId, $leaveTypeId, $year]
        )->fetchColumn();
        return (float)$v;
    }

    public static function create(array $data): int {
        self::ensureColumnsExist();
        Database::query(
            "INSERT INTO `leave_requests` (`user_id`,`leave_type_id`,`start_date`,`end_date`,`total_days`,`half_day`,`reason`,`proof_type`,`attachment_path`,`gps_coordinates`,`status`)
             VALUES (?,?,?,?,?,?,?,?,?,?,'pending')",
            [$data['user_id'],$data['leave_type_id'],$data['start_date'],$data['end_date'],
             $data['total_days'],$data['half_day']??0,$data['reason']??null,
             $data['proof_type'] ?? 'none', $data['attachment_path'] ?? null, $data['gps_coordinates'] ?? null]
        );
        return (int)Database::lastInsertId();
    }

    public static function updateStatus(int $id, string $status, int $approverId, ?string $reason = null): bool {
        $stmt = Database::query(
            "UPDATE `leave_requests` SET `status`=?, `approved_by`=?, `approved_at`=NOW(), `rejection_reason`=? WHERE `id`=? AND `status`='pending'",
            [$status, $approverId, $reason, $id]
        );
        return $stmt->rowCount() === 1;
    }

    public static function cancel(int $id, int $userId): bool {
        $stmt = Database::query(
            "UPDATE `leave_requests` SET `status`='cancelled' WHERE `id`=? AND `user_id`=? AND `status`='pending'",
            [$id, $userId]
        );
        return $stmt->rowCount() === 1;
    }

    public static function delete(int $id): void {
        Database::query("DELETE FROM `leave_requests` WHERE `id`=?", [$id]);
    }
}
?>

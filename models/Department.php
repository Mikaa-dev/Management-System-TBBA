<?php
/**
 * Department Model — TBBA ERP
 * Company: The Bridge Business Alliance (TBBA)
 */

require_once __DIR__ . '/../config/database.php';

class Department {
    /** Get all departments with head user info */
    public static function getAll(): array {
        try {
            return Database::query(
                "SELECT d.*, u.name AS head_name
                 FROM `departments` d
                 LEFT JOIN `users` u ON u.id = d.head_user_id
                 ORDER BY d.name ASC"
            )->fetchAll();
        } catch (Exception $e) {
            error_log("[Department] getAll error: " . $e->getMessage());
            return [];
        }
    }

    /** Get all active departments */
    public static function getAllActive(): array {
        try {
            return Database::query(
                "SELECT d.*, u.name AS head_name
                 FROM `departments` d
                 LEFT JOIN `users` u ON u.id = d.head_user_id
                 WHERE d.status = 'active'
                 ORDER BY d.name ASC"
            )->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    /** Find by ID */
    public static function findById(int $id): ?array {
        try {
            $row = Database::query(
                "SELECT d.*, u.name AS head_name
                 FROM `departments` d
                 LEFT JOIN `users` u ON u.id = d.head_user_id
                 WHERE d.id = ? LIMIT 1",
                [$id]
            )->fetch();
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /** Create new department */
    public static function create(array $data): int {
        Database::query(
            "INSERT INTO `departments` (`name`, `code`, `head_user_id`, `description`, `contact_email`, `contact_phone`, `status`)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $data['name'],
                $data['code'] ?? null,
                $data['head_user_id'] ?? null,
                $data['description'] ?? null,
                $data['contact_email'] ?? null,
                $data['contact_phone'] ?? null,
                $data['status'] ?? 'active',
            ]
        );
        return (int)Database::lastInsertId();
    }

    /** Update department */
    public static function update(int $id, array $data): void {
        Database::query(
            "UPDATE `departments` SET `name`=?, `code`=?, `head_user_id`=?, `description`=?,
             `contact_email`=?, `contact_phone`=?, `status`=?, `updated_at`=NOW()
             WHERE `id`=?",
            [
                $data['name'],
                $data['code'] ?? null,
                $data['head_user_id'] ?? null,
                $data['description'] ?? null,
                $data['contact_email'] ?? null,
                $data['contact_phone'] ?? null,
                $data['status'] ?? 'active',
                $id,
            ]
        );
    }

    /** Delete department */
    public static function delete(int $id): void {
        Database::query("DELETE FROM `departments` WHERE `id` = ?", [$id]);
    }

    /** Validate that a selected department head belongs to the same organization branch. */
    public static function validateHeadAssignment(?int $headUserId, ?int $departmentId = null): void {
        if ($headUserId === null) return;

        $user = Database::query(
            "SELECT u.id, u.department_id, u.position_id, p.department_id AS position_department_id
             FROM `users` u
             LEFT JOIN `positions` p ON p.id = u.position_id
             WHERE u.id=? LIMIT 1",
            [$headUserId]
        )->fetch();
        if (!$user) throw new InvalidArgumentException('Selected department head does not exist.');

        if ($departmentId === null) {
            if ($user['department_id'] !== null || $user['position_id'] !== null) {
                throw new InvalidArgumentException(
                    'For a new department, select an unassigned user first. Assign its positions after creating the department.'
                );
            }
            return;
        }

        if ($user['position_id'] !== null
            && (int)($user['position_department_id'] ?? 0) !== $departmentId) {
            throw new InvalidArgumentException('The selected head position belongs to a different department.');
        }
        if ($user['position_id'] === null && $user['department_id'] !== null
            && (int)$user['department_id'] !== $departmentId) {
            throw new InvalidArgumentException('The selected head currently belongs to a different department.');
        }
    }

    public static function synchronizeHeadDepartment(int $departmentId, ?int $headUserId): void {
        if ($headUserId === null) return;
        Database::query(
            "UPDATE `users` SET `department_id`=? WHERE `id`=?",
            [$departmentId, $headUserId]
        );
    }

    /** Count staff in a department */
    public static function countStaff(int $deptId): int {
        try {
            return (int)Database::query(
                "SELECT COUNT(*) FROM `users` WHERE `department_id` = ?", [$deptId]
            )->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /** Get simple dropdown list [id => name] */
    public static function getDropdown(): array {
        try {
            $rows = Database::query(
                "SELECT `id`, `name` FROM `departments` WHERE `status`='active' ORDER BY `name` ASC"
            )->fetchAll();
            $out = [];
            foreach ($rows as $r) $out[$r['id']] = $r['name'];
            return $out;
        } catch (Exception $e) {
            return [];
        }
    }

    /** Check if code is taken */
    public static function isCodeTaken(string $code, ?int $excludeId = null): bool {
        if (empty($code)) return false;
        try {
            if ($excludeId) {
                $cnt = Database::query(
                    "SELECT COUNT(*) FROM `departments` WHERE `code`=? AND `id`!=?", [$code, $excludeId]
                )->fetchColumn();
            } else {
                $cnt = Database::query(
                    "SELECT COUNT(*) FROM `departments` WHERE `code`=?", [$code]
                )->fetchColumn();
            }
            return (int)$cnt > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>

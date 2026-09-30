<?php
/**
 * User Model — TBBA ERP
 * Company: The Bridge Business Alliance (TBBA)
 * Updated: Module 1 — expanded fields (department, branch, status, phone, employee_id)
 */

require_once __DIR__ . '/../config/database.php';

class User {

    // ─── Lookups ──────────────────────────────────────────────────────────────

    /** Find user by email */
    public static function findByEmail(string $email): ?array {
        $stmt = Database::query("SELECT * FROM `users` WHERE `email` = ? LIMIT 1", [$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Find user by ID */
    public static function findById(int $id): ?array {
        $stmt = Database::query("SELECT * FROM `users` WHERE `id` = ? LIMIT 1", [$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Get user with department and branch info */
    public static function findByIdWithDetails(int $id): ?array {
        $stmt = Database::query(
            "SELECT u.*, d.name AS department_name, b.name AS branch_name, p.title AS position_title
             FROM `users` u
             LEFT JOIN `departments` d ON d.id = u.department_id
             LEFT JOIN `branches` b ON b.id = u.branch_id
             LEFT JOIN `positions` p ON p.id = u.position_id
             WHERE u.id = ? LIMIT 1",
            [$id]
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ─── Collections ──────────────────────────────────────────────────────────

    /** Get all staff (role = 'staff') */
    public static function getAllStaff(?int $deptId = null): array {
        $sql = "SELECT u.id, u.name, u.email, u.role, u.position, u.position_id, u.avatar, u.status, u.department_id, u.branch_id, d.name AS department_name, p.title AS position_title
                FROM `users` u
                LEFT JOIN `departments` d ON d.id = u.department_id
                LEFT JOIN `positions` p ON p.id = u.position_id
                WHERE u.role = 'staff'";
        $params = [];
        if ($deptId !== null && $deptId > 0) {
            $sql .= " AND u.department_id = ?";
            $params[] = $deptId;
        }
        $sql .= " ORDER BY u.name ASC";
        $stmt = Database::query($sql, $params);
        return $stmt->fetchAll();
    }

    /** Get all users (all roles) with department + branch */
    public static function getAll(?int $deptId = null): array {
        $sql = "SELECT u.id, u.name, u.email, u.role, u.position, u.position_id, u.avatar, u.status,
                       u.phone, u.employee_id, u.department_id, u.branch_id, u.created_at,
                       d.name AS department_name, b.name AS branch_name, p.title AS position_title
                FROM `users` u
                LEFT JOIN `departments` d ON d.id = u.department_id
                LEFT JOIN `branches` b ON b.id = u.branch_id
                LEFT JOIN `positions` p ON p.id = u.position_id";
        $params = [];
        if ($deptId !== null && $deptId > 0) {
            $sql .= " WHERE u.department_id = ?";
            $params[] = $deptId;
        }
        $sql .= " ORDER BY u.role ASC, u.name ASC";
        $stmt = Database::query($sql, $params);
        return $stmt->fetchAll();
    }

    /** Count all staff */
    public static function countAllStaff(?int $deptId = null): int {
        $sql = "SELECT COUNT(*) FROM `users` WHERE `role` = 'staff'";
        $params = [];
        if ($deptId !== null && $deptId > 0) {
            $sql .= " AND `department_id` = ?";
            $params[] = $deptId;
        }
        $stmt = Database::query($sql, $params);
        return (int)$stmt->fetchColumn();
    }

    /** Count all users by role */
    public static function countByRole(string $role): int {
        $stmt = Database::query("SELECT COUNT(*) FROM `users` WHERE `role` = ?", [$role]);
        return (int)$stmt->fetchColumn();
    }

    /** Get users for dropdown [id => name] */
    public static function getDropdown(?string $roleFilter = null): array {
        if ($roleFilter) {
            $rows = Database::query(
                "SELECT `id`, `name` FROM `users` WHERE `role`=? AND `status`='active' ORDER BY `name` ASC",
                [$roleFilter]
            )->fetchAll();
        } else {
            $rows = Database::query(
                "SELECT `id`, `name` FROM `users` WHERE `status`='active' ORDER BY `name` ASC"
            )->fetchAll();
        }
        $out = [];
        foreach ($rows as $r) $out[$r['id']] = $r['name'];
        return $out;
    }

    // ─── Write Operations ─────────────────────────────────────────────────────

    /** Create new user */
    public static function create(
        string $name, string $email, string $password, string $role, string $position,
        string $avatar = 'default.png', ?int $departmentId = null, ?int $branchId = null,
        string $status = 'active', ?string $phone = null, ?string $employeeId = null, ?int $positionId = null
    ): int {
        Database::query(
            "INSERT INTO `users` (`name`, `email`, `password`, `password_changed_at`, `password_expires_at`, `role`, `position`, `avatar`,
                                  `department_id`, `branch_id`, `status`, `phone`, `employee_id`, `position_id`)
             VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$name, $email, $password, $role, $position, $avatar,
             $departmentId, $branchId, $status, $phone, $employeeId, $positionId]
        );
        return (int)Database::lastInsertId();
    }

    /** Update user profile */
    public static function update(
        int $id, string $name, string $email, string $role, string $position,
        ?int $departmentId = null, ?int $branchId = null, string $status = 'active',
        ?string $phone = null, ?string $employeeId = null, ?int $positionId = null
    ): void {
        Database::query(
            "UPDATE `users` SET `name`=?, `email`=?, `role`=?, `position`=?,
                                `department_id`=?, `branch_id`=?, `status`=?,
                                `phone`=?, `employee_id`=?, `position_id`=?
             WHERE `id`=?",
            [$name, $email, $role, $position, $departmentId, $branchId, $status, $phone, $employeeId, $positionId, $id]
        );
    }

    /**
     * Update fields owned by the employee profile page.
     *
     * Organizational fields such as role, position, department, and branch are
     * deliberately excluded because they are managed from Staff Management.
     */
    public static function updatePersonalDetails(int $id, string $name, string $email): void {
        Database::query(
            "UPDATE `users` SET `name`=?, `email`=? WHERE `id`=?",
            [$name, $email, $id]
        );
    }

    /** Update password */
    public static function updatePassword(int $id, string $hashedPassword): void {
        Database::query("UPDATE `users` SET `password`=?,`password_changed_at`=NOW(),`password_expires_at`=DATE_ADD(NOW(),INTERVAL 90 DAY) WHERE `id`=?", [$hashedPassword, $id]);
    }

    /** Update avatar */
    public static function updateAvatar(int $id, string $avatar): void {
        Database::query("UPDATE `users` SET `avatar`=? WHERE `id`=?", [$avatar, $id]);
    }

    /** Toggle account status */
    public static function setStatus(int $id, string $status): void {
        Database::query("UPDATE `users` SET `status`=? WHERE `id`=?", [$status, $id]);
    }

    /** Delete user */
    public static function delete(int $id): void {
        Database::query("DELETE FROM `users` WHERE `id`=?", [$id]);
    }

    // ─── Validation ───────────────────────────────────────────────────────────

    /** Check if email is already taken (optionally excluding a specific user ID) */
    public static function isEmailTaken(string $email, ?int $excludeId = null): bool {
        if ($excludeId) {
            $cnt = Database::query(
                "SELECT COUNT(*) FROM `users` WHERE `email`=? AND `id`!=?", [$email, $excludeId]
            )->fetchColumn();
        } else {
            $cnt = Database::query("SELECT COUNT(*) FROM `users` WHERE `email`=?", [$email])->fetchColumn();
        }
        return (int)$cnt > 0;
    }

    /** Check if employee ID is taken */
    public static function isEmployeeIdTaken(string $empId, ?int $excludeId = null): bool {
        if (empty($empId)) return false;
        if ($excludeId) {
            $cnt = Database::query(
                "SELECT COUNT(*) FROM `users` WHERE `employee_id`=? AND `id`!=?", [$empId, $excludeId]
            )->fetchColumn();
        } else {
            $cnt = Database::query(
                "SELECT COUNT(*) FROM `users` WHERE `employee_id`=?", [$empId]
            )->fetchColumn();
        }
        return (int)$cnt > 0;
    }

    // ─── Login History ────────────────────────────────────────────────────────

    /** Record a login attempt in login_history */
    public static function logLogin(int $userId, string $ip, string $userAgent, string $status = 'success', string $method = 'email'): void {
        try {
            Database::query(
                "INSERT INTO `login_history` (`user_id`, `ip_address`, `user_agent`, `method`, `status`)
                 VALUES (?, ?, ?, ?, ?)",
                [$userId, $ip, $userAgent, $method, $status]
            );
        } catch (Exception $e) {
            error_log("[User] logLogin error: " . $e->getMessage());
        }
    }

    /** Get login history for a user */
    public static function getLoginHistory(int $userId, int $limit = 50): array {
        try {
            return Database::query(
                "SELECT * FROM `login_history` WHERE `user_id`=? ORDER BY `created_at` DESC LIMIT " . intval($limit),
                [$userId]
            )->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
}
?>

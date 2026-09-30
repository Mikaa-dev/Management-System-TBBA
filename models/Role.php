<?php
/**
 * Role Model — TBBA ERP
 * Company: The Bridge Business Alliance (TBBA)
 */

require_once __DIR__ . '/../config/database.php';

class Role {
    /** Get all roles ordered by authority level */
    public static function getAll(): array {
        try {
            return Database::query(
                "SELECT * FROM `roles` ORDER BY `level` ASC"
            )->fetchAll();
        } catch (Exception $e) {
            error_log("[Role] getAll error: " . $e->getMessage());
            return [];
        }
    }

    /** Get role by name */
    public static function findByName(string $name): ?array {
        try {
            $row = Database::query(
                "SELECT * FROM `roles` WHERE `name` = ? LIMIT 1", [$name]
            )->fetch();
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /** Get all role names as a simple array */
    public static function getAllNames(): array {
        try {
            $rows = Database::query("SELECT `name` FROM `roles` ORDER BY `level` ASC")->fetchAll();
            return array_column($rows, 'name');
        } catch (Exception $e) {
            return ['super_admin', 'admin', 'hr', 'manager', 'dept_head', 'finance', 'auditor', 'staff'];
        }
    }

    /** Get roles that a given role level can assign (only roles >= their own level) */
    public static function getAssignableBy(string $roleName): array {
        try {
            $r = self::findByName($roleName);
            if (!$r) return self::getAll();
            // super_admin can assign all; others can assign >= their own level
            $minLevel = ($r['level'] <= 1) ? 1 : $r['level'];
            return Database::query(
                "SELECT * FROM `roles` WHERE `level` >= ? ORDER BY `level` ASC",
                [$minLevel]
            )->fetchAll();
        } catch (Exception $e) {
            return self::getAll();
        }
    }
}
?>

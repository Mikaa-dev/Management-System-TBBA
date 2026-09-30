<?php
/**
 * Branch Model — TBBA ERP
 * Company: The Bridge Business Alliance (TBBA)
 */

require_once __DIR__ . '/../config/database.php';

class Branch {
    /** Get all branches */
    public static function getAll(): array {
        try {
            return Database::query("SELECT * FROM `branches` ORDER BY `name` ASC")->fetchAll();
        } catch (Exception $e) {
            error_log("[Branch] getAll error: " . $e->getMessage());
            return [];
        }
    }

    /** Get all active branches */
    public static function getAllActive(): array {
        try {
            return Database::query(
                "SELECT * FROM `branches` WHERE `status`='active' ORDER BY `name` ASC"
            )->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    /** Find by ID */
    public static function findById(int $id): ?array {
        try {
            $row = Database::query("SELECT * FROM `branches` WHERE `id`=? LIMIT 1", [$id])->fetch();
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /** Create new branch */
    public static function create(array $data): int {
        Database::query(
            "INSERT INTO `branches` (`name`, `code`, `address`, `city`, `state`, `phone`, `latitude`, `longitude`, `status`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['name'],
                $data['code'] ?? null,
                $data['address'] ?? null,
                $data['city'] ?? null,
                $data['state'] ?? null,
                $data['phone'] ?? null,
                $data['latitude'] ?? null,
                $data['longitude'] ?? null,
                $data['status'] ?? 'active',
            ]
        );
        return (int)Database::lastInsertId();
    }

    /** Update branch */
    public static function update(int $id, array $data): void {
        Database::query(
            "UPDATE `branches` SET `name`=?, `code`=?, `address`=?, `city`=?, `state`=?, `phone`=?, `latitude`=?, `longitude`=?, `status`=?
             WHERE `id`=?",
            [
                $data['name'],
                $data['code'] ?? null,
                $data['address'] ?? null,
                $data['city'] ?? null,
                $data['state'] ?? null,
                $data['phone'] ?? null,
                $data['latitude'] ?? null,
                $data['longitude'] ?? null,
                $data['status'] ?? 'active',
                $id,
            ]
        );
    }

    /** Delete branch */
    public static function delete(int $id): void {
        Database::query("DELETE FROM `branches` WHERE `id`=?", [$id]);
    }

    /** Get dropdown [id => name] */
    public static function getDropdown(): array {
        try {
            $rows = Database::query(
                "SELECT `id`, `name` FROM `branches` WHERE `status`='active' ORDER BY `name` ASC"
            )->fetchAll();
            $out = [];
            foreach ($rows as $r) $out[$r['id']] = $r['name'];
            return $out;
        } catch (Exception $e) {
            return [];
        }
    }
}
?>

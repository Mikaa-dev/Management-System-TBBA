<?php
/**
 * Event Model — TBBA ERP Module 9
 */
require_once __DIR__ . '/../config/database.php';

class Event {
    public static function getAll(?string $from = null, ?string $to = null, ?int $departmentId = null, ?int $userId = null, bool $canManage = false): array {
        $sql = "SELECT e.*, u.name AS creator_name FROM `events` e JOIN `users` u ON u.id=e.created_by";
        $params = []; $where = [];
        if ($from) { $where[] = "e.end_datetime >= ?"; $params[] = $from . ' 00:00:00'; }
        if ($to)   { $where[] = "e.start_datetime <= ?"; $params[] = $to . ' 23:59:59'; }
        if (!$canManage) {
            $where[] = "(e.is_public=1 OR e.created_by=? OR (e.department_id IS NOT NULL AND e.department_id=?))";
            $params[] = (int)($userId ?? 0);
            $params[] = (int)($departmentId ?? 0);
        }
        if ($where) $sql .= " WHERE " . implode(" AND ", $where);
        $sql .= " ORDER BY e.start_datetime ASC";
        return Database::query($sql, $params)->fetchAll();
    }

    public static function getForCalendar(string $year, string $month, ?int $departmentId = null, ?int $userId = null, bool $canManage = false): array {
        $from = "$year-$month-01";
        $to   = date('Y-m-t', strtotime($from));
        return self::getAll($from, $to, $departmentId, $userId, $canManage);
    }

    public static function findById(int $id): ?array {
        $r = Database::query(
            "SELECT e.*, u.name AS creator_name FROM `events` e JOIN `users` u ON u.id=e.created_by WHERE e.id=? LIMIT 1", [$id]
        )->fetch();
        return $r ?: null;
    }

    public static function create(array $data): int {
        Database::query(
            "INSERT INTO `events` (`title`,`description`,`type`,`start_datetime`,`end_datetime`,`all_day`,`location`,`color`,`is_public`,`department_id`,`created_by`)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [$data['title'],$data['description']??null,$data['type']??'meeting',
             $data['start_datetime'],$data['end_datetime'],$data['all_day']??0,
             $data['location']??null,$data['color']??'#2563EB',
             $data['is_public']??1,$data['department_id']??null,$data['created_by']]
        );
        return (int)Database::lastInsertId();
    }

    public static function update(int $id, array $data): void {
        Database::query(
            "UPDATE `events` SET `title`=?,`description`=?,`type`=?,`start_datetime`=?,`end_datetime`=?,`all_day`=?,`location`=?,`color`=?,`is_public`=?,`department_id`=? WHERE `id`=?",
            [$data['title'],$data['description']??null,$data['type']??'meeting',
             $data['start_datetime'],$data['end_datetime'],$data['all_day']??0,
             $data['location']??null,$data['color']??'#2563EB',
             $data['is_public']??1,$data['department_id']??null,$id]
        );
    }

    public static function delete(int $id): void {
        Database::query("DELETE FROM `events` WHERE `id`=?", [$id]);
    }

    public static function getUpcoming(int $limit = 5, ?int $departmentId = null, ?int $userId = null, bool $canManage = false): array {
        $rows = self::getAll(date('Y-m-d'), null, $departmentId, $userId, $canManage);
        return array_slice($rows, 0, max(1, min(50, $limit)));
    }
}
?>

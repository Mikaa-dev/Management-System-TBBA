<?php
/**
 * Announcement Model — TBBA ERP Module 8
 */
require_once __DIR__ . '/../config/database.php';

class Announcement {
    public static function getAll(bool $visibleOnly = false, ?int $deptId = null, ?string $role = null): array {
        $sql = "SELECT a.*, u.name AS author_name FROM `announcements` a JOIN `users` u ON u.id=a.author_id";
        $where = [];
        $params = [];
        if ($visibleOnly) {
            $where[] = "(a.published_at IS NULL OR a.published_at <= NOW())";
            $where[] = "(a.expires_at IS NULL OR a.expires_at > NOW())";
            if ($deptId || $role) {
                $audienceWhere = ["a.audience='all'"];
                if ($deptId) {
                    $audienceWhere[] = "(a.audience='department' AND a.audience_target=?)";
                    $params[] = (string)$deptId;
                }
                if ($role) {
                    $audienceWhere[] = "(a.audience='role' AND a.audience_target=?)";
                    $params[] = $role;
                }
                $where[] = "(" . implode(" OR ", $audienceWhere) . ")";
            }
        }
        if ($where) $sql .= " WHERE " . implode(" AND ", $where);
        $sql .= " ORDER BY a.is_pinned DESC, a.created_at DESC";
        return Database::query($sql, $params)->fetchAll();
    }

    public static function findById(int $id): ?array {
        $r = Database::query(
            "SELECT a.*, u.name AS author_name FROM `announcements` a JOIN `users` u ON u.id=a.author_id WHERE a.id=? LIMIT 1", [$id]
        )->fetch();
        return $r ?: null;
    }

    public static function isVisibleTo(array $announcement, ?int $departmentId, ?string $role): bool {
        $now = time();
        if (!empty($announcement['published_at']) && strtotime((string)$announcement['published_at']) > $now) return false;
        if (!empty($announcement['expires_at']) && strtotime((string)$announcement['expires_at']) <= $now) return false;

        return match ((string)($announcement['audience'] ?? 'all')) {
            'all' => true,
            'department' => $departmentId !== null
                && (string)$departmentId === (string)($announcement['audience_target'] ?? ''),
            'role' => $role !== null
                && hash_equals($role, (string)($announcement['audience_target'] ?? '')),
            default => false,
        };
    }

    public static function create(array $data): int {
        Database::query(
            "INSERT INTO `announcements` (`title`,`content`,`type`,`audience`,`audience_target`,`is_pinned`,`author_id`,`published_at`,`expires_at`)
             VALUES (?,?,?,?,?,?,?,?,?)",
            [$data['title'],$data['content'],$data['type']??'general',
             $data['audience']??'all',$data['audience_target']??null,
             $data['is_pinned']??0,$data['author_id'],
             $data['published_at']??null,$data['expires_at']??null]
        );
        return (int)Database::lastInsertId();
    }

    public static function update(int $id, array $data): void {
        Database::query(
            "UPDATE `announcements` SET `title`=?,`content`=?,`type`=?,`audience`=?,`audience_target`=?,`is_pinned`=?,`published_at`=?,`expires_at`=?,`updated_at`=NOW() WHERE `id`=?",
            [$data['title'],$data['content'],$data['type']??'general',
             $data['audience']??'all',$data['audience_target']??null,
             $data['is_pinned']??0,$data['published_at']??null,$data['expires_at']??null,$id]
        );
    }

    public static function delete(int $id): void {
        Database::query("DELETE FROM `announcements` WHERE `id`=?", [$id]);
    }

    public static function togglePin(int $id): void {
        Database::query("UPDATE `announcements` SET `is_pinned`=NOT `is_pinned` WHERE `id`=?", [$id]);
    }

    public static function getRecent(int $limit = 5, ?int $departmentId = null, ?string $role = null): array {
        $limit = max(1, min(50, $limit));
        $departmentId ??= isset($_SESSION['user_department_id']) ? (int)$_SESSION['user_department_id'] : null;
        $role ??= $_SESSION['user_role'] ?? null;
        $rows = self::getAll(true, $departmentId ?: null, $role);
        return array_slice($rows, 0, $limit);
    }
}
?>

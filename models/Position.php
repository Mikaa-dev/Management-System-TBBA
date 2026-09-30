<?php
/**
 * Position Model — TBBA ERP
 * Company: The Bridge Business Alliance (TBBA)
 * Handles Positions and Auto-Updating Org Chart logic
 */

require_once __DIR__ . '/../config/database.php';

class Position {
    
    /** Get all positions */
    public static function getAll(): array {
        try {
            return Database::query(
                "SELECT p.*, d.name AS department_name, 
                        p2.title AS reports_to_title
                 FROM `positions` p
                 LEFT JOIN `departments` d ON d.id = p.department_id
                 LEFT JOIN `positions` p2 ON p2.id = p.reports_to_position_id
                 ORDER BY p.level ASC, p.title ASC"
            )->fetchAll();
        } catch (Exception $e) {
            error_log("[Position] getAll error: " . $e->getMessage());
            return [];
        }
    }

    /** Find by ID */
    public static function findById(int $id): ?array {
        try {
            $row = Database::query(
                "SELECT p.* FROM `positions` p WHERE p.id = ? LIMIT 1",
                [$id]
            )->fetch();
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /** Create new position */
    public static function create(array $data): int {
        $data = self::normalizeData($data);
        Database::query(
            "INSERT INTO `positions` (`title`, `department_id`, `reports_to_position_id`, `level`, `max_headcount`, `status`)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $data['title'],
                $data['department_id'],
                $data['reports_to_position_id'],
                $data['level'],
                $data['max_headcount'],
                $data['status']
            ]
        );
        return (int)Database::lastInsertId();
    }

    /** Update position */
    public static function update(int $id, array $data): void {
        if (!self::findById($id)) {
            throw new InvalidArgumentException('Position not found.');
        }

        $data = self::normalizeData($data, $id);
        $conflictingHead = Database::query(
            "SELECT d.name
             FROM `departments` d
             INNER JOIN `users` u ON u.id = d.head_user_id
             WHERE u.position_id=? AND NOT (d.id <=> ?)
             LIMIT 1",
            [$id, $data['department_id']]
        )->fetchColumn();
        if ($conflictingHead !== false) {
            throw new InvalidArgumentException(
                "This position is assigned to the head of {$conflictingHead}. Update the department head first."
            );
        }
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            Database::query(
                "UPDATE `positions` SET `title`=?, `department_id`=?, `reports_to_position_id`=?, `level`=?, `max_headcount`=?, `status`=?
                 WHERE `id`=?",
                [
                    $data['title'],
                    $data['department_id'],
                    $data['reports_to_position_id'],
                    $data['level'],
                    $data['max_headcount'],
                    $data['status'],
                    $id
                ]
            );

            // A position is authoritative for both the user's title and department.
            Database::query(
                "UPDATE `users` SET `position`=?, `department_id`=? WHERE `position_id`=?",
                [$data['title'], $data['department_id'], $id]
            );
            self::recalculateDescendantLevels($id, $data['level']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Delete position */
    public static function delete(int $id): void {
        Database::query("DELETE FROM `positions` WHERE `id` = ?", [$id]);
    }

    /** Rebuild every stored level from the reports-to relationships. */
    public static function rebuildHierarchyLevels(): void {
        $rows = Database::query(
            "SELECT `id`, `reports_to_position_id` FROM `positions` ORDER BY `id`"
        )->fetchAll();
        $parents = [];
        foreach ($rows as $row) {
            $parents[(int)$row['id']] = $row['reports_to_position_id'] !== null
                ? (int)$row['reports_to_position_id']
                : null;
        }

        $levels = [];
        $resolve = function (int $id, array $path = []) use (&$resolve, &$levels, $parents): int {
            if (isset($levels[$id])) return $levels[$id];
            if (isset($path[$id])) {
                throw new RuntimeException('Hierarchy cycle detected while rebuilding position levels.');
            }
            $path[$id] = true;
            $parentId = $parents[$id] ?? null;
            if ($parentId === null || !array_key_exists($parentId, $parents)) {
                return $levels[$id] = 1;
            }
            return $levels[$id] = $resolve($parentId, $path) + 1;
        };

        foreach (array_keys($parents) as $id) {
            $level = $resolve((int)$id);
            Database::query("UPDATE `positions` SET `level`=? WHERE `id`=?", [$level, $id]);
        }
    }

    /** Keep existing staff assignments aligned with their position departments. */
    public static function synchronizeUserDepartments(): void {
        Database::query(
            "UPDATE `users` u
             INNER JOIN `positions` p ON p.id = u.position_id
             SET u.department_id = p.department_id,
                 u.position = p.title
             WHERE NOT (u.department_id <=> p.department_id) OR NOT (u.position <=> p.title)"
        );
    }

    private static function normalizeData(array $data, ?int $positionId = null): array {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Position title is required.');
        }

        $departmentId = !empty($data['department_id']) ? (int)$data['department_id'] : null;
        if ($departmentId !== null) {
            $exists = Database::query("SELECT 1 FROM `departments` WHERE `id`=?", [$departmentId])->fetchColumn();
            if (!$exists) throw new InvalidArgumentException('Selected department does not exist.');
        }

        $parentId = !empty($data['reports_to_position_id']) ? (int)$data['reports_to_position_id'] : null;
        $level = 1;
        if ($parentId !== null) {
            if ($positionId !== null && $parentId === $positionId) {
                throw new InvalidArgumentException('A position cannot report to itself.');
            }
            $parent = self::findById($parentId);
            if (!$parent) throw new InvalidArgumentException('Selected superior position does not exist.');
            if ($positionId !== null && self::wouldCreateCycle($positionId, $parentId)) {
                throw new InvalidArgumentException('This reporting line would create a hierarchy cycle.');
            }
            $level = (int)$parent['level'] + 1;
        }

        return [
            'title' => $title,
            'department_id' => $departmentId,
            'reports_to_position_id' => $parentId,
            'level' => $level,
            'max_headcount' => max(1, (int)($data['max_headcount'] ?? 1)),
            'status' => in_array($data['status'] ?? '', ['active', 'inactive'], true)
                ? $data['status']
                : 'active',
        ];
    }

    private static function wouldCreateCycle(int $positionId, int $parentId): bool {
        $visited = [];
        while ($parentId > 0) {
            if ($parentId === $positionId) return true;
            if (isset($visited[$parentId])) return true;
            $visited[$parentId] = true;
            $row = Database::query(
                "SELECT `reports_to_position_id` FROM `positions` WHERE `id`=?",
                [$parentId]
            )->fetch();
            if (!$row || $row['reports_to_position_id'] === null) return false;
            $parentId = (int)$row['reports_to_position_id'];
        }
        return false;
    }

    private static function recalculateDescendantLevels(int $positionId, int $level, array $visited = []): void {
        if (isset($visited[$positionId])) {
            throw new RuntimeException('Hierarchy cycle detected while updating descendants.');
        }
        $visited[$positionId] = true;
        $children = Database::query(
            "SELECT `id` FROM `positions` WHERE `reports_to_position_id`=?",
            [$positionId]
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($children as $childId) {
            $childId = (int)$childId;
            $childLevel = $level + 1;
            Database::query("UPDATE `positions` SET `level`=? WHERE `id`=?", [$childLevel, $childId]);
            self::recalculateDescendantLevels($childId, $childLevel, $visited);
        }
    }

    /** Get active positions for dropdown [id => title] */
    public static function getDropdown(): array {
        try {
            $rows = Database::query(
                "SELECT `id`, `title` FROM `positions` WHERE `status`='active' ORDER BY `level` ASC, `title` ASC"
            )->fetchAll();
            $out = [];
            foreach ($rows as $r) $out[$r['id']] = $r['title'];
            return $out;
        } catch (Exception $e) {
            return [];
        }
    }

    /** 
     * Get Org Chart Data (Nested JSON structure)
     * Fetches all positions and the users assigned to them.
     */
    public static function getOrgChartData(): array {
        try {
            $positions = Database::query(
                "SELECT p.id, p.title AS position, p.reports_to_position_id AS parentId, d.name AS department_name
                 FROM `positions` p
                 LEFT JOIN `departments` d ON d.id = p.department_id
                 WHERE p.status = 'active'
                 ORDER BY p.level ASC"
            )->fetchAll(PDO::FETCH_ASSOC);

            $users = Database::query(
                "SELECT id, name, avatar, position_id 
                 FROM `users` 
                 WHERE status = 'active' AND position_id IS NOT NULL"
            )->fetchAll(PDO::FETCH_ASSOC);

            $usersByPosition = [];
            foreach ($users as $user) {
                $posId = $user['position_id'];
                $usersByPosition[$posId][] = $user;
            }

            $flatData = [];
            
            foreach ($positions as $pos) {
                $posId = $pos['id'];
                $parentId = $pos['parentId'] ? 'pos_' . $pos['parentId'] : ''; // top level has no parent
                
                $assignedUsers = $usersByPosition[$posId] ?? [];
                
                if (empty($assignedUsers)) {
                    $flatData[] = [
                        'id' => 'pos_' . $posId,
                        'parentId' => $parentId,
                        'positionId' => $posId,
                        'position' => $pos['position'],
                        'department' => $pos['department_name'],
                        'employee' => null,
                        'vacant' => true,
                    ];
                } else {
                    // For d3-org-chart, if multiple users share a position, we should make the position a node,
                    // and the users as children of that position to look clean, OR just make users siblings.
                    // Let's make a "Position Node" and attach users to it.
                    $posNodeId = 'pos_' . $posId . '_node';
                    
                    // Actually, if we just want users to be the main boxes with position title inside:
                    // If multiple, they become siblings sharing the same parentId.
                    // Wait, if they are siblings, who is the parent of the NEXT level?
                    // The next level reports to 'pos_' . $posId.
                    // So we MUST have a stable ID for the position.
                    // Best way: Create a hidden 'position' node if we want, or just let 'd3-org-chart' handle it.
                    // Let's create a single node for the first user, and if there are more, we append them as siblings?
                    // No, if they are siblings, children reporting to this position will only attach to one of them.
                    
                    // Standard approach for flat org chart with multiple people in same role:
                    // 1. Create a Position node (invisible or visible).
                    // 2. People are children of the Position node.
                    // 3. Sub-positions report to the Position node.
                    
                    // Let's just create one node per position. If multiple people, we combine their names!
                    // d3-org-chart nodeContent can render multiple people if we pass an array.
                    
                    $flatData[] = [
                        'id' => 'pos_' . $posId,
                        'parentId' => $parentId,
                        'positionId' => $posId,
                        'position' => $pos['position'],
                        'department' => $pos['department_name'],
                        'employees' => array_map(function($u) {
                            $avatarFile = !empty($u['avatar']) ? basename((string)$u['avatar']) : '';
                            $avatarPath = __DIR__ . '/../uploads/avatars/' . $avatarFile;
                            $photo = $avatarFile !== '' && $avatarFile !== 'default.png' && is_file($avatarPath)
                                ? 'uploads/avatars/' . rawurlencode($avatarFile)
                                : 'assets/images/default-avatar.svg';

                            return [
                                'name' => $u['name'],
                                'photo' => $photo,
                            ];
                        }, $assignedUsers),
                        'vacant' => false,
                    ];
                }
            }

            return $flatData;

        } catch (Exception $e) {
            error_log("[Position] getOrgChartData error: " . $e->getMessage());
            return [];
        }
    }
}
?>

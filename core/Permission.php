<?php
/**
 * Permission Engine — Role-Based Access Control (RBAC) Core
 * Company: The Bridge Business Alliance (TBBA)
 *
 * Usage:
 *   Permission::can($userId, 'leave', 'approve')    → true/false
 *   Permission::userCan('leave', 'approve')          → true/false (uses current session user)
 *   Permission::getRoleMatrix()                      → full matrix array for UI
 *   Permission::updateRolePermission(...)            → save to DB
 *   Permission::setUserOverride(...)                 → set per-user override
 */

require_once __DIR__ . '/../config/database.php';

class Permission {

    // In-memory cache per request to avoid redundant DB hits
    private static array $cache = [];

    /**
     * Check if a specific user can perform an action on a module.
     * User-level overrides take precedence over role permissions.
     */
    public static function can(int $userId, string $module, string $action): bool {
        $cacheKey = "{$userId}_{$module}_{$action}";

        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        // 1. Check user-level override first (highest priority)
        try {
            $stmt = Database::query(
                "SELECT `allowed` FROM `user_permission_overrides`
                 WHERE `user_id` = ? AND `module` = ? AND `action` = ?
                 LIMIT 1",
                [$userId, $module, $action]
            );
            $override = $stmt->fetch();
            if ($override !== false) {
                $result = (bool)$override['allowed'];
                self::$cache[$cacheKey] = $result;
                return $result;
            }
        } catch (Exception $e) {
            error_log("[Permission] Override lookup error: " . $e->getMessage());
        }

        // 2. Fallback to role-based permission
        try {
            $stmt = Database::query(
                "SELECT rp.`allowed`
                 FROM `role_permissions` rp
                 INNER JOIN `users` u ON u.`role` = rp.`role_name`
                 WHERE u.`id` = ? AND rp.`module` = ? AND rp.`action` = ?
                 LIMIT 1",
                [$userId, $module, $action]
            );
            $row = $stmt->fetch();
            if ($row !== false) {
                $result = (bool)$row['allowed'];
                self::$cache[$cacheKey] = $result;
                return $result;
            }
        } catch (Exception $e) {
            error_log("[Permission] Role lookup error: " . $e->getMessage());
        }

        self::$cache[$cacheKey] = false;
        return false;
    }

    /**
     * Check permission for the currently authenticated user (convenience wrapper).
     */
    public static function userCan(string $module, string $action): bool {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) return false;
        return self::can((int)$userId, $module, $action);
    }

    /**
     * Get all permissions for a given role name (returns assoc array [module][action] => bool).
     */
    public static function getRolePermissions(string $roleName): array {
        try {
            $stmt = Database::query(
                "SELECT `module`, `action`, `allowed` FROM `role_permissions` WHERE `role_name` = ?",
                [$roleName]
            );
            $rows = $stmt->fetchAll();
            $matrix = [];
            foreach ($rows as $row) {
                $matrix[$row['module']][$row['action']] = (bool)$row['allowed'];
            }
            return $matrix;
        } catch (Exception $e) {
            error_log("[Permission] getRolePermissions error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get the full permission matrix for the UI — all roles × all modules × all actions.
     * Returns: [ [role_name => [...]], [modules => [...]], [matrix => [...]] ]
     */
    public static function getRoleMatrix(): array {
        try {
            // All roles
            $roles = Database::query("SELECT `name`, `display_name`, `color`, `icon`, `level` FROM `roles` ORDER BY `level` ASC")->fetchAll();

            // All permissions grouped by module
            $permsRaw = Database::query("SELECT `module`, `action`, `label` FROM `permissions` ORDER BY `module` ASC, `action` ASC")->fetchAll();
            $modulesUnsorted = [];
            foreach ($permsRaw as $p) {
                $modulesUnsorted[$p['module']][] = ['action' => $p['action'], 'label' => $p['label']];
            }

            // Define custom logical sort order for modules (Core, HR, Finance, Operations, Docs, System)
            $moduleOrder = [
                'dashboard', 'calendar', 'notifications', 'announcements',
                'staff', 'roles', 'attendance', 'leave',
                'departments', 'branches', 'positions', 'org_chart',
                'expense', 'purchase', 'finance_control', 'purchases',
                'projects', 'sales', 'logistics', 'tender_board', 'tenders',
                'documents', 'letters',
                'system', 'audit_logs'
            ];

            $modules = [];
            // 1. Add modules in specified order
            foreach ($moduleOrder as $m) {
                if (isset($modulesUnsorted[$m])) {
                    $modules[$m] = $modulesUnsorted[$m];
                    unset($modulesUnsorted[$m]);
                }
            }
            // 2. Add any remaining/new modules not in the hardcoded list
            foreach ($modulesUnsorted as $m => $actions) {
                $modules[$m] = $actions;
            }

            // Full matrix data
            $matrixRaw = Database::query("SELECT `role_name`, `module`, `action`, `allowed` FROM `role_permissions`")->fetchAll();
            $matrix = [];
            foreach ($matrixRaw as $r) {
                $matrix[$r['role_name']][$r['module']][$r['action']] = (bool)$r['allowed'];
            }

            return compact('roles', 'modules', 'matrix');
        } catch (Exception $e) {
            error_log("[Permission] getRoleMatrix error: " . $e->getMessage());
            return ['roles' => [], 'modules' => [], 'matrix' => []];
        }
    }

    /**
     * Update a single role permission toggle in the DB.
     */
    public static function updateRolePermission(string $roleName, string $module, string $action, bool $allowed): bool {
        try {
            Database::query(
                "INSERT INTO `role_permissions` (`role_name`, `module`, `action`, `allowed`)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE `allowed` = VALUES(`allowed`)",
                [$roleName, $module, $action, $allowed ? 1 : 0]
            );
            // Clear any cached entries for this module+action (can't easily target by user here)
            self::$cache = [];
            return true;
        } catch (Exception $e) {
            error_log("[Permission] updateRolePermission error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all user-level permission overrides for a user.
     */
    public static function getUserOverrides(int $userId): array {
        try {
            $stmt = Database::query(
                "SELECT `module`, `action`, `allowed` FROM `user_permission_overrides` WHERE `user_id` = ?",
                [$userId]
            );
            $rows = $stmt->fetchAll();
            $overrides = [];
            foreach ($rows as $row) {
                $overrides[$row['module']][$row['action']] = (bool)$row['allowed'];
            }
            return $overrides;
        } catch (Exception $e) {
            error_log("[Permission] getUserOverrides error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Set (upsert) a per-user permission override.
     * Pass $allowed = null to REMOVE the override (revert to role default).
     */
    public static function setUserOverride(int $userId, string $module, string $action, ?bool $allowed, int $setBy): bool {
        try {
            if ($allowed === null) {
                // Remove override
                Database::query(
                    "DELETE FROM `user_permission_overrides` WHERE `user_id` = ? AND `module` = ? AND `action` = ?",
                    [$userId, $module, $action]
                );
            } else {
                Database::query(
                    "INSERT INTO `user_permission_overrides` (`user_id`, `module`, `action`, `allowed`, `set_by`)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE `allowed` = VALUES(`allowed`), `set_by` = VALUES(`set_by`)",
                    [$userId, $module, $action, $allowed ? 1 : 0, $setBy]
                );
            }
            // Invalidate cache for this user
            foreach (array_keys(self::$cache) as $key) {
                if (str_starts_with($key, "{$userId}_")) {
                    unset(self::$cache[$key]);
                }
            }
            return true;
        } catch (Exception $e) {
            error_log("[Permission] setUserOverride error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove ALL user-level overrides for a user (reset to role defaults).
     */
    public static function clearUserOverrides(int $userId): bool {
        try {
            Database::query("DELETE FROM `user_permission_overrides` WHERE `user_id` = ?", [$userId]);
            self::$cache = [];
            return true;
        } catch (Exception $e) {
            error_log("[Permission] clearUserOverrides error: " . $e->getMessage());
            return false;
        }
    }
}
?>

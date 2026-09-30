<?php
/**
 * Role & Permission Controller — TBBA ERP Module 1
 * Company: The Bridge Business Alliance (TBBA)
 *
 * Handles:
 * - Permission Matrix page (view + AJAX update)
 * - User Permission Overrides (get + set)
 * - Department & Branch management (CRUD)
 */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../core/Permission.php';
require_once __DIR__ . '/../models/Role.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Department.php';
require_once __DIR__ . '/../models/Branch.php';
require_once __DIR__ . '/../models/AuditLog.php';

class RoleController {

    // ─── Page ─────────────────────────────────────────────────────────────────

    /** Render Roles & Permissions management page */
    public static function index(): void {
        Auth::requireLogin();
        Auth::requirePermission('roles', 'view');

        $pageTitle   = 'Roles & Permissions';
        $matrixData  = Permission::getRoleMatrix();
        $roles       = $matrixData['roles'];
        $modules     = $matrixData['modules'];
        $matrix      = $matrixData['matrix'];
        $allUsers    = User::getAll();
        $departments = Department::getAll();
        $branches    = Branch::getAll();

        include __DIR__ . '/../views/roles/index.php';
    }

    // ─── Permission Matrix AJAX ───────────────────────────────────────────────

    /** GET: Return full permission matrix as JSON */
    public static function getMatrix(): void {
        Auth::requireLogin();
        Auth::requirePermission('roles', 'view');

        $data = Permission::getRoleMatrix();
        Helper::json('success', 'Permission matrix retrieved.', $data);
    }

    /** POST: Toggle a role+module+action permission */
    public static function updatePermission(): void {
        Auth::requireLogin();
        Auth::requirePermission('roles', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::json('error', 'Invalid request method.');
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) {
            Helper::json('error', 'Invalid CSRF token security.');
        }

        $roleName = trim($_POST['role_name'] ?? '');
        $module   = trim($_POST['module'] ?? '');
        $action   = trim($_POST['action'] ?? '');
        $allowed  = (int)($_POST['allowed'] ?? 0);

        if (empty($roleName) || empty($module) || empty($action)) {
            Helper::json('error', 'Missing required fields: role_name, module, action.');
        }

        // Prevent editing super_admin permissions via UI (they always have all)
        if ($roleName === 'super_admin') {
            Helper::json('error', 'Super Admin permissions cannot be modified — they always have full access.');
        }

        $ok = Permission::updateRolePermission($roleName, $module, $action, (bool)$allowed);
        if (!$ok) {
            Helper::json('error', 'Failed to update permission. Please try again.');
        }

        AuditLog::record(
            'PERMISSION_CHANGE',
            "Permission matrix updated: role '{$roleName}' — {$module}.{$action} set to " . ($allowed ? 'ALLOWED' : 'DENIED')
        );

        Helper::json('success', 'Permission updated successfully.');
    }

    // ─── User Override AJAX ───────────────────────────────────────────────────

    /** GET: Return user-level permission overrides */
    public static function getUserOverrides(): void {
        Auth::requireLogin();
        Auth::requirePermission('roles', 'view');

        $userId = (int)($_GET['user_id'] ?? 0);
        if ($userId <= 0) {
            Helper::json('error', 'Invalid user ID.');
        }

        $user      = User::findByIdWithDetails($userId);
        $overrides = Permission::getUserOverrides($userId);
        $roleName  = $user['role'] ?? 'staff';
        $rolePerms = Permission::getRolePermissions($roleName);

        Helper::json('success', 'User overrides retrieved.', [
            'user'      => [
                'id'   => $user['id'],
                'name' => $user['name'],
                'role' => $user['role'],
            ],
            'overrides' => $overrides,
            'rolePerms' => $rolePerms,
        ]);
    }

    /** POST: Set a per-user permission override */
    public static function setUserOverride(): void {
        Auth::requireLogin();
        Auth::requirePermission('roles', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::json('error', 'Invalid request method.');
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) {
            Helper::json('error', 'Invalid CSRF token security.');
        }

        $userId  = (int)($_POST['user_id'] ?? 0);
        $module  = trim($_POST['module'] ?? '');
        $action  = trim($_POST['action'] ?? '');
        $allowed = $_POST['allowed'] ?? null;   // '0', '1', or 'reset'

        if ($userId <= 0 || empty($module) || empty($action)) {
            Helper::json('error', 'Missing required fields.');
        }

        $user = User::findById($userId);
        if (!$user) {
            Helper::json('error', 'User not found.');
        }

        $currentUser = Auth::user();
        $setBy       = (int)($currentUser['id'] ?? 0);

        if ($allowed === 'reset') {
            Permission::setUserOverride($userId, $module, $action, null, $setBy);
            $msg = "Removed override for {$user['name']}: {$module}.{$action}";
        } else {
            $allow = (bool)(int)$allowed;
            Permission::setUserOverride($userId, $module, $action, $allow, $setBy);
            $msg = "Set override for {$user['name']}: {$module}.{$action} = " . ($allow ? 'ALLOW' : 'DENY');
        }

        AuditLog::record('PERMISSION_OVERRIDE', $msg);
        Helper::json('success', 'User permission override saved.');
    }

    /** POST: Clear ALL overrides for a user */
    public static function clearUserOverrides(): void {
        Auth::requireLogin();
        Auth::requirePermission('roles', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');

        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) Helper::json('error', 'Invalid user ID.');

        $user = User::findById($userId);
        if (!$user) Helper::json('error', 'User not found.');

        Permission::clearUserOverrides($userId);
        AuditLog::record('PERMISSION_OVERRIDE', "Cleared all permission overrides for user '{$user['name']}'");
        Helper::json('success', 'All overrides cleared. User now uses role defaults.');
    }

    // ─── Helper Data Endpoints ────────────────────────────────────────────────

    /** GET: Return all roles as JSON (for dropdowns) */
    public static function getRoles(): void {
        Auth::requireLogin();
        $currentRole = Auth::role();
        $roles = Role::getAssignableBy($currentRole);
        Helper::json('success', 'Roles retrieved.', $roles);
    }

    /** GET: Return all departments as JSON */
    public static function getDepartments(): void {
        Auth::requireLogin();
        Auth::requirePermission('departments', 'view');
        Helper::json('success', 'Departments retrieved.', Department::getAll());
    }

    /** GET: Return all branches as JSON */
    public static function getBranches(): void {
        Auth::requireLogin();
        Auth::requirePermission('branches', 'view');
        Helper::json('success', 'Branches retrieved.', Branch::getAll());
    }

    // ─── Department CRUD ──────────────────────────────────────────────────────

    /** POST: Create department */
    public static function storeDepartment(): void {
        Auth::requireLogin();
        Auth::requirePermission('departments', 'create');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');

        $name  = trim($_POST['name'] ?? '');
        $code  = strtoupper(trim($_POST['code'] ?? ''));

        if (empty($name)) Helper::json('error', 'Department name is required.');
        if (!empty($code) && Department::isCodeTaken($code)) {
            Helper::json('error', "Department code '{$code}' is already in use.");
        }

        $headUserId = (int)($_POST['head_user_id'] ?? 0) ?: null;
        try {
            Department::validateHeadAssignment($headUserId);
        } catch (InvalidArgumentException $e) {
            Helper::json('error', $e->getMessage());
        }

        $id = Department::create([
            'name'          => $name,
            'code'          => $code ?: null,
            'head_user_id'  => $headUserId,
            'description'   => trim($_POST['description'] ?? '') ?: null,
            'contact_email' => trim($_POST['contact_email'] ?? '') ?: null,
            'contact_phone' => trim($_POST['contact_phone'] ?? '') ?: null,
            'status'        => in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active',
        ]);
        Department::synchronizeHeadDepartment($id, $headUserId);

        AuditLog::record('CREATE', "Created new department: '{$name}' (#{$id})");
        Helper::json('success', 'Department created successfully!', ['id' => $id]);
    }

    /** POST: Update department */
    public static function updateDepartment(): void {
        Auth::requireLogin();
        Auth::requirePermission('departments', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');

        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));

        if ($id <= 0 || empty($name)) Helper::json('error', 'Invalid data.');
        if (!empty($code) && Department::isCodeTaken($code, $id)) {
            Helper::json('error', "Department code '{$code}' is already in use.");
        }

        $headUserId = (int)($_POST['head_user_id'] ?? 0) ?: null;
        try {
            Department::validateHeadAssignment($headUserId, $id);
        } catch (InvalidArgumentException $e) {
            Helper::json('error', $e->getMessage());
        }

        Department::update($id, [
            'name'          => $name,
            'code'          => $code ?: null,
            'head_user_id'  => $headUserId,
            'description'   => trim($_POST['description'] ?? '') ?: null,
            'contact_email' => trim($_POST['contact_email'] ?? '') ?: null,
            'contact_phone' => trim($_POST['contact_phone'] ?? '') ?: null,
            'status'        => in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active',
        ]);
        Department::synchronizeHeadDepartment($id, $headUserId);

        AuditLog::record('UPDATE', "Updated department: '{$name}' (#{$id})");
        Helper::json('success', 'Department updated successfully!');
    }

    /** POST: Delete department */
    public static function deleteDepartment(): void {
        Auth::requireLogin();
        Auth::requirePermission('departments', 'delete');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) Helper::json('error', 'Invalid department ID.');

        $dept = Department::findById($id);
        if (!$dept) Helper::json('error', 'Department not found.');

        // Unassign staff members from this department before deleting
        Database::query("UPDATE `users` SET `department_id` = NULL WHERE `department_id` = ?", [$id]);

        Department::delete($id);
        AuditLog::record('DELETE', "Deleted department: '{$dept['name']}' (#{$id})");
        Helper::json('success', 'Department deleted successfully.');
    }

    // ─── Branch CRUD ──────────────────────────────────────────────────────────

    /** POST: Create branch */
    public static function storeBranch(): void {
        Auth::requireLogin();
        Auth::requirePermission('branches', 'create');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');

        $name = trim($_POST['name'] ?? '');
        if (empty($name)) Helper::json('error', 'Branch name is required.');

        $id = Branch::create([
            'name'      => $name,
            'code'      => strtoupper(trim($_POST['code'] ?? '')) ?: null,
            'address'   => trim($_POST['address'] ?? '') ?: null,
            'city'      => trim($_POST['city'] ?? '') ?: null,
            'state'     => trim($_POST['state'] ?? '') ?: null,
            'phone'     => trim($_POST['phone'] ?? '') ?: null,
            'latitude'  => isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null,
            'longitude' => isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null,
            'status'    => in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active',
        ]);

        AuditLog::record('CREATE', "Created new branch/office: '{$name}' (#{$id})");
        Helper::json('success', 'Branch created successfully!', ['id' => $id]);
    }

    /** POST: Update branch */
    public static function updateBranch(): void {
        Auth::requireLogin();
        Auth::requirePermission('branches', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');

        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($id <= 0 || empty($name)) Helper::json('error', 'Invalid data.');

        Branch::update($id, [
            'name'      => $name,
            'code'      => strtoupper(trim($_POST['code'] ?? '')) ?: null,
            'address'   => trim($_POST['address'] ?? '') ?: null,
            'city'      => trim($_POST['city'] ?? '') ?: null,
            'state'     => trim($_POST['state'] ?? '') ?: null,
            'phone'     => trim($_POST['phone'] ?? '') ?: null,
            'latitude'  => isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null,
            'longitude' => isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null,
            'status'    => in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active',
        ]);

        AuditLog::record('UPDATE', "Updated branch: '{$name}' (#{$id})");
        Helper::json('success', 'Branch updated successfully!');
    }

    /** POST: Delete branch */
    public static function deleteBranch(): void {
        Auth::requireLogin();
        Auth::requirePermission('branches', 'delete');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid method.');

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) Helper::json('error', 'Invalid branch ID.');

        $branch = Branch::findById($id);
        if (!$branch) Helper::json('error', 'Branch not found.');

        Branch::delete($id);
        AuditLog::record('DELETE', "Deleted branch/office: '{$branch['name']}' (#{$id})");
        Helper::json('success', 'Branch deleted successfully.');
    }

    /** GET: Login history for a specific user */
    public static function getLoginHistory(): void {
        Auth::requireLogin();
        if (!Auth::hasPermission('roles', 'view') && !Auth::hasPermission('staff', 'view')) {
            Helper::json('error', 'Access Denied: Insufficient permissions to view login history.');
        }

        $userId = (int)($_GET['user_id'] ?? 0);
        if ($userId <= 0) Helper::json('error', 'Invalid user ID.');

        $user    = User::findById($userId);
        if (!$user) Helper::json('error', 'User not found.');

        $history = User::getLoginHistory($userId, 50);
        Helper::json('success', 'Login history retrieved.', [
            'user'    => ['id' => $user['id'], 'name' => $user['name']],
            'history' => $history,
        ]);
    }
}
?>

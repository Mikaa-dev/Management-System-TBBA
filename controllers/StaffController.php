<?php
/**
 * Staff Management Controller — TBBA ERP
 * Company: The Bridge Business Alliance (TBBA)
 * Updated: Module 1 — Expanded fields + RBAC permission guards
 */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/AccountSecurity.php';
require_once __DIR__ . '/../models/Role.php';
require_once __DIR__ . '/../models/Department.php';
require_once __DIR__ . '/../models/Branch.php';
require_once __DIR__ . '/../models/Position.php';
require_once __DIR__ . '/../models/AuditLog.php';

class StaffController {

    /** Render Staff Management page */
    public static function index(): void {
        Auth::requireLogin();
        Auth::requirePermission('staff', 'view');

        $pageTitle       = 'Staff Management';
        $deptScope       = Auth::getScopeDepartmentId();
        $staffList       = User::getAll($deptScope);
        $departments     = Department::getAllActive();
        $branches        = Branch::getAllActive();
        $roles           = Role::getAll();
        $assignableRoles = Role::getAssignableBy(Auth::user()['role'] ?? 'staff');
        $positions       = Position::getAll();

        include __DIR__ . '/../views/staff/index.php';
    }

    /** GET: Fetch a single staff member (AJAX) */
    public static function getStaff(): void {
        Auth::requireLogin();
        Auth::requirePermission('staff', 'view');

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) Helper::json('error', 'Invalid staff ID.');

        $user = User::findByIdWithDetails($id);
        if (!$user) Helper::json('error', 'Staff member not found.');
        if (!Auth::canAccessUser($user)) {
            Helper::json('error', 'Access Denied: You can only view staff within your own department.');
        }

        unset($user['password']);
        Helper::json('success', 'Staff details retrieved.', $user);
    }

    /** POST: Add new staff member (AJAX) */
    public static function store(): void {
        Auth::requireLogin();
        Auth::requirePermission('staff', 'create');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid request method.');

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');

        $name       = trim($_POST['name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $password   = trim($_POST['password'] ?? '');
        $role       = trim($_POST['role'] ?? 'staff');
        $position   = trim($_POST['position'] ?? '');
        $positionId = !empty($_POST['position_id']) ? (int)$_POST['position_id'] : null;
        $deptId     = (int)($_POST['department_id'] ?? 0) ?: null;
        $branchId   = (int)($_POST['branch_id'] ?? 0) ?: null;
        try {
            [$position, $deptId] = self::resolveOrganizationalAssignment($positionId, $deptId);
        } catch (InvalidArgumentException $e) {
            Helper::json('error', $e->getMessage());
        }
        $status     = trim($_POST['status'] ?? 'active');
        $phone      = trim($_POST['phone'] ?? '') ?: null;
        $employeeId = trim($_POST['employee_id'] ?? '') ?: null;

        // Validations
        if (empty($name) || empty($email) || empty($password)) {
            Helper::json('error', 'Name, Email Address, and Password are required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Helper::json('error', 'Please enter a valid email address.');
        }
        $passwordErrors = AccountSecurity::validatePassword($password);
        if ($passwordErrors) {
            Helper::json('error', 'Password must contain ' . implode(', ', $passwordErrors) . '.');
        }
        $validRoles = Role::getAllNames();
        if (!in_array($role, $validRoles, true)) {
            $role = 'staff';
        }
        // Non-super-admin cannot create super_admin accounts
        if ($role === 'super_admin' && !Auth::isSuperAdmin()) {
            Helper::json('error', 'Only Super Administrators can create Super Admin accounts.');
        }
        if (!in_array($status, ['active', 'suspended'], true)) $status = 'active';
        if (User::isEmailTaken($email)) {
            Helper::json('error', 'This email address is already registered in the system.');
        }
        if ($employeeId && User::isEmployeeIdTaken($employeeId)) {
            Helper::json('error', 'This Employee ID is already in use.');
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $id = User::create($name, $email, $hashedPassword, $role, $position, 'default.png',
                           $deptId, $branchId, $status, $phone, $employeeId, $positionId);

        AuditLog::record('CREATE',
            "Created new staff account for '{$name}' (Role: " . Auth::roleLabel($role) . ", ID: #{$id})"
        );
        Helper::json('success', 'New staff member added successfully!', ['id' => $id]);
    }

    /** POST: Update staff profile (AJAX) */
    public static function update(): void {
        Auth::requireLogin();
        Auth::requirePermission('staff', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid request method.');

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');

        $id         = (int)($_POST['id'] ?? 0);
        $name       = trim($_POST['name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $password   = trim($_POST['password'] ?? '');
        $role       = trim($_POST['role'] ?? 'staff');
        $position   = trim($_POST['position'] ?? '');
        $positionId = !empty($_POST['position_id']) ? (int)$_POST['position_id'] : null;
        $deptId     = (int)($_POST['department_id'] ?? 0) ?: null;
        $branchId   = (int)($_POST['branch_id'] ?? 0) ?: null;
        try {
            [$position, $deptId] = self::resolveOrganizationalAssignment($positionId, $deptId);
        } catch (InvalidArgumentException $e) {
            Helper::json('error', $e->getMessage());
        }
        $status     = trim($_POST['status'] ?? 'active');
        $phone      = trim($_POST['phone'] ?? '') ?: null;
        $employeeId = trim($_POST['employee_id'] ?? '') ?: null;

        if ($id <= 0 || empty($name)) {
            Helper::json('error', 'Name is required.');
        }
        $existingUser = User::findById($id);
        if (!$existingUser) {
            Helper::json('error', 'Staff member not found.');
        }
        if (!Auth::canAccessUser($existingUser)) {
            Helper::json('error', 'Access Denied: You can only modify staff within your own department.');
        }

        // Hanya super admin yang boleh tukar email pengguna sedia ada
        if (!Auth::isSuperAdmin()) {
            $email = $existingUser['email'];
        } else {
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Helper::json('error', 'Please enter a valid email address.');
            }
            if (User::isEmailTaken($email, $id)) {
                Helper::json('error', 'This email address is already taken by another user.');
            }
        }

        if ($existingUser['role'] === 'super_admin' && !Auth::isSuperAdmin()) {
            Helper::json('error', 'Only Super Administrators can modify existing Super Admin accounts.');
        }
        $validRoles = Role::getAllNames();
        if (!in_array($role, $validRoles, true)) $role = 'staff';
        if ($role === 'super_admin' && !Auth::isSuperAdmin()) {
            Helper::json('error', 'Only Super Administrators can assign the Super Admin role.');
        }
        if (!in_array($status, ['active', 'suspended'], true)) $status = 'active';
        if ($employeeId && User::isEmployeeIdTaken($employeeId, $id)) {
            Helper::json('error', 'This Employee ID is already in use by another user.');
        }

        // Prevent self-suspension
        $currentUser = Auth::user();
        if ($id === (int)$currentUser['id'] && $status === 'suspended') {
            Helper::json('error', 'You cannot suspend your own account.');
        }

        // Validate password BEFORE saving profile (prevent partial save on error)
        $passwordErrors = $password !== '' ? AccountSecurity::validatePassword($password) : [];
        if ($passwordErrors) {
            Helper::json('error', 'New password must contain ' . implode(', ', $passwordErrors) . '.');
        }

        $identityChanged = $email !== (string)$existingUser['email']
            || $role !== (string)$existingUser['role']
            || $status !== (string)$existingUser['status']
            || (int)($deptId ?? 0) !== (int)($existingUser['department_id'] ?? 0)
            || (int)($branchId ?? 0) !== (int)($existingUser['branch_id'] ?? 0);

        User::update($id, $name, $email, $role, $position, $deptId, $branchId, $status, $phone, $employeeId, $positionId);

        if (!empty($password)) {
            AccountSecurity::updatePassword($id, password_hash($password, PASSWORD_DEFAULT));
            AccountSecurity::revokeAllSessions($id);
        } elseif ($identityChanged) {
            AccountSecurity::revokeAllSessions($id);
        }
        if ($status === 'suspended') {
            AccountSecurity::revokeAllAccess($id);
        }

        AuditLog::record('UPDATE', "Updated staff profile for '{$name}' (#{$id})");
        Helper::json('success', 'Staff profile updated successfully!');
    }

    /** POST: Delete staff account (AJAX) */
    public static function delete(): void {
        Auth::requireLogin();
        Auth::requirePermission('staff', 'delete');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid request method.');

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) Helper::json('error', 'Invalid staff ID.');

        $currentUser = Auth::user();
        if ($id === (int)$currentUser['id']) {
            Helper::json('error', 'You cannot delete your own account!');
        }

        $targetUser = User::findById($id);
        if (!$targetUser) Helper::json('error', 'Staff member not found.');
        if (!Auth::canAccessUser($targetUser)) {
            Helper::json('error', 'Access Denied: You can only delete staff within your own department.');
        }

        // Non-super-admin cannot delete super_admin
        if ($targetUser['role'] === 'super_admin' && !Auth::isSuperAdmin()) {
            Helper::json('error', 'Only Super Administrators can delete Super Admin accounts.');
        }

        // Developer Protection Rule
        if ($targetUser['email'] === 'minhalkazim18@gmail.com') {
            Helper::json('error', 'Access Denied: This account is protected (System Developer) and cannot be deleted by anyone.');
        }

        $targetName = $targetUser['name'] ?? "ID #{$id}";
        
        try {
            User::delete($id);
            AuditLog::record('DELETE', "Permanently deleted staff account '{$targetName}' (#{$id})");
            Helper::json('success', 'Staff member deleted successfully.');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' || strpos($e->getMessage(), 'foreign key constraint') !== false) {
                Helper::json('error', "Cannot delete {$targetName} because their account is tied to historical records (like logs, announcements, etc). Please suspend the account instead.");
            }
            Helper::json('error', 'A database error occurred while trying to delete the user.');
        }
    }

    /** POST: Toggle account status — suspend or activate (AJAX) */
    public static function toggleStatus(): void {
        Auth::requireLogin();
        Auth::requirePermission('staff', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid request method.');

        $csrf = $_POST['csrf_token'] ?? '';
        if (!Helper::verifyCsrf($csrf)) Helper::json('error', 'Invalid CSRF token security.');

        $id     = (int)($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? 'active');

        if ($id <= 0) Helper::json('error', 'Invalid staff ID.');
        if (!in_array($status, ['active', 'suspended'], true)) Helper::json('error', 'Invalid status value.');

        $currentUser = Auth::user();
        if ($id === (int)$currentUser['id'] && $status === 'suspended') {
            Helper::json('error', 'You cannot suspend your own account.');
        }

        $user = User::findById($id);
        if (!$user) Helper::json('error', 'User not found.');
        if (!Auth::canAccessUser($user)) {
            Helper::json('error', 'Access Denied: You can only modify status for staff within your own department.');
        }
        if ($user['role'] === 'super_admin' && !Auth::isSuperAdmin()) {
            Helper::json('error', 'Cannot modify Super Admin account status.');
        }

        User::setStatus($id, $status);
        if ($status === 'suspended') {
            AccountSecurity::revokeAllAccess($id);
        } else {
            // Force any stale role/department session to authenticate again.
            AccountSecurity::revokeAllSessions($id);
        }
        $statusLabel = $status === 'suspended' ? 'Suspended' : 'Activated';
        AuditLog::record('UPDATE', "Account status changed to {$statusLabel} for '{$user['name']}' (#{$id})");
        Helper::json('success', "Account {$statusLabel} successfully.");
    }

    /**
     * Position is the source of truth for a staff member's title and department.
     * Users without a position may still be attached directly to a department.
     */
    private static function resolveOrganizationalAssignment(?int $positionId, ?int $departmentId): array {
        $scopeDepartmentId = Auth::getScopeDepartmentId();

        if ($positionId === null) {
            if ($scopeDepartmentId !== null) {
                $departmentId = $scopeDepartmentId;
            }
            return ['', $departmentId];
        }

        $position = Position::findById($positionId);
        if (!$position) {
            throw new InvalidArgumentException('Selected position does not exist.');
        }
        if (($position['status'] ?? 'active') !== 'active') {
            throw new InvalidArgumentException('Selected position is inactive.');
        }

        $positionDepartmentId = $position['department_id'] !== null
            ? (int)$position['department_id']
            : null;
        if ($scopeDepartmentId !== null && $positionDepartmentId !== $scopeDepartmentId) {
            throw new InvalidArgumentException('You can only assign positions within your own department.');
        }

        return [(string)$position['title'], $positionDepartmentId];
    }
}
?>

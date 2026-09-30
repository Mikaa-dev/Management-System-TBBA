<?php
/**
 * Authentication & Role-Based Access Control (RBAC) Core
 * Company: The Bridge Business Alliance (TBBA)
 * Updated: Module 1 — Full 8-role permission system
 */

require_once __DIR__ . '/Helper.php';
require_once __DIR__ . '/Permission.php';
require_once __DIR__ . '/../models/AccountSecurity.php';

class Auth {

    // ─── Session Checks ──────────────────────────────────────────────────────

    /** Check if user is logged in */
    public static function check(): bool {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /** Get current session user as array */
    public static function user(): ?array {
        if (!self::check()) return null;
        return [
            'id'            => $_SESSION['user_id']            ?? 0,
            'name'          => $_SESSION['user_name']          ?? 'Unknown User',
            'email'         => $_SESSION['user_email']         ?? '',
            'role'          => $_SESSION['user_role']          ?? 'staff',
            'avatar'        => $_SESSION['user_avatar']        ?? 'default.png',
            'department_id' => $_SESSION['user_department_id'] ?? null,
            'branch_id'     => $_SESSION['user_branch_id']     ?? null,
            'status'        => $_SESSION['user_status']        ?? 'active',
            'employee_id'   => $_SESSION['user_employee_id']   ?? null,
            'two_factor_enabled' => $_SESSION['user_two_factor_enabled'] ?? 0,
        ];
    }

    /** Get current user's ID */
    public static function id(): ?int {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    /** Get current user's role string */
    public static function role(): string {
        return $_SESSION['user_role'] ?? 'guest';
    }

    // ─── Role Checks ─────────────────────────────────────────────────────────

    public static function isSuperAdmin(): bool {
        return self::check() && self::role() === 'super_admin';
    }

    /** Admin = super_admin OR admin */
    public static function isAdmin(): bool {
        return self::check() && in_array(self::role(), ['super_admin', 'admin'], true);
    }

    public static function isHR(): bool {
        return self::check() && self::role() === 'hr';
    }

    public static function isManager(): bool {
        return self::check() && in_array(self::role(), ['super_admin', 'admin', 'manager'], true);
    }

    public static function isDeptHead(): bool {
        return self::check() && in_array(self::role(), ['super_admin', 'admin', 'manager', 'dept_head'], true);
    }

    public static function isFinance(): bool {
        return self::check() && in_array(self::role(), ['super_admin', 'admin', 'finance'], true);
    }

    public static function isAuditor(): bool {
        return self::check() && self::role() === 'auditor';
    }

    public static function isStaff(): bool {
        return self::check() && self::role() === 'staff';
    }

    /** Check if current user is the HR Department Head */
    public static function isHrDepartmentHead(): bool {
        if (!self::check() || ($_SESSION['user_role'] ?? '') !== 'dept_head') return false;
        $deptId = self::userDepartmentId();
        if (!$deptId) return false;
        require_once __DIR__ . '/../models/Department.php';
        $dept = Department::findById($deptId);
        if (!$dept || (int)($dept['head_user_id'] ?? 0) !== self::id()) return false;
        $code = strtoupper((string)($dept['code'] ?? ''));
        $name = strtolower((string)($dept['name'] ?? ''));
        return in_array($code, ['HR', 'HRD'], true) || str_contains($name, 'human resource');
    }

    /** Get current user's Department ID */
    public static function userDepartmentId(): ?int {
        if (!self::check()) return null;
        $deptId = (int)($_SESSION['user_department_id'] ?? 0);
        return $deptId > 0 ? $deptId : null;
    }

    /**
     * Get the department ID scope for the current user.
     * Returns null if user has company-wide access (super_admin, admin, hr, manager, etc.).
     * Returns the user's department ID if they are restricted to their own department (e.g. dept_head).
     */
    public static function getScopeDepartmentId(): ?int {
        if (!self::check()) return null;
        $role = $_SESSION['user_role'] ?? '';
        if ($role === 'dept_head') {
            if (self::isHrDepartmentHead()) return null;
            return self::userDepartmentId();
        }
        return null;
    }

    /**
     * Check if the current user is allowed to access/manage a target user profile.
     */
    public static function canAccessUser(array $targetUser): bool {
        if (!self::check()) return false;
        if ((int)($targetUser['id'] ?? 0) === self::id()) return true;
        $role = $_SESSION['user_role'] ?? '';
        if ($role === 'dept_head') {
            if (self::isHrDepartmentHead()) return true;
            $myDept = self::userDepartmentId();
            $targetDept = (int)($targetUser['department_id'] ?? 0);
            return ($myDept !== null && $myDept > 0 && $myDept === $targetDept);
        }
        return true;
    }

    /**
     * Authorize access to an employee-owned workflow record.
     *
     * Owners may always view their own record. Access to another employee's
     * record requires the module's approve permission and, for department
     * heads, the target employee must be inside their department scope.
     */
    public static function canAccessOwnedResource(int $ownerUserId, string $module): bool {
        if (!self::check() || $ownerUserId <= 0) return false;
        if ($ownerUserId === self::id()) return true;
        if (!self::hasPermission($module, 'approve')) return false;
        if (self::getScopeDepartmentId() === null) return true;

        require_once __DIR__ . '/../models/User.php';
        $targetUser = User::findById($ownerUserId);
        return $targetUser !== null && self::canAccessUser($targetUser);
    }

    /**
     * Check if current user has a specific module+action permission.
     * This is the primary RBAC gate for all modules.
     */
    public static function hasPermission(string $module, string $action): bool {
        if (!self::check()) return false;
        // super_admin always has all permissions
        if (self::isSuperAdmin()) return true;
        return Permission::userCan($module, $action);
    }

    // ─── Role Display Helpers ─────────────────────────────────────────────────

    /** Returns human-readable role display name */
    public static function roleLabel(?string $role = null): string {
        $r = $role ?? self::role();
        $labels = [
            'super_admin' => 'Super Admin',
            'admin'       => 'Administrator',
            'hr'          => 'HR Manager',
            'manager'     => 'Manager',
            'dept_head'   => 'Department Head',
            'finance'     => 'Finance Officer',
            'auditor'     => 'Auditor',
            'staff'       => 'Staff',
        ];
        return $labels[$r] ?? ucfirst($r);
    }

    /** Returns badge color class for a role */
    public static function roleBadgeColor(?string $role = null): string {
        $r = $role ?? self::role();
        $colors = [
            'super_admin' => '#7C3AED',
            'admin'       => '#1D4ED8',
            'hr'          => '#0891B2',
            'manager'     => '#059669',
            'dept_head'   => '#D97706',
            'finance'     => '#BE185D',
            'auditor'     => '#475569',
            'staff'       => '#64748B',
        ];
        return $colors[$r] ?? '#64748B';
    }

    // ─── Session Management ───────────────────────────────────────────────────

    public static function login(array $user): void {
        $_SESSION['user_id']            = $user['id'];
        $_SESSION['user_name']          = $user['name'];
        $_SESSION['user_email']         = $user['email'];
        $_SESSION['user_role']          = $user['role'];
        $_SESSION['user_avatar']        = $user['avatar']        ?? 'default.png';
        $_SESSION['user_department_id'] = $user['department_id'] ?? null;
        $_SESSION['user_branch_id']     = $user['branch_id']     ?? null;
        $_SESSION['user_status']        = $user['status']        ?? 'active';
        $_SESSION['user_employee_id']   = $user['employee_id']   ?? null;
        $_SESSION['user_two_factor_enabled'] = (int)($user['two_factor_enabled'] ?? 0);

        // Regenerate session ID to prevent session fixation attacks
        session_regenerate_id(true);
        $_SESSION['password_expired'] = AccountSecurity::passwordExpired($user) ? 1 : 0;
        AccountSecurity::registerSession((int)$user['id']);
    }

    /** Create a persistent remember me token */
    public static function createRememberToken(int $userId): void {
        require_once __DIR__ . '/../models/RememberToken.php';
        $token = RememberToken::create($userId);
        $secureCookie = function_exists('tbba_is_https') ? tbba_is_https() : false;
        setcookie('tbba_remember_token', $token, time() + (90 * 86400), '/', '', $secureCookie, true);
    }

    /** Login via persistent remember me token */
    public static function loginWithToken(string $token): bool {
        require_once __DIR__ . '/../models/RememberToken.php';
        $userId = RememberToken::validate($token);
        if ($userId) {
            $user = Database::query("SELECT * FROM users WHERE id=? AND status='active' LIMIT 1", [$userId])->fetch();
            if ($user) {
                self::login($user);
                // Rotate token for security
                RememberToken::revoke($token);
                self::createRememberToken($userId);
                return true;
            }
        }
        // Invalid token, destroy cookie
        setcookie('tbba_remember_token', '', time() - 3600, '/');
        return false;
    }

    public static function logout(): void {
        if (self::check()) {
            try { AccountSecurity::revokeCurrentSession(); } catch (Throwable $e) { /* logout must continue */ }
        }
        
        if (isset($_COOKIE['tbba_remember_token'])) {
            require_once __DIR__ . '/../models/RememberToken.php';
            RememberToken::revoke($_COOKIE['tbba_remember_token']);
            setcookie('tbba_remember_token', '', time() - 3600, '/');
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    // ─── Access Guards ────────────────────────────────────────────────────────

    /** Require user to be logged in (redirect or AJAX error if not) */
    public static function requireLogin(): void {
        if (!self::check()) {
            if (self::isAjax()) {
                Helper::json('error', 'Your session has expired. Please log in again.');
            } else {
                Helper::redirect('index.php?page=login');
            }
            exit;
        }

        // Refresh security-sensitive identity fields from the database on every
        // request. A role change or suspension must not wait for the browser's
        // cached session values to expire.
        $freshUser = Database::query("SELECT * FROM users WHERE id=? LIMIT 1", [(int)self::id()])->fetch();
        if (!$freshUser) {
            self::logout();
            if (self::isAjax()) Helper::json('error', 'This account is no longer available. Please sign in again.');
            Helper::redirect('index.php?page=login');
            exit;
        }
        self::refreshSessionIdentity($freshUser);

        // Check account suspension
        if (($_SESSION['user_status'] ?? 'active') === 'suspended') {
            self::logout();
            if (self::isAjax()) {
                Helper::json('error', 'Your account has been suspended. Please contact an administrator.');
            } else {
                Helper::redirect('index.php?page=login');
            }
            exit;
        }

        // Server-side session registry allows a user to sign out lost devices.
        // Existing sessions created before this upgrade are registered lazily.
        try {
            if (empty($_SESSION['auth_session_token'])) {
                AccountSecurity::registerSession((int)self::id());
            } elseif (time() - (int)($_SESSION['auth_session_checked_at'] ?? 0) >= 60
                && !AccountSecurity::validateCurrentSession((int)self::id())) {
                self::logout();
                if (self::isAjax()) Helper::json('error', 'This device session has been signed out. Please log in again.');
                Helper::redirect('index.php?page=login');
                exit;
            }
        } catch (Throwable $e) {
            error_log('[Auth] Session registry check failed: ' . $e->getMessage());
        }

        if (!empty($_SESSION['password_expired'])) {
            $page = $_GET['page'] ?? '';
            $action = $_GET['action'] ?? '';
            $allowedPages = ['profile', 'logout'];
            $allowedActions = ['update_profile', 'begin_two_factor', 'enable_two_factor', 'disable_two_factor', 'revoke_session', 'revoke_other_sessions', 'revoke_trusted_devices'];
            if (!in_array($page, $allowedPages, true) && !in_array($action, $allowedActions, true)) {
                if (self::isAjax()) Helper::json('error', 'Your password has expired. Update it from My Profile before continuing.');
                Helper::redirect('index.php?page=profile&tab=security&expired=1');
                exit;
            }
        }

        if (self::isAdmin() && AccountSecurity::setting('require_2fa_admin', '0') === '1'
            && empty($_SESSION['user_two_factor_enabled'])) {
            $page = $_GET['page'] ?? '';
            $action = $_GET['action'] ?? '';
            $allowedPages = ['profile', 'logout'];
            $allowedActions = ['begin_two_factor', 'enable_two_factor', 'update_profile'];
            if (!in_array($page, $allowedPages, true) && !in_array($action, $allowedActions, true)) {
                if (self::isAjax()) Helper::json('error', 'Administrator accounts must enable two-factor authentication before continuing.');
                Helper::redirect('index.php?page=profile&tab=security&enrol2fa=1');
                exit;
            }
        }
    }

    /** Keep authorization and security policy fields synchronized with users. */
    private static function refreshSessionIdentity(array $user): void {
        $_SESSION['user_name'] = $user['name'] ?? $_SESSION['user_name'] ?? 'Unknown User';
        $_SESSION['user_email'] = $user['email'] ?? $_SESSION['user_email'] ?? '';
        $_SESSION['user_role'] = $user['role'] ?? 'staff';
        $_SESSION['user_avatar'] = $user['avatar'] ?? 'default.png';
        $_SESSION['user_department_id'] = $user['department_id'] ?? null;
        $_SESSION['user_branch_id'] = $user['branch_id'] ?? null;
        $_SESSION['user_status'] = $user['status'] ?? 'active';
        $_SESSION['user_employee_id'] = $user['employee_id'] ?? null;
        $_SESSION['user_two_factor_enabled'] = (int)($user['two_factor_enabled'] ?? 0);
        $_SESSION['password_expired'] = AccountSecurity::passwordExpired($user) ? 1 : 0;
    }

    /** Require admin-level role (super_admin or admin) */
    public static function requireAdmin(): void {
        self::requireLogin();
        if (!self::isAdmin()) {
            if (self::isAjax()) {
                Helper::json('error', 'Access denied. Administrator role required.');
            } else {
                Helper::redirect('index.php?page=dashboard');
            }
            exit;
        }
    }

    /** Require Super Admin only */
    public static function requireSuperAdmin(): void {
        self::requireLogin();
        if (!self::isSuperAdmin()) {
            if (self::isAjax()) {
                Helper::json('error', 'Access denied. Super Administrator role required.');
            } else {
                Helper::redirect('index.php?page=dashboard');
            }
            exit;
        }
    }

    /**
     * Require a specific module+action permission.
     * Use this as the standard guard in every controller method.
     *
     * Example: Auth::requirePermission('leave', 'approve');
     */
    public static function requirePermission(string $module, string $action): void {
        self::requireLogin();
        if (!self::hasPermission($module, $action)) {
            if (self::isAjax()) {
                Helper::json('error', "Access denied. You do not have permission to {$action} {$module}.");
            } else {
                Helper::redirect('index.php?page=dashboard');
            }
            exit;
        }
    }

    // ─── Utility ─────────────────────────────────────────────────────────────

    /** Detect if current request is an AJAX/XHR call */
    private static function isAjax(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
?>

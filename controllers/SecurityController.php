<?php
/** Password recovery, two-factor authentication and device-session controls. */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../core/SecurityMailer.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/AccountSecurity.php';
require_once __DIR__ . '/../models/AuditLog.php';

class SecurityController
{
    public static function requestReset(): void
    {
        self::publicPostGuard();
        $email = strtolower(trim($_POST['email'] ?? ''));
        $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? User::findByEmail($email) : null;
        if ($user && ($user['status'] ?? 'active') === 'active') {
            $token = AccountSecurity::createResetToken((int)$user['id'], $_SERVER['REMOTE_ADDR'] ?? '');
            $link = Helper::url('index.php?page=reset_password&token=' . rawurlencode($token));
            if (!SecurityMailer::sendPasswordReset($user, $link)) {
                AccountSecurity::event((int)$user['id'], 'password_reset_delivery_failed', 'critical', 'The server mail transport could not deliver a password reset message.');
            }
        }
        // Always return the same response to prevent account enumeration.
        Helper::json('success', 'If that email is registered, a password reset link has been sent.');
    }

    public static function resetPassword(): void
    {
        self::publicPostGuard();
        $token = trim($_POST['token'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $confirmation = (string)($_POST['password_confirm'] ?? '');
        $user = AccountSecurity::findResetUser($token);
        if (!$user) Helper::json('error', 'This reset link is invalid or has expired. Request a new link.');
        if ($password !== $confirmation) Helper::json('error', 'Password and confirmation do not match.');
        $errors = AccountSecurity::validatePassword($password);
        if ($errors) Helper::json('error', 'Password must contain ' . implode(', ', $errors) . '.');
        if (password_verify($password, $user['password'])) Helper::json('error', 'Choose a password different from your current password.');

        AccountSecurity::updatePassword((int)$user['id'], password_hash($password, PASSWORD_DEFAULT));
        Database::query("UPDATE user_sessions SET revoked_at=NOW() WHERE user_id=? AND revoked_at IS NULL", [$user['id']]);
        AccountSecurity::event((int)$user['id'], 'password_reset_completed', 'warning', 'Password was changed through the recovery flow.');
        AuditLog::record('PASSWORD_RESET', 'Account password reset completed', $user['id'], $user['name'], $user['role']);
        Helper::json('success', 'Password reset successfully. You can now sign in.', ['redirect' => 'index.php?page=login']);
    }

    public static function verifyTwoFactor(): void
    {
        self::publicPostGuard();
        $userId = (int)($_SESSION['preauth_user_id'] ?? 0);
        $expires = (int)($_SESSION['preauth_expires_at'] ?? 0);
        if ($userId <= 0 || $expires < time()) {
            self::clearPreauth();
            Helper::json('error', 'Your sign-in verification has expired. Enter your email and password again.');
        }
        $_SESSION['preauth_attempts'] = (int)($_SESSION['preauth_attempts'] ?? 0) + 1;
        if ($_SESSION['preauth_attempts'] > 5) {
            AccountSecurity::event($userId, 'two_factor_locked', 'critical', 'Too many invalid two-factor codes.');
            self::clearPreauth();
            Helper::json('error', 'Too many invalid codes. Sign in again.');
        }
        $user = User::findById($userId);
        if (!$user || !AccountSecurity::verifyUserTotp($user, trim($_POST['code'] ?? ''))) {
            Helper::json('error', 'Invalid authenticator code.');
        }

        $method = $_SESSION['preauth_method'] ?? 'email';
        $isNewContext = !empty($_SESSION['preauth_new_context']);
        $rememberDevice = !empty($_POST['remember_device']);
        self::clearPreauth();
        if ($rememberDevice && AccountSecurity::trustCurrentDevice($userId)) {
            AccountSecurity::event($userId, 'trusted_device_added', 'info', 'This browser was trusted for 90 days after a successful two-factor challenge.');
        }
        self::completeLogin($user, $method, $isNewContext);
    }

    public static function beginTwoFactor(): void
    {
        self::authenticatedPostGuard();
        $setup = AccountSecurity::beginTwoFactor((int)Auth::id());
        Helper::json('success', 'Authenticator setup started.', $setup);
    }

    public static function enableTwoFactor(): void
    {
        self::authenticatedPostGuard();
        if (!AccountSecurity::enableTwoFactor((int)Auth::id(), trim($_POST['code'] ?? ''))) {
            Helper::json('error', 'The authenticator code is invalid. Confirm the secret and try again.');
        }
        AuditLog::record('TWO_FACTOR_ENABLED', 'Enabled two-factor authentication');
        $_SESSION['user_two_factor_enabled'] = 1;
        Helper::json('success', 'Two-factor authentication is now active.');
    }

    public static function disableTwoFactor(): void
    {
        self::authenticatedPostGuard();
        if (!AccountSecurity::disableTwoFactor((int)Auth::id(), (string)($_POST['current_password'] ?? ''), trim($_POST['code'] ?? ''))) {
            Helper::json('error', 'Current password or authenticator code is incorrect.');
        }
        AuditLog::record('TWO_FACTOR_DISABLED', 'Disabled two-factor authentication');
        $_SESSION['user_two_factor_enabled'] = 0;
        Helper::json('success', 'Two-factor authentication has been disabled.');
    }

    public static function revokeSession(): void
    {
        self::authenticatedPostGuard();
        if (!AccountSecurity::revokeSession((int)($_POST['session_id'] ?? 0), (int)Auth::id())) {
            Helper::json('error', 'This session cannot be revoked.');
        }
        AuditLog::record('SESSION_REVOKED', 'Revoked account session #' . (int)$_POST['session_id']);
        Helper::json('success', 'The selected device session was signed out.');
    }

    public static function revokeOtherSessions(): void
    {
        self::authenticatedPostGuard();
        AccountSecurity::revokeOtherSessions((int)Auth::id());
        AuditLog::record('SESSIONS_REVOKED', 'Signed out all other devices');
        Helper::json('success', 'All other device sessions were signed out.');
    }

    public static function revokeTrustedDevices(): void
    {
        self::authenticatedPostGuard();
        AccountSecurity::revokeTrustedDevices((int)Auth::id());
        AccountSecurity::event((int)Auth::id(), 'trusted_devices_revoked', 'warning', 'All remembered browsers were removed.');
        AuditLog::record('TRUSTED_DEVICES_REVOKED', 'Removed all remembered browsers');
        Helper::json('success', 'All remembered browsers have been removed. A 2FA code will be required on the next login.');
    }

    public static function saveSettings(): void
    {
        Auth::requirePermission('security', 'manage');
        self::publicPostGuard();
        $values = [
            'password_min_length' => max(6, min(64, (int)($_POST['password_min_length'] ?? 6))),
            'password_expiry_days' => max(1, min(365, (int)($_POST['password_expiry_days'] ?? 90))),
            'session_lifetime_days' => max(1, min(90, (int)($_POST['session_lifetime_days'] ?? 30))),
            'require_2fa_admin' => !empty($_POST['require_2fa_admin']) ? 1 : 0,
            'login_alerts' => !empty($_POST['login_alerts']) ? 1 : 0,
            'backup_retention_days' => max(7, min(365, (int)($_POST['backup_retention_days'] ?? 30))),
        ];
        foreach ($values as $key => $value) {
            Database::query(
                "INSERT INTO security_settings (setting_key,setting_value,updated_by) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)",
                [$key, (string)$value, Auth::id()]
            );
        }
        AuditLog::record('SECURITY_SETTINGS', 'Updated account security policy');
        Helper::json('success', 'Security policy saved successfully.');
    }

    public static function completeLogin(array $user, string $method, bool $newContext): void
    {
        Auth::login($user);
        Auth::createRememberToken((int)$user['id']);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 500);
        User::logLogin((int)$user['id'], $ip, $agent, 'success', $method);
        if ($newContext && AccountSecurity::setting('login_alerts', '1') === '1') {
            require_once __DIR__ . '/../models/Notification.php';
            Notification::send((int)$user['id'], 'security_new_login', 'New sign-in detected',
                'A new device or network signed in at ' . date('d M Y, h:i A') . '. IP: ' . $ip,
                'fa-shield-halved', '#D97706', 'index.php?page=profile&tab=security');
            SecurityMailer::sendLoginAlert($user, $ip, $agent);
            AccountSecurity::event((int)$user['id'], 'new_login_context', 'warning', 'New device or network sign-in detected.');
        }
        AuditLog::record('LOGIN', "User logged in successfully via {$method}", $user['id'], $user['name'], $user['role']);
        $redirect = AccountSecurity::passwordExpired($user) ? 'index.php?page=profile&tab=security&expired=1' : 'index.php?page=dashboard';
        Helper::json('success', 'Login successful.', ['redirect' => $redirect, 'role' => $user['role'], 'name' => $user['name']]);
    }

    private static function publicPostGuard(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helper::json('error', 'Invalid request method.');
        if (!Helper::verifyCsrf($_POST['csrf_token'] ?? '')) Helper::json('error', 'Invalid security token. Refresh the page and try again.');
    }

    private static function authenticatedPostGuard(): void
    {
        Auth::requireLogin();
        self::publicPostGuard();
    }

    private static function clearPreauth(): void
    {
        unset($_SESSION['preauth_user_id'], $_SESSION['preauth_expires_at'], $_SESSION['preauth_attempts'], $_SESSION['preauth_method'], $_SESSION['preauth_new_context']);
    }
}

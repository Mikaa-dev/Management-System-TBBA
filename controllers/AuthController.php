<?php
/**
 * Authentication Controller — AJAX Endpoints
 * Company: The Bridge Business Alliance (TBBA)
 * Updated: Module 1 — Login history recording + suspension check
 */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helper.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../models/AccountSecurity.php';
require_once __DIR__ . '/SecurityController.php';

class AuthController {
    // Pengendali Log Masuk menerusi AJAX POST
    public static function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helper::json('error', 'Request method not allowed.');
        }

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $csrf     = $_POST['csrf_token'] ?? '';

        if (!Helper::verifyCsrf($csrf)) {
            Helper::json('error', 'CSRF token security validation failed. Please refresh the page.');
        }

        // 1. Semak Status Sekatan Cubaan Log Masuk (Login Brute-Force Shield)
        SecurityFirewall::checkLoginThrottle(5, 15);

        if (empty($email) || empty($password)) {
            Helper::json('error', 'Please enter your email and password.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Helper::json('error', 'Invalid email address format.');
        }

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 500);

        $user = User::findByEmail($email);
        $isValid = false;
        if ($user) {
            if (password_verify($password, $user['password'])) {
                $isValid = true;
            }
        }

        if (!$isValid) {
            // Record failed attempt in login history (if user exists)
            if ($user) {
                User::logLogin((int)$user['id'], $ipAddress, $userAgent, 'failed', 'email');
            }
            $attempts = SecurityFirewall::recordLoginFailure($email, 5, 15);
            $remaining = 5 - $attempts;
            if ($remaining <= 0) {
                Helper::json('error', 'Too many failed login attempts (5/5). Your IP address is temporarily locked for 15 minutes.');
            }
            Helper::json('error', "Incorrect email or password. You have {$remaining} attempt(s) remaining before temporary account lockout.");
        }

        // Check account suspension status
        if (($user['status'] ?? 'active') === 'suspended') {
            User::logLogin((int)$user['id'], $ipAddress, $userAgent, 'failed', 'email');
            Helper::json('error', 'Your account has been suspended. Please contact an administrator.');
        }

        // Password is valid. Complete 2FA before creating an authenticated session.
        SecurityFirewall::clearLoginThrottle();
        $newContext = AccountSecurity::isNewLoginContext((int)$user['id'], $ipAddress, $userAgent);
        if (!empty($user['two_factor_enabled'])) {
            if (AccountSecurity::isTrustedDevice((int)$user['id'])) {
                AccountSecurity::event((int)$user['id'], 'trusted_device_used', 'info', 'Two-factor challenge skipped for a verified trusted browser.');
                SecurityController::completeLogin($user, 'email', $newContext);
            }
            $_SESSION['preauth_user_id'] = (int)$user['id'];
            $_SESSION['preauth_expires_at'] = time() + 300;
            $_SESSION['preauth_attempts'] = 0;
            $_SESSION['preauth_method'] = 'email';
            $_SESSION['preauth_new_context'] = $newContext ? 1 : 0;
            Helper::json('success', 'Password accepted. Enter your authenticator code.', ['requires_2fa' => true]);
        }
        SecurityController::completeLogin($user, 'email', $newContext);
    }

    // Pengendali Log Keluar
    public static function logout() {
        AuditLog::record('LOGIN', "User logged out of the system");
        Auth::logout();
        Helper::redirect('index.php?page=login');
    }

    // ==========================================
    // GOOGLE OAUTH 2.0 IMPLEMENTATION (NATIVE PHP)
    // ==========================================
    
    // Step 1: Redirect user to Google OAuth 2.0 Authorization Endpoint
    public static function googleLogin() {
        $clientId = defined('GOOGLE_OAUTH_CLIENT_ID') ? trim(GOOGLE_OAUTH_CLIENT_ID) : '';
        $clientSecret = defined('GOOGLE_OAUTH_CLIENT_SECRET') ? trim(GOOGLE_OAUTH_CLIENT_SECRET) : '';
        if ($clientId === '' || $clientSecret === '') {
            $_SESSION['login_error'] = 'Google sign-in has not been configured by the administrator.';
            header('Location: index.php?page=login');
            exit;
        }

        $redirectUri = self::googleRedirectUri();
        
        // Generate security state token against CSRF attacks
        $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
        $state = $_SESSION['oauth_state'];
        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);

        header("Location: " . $authUrl);
        exit;
    }

    // Step 2: Handle callback from Google, exchange code for token, verify user email in DB
    public static function googleCallback() {
        $clientId     = defined('GOOGLE_OAUTH_CLIENT_ID') ? trim(GOOGLE_OAUTH_CLIENT_ID) : '';
        $clientSecret = defined('GOOGLE_OAUTH_CLIENT_SECRET') ? trim(GOOGLE_OAUTH_CLIENT_SECRET) : '';
        if ($clientId === '' || $clientSecret === '') {
            $_SESSION['login_error'] = 'Google sign-in has not been configured by the administrator.';
            header('Location: index.php?page=login');
            exit;
        }
        $redirectUri = self::googleRedirectUri();

        // A. Verify OAuth state parameter to prevent CSRF / MITM attacks
        if (empty($_GET['state']) || empty($_SESSION['oauth_state']) || !hash_equals((string)$_SESSION['oauth_state'], (string)$_GET['state'])) {
            $_SESSION['login_error'] = "Security validation failed (OAuth State Mismatch). Please try again.";
            header("Location: index.php?page=login");
            exit;
        }
        unset($_SESSION['oauth_state']);

        if (!isset($_GET['code'])) {
            $_SESSION['login_error'] = "Authorization code was not returned by Google.";
            header("Location: index.php?page=login");
            exit;
        }

        // B. Exchange authorization code for Access Token using cURL
        $tokenUrl = "https://oauth2.googleapis.com/token";
        $postData = [
            'code'          => $_GET['code'],
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $tokenResponse = curl_exec($ch);
        $tokenHttpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $tokenCurlError = curl_error($ch);
        curl_close($ch);

        $tokenData = is_string($tokenResponse) ? json_decode($tokenResponse, true) : null;

        if ($tokenCurlError !== '' || $tokenHttpCode !== 200 || empty($tokenData['access_token'])) {
            error_log('[Google OAuth] Token exchange failed. HTTP ' . $tokenHttpCode . ($tokenCurlError !== '' ? ': ' . $tokenCurlError : ''));
            $_SESSION['login_error'] = "Failed to obtain access token from Google OAuth.";
            header("Location: index.php?page=login");
            exit;
        }

        // C. Fetch the Google profile using a Bearer token (never place it in the URL).
        $userInfoUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, $userInfoUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenData['access_token']]);
        curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 20);
        $userResponse = curl_exec($ch2);
        $userHttpCode = (int)curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        $userCurlError = curl_error($ch2);
        curl_close($ch2);

        $googleUser = is_string($userResponse) ? json_decode($userResponse, true) : null;

        if ($userCurlError !== '' || $userHttpCode !== 200 || empty($googleUser['email']) || empty($googleUser['email_verified'])) {
            error_log('[Google OAuth] User profile request failed. HTTP ' . $userHttpCode . ($userCurlError !== '' ? ': ' . $userCurlError : ''));
            $_SESSION['login_error'] = "Could not retrieve email address from Google profile.";
            header("Location: index.php?page=login");
            exit;
        }

        $email = trim($googleUser['email']);

        // D. Query MySQL database to check if email exists in users table
        $user = User::findByEmail($email);

        if (!$user) {
            // Email DOES NOT EXIST: Deny login and record failed attempt
            SecurityFirewall::recordLoginFailure($email, 5, 15);
            $_SESSION['login_error'] = "Sorry, this email ({$email}) is not registered with the TBBA portal.";
            header("Location: index.php?page=login");
            exit;
        }

        // E. Email EXISTS: check suspension, log user in
        if (($user['status'] ?? 'active') === 'suspended') {
            $_SESSION['login_error'] = "Your account has been suspended. Please contact an administrator.";
            header("Location: index.php?page=login");
            exit;
        }
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 500);
        $newContext = AccountSecurity::isNewLoginContext((int)$user['id'], $ipAddress, $userAgent);
        SecurityFirewall::clearLoginThrottle();
        if (!empty($user['two_factor_enabled'])) {
            if (AccountSecurity::isTrustedDevice((int)$user['id'])) {
                AccountSecurity::event((int)$user['id'], 'trusted_device_used', 'info', 'Two-factor challenge skipped for a verified trusted browser.');
            } else {
                $_SESSION['preauth_user_id'] = (int)$user['id'];
                $_SESSION['preauth_expires_at'] = time() + 300;
                $_SESSION['preauth_attempts'] = 0;
                $_SESSION['preauth_method'] = 'google';
                $_SESSION['preauth_new_context'] = $newContext ? 1 : 0;
                header('Location: index.php?page=login&verify=1');
                exit;
            }
        }
        Auth::login($user);
        Auth::createRememberToken((int)$user['id']);
        User::logLogin((int)$user['id'], $ipAddress, $userAgent, 'success', 'google');
        if ($newContext && AccountSecurity::setting('login_alerts', '1') === '1') {
            require_once __DIR__ . '/../models/Notification.php';
            require_once __DIR__ . '/../core/SecurityMailer.php';
            Notification::send((int)$user['id'], 'security_new_login', 'New sign-in detected',
                'A new Google sign-in was detected at ' . date('d M Y, h:i A') . '. IP: ' . $ipAddress,
                'fa-shield-halved', '#D97706', 'index.php?page=profile&tab=security');
            SecurityMailer::sendLoginAlert($user, $ipAddress, $userAgent);
            AccountSecurity::event((int)$user['id'], 'new_login_context', 'warning', 'New Google sign-in context detected.');
        }
        AuditLog::record('LOGIN', "User logged in successfully via Google OAuth single sign-on", $user['id'], $user['name'], $user['role']);
        header("Location: index.php?page=dashboard");
        exit;
    }

    /** Use an explicit OAuth callback when configured, otherwise derive it from APP_URL/request. */
    private static function googleRedirectUri(): string
    {
        $configured = defined('GOOGLE_OAUTH_REDIRECT_URI') ? trim(GOOGLE_OAUTH_REDIRECT_URI) : '';
        if ($configured !== '') {
            return $configured;
        }
        return Helper::url('index.php?action=google_callback');
    }
}
?>

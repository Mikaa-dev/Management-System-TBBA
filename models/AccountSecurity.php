<?php
/** Account security primitives: password policy, TOTP, reset tokens and device sessions. */

require_once __DIR__ . '/../config/database.php';

class AccountSecurity
{
    private const TRUSTED_DEVICE_COOKIE = 'tbba_trusted_device';
    private const TRUSTED_DEVICE_DAYS = 90;

    public static function setting(string $key, string $default = ''): string
    {
        try {
            $value = Database::query("SELECT setting_value FROM security_settings WHERE setting_key=?", [$key])->fetchColumn();
            return $value !== false ? (string)$value : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }

    public static function validatePassword(string $password): array
    {
        $minimum = max(6, (int)self::setting('password_min_length', '6'));
        $errors = [];
        if (strlen($password) < $minimum) $errors[] = "at least {$minimum} characters";
        if (!preg_match('/[a-z]/', $password)) $errors[] = 'a lowercase letter';
        if (!preg_match('/[A-Z]/', $password)) $errors[] = 'an uppercase letter';
        if (!preg_match('/\d/', $password)) $errors[] = 'a number';
        if (!preg_match('/[^A-Za-z0-9]/', $password)) $errors[] = 'a symbol';
        return $errors;
    }

    public static function updatePassword(int $userId, string $hash): void
    {
        $days = max(1, (int)self::setting('password_expiry_days', '90'));
        Database::query(
            "UPDATE users SET password=?,password_changed_at=NOW(),password_expires_at=DATE_ADD(NOW(),INTERVAL ? DAY) WHERE id=?",
            [$hash, $days, $userId]
        );
        Database::query("UPDATE password_reset_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL", [$userId]);
        self::revokeTrustedDevices($userId);
    }

    public static function passwordExpired(array $user): bool
    {
        return !empty($user['password_expires_at']) && strtotime($user['password_expires_at']) <= time();
    }

    public static function createResetToken(int $userId, string $ip): string
    {
        Database::query("UPDATE password_reset_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL", [$userId]);
        $token = bin2hex(random_bytes(32));
        Database::query(
            "INSERT INTO password_reset_tokens (user_id,token_hash,expires_at,requested_ip) VALUES (?, ?, DATE_ADD(NOW(),INTERVAL 30 MINUTE), ?)",
            [$userId, hash('sha256', $token), $ip]
        );
        self::event($userId, 'password_reset_requested', 'info', 'A password reset was requested.');
        return $token;
    }

    public static function findResetUser(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        $row = Database::query(
            "SELECT u.*,prt.id reset_id FROM password_reset_tokens prt JOIN users u ON u.id=prt.user_id
             WHERE prt.token_hash=? AND prt.used_at IS NULL AND prt.expires_at>NOW() LIMIT 1",
            [hash('sha256', $token)]
        )->fetch();
        return $row ?: null;
    }

    public static function registerSession(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        $days = max(1, (int)self::setting('session_lifetime_days', '30'));
        Database::query("DELETE FROM user_sessions WHERE expires_at<NOW() OR revoked_at IS NOT NULL AND revoked_at<DATE_SUB(NOW(),INTERVAL 30 DAY)");
        Database::query(
            "INSERT INTO user_sessions (user_id,session_token_hash,session_id_hash,ip_address,user_agent,expires_at)
             VALUES (?,?,?,?,?,DATE_ADD(NOW(),INTERVAL ? DAY))",
            [
                $userId, hash('sha256', $token), hash('sha256', session_id()),
                $_SERVER['REMOTE_ADDR'] ?? '', substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown device', 0, 500), $days,
            ]
        );
        $_SESSION['auth_session_token'] = $token;
        $_SESSION['auth_session_checked_at'] = time();
    }

    public static function validateCurrentSession(int $userId): bool
    {
        $token = $_SESSION['auth_session_token'] ?? '';
        if ($token === '') return false;
        $row = Database::query(
            "SELECT id FROM user_sessions WHERE user_id=? AND session_token_hash=? AND revoked_at IS NULL AND expires_at>NOW() LIMIT 1",
            [$userId, hash('sha256', $token)]
        )->fetch();
        if (!$row) return false;
        Database::query("UPDATE user_sessions SET last_seen_at=NOW() WHERE id=?", [$row['id']]);
        $_SESSION['auth_session_checked_at'] = time();
        return true;
    }

    public static function revokeCurrentSession(): void
    {
        $token = $_SESSION['auth_session_token'] ?? '';
        if ($token !== '') {
            Database::query("UPDATE user_sessions SET revoked_at=NOW() WHERE session_token_hash=?", [hash('sha256', $token)]);
        }
    }

    public static function sessionsForUser(int $userId): array
    {
        $currentHash = !empty($_SESSION['auth_session_token']) ? hash('sha256', $_SESSION['auth_session_token']) : '';
        $rows = Database::query(
            "SELECT * FROM user_sessions WHERE user_id=? AND revoked_at IS NULL AND expires_at>NOW() ORDER BY last_seen_at DESC",
            [$userId]
        )->fetchAll();
        foreach ($rows as &$row) {
            $row['is_current'] = hash_equals($currentHash, (string)$row['session_token_hash']);
            $row['device_name'] = self::deviceName((string)$row['user_agent']);
        }
        return $rows;
    }

    public static function revokeSession(int $sessionId, int $userId): bool
    {
        $currentHash = !empty($_SESSION['auth_session_token']) ? hash('sha256', $_SESSION['auth_session_token']) : '';
        $row = Database::query("SELECT session_token_hash FROM user_sessions WHERE id=? AND user_id=?", [$sessionId, $userId])->fetch();
        if (!$row || hash_equals($currentHash, (string)$row['session_token_hash'])) return false;
        Database::query("UPDATE user_sessions SET revoked_at=NOW() WHERE id=? AND user_id=?", [$sessionId, $userId]);
        return true;
    }

    public static function revokeOtherSessions(int $userId): void
    {
        $currentHash = !empty($_SESSION['auth_session_token']) ? hash('sha256', $_SESSION['auth_session_token']) : '';
        Database::query("UPDATE user_sessions SET revoked_at=NOW() WHERE user_id=? AND session_token_hash!=? AND revoked_at IS NULL", [$userId, $currentHash]);
    }

    /** Revoke every authenticated session for an account after a security-sensitive change. */
    public static function revokeAllSessions(int $userId): void
    {
        Database::query(
            "UPDATE user_sessions SET revoked_at=NOW() WHERE user_id=? AND revoked_at IS NULL",
            [$userId]
        );
    }

    /** Revoke sessions and remembered browsers for a suspended or compromised account. */
    public static function revokeAllAccess(int $userId): void
    {
        self::revokeAllSessions($userId);
        self::revokeTrustedDevices($userId);
    }

    /** Verify and rotate a trusted-browser token after the primary login succeeds. */
    public static function isTrustedDevice(int $userId): bool
    {
        $cookie = self::trustedDeviceCookie();
        if (!$cookie || $cookie['user_id'] !== $userId) return false;

        try {
            $row = Database::query(
                "SELECT id,user_agent_hash,expires_at FROM trusted_devices
                 WHERE user_id=? AND token_hash=? AND revoked_at IS NULL AND expires_at>NOW() LIMIT 1",
                [$userId, hash('sha256', $cookie['token'])]
            )->fetch();
            if (!$row) {
                self::clearTrustedDeviceCookie($userId);
                return false;
            }

            $agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown device', 0, 500);
            if (!hash_equals((string)$row['user_agent_hash'], hash('sha256', $agent))) {
                Database::query("UPDATE trusted_devices SET revoked_at=NOW() WHERE id=?", [$row['id']]);
                self::clearTrustedDeviceCookie($userId);
                self::event($userId, 'trusted_device_rejected', 'warning', 'A trusted-device token was presented from a different browser signature.');
                return false;
            }

            // Rotate the bearer token after every successful use to limit replay.
            $newToken = bin2hex(random_bytes(32));
            Database::query(
                "UPDATE trusted_devices SET token_hash=?,last_used_at=NOW(),ip_address=? WHERE id=?",
                [hash('sha256', $newToken), $_SERVER['REMOTE_ADDR'] ?? '', $row['id']]
            );
            self::setTrustedDeviceCookie($userId, $newToken, (int)strtotime((string)$row['expires_at']));
            return true;
        } catch (Throwable $e) {
            error_log('[AccountSecurity] Trusted-device validation failed: ' . $e->getMessage());
            return false;
        }
    }

    /** Remember this browser after a successful TOTP challenge. */
    public static function trustCurrentDevice(int $userId): bool
    {
        try {
            $token = bin2hex(random_bytes(32));
            $agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown device', 0, 500);
            $agentHash = hash('sha256', $agent);
            Database::query("DELETE FROM trusted_devices WHERE expires_at<NOW() OR revoked_at<DATE_SUB(NOW(),INTERVAL 30 DAY)");
            Database::query(
                "UPDATE trusted_devices SET revoked_at=NOW() WHERE user_id=? AND user_agent_hash=? AND revoked_at IS NULL",
                [$userId, $agentHash]
            );
            Database::query(
                "INSERT INTO trusted_devices (user_id,token_hash,user_agent_hash,user_agent,ip_address,expires_at)
                 VALUES (?,?,?,?,?,DATE_ADD(NOW(),INTERVAL ? DAY))",
                [$userId, hash('sha256', $token), $agentHash, $agent, $_SERVER['REMOTE_ADDR'] ?? '', self::TRUSTED_DEVICE_DAYS]
            );
            self::setTrustedDeviceCookie($userId, $token, time() + (self::TRUSTED_DEVICE_DAYS * 86400));
            return true;
        } catch (Throwable $e) {
            error_log('[AccountSecurity] Unable to remember trusted device: ' . $e->getMessage());
            return false;
        }
    }

    public static function trustedDeviceCount(int $userId): int
    {
        try {
            return (int)Database::query(
                "SELECT COUNT(*) FROM trusted_devices WHERE user_id=? AND revoked_at IS NULL AND expires_at>NOW()",
                [$userId]
            )->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    public static function revokeTrustedDevices(int $userId): void
    {
        try {
            Database::query("UPDATE trusted_devices SET revoked_at=NOW() WHERE user_id=? AND revoked_at IS NULL", [$userId]);
        } catch (Throwable $e) {
            error_log('[AccountSecurity] Unable to revoke trusted devices: ' . $e->getMessage());
        }
        self::clearTrustedDeviceCookie($userId);
    }

    public static function isNewLoginContext(int $userId, string $ip, string $userAgent): bool
    {
        $fingerprint = self::deviceName($userAgent);
        $rows = Database::query(
            "SELECT ip_address,user_agent FROM login_history WHERE user_id=? AND status='success' ORDER BY created_at DESC LIMIT 20",
            [$userId]
        )->fetchAll();
        if (!$rows) return false; // First login is expected, not suspicious.
        foreach ($rows as $row) {
            if ((string)$row['ip_address'] === $ip && self::deviceName((string)$row['user_agent']) === $fingerprint) return false;
        }
        return true;
    }

    public static function event(?int $userId, string $type, string $severity = 'info', string $details = ''): void
    {
        try {
            Database::query(
                "INSERT INTO security_events (user_id,event_type,severity,ip_address,user_agent,details) VALUES (?,?,?,?,?,?)",
                [$userId, $type, $severity, $_SERVER['REMOTE_ADDR'] ?? '', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500), substr($details, 0, 1000)]
            );
        } catch (Throwable $e) {
            error_log('[AccountSecurity] ' . $e->getMessage());
        }
    }

    public static function beginTwoFactor(int $userId): array
    {
        $secret = self::generateSecret();
        self::revokeTrustedDevices($userId);
        Database::query("UPDATE users SET two_factor_secret=?,two_factor_enabled=0 WHERE id=?", [self::encryptSecret($secret), $userId]);
        $user = Database::query("SELECT email FROM users WHERE id=?", [$userId])->fetch();
        $label = rawurlencode('TBBA:' . ($user['email'] ?? ('user-' . $userId)));
        $issuer = rawurlencode('TBBA ERP');
        return ['secret' => $secret, 'otpauth_uri' => "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&digits=6&period=30"];
    }

    public static function enableTwoFactor(int $userId, string $code): bool
    {
        $user = Database::query("SELECT two_factor_secret FROM users WHERE id=?", [$userId])->fetch();
        $secret = self::decryptSecret((string)($user['two_factor_secret'] ?? ''));
        if ($secret === '' || !self::verifyTotp($secret, $code)) return false;
        Database::query("UPDATE users SET two_factor_enabled=1 WHERE id=?", [$userId]);
        self::event($userId, 'two_factor_enabled', 'info', 'Authenticator-based two-factor authentication was enabled.');
        return true;
    }

    public static function disableTwoFactor(int $userId, string $password, string $code): bool
    {
        $user = Database::query("SELECT password,two_factor_secret,two_factor_enabled FROM users WHERE id=?", [$userId])->fetch();
        if (!$user || !$user['two_factor_enabled'] || !password_verify($password, $user['password'])) return false;
        $secret = self::decryptSecret((string)$user['two_factor_secret']);
        if (!self::verifyTotp($secret, $code)) return false;
        Database::query("UPDATE users SET two_factor_enabled=0,two_factor_secret=NULL WHERE id=?", [$userId]);
        self::revokeTrustedDevices($userId);
        self::event($userId, 'two_factor_disabled', 'warning', 'Two-factor authentication was disabled.');
        return true;
    }

    public static function verifyUserTotp(array $user, string $code): bool
    {
        if (empty($user['two_factor_enabled']) || empty($user['two_factor_secret'])) return false;
        return self::verifyTotp(self::decryptSecret((string)$user['two_factor_secret']), $code);
    }

    private static function verifyTotp(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6 || $secret === '') return false;
        $counter = (int)floor(time() / 30);
        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals(self::totp($secret, $counter + $offset), $code)) return true;
        }
        return false;
    }

    private static function totp(string $secret, int $counter): string
    {
        $key = self::base32Decode($secret);
        $binaryCounter = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24) | ((ord($hash[$offset + 1]) & 0xff) << 16)
               | ((ord($hash[$offset + 2]) & 0xff) << 8) | (ord($hash[$offset + 3]) & 0xff);
        return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function generateSecret(int $bytes = 20): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data = random_bytes($bytes);
        $bits = '';
        foreach (str_split($data) as $char) $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        $secret = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0');
            $secret .= $alphabet[bindec($chunk)];
        }
        return $secret;
    }

    private static function base32Decode(string $input): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $input));
        $bits = '';
        foreach (str_split($input) as $char) {
            $position = strpos($alphabet, $char);
            if ($position === false) continue;
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }
        $output = '';
        foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $output .= chr(bindec($byte));
        return $output;
    }

    private static function encryptionKey(): string
    {
        $configured = $_ENV['SECURITY_ENCRYPTION_KEY'] ?? (getenv('SECURITY_ENCRYPTION_KEY') ?: '');
        return hash('sha256', $configured !== '' ? $configured : DB_NAME . '|' . DB_PASS . '|' . __DIR__, true);
    }

    private static function encryptSecret(string $secret): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($secret, 'aes-256-gcm', self::encryptionKey(), OPENSSL_RAW_DATA, $iv, $tag);
        return 'enc:' . base64_encode($iv . $tag . $cipher);
    }

    private static function decryptSecret(string $stored): string
    {
        if (!str_starts_with($stored, 'enc:')) return $stored; // migration compatibility
        $raw = base64_decode(substr($stored, 4), true);
        if ($raw === false || strlen($raw) < 29) return '';
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        return (string)openssl_decrypt($cipher, 'aes-256-gcm', self::encryptionKey(), OPENSSL_RAW_DATA, $iv, $tag);
    }

    private static function trustedDeviceCookie(): ?array
    {
        $value = (string)($_COOKIE[self::TRUSTED_DEVICE_COOKIE] ?? '');
        if (!preg_match('/^(\d+):([a-f0-9]{64})$/', $value, $matches)) return null;
        return ['user_id' => (int)$matches[1], 'token' => $matches[2]];
    }

    private static function setTrustedDeviceCookie(int $userId, string $token, int $expiresAt): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) return;
        setcookie(self::TRUSTED_DEVICE_COOKIE, $userId . ':' . $token, [
            'expires' => $expiresAt,
            'path' => '/',
            'secure' => tbba_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function clearTrustedDeviceCookie(?int $userId = null): void
    {
        $cookie = self::trustedDeviceCookie();
        if ($userId !== null && $cookie && $cookie['user_id'] !== $userId) return;
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            setcookie(self::TRUSTED_DEVICE_COOKIE, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => tbba_is_https(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        unset($_COOKIE[self::TRUSTED_DEVICE_COOKIE]);
    }

    private static function deviceName(string $userAgent): string
    {
        $browser = str_contains($userAgent, 'Edg/') ? 'Edge' : (str_contains($userAgent, 'Chrome/') ? 'Chrome' : (str_contains($userAgent, 'Firefox/') ? 'Firefox' : (str_contains($userAgent, 'Safari/') ? 'Safari' : 'Browser')));
        $os = str_contains($userAgent, 'Windows') ? 'Windows' : (str_contains($userAgent, 'Macintosh') ? 'macOS' : (str_contains($userAgent, 'Android') ? 'Android' : (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') ? 'iOS' : 'Device')));
        return $browser . ' on ' . $os;
    }
}

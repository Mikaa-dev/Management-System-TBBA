<?php
/**
 * Modul Keselamatan Rangkaian & Aplikasi (WAF & Anti-DDoS Firewall)
 * Syarikat: The Bridge Business Alliance (TBBA)
 * Fungsi:
 *   1. Web Application Firewall (WAF) - Menapis serangan SQL Injection, XSS, Path Traversal
 *   2. Anti-DDoS Protection - Rate Limiting & Request Flood Throttling
 *   3. HTTP Security Headers - Perlindungan Edge & Penumpasan Serangan Penyemak imbas
 */

class SecurityFirewall {

    private static $logFile = __DIR__ . '/../storage/logs/waf_audit.json';
    private static $rateLimitDir = __DIR__ . '/../storage/logs/ddos_ratelimit';
    private static $loginThrottleDir = __DIR__ . '/../storage/logs/login_throttle';
    private static $inquiryThrottleDir = __DIR__ . '/../storage/logs/inquiry_throttle';

    /**
     * Permulaan Utama (Dijalankan pada setiap request di index.php)
     */
     public static function init() {
         self::ensureDirectories();
         self::sendSecurityHeaders();
         self::antiDdosGuard();
         self::wafInspectRequest();
     }

    /**
     * Pastikan direktori log wujud
     */
    private static function ensureDirectories() {
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0750, true);
        }
        if (!is_dir(self::$rateLimitDir)) {
            @mkdir(self::$rateLimitDir, 0750, true);
        }
        if (!is_dir(self::$loginThrottleDir)) {
            @mkdir(self::$loginThrottleDir, 0750, true);
        }
        if (!is_dir(self::$inquiryThrottleDir)) {
            @mkdir(self::$inquiryThrottleDir, 0750, true);
        }
    }

    /**
     * 1. HTTP Security Headers (Perlindungan Edge & Browser Hardening)
     */
    public static function sendSecurityHeaders() {
        if (!headers_sent()) {
            header("X-Frame-Options: DENY");
            header("X-XSS-Protection: 1; mode=block");
            header("X-Content-Type-Options: nosniff");
            header("Referrer-Policy: strict-origin-when-cross-origin");
            // Keep GPS and camera available to this first-party PWA. The front
            // controller applies the same policy for attendance and uploads.
            header("Permissions-Policy: camera=(self), geolocation=(self), microphone=()");
            header("X-TBBA-Security-Firewall: WAF-Active/1.2");
        }
    }

    /**
     * 2. Anti-DDoS & Flood Protection Layer (Rate Limiting per IP Address)
     */
    public static function antiDdosGuard($maxRequestsPerMinute = 120) {
        $clientIp = self::getClientIp();
        $ipHash = md5($clientIp);
        $timeWindow = 60; // 60 saat (1 minit)
        $now = time();

        $rateFile = self::$rateLimitDir . '/' . $ipHash . '.json';
        $requestHistory = [];

        if (file_exists($rateFile)) {
            $content = @file_get_contents($rateFile);
            $data = json_decode($content, true);
            if (is_array($data)) {
                $requestHistory = $data;
            }
        }

        // Tapis rekod masa yang masih dalam tetingkap masa 60 saat lepas
        $validRequests = [];
        foreach ($requestHistory as $timestamp) {
            if ($now - $timestamp <= $timeWindow) {
                $validRequests[] = $timestamp;
            }
        }

        // Jika melebihi had maksimum request per minit -> Sekat sebagai DDoS/Flood Attempt
        if (count($validRequests) >= $maxRequestsPerMinute) {
            self::logAttack('DDoS_RATE_LIMIT_EXCEEDED', 'Excessive requests flood detected (' . count($validRequests) . ' reqs/min)');
            self::blockRequest(429, 'Anti-DDoS Shield Triggered', 'Your request rate has exceeded the real-time threshold (Rate Limit Exceeded). Please try again after 60 seconds.');
        }

        $validRequests[] = $now;
        @file_put_contents($rateFile, json_encode($validRequests), LOCK_EX);
        @chmod($rateFile, 0640);
    }

    /**
     * 3. Web Application Firewall (WAF) - Pemeriksaan Serangan SQLi, XSS, LFI
     */
    public static function wafInspectRequest() {
        // Jangan block request internal jika parameter biasa, tetapi periksa corak serangan berbahaya
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Corak Serangan SQL Injection Berbahaya
        $sqlPatterns = [
            '/\bUNION\b\s+(ALL\s+)?\bSELECT\b/i',
            '/\bCONCAT\s*\(/i',
            '/\bINFORMATION_SCHEMA\b/i',
            '/\bSLEEP\s*\(\s*\d+\s*\)/i',
            '/\bBENCHMARK\s*\(/i',
            '/(\%27)|(\')\s*(--|\#|\/\*)/i',
            '/\bOR\b\s+[\d\']+\s*=\s*[\d\']/i'
        ];

        // Corak Serangan Cross-Site Scripting (XSS)
        $xssPatterns = [
            '/<script\b[^>]*>(.*?)<\/script>/is',
            '/javascript\s*:/i',
            '/\bon(load|error|click|mouseover|submit|focus)\s*=/i',
            '/<iframe\b[^>]*>/i',
            '/\beval\s*\(/i'
        ];

        // Corak Path Traversal / LFI
        $lfiPatterns = [
            '/\.\.\/\.\.\//i',
            '/\.\.\\\\.\.\\\\/i',
            '/\/etc\/passwd/i',
            '/c:\\\\windows\\\\/i',
            '/php:\/\/input/i'
        ];

        // Gabungkan semua data input untuk diperiksa
        $inputsToInspect = [
            'GET' => $_GET,
            'POST' => $_POST,
            'COOKIE' => $_COOKIE,
            'URI' => $uri
        ];

        foreach ($inputsToInspect as $source => $data) {
            self::recursiveInspect($data, $source, $sqlPatterns, 'SQL_INJECTION');
            self::recursiveInspect($data, $source, $xssPatterns, 'XSS_ATTACK');
            self::recursiveInspect($data, $source, $lfiPatterns, 'PATH_TRAVERSAL');
        }
    }

    /**
     * Pemeriksaan mendalam (recursive) ke atas input
     */
    private static function recursiveInspect($input, $source, $patterns, $attackType) {
        if (is_array($input) || is_object($input)) {
            foreach ($input as $key => $value) {
                $subSource = $source . '[' . $key . ']';
                self::recursiveInspect($value, $subSource, $patterns, $attackType);
            }
            return;
        }

        if (!is_string($input) || empty($input)) {
            return;
        }

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                self::logAttack($attackType, "Threat detected on " . $source . ": " . substr(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'), 0, 150));
                self::blockRequest(403, "WAF Security Alert: " . $attackType . " Blocked", "The Web Application Firewall (WAF) has detected and blocked a malicious security attack attempt.");
            }
        }
    }

    /**
     * Menyekat request yang merbahaya dan memaparkan respons yang profesional
     */
    private static function blockRequest($httpCode, $title, $message) {
        http_response_code($httpCode);

        // Jika request adalah AJAX / API, kembalikan JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' || isset($_GET['action'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'error',
                'security_intercept' => true,
                'code' => $httpCode,
                'threat' => $title,
                'message' => $message,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            exit;
        }

        // Paparan halaman WAF Blocked UI yang elegan
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Security Interception | TBBA Security Firewall</title>
    <style>
        body { margin:0; padding:0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0F172A; color: #F8FAFC; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .box { background: #1E293B; border: 1px solid #334155; border-radius: 12px; padding: 36px; max-width: 520px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
        .badge { background: #EF4444; color: #FFFFFF; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 1px; }
        h1 { font-size: 24px; margin: 18px 0 10px; color: #F8FAFC; }
        p { font-size: 14px; color: #94A3B8; line-height: 1.6; margin-bottom: 24px; }
        .ip-box { background: #0F172A; padding: 12px; border-radius: 8px; font-family: monospace; font-size: 13px; color: #38BDF8; margin-bottom: 24px; }
        .btn { display: inline-block; background: #3B82F6; color: #FFFFFF; text-decoration: none; font-weight: 700; font-size: 13px; padding: 12px 24px; border-radius: 8px; transition: background 0.2s; }
        .btn:hover { background: #2563EB; }
    </style>
</head>
<body>
    <div class="box">
        <span class="badge">WAF & Anti-DDoS Active Defense</span>
        <h1>' . htmlspecialchars($title) . '</h1>
        <p>' . htmlspecialchars($message) . '</p>
        <div class="ip-box">
            Client IP: ' . htmlspecialchars(self::getClientIp()) . '<br>
            Event Reference: ' . strtoupper(substr(md5(time()), 0, 8)) . '
        </div>
        <a href="index.php?page=home" class="btn">Back to Home Page</a>
    </div>
</body>
</html>';
        exit;
    }

    /**
     * Merekodkan log serangan keselamatan ke dalam fail JSON audit
     */
    public static function logAttack($attackType, $details) {
        self::ensureDirectories();

        $logs = [];
        if (file_exists(self::$logFile)) {
            $content = @file_get_contents(self::$logFile);
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $logs = $decoded;
            }
        }

        $entry = [
            'id' => uniqid('sec_'),
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => self::getClientIp(),
            'type' => $attackType,
            'details' => $details,
            'uri' => $_SERVER['REQUEST_URI'] ?? '-',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ];

        // Simpan 100 log terkini di bahagian atas
        array_unshift($logs, $entry);
        $logs = array_slice($logs, 0, 100);

        @file_put_contents(self::$logFile, json_encode($logs, JSON_PRETTY_PRINT), LOCK_EX);
        @chmod(self::$logFile, 0640);
    }

    /**
     * Dapatkan IP sebenar pengguna (termasuk sokongan proxy Cloudflare / Load Balancer)
     */
    public static function getClientIp() {
        $remoteAddress = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP)
            ? (string)$_SERVER['REMOTE_ADDR']
            : '127.0.0.1';

        // Forwarding headers are controlled by the client unless the direct peer
        // is an explicitly configured reverse proxy. Never trust them by default.
        if (!self::isTrustedProxy($remoteAddress)) {
            return $remoteAddress;
        }

        $cloudflareIp = trim((string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
        if (filter_var($cloudflareIp, FILTER_VALIDATE_IP)) {
            return $cloudflareIp;
        }

        foreach (explode(',', (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')) as $candidate) {
            $candidate = trim($candidate);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        return $remoteAddress;
    }

    private static function isTrustedProxy(string $remoteAddress): bool {
        $configured = $_ENV['TRUSTED_PROXIES'] ?? $_SERVER['TRUSTED_PROXIES'] ?? getenv('TRUSTED_PROXIES') ?: '';
        foreach (array_filter(array_map('trim', explode(',', (string)$configured))) as $network) {
            if (self::ipMatchesNetwork($remoteAddress, $network)) return true;
        }
        return false;
    }

    private static function ipMatchesNetwork(string $ip, string $network): bool {
        if (!str_contains($network, '/')) {
            return filter_var($network, FILTER_VALIDATE_IP) !== false && hash_equals($network, $ip);
        }

        [$subnet, $prefixRaw] = array_pad(explode('/', $network, 2), 2, '');
        $ipBinary = @inet_pton($ip);
        $subnetBinary = @inet_pton($subnet);
        if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) return false;

        $maxBits = strlen($ipBinary) * 8;
        if ($prefixRaw === '' || !ctype_digit($prefixRaw)) return false;
        $prefix = (int)$prefixRaw;
        if ($prefix < 0 || $prefix > $maxBits) return false;

        $fullBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;
        if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($subnetBinary, 0, $fullBytes)) return false;
        if ($remainingBits === 0) return true;

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
        return (ord($ipBinary[$fullBytes]) & $mask) === (ord($subnetBinary[$fullBytes]) & $mask);
    }

    /**
     * Dapatkan statistik keselamatan semasa untuk paparan Admin Dashboard
     */
    public static function getStats() {
        self::ensureDirectories();

        $logs = [];
        if (file_exists(self::$logFile)) {
            $content = @file_get_contents(self::$logFile);
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $logs = $decoded;
            }
        }

        $sqliCount = 0;
        $xssCount = 0;
        $ddosCount = 0;
        $lfiCount = 0;
        $bruteForceCount = 0;

        foreach ($logs as $item) {
            $type = $item['type'] ?? '';
            if ($type === 'SQL_INJECTION') $sqliCount++;
            elseif ($type === 'XSS_ATTACK') $xssCount++;
            elseif ($type === 'DDoS_RATE_LIMIT_EXCEEDED') $ddosCount++;
            elseif ($type === 'PATH_TRAVERSAL') $lfiCount++;
            elseif ($type === 'LOGIN_BRUTE_FORCE_BLOCKED' || $type === 'LOGIN_FAILED_ATTEMPT') $bruteForceCount++;
        }

        return [
            'waf_status' => 'Active',
            'ddos_shield_status' => 'Active (Max 120 req/min per IP)',
            'total_blocked' => count($logs),
            'sqli_blocked' => $sqliCount,
            'xss_blocked' => $xssCount,
            'ddos_mitigated' => $ddosCount,
            'lfi_blocked' => $lfiCount,
            'brute_force_blocked' => $bruteForceCount,
            'recent_logs' => array_slice($logs, 0, 15)
        ];
    }

    /**
     * Kosongkan log keselamatan (Admin sahaja)
     */
    public static function clearLogs() {
        self::ensureDirectories();
        @file_put_contents(self::$logFile, json_encode([], JSON_PRETTY_PRINT), LOCK_EX);
        @chmod(self::$logFile, 0640);
        if (is_dir(self::$loginThrottleDir)) {
            $files = glob(self::$loginThrottleDir . '/*.json');
            if (is_array($files)) {
                foreach ($files as $f) {
                    @unlink($f);
                }
            }
        }
        return true;
    }

    /**
     * 4. Login Brute-Force Shield - Semak status sekatan log masuk sebelum memproses login
     */
    public static function checkLoginThrottle($maxAttempts = 5, $lockoutMinutes = 15) {
        self::ensureDirectories();
        $ip = self::getClientIp();
        $file = self::$loginThrottleDir . '/' . md5($ip) . '.json';
        if (!file_exists($file)) return;

        $data = json_decode(@file_get_contents($file), true);
        if (!is_array($data)) return;

        $now = time();
        $attempts = $data['attempts'] ?? [];
        $lockoutWindow = $lockoutMinutes * 60;

        $validAttempts = [];
        foreach ($attempts as $timestamp) {
            if ($now - $timestamp <= $lockoutWindow) {
                $validAttempts[] = $timestamp;
            }
        }

        if (count($validAttempts) >= $maxAttempts) {
            $remainingSeconds = $lockoutWindow - ($now - end($validAttempts));
            if ($remainingSeconds < 0) $remainingSeconds = 60;
            $remainingMinutes = ceil($remainingSeconds / 60);

            self::logAttack('LOGIN_BRUTE_FORCE_BLOCKED', "IP blocked due to exceeding {$maxAttempts} failed login attempts. Remaining lockout time: {$remainingMinutes} minute(s).");
            
            if (class_exists('Helper')) {
                Helper::json('error', "Too many failed login attempts ({$maxAttempts}/{$maxAttempts}). Your IP address (" . htmlspecialchars($ip) . ") is temporarily locked for {$remainingMinutes} minute(s).");
            } else {
                self::blockRequest(429, "Account Lockout & Brute-Force Shield", "Too many failed login attempts. Your IP is temporarily locked for {$remainingMinutes} minute(s).");
            }
        }
    }

    /**
     * Rekod cubaan log masuk yang gagal bagi IP masa kini
     */
    public static function recordLoginFailure($email = '', $maxAttempts = 5, $lockoutMinutes = 15) {
        self::ensureDirectories();
        $ip = self::getClientIp();
        $file = self::$loginThrottleDir . '/' . md5($ip) . '.json';
        $now = time();
        $attempts = [];

        if (file_exists($file)) {
            $data = json_decode(@file_get_contents($file), true);
            if (is_array($data) && isset($data['attempts'])) {
                $attempts = $data['attempts'];
            }
        }

        $lockoutWindow = $lockoutMinutes * 60;
        $validAttempts = [];
        foreach ($attempts as $ts) {
            if ($now - $ts <= $lockoutWindow) {
                $validAttempts[] = $ts;
            }
        }

        $validAttempts[] = $now;
        $count = count($validAttempts);

        @file_put_contents($file, json_encode(['attempts' => $validAttempts]), LOCK_EX);
        @chmod($file, 0640);

        self::logAttack('LOGIN_FAILED_ATTEMPT', "Failed login attempt #{$count} for email: " . ($email ? htmlspecialchars($email) : 'Unknown') . " (IP: {$ip})");

        if ($count >= $maxAttempts) {
            self::logAttack('LOGIN_BRUTE_FORCE_BLOCKED', "IP {$ip} automatically locked for {$lockoutMinutes} minute(s) due to {$count} consecutive failed login attempts.");
        }

        return $count;
    }

    /**
     * Kosongkan rekod cubaan gagal apabila pengguna berjaya log masuk
     */
    public static function clearLoginThrottle() {
        self::ensureDirectories();
        $ip = self::getClientIp();
        $file = self::$loginThrottleDir . '/' . md5($ip) . '.json';
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /** Atomically reserve one public-inquiry submission slot for this IP. */
    public static function consumeInquirySlot(int $maxSubmissions = 5, int $windowSeconds = 3600): bool {
        self::ensureDirectories();
        return self::consumeRateLimitSlot(self::$inquiryThrottleDir, $maxSubmissions, $windowSeconds);
    }

    private static function consumeRateLimitSlot(string $directory, int $maximum, int $windowSeconds): bool {
        $file = $directory . '/' . hash('sha256', self::getClientIp()) . '.json';
        $handle = @fopen($file, 'c+');
        if (!$handle || !flock($handle, LOCK_EX)) {
            if ($handle) fclose($handle);
            return false;
        }

        try {
            rewind($handle);
            $decoded = json_decode((string)stream_get_contents($handle), true);
            $now = time();
            $attempts = array_values(array_filter(
                is_array($decoded) ? $decoded : [],
                static fn($timestamp) => is_int($timestamp) && $timestamp >= $now - $windowSeconds
            ));
            if (count($attempts) >= max(1, $maximum)) return false;
            $attempts[] = $now;
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($attempts));
            fflush($handle);
            @chmod($file, 0640);
            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
?>

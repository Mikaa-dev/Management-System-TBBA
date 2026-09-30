<?php
/**
 * Fail Utiliti & Fungsi Bantuan (Helper Functions)
 * Syarikat: The Bridge Business Alliance (TBBA)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Helper {
    // Membersihkan input dari XSS & aksara berbahaya
    public static function clean($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::clean($value);
            }
            return $data;
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }

    // Menghantar respons JSON untuk interaksi AJAX
    public static function json($status, $message = '', $data = []) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status'  => $status,  // 'success' atau 'error'
            'message' => $message,
            'data'    => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    // Pengalihan halaman (Redirect)
    public static function redirect($url) {
        header("Location: " . self::url($url));
        exit;
    }

    // Penjanaan Base URL dinamik
    // Baca dari APP_URL dalam .env jika ada, fallback ke auto-detect
    public static function url($path = '') {
        // Cuba baca APP_URL dari environment (set dalam .env)
        $appUrl = $_ENV['APP_URL'] ?? getenv('APP_URL') ?? '';

        // The PHP development server can run on any port and does not use the
        // Apache subfolder from APP_URL. Prefer the live request in that mode.
        if (!empty($appUrl) && PHP_SAPI !== 'cli-server') {
            // Guna APP_URL dari .env — ini paling reliable untuk semua environment
            return rtrim($appUrl, '/') . '/' . ltrim($path, '/');
        }

        // Fallback: auto-detect (untuk hosting yang tak set APP_URL)
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Auto-detect base path dari SCRIPT_NAME
        // Cth: /Bridge/index.php → base = /Bridge
        //      /index.php        → base = (kosong)
        $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
        $base = $protocol . $host . $scriptDir;
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    // Format mata wang Ringgit Malaysia (RM)
    public static function rm($amount) {
        return 'RM ' . number_format((float)$amount, 2, '.', ',');
    }

    // Format tarikh Bahasa / Standard
    public static function date($dateString, $format = 'd M Y, h:i A') {
        if (empty($dateString)) return '-';
        $dt = new DateTime($dateString);
        return $dt->format($format);
    }

    // Penjanaan Nombor Rujukan Surat Korporat (cth: TBBA/IN/2026/07/001)
    public static function generateLetterRef($type = 'IN', $sequence = 1) {
        $year = date('Y');
        $month = date('m');
        $seqFormatted = str_pad($sequence, 3, '0', STR_PAD_LEFT);
        return sprintf("TBBA/%s/%s/%s/%s", strtoupper($type), $year, $month, $seqFormatted);
    }

    // Penjanaan Token CSRF untuk keselamatan Borang AJAX
    public static function csrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    // Pengesahan Token CSRF
    public static function verifyCsrf($token) {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /** Validate upload status, size, extension and server-detected MIME type. */
    public static function validateUpload(array $file, array $allowedMimeMap, int $maxBytes): string {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('The uploaded file could not be processed.');
        }
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            throw new InvalidArgumentException('The uploaded file is empty or exceeds the allowed size.');
        }

        $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension === '' || !isset($allowedMimeMap[$extension])) {
            throw new InvalidArgumentException('The uploaded file format is not allowed.');
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        $mime = is_file($tmpName) ? (mime_content_type($tmpName) ?: '') : '';
        if ($mime === '' || !in_array($mime, $allowedMimeMap[$extension], true)) {
            throw new InvalidArgumentException('The file contents do not match the selected file format.');
        }
        return $extension;
    }
}
?>

<?php
/**
 * Signature Model — TBBA ERP E-Sign Module
 * Menguruskan semua operasi signature dalam database.
 */
require_once __DIR__ . '/../config/database.php';

class Signature {

    // ─── Schema Auto-Heal ─────────────────────────────────────────────────────
    private static bool $schemaChecked = false;

    /** Verify migrations without mutating schema during a request. */
    public static function ensureSchema(): void {
        if (self::$schemaChecked) return;
        try {
            Database::query("SELECT document_type, document_id, user_id, signer_role, signature_url, signature_hash, signed_at, ip_address, user_agent FROM signatures LIMIT 0");
            self::$schemaChecked = true;
        } catch (Throwable $e) {
            throw new RuntimeException('Signature schema is unavailable. Run the database migrations.', 0, $e);
        }
    }

    // ─── Core CRUD ────────────────────────────────────────────────────────────

    /**
     * Simpan signature baru dalam database.
     * Guna INSERT ... ON DUPLICATE KEY UPDATE untuk allow re-sign (replace lama).
     *
     * @param string $documentType  Jenis dokumen ('leave_request', 'expense_claim', dll.)
     * @param int    $documentId    ID dokumen
     * @param int    $userId        ID user yang sign
     * @param string $signerRole    'applicant' atau 'approver'
     * @param string $signatureUrl  Path relatif ke PNG file
     * @param string $signatureHash SHA-256 hash untuk integrity check
     * @param string $ip            IP address penanda tangan
     * @param string $userAgent     Browser/device info
     * @return int                  ID record yang baru/dikemaskini
     */
    public static function save(
        string $documentType,
        int    $documentId,
        int    $userId,
        string $signerRole,
        string $signatureUrl,
        string $signatureHash,
        string $ip = '',
        string $userAgent = ''
    ): int {
        self::ensureSchema();

        $existing = Database::query(
            "SELECT signature_url FROM signatures WHERE document_type=? AND document_id=? AND user_id=? AND signer_role=? LIMIT 1",
            [$documentType, $documentId, $userId, $signerRole]
        )->fetch();

        // INSERT atau replace jika sudah ada (user sign semula)
        Database::query(
            "INSERT INTO `signatures`
                (`document_type`, `document_id`, `user_id`, `signer_role`,
                 `signature_url`, `signature_hash`, `ip_address`, `user_agent`, `signed_at`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                `signature_url`  = VALUES(`signature_url`),
                `signature_hash` = VALUES(`signature_hash`),
                `ip_address`     = VALUES(`ip_address`),
                `user_agent`     = VALUES(`user_agent`),
                `signed_at`      = NOW()",
            [$documentType, $documentId, $userId, $signerRole,
             $signatureUrl, $signatureHash, $ip ?: null, $userAgent ?: null]
        );

        $oldPath = str_replace('\\', '/', ltrim((string)($existing['signature_url'] ?? ''), '/'));
        $newPath = str_replace('\\', '/', ltrim($signatureUrl, '/'));
        if ($oldPath !== '' && $oldPath !== $newPath && !str_contains($oldPath, '..')) {
            $absolute = realpath(__DIR__ . '/../' . $oldPath);
            $roots = [realpath(__DIR__ . '/../storage/private/signatures'), realpath(__DIR__ . '/../uploads/signatures')];
            foreach (array_filter($roots) as $root) {
                if ($absolute && is_file($absolute) && str_starts_with($absolute, $root . DIRECTORY_SEPARATOR)) {
                    @unlink($absolute);
                    break;
                }
            }
        }

        return (int) Database::lastInsertId();
    }

    /**
     * Dapatkan semua signatures untuk sebuah dokumen.
     * Include nama user untuk papar dalam UI.
     *
     * @param string $documentType
     * @param int    $documentId
     * @return array  Array of signature records dengan user info
     */
    public static function getByDocument(string $documentType, int $documentId): array {
        self::ensureSchema();
        try {
            return Database::query(
                "SELECT s.*, u.name AS user_name, u.position, u.role AS user_role
                 FROM `signatures` s
                 JOIN `users` u ON u.id = s.user_id
                 WHERE s.document_type = ? AND s.document_id = ?
                 ORDER BY s.signed_at ASC",
                [$documentType, $documentId]
            )->fetchAll();
        } catch (Exception $e) {
            error_log('[Signature] getByDocument error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Dapatkan satu signature spesifik (berdasarkan dokumen + user + role).
     *
     * @param string $documentType
     * @param int    $documentId
     * @param int    $userId
     * @param string $signerRole
     * @return array|null
     */
    public static function findOne(
        string $documentType,
        int    $documentId,
        int    $userId,
        string $signerRole
    ): ?array {
        self::ensureSchema();
        try {
            $r = Database::query(
                "SELECT s.*, u.name AS user_name, u.position
                 FROM `signatures` s
                 JOIN `users` u ON u.id = s.user_id
                 WHERE s.document_type = ? AND s.document_id = ?
                   AND s.user_id = ? AND s.signer_role = ?
                 LIMIT 1",
                [$documentType, $documentId, $userId, $signerRole]
            )->fetch();
            return $r ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Semak jika dokumen sudah mempunyai signature dari role tertentu.
     *
     * @param string $documentType
     * @param int    $documentId
     * @param string $signerRole  'applicant', 'approver', atau null untuk semak mana-mana
     * @return bool
     */
    public static function hasSigned(
        string  $documentType,
        int     $documentId,
        string  $signerRole = ''
    ): bool {
        self::ensureSchema();
        try {
            $sql = "SELECT COUNT(*) FROM `signatures`
                    WHERE `document_type` = ? AND `document_id` = ?";
            $params = [$documentType, $documentId];
            if ($signerRole) {
                $sql .= " AND `signer_role` = ?";
                $params[] = $signerRole;
            }
            return (int) Database::query($sql, $params)->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Verify integriti signature — bandingkan hash dalam DB dengan hash fail PNG sebenar.
     * Jika hash tidak sama, bermakna fail telah diubah (tampered).
     *
     * @param string $documentType
     * @param int    $documentId
     * @param int    $userId
     * @param string $signerRole
     * @return array ['valid' => bool, 'reason' => string]
     */
    public static function verify(
        string $documentType,
        int    $documentId,
        int    $userId,
        string $signerRole
    ): array {
        $record = self::findOne($documentType, $documentId, $userId, $signerRole);

        if (!$record) {
            return ['valid' => false, 'reason' => 'Signature record not found.'];
        }

        // Bina path absolut ke fail PNG
        $filePath = __DIR__ . '/../' . $record['signature_url'];
        if (!file_exists($filePath)) {
            return ['valid' => false, 'reason' => 'Signature file not found in storage.'];
        }

        // Kira semula hash fail sebenar
        $actualHash = hash_file('sha256', $filePath);

        if ($actualHash !== $record['signature_hash']) {
            return [
                'valid'  => false,
                'reason' => 'Warning: The signature has been modified. The hash does not match.',
                'stored' => $record['signature_hash'],
                'actual' => $actualHash,
            ];
        }

        return [
            'valid'    => true,
            'reason'   => 'The signature is valid and has not been modified.',
            'signed_at'=> $record['signed_at'],
            'signer'   => $record['user_name'],
        ];
    }

    /**
     * Dapatkan semua signatures oleh seorang user (history).
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public static function getByUser(int $userId, int $limit = 50): array {
        self::ensureSchema();
        try {
            return Database::query(
                "SELECT * FROM `signatures`
                 WHERE `user_id` = ?
                 ORDER BY `signed_at` DESC
                 LIMIT " . intval($limit),
                [$userId]
            )->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Dapatkan status signature untuk sesebuah dokumen (untuk papar dalam UI).
     * Return summary berapa signature dah ada vs diperlukan.
     *
     * @param string $documentType
     * @param int    $documentId
     * @return array
     */
    public static function getStatus(string $documentType, int $documentId): array {
        $signatures = self::getByDocument($documentType, $documentId);

        $status = [
            'applicant_signed' => false,
            'approver_signed'  => false,
            'fully_signed'     => false,
            'signatures'       => $signatures,
            'count'            => count($signatures),
        ];

        foreach ($signatures as $sig) {
            if ($sig['signer_role'] === 'applicant') $status['applicant_signed'] = true;
            if ($sig['signer_role'] === 'approver')  $status['approver_signed']  = true;
        }

        $status['fully_signed'] = $status['applicant_signed'] && $status['approver_signed'];

        return $status;
    }
}
?>

<?php
/**
 * Remember Me Token Management
 * Company: The Bridge Business Alliance (TBBA)
 */
require_once __DIR__ . '/../config/database.php';

class RememberToken
{
    /**
     * Generate a new persistent token for a user and save it to the database.
     * Returns the raw plaintext token to be stored in the user's cookie.
     */
    public static function create(int $userId, int $daysValid = 90): string
    {
        // 1. Generate 32 bytes of secure randomness
        $rawToken = bin2hex(random_bytes(32));
        
        // 2. Hash it before storing in the database
        $hashedToken = hash('sha256', $rawToken);
        
        // 3. Set expiration
        $expiresAt = date('Y-m-d H:i:s', time() + ($daysValid * 86400));
        
        // 4. Get User Agent
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 500);
        
        // 5. Store in database
        Database::query(
            "INSERT INTO `user_remember_tokens` (`user_id`, `token_hash`, `expires_at`, `user_agent`) VALUES (?, ?, ?, ?)",
            [$userId, $hashedToken, $expiresAt, $userAgent]
        );
        
        return $rawToken;
    }

    /**
     * Validate a plaintext token from a cookie.
     * Returns the user ID if valid, or null if invalid/expired.
     */
    public static function validate(string $rawToken): ?int
    {
        $hashedToken = hash('sha256', $rawToken);
        
        // Delete expired tokens globally to keep table clean
        self::cleanup();
        
        $row = Database::query(
            "SELECT `user_id` FROM `user_remember_tokens` WHERE `token_hash` = ? AND `expires_at` > NOW() LIMIT 1",
            [$hashedToken]
        )->fetch();
        
        if ($row) {
            return (int)$row['user_id'];
        }
        
        return null;
    }

    /**
     * Revoke a specific token from the database.
     */
    public static function revoke(string $rawToken): void
    {
        $hashedToken = hash('sha256', $rawToken);
        Database::query("DELETE FROM `user_remember_tokens` WHERE `token_hash` = ?", [$hashedToken]);
    }

    /**
     * Revoke all tokens for a specific user (e.g. on password change).
     */
    public static function revokeAllForUser(int $userId): void
    {
        Database::query("DELETE FROM `user_remember_tokens` WHERE `user_id` = ?", [$userId]);
    }

    /**
     * Remove expired tokens.
     */
    public static function cleanup(): void
    {
        Database::query("DELETE FROM `user_remember_tokens` WHERE `expires_at` < NOW()");
    }
}

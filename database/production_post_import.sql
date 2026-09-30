-- TBBA ERP: run once after importing the existing local database into hosting.
-- This preserves business/audit data while invalidating browser-bound secrets
-- that should not remain active after a domain/server migration.

START TRANSACTION;

UPDATE `user_sessions`
SET `revoked_at` = COALESCE(`revoked_at`, NOW());

UPDATE `trusted_devices`
SET `revoked_at` = COALESCE(`revoked_at`, NOW());

UPDATE `password_reset_tokens`
SET `used_at` = COALESCE(`used_at`, NOW());

UPDATE `notification_tokens`
SET `is_active` = 0;

INSERT INTO `security_settings` (`setting_key`, `setting_value`, `updated_by`)
VALUES
    ('password_min_length', '12', NULL),
    ('password_expiry_days', '90', NULL),
    ('session_lifetime_days', '30', NULL),
    ('require_2fa_admin', '1', NULL),
    ('login_alerts', '1', NULL),
    ('backup_retention_days', '30', NULL)
ON DUPLICATE KEY UPDATE
    `setting_value` = VALUES(`setting_value`),
    `updated_by` = NULL;

COMMIT;


-- ============================================================
--  TBBA ERP — FCM Push Notification Database Migration
--  Jalankan SQL ini dalam MySQL untuk create jadual baru.
--
--  Cara jalankan:
--    1. Buka phpMyAdmin → database: tbba_erp → tab SQL
--    2. Paste keseluruhan SQL ini dan klik "Go"
--  ATAU via command line:
--    mysql -u root tbba_erp < database/push_notification_migration.sql
-- ============================================================

USE `tbba_erp`;

-- ─── Table 1: notification_tokens ────────────────────────────────────────────
-- Simpan FCM registration tokens per user per device.
-- Setiap user boleh ada multiple tokens (laptop, phone, tablet, dll.)
CREATE TABLE IF NOT EXISTS `notification_tokens` (
    `id`           INT(11) NOT NULL AUTO_INCREMENT,
    `user_id`      INT(11) NOT NULL                  COMMENT 'FK kepada users.id',
    `fcm_token`    VARCHAR(512) NOT NULL              COMMENT 'FCM registration token dari browser',
    `device_info`  VARCHAR(255) DEFAULT NULL          COMMENT 'Info browser dan OS (cth: Chrome on Windows)',
    `platform`     ENUM('web','android','ios') DEFAULT 'web' COMMENT 'Platform device',
    `is_active`    TINYINT(1) DEFAULT 1              COMMENT '1=aktif, 0=tidak aktif (soft delete)',
    `last_used_at` DATETIME DEFAULT NULL             COMMENT 'Terakhir token digunakan',
    `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Tarikh token pertama kali didaftarkan',

    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_fcm_token` (`fcm_token`(191)),   -- Partial index untuk VARCHAR panjang
    KEY `idx_user_id`     (`user_id`),
    KEY `idx_is_active`   (`is_active`),
    KEY `idx_last_used`   (`last_used_at`),

    CONSTRAINT `fk_notif_token_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FCM Push Notification tokens per user per device';


-- ─── Table 2: push_notification_logs ─────────────────────────────────────────
-- Log semua push notification yang dihantar.
-- Berguna untuk:
--   - Audit trail (siapa hantar apa kepada siapa)
--   - Notification Center dalam app (history push)
--   - Analytics (berapa berjaya vs gagal)
CREATE TABLE IF NOT EXISTS `push_notification_logs` (
    `id`             INT(11) NOT NULL AUTO_INCREMENT,
    `user_id`        INT(11) NOT NULL                  COMMENT 'FK kepada users.id (penerima)',
    `title`          VARCHAR(255) NOT NULL             COMMENT 'Tajuk notification',
    `body`           TEXT NOT NULL                     COMMENT 'Isi/body notification',
    `link`           VARCHAR(512) DEFAULT NULL         COMMENT 'URL destinasi bila notification diklik',
    `type`           VARCHAR(100) DEFAULT 'general'    COMMENT 'Jenis event (leave_approved, expense_approved, dll.)',
    `status`         ENUM('sent','failed','partial') DEFAULT 'sent'
                                                       COMMENT 'sent=semua berjaya, partial=sebahagian, failed=semua gagal',
    `tokens_sent`    INT DEFAULT 0                     COMMENT 'Bilangan token yang berjaya dihantar',
    `tokens_failed`  INT DEFAULT 0                     COMMENT 'Bilangan token yang gagal / expired',
    `sent_at`        DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Masa notification dihantar',

    PRIMARY KEY (`id`),
    KEY `idx_push_user_id` (`user_id`),
    KEY `idx_push_type`    (`type`),
    KEY `idx_push_sent_at` (`sent_at`),
    KEY `idx_push_status`  (`status`),

    CONSTRAINT `fk_push_log_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Log audit semua FCM push notification yang dihantar';


-- ─── Semak: papar semua jadual dalam DB selepas migration ────────────────────
SHOW TABLES LIKE '%notification%';

-- ─── Semak: papar structure kedua-dua jadual baru ────────────────────────────
DESCRIBE `notification_tokens`;
DESCRIBE `push_notification_logs`;

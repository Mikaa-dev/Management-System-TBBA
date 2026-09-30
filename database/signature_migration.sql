-- ============================================================
--  TBBA ERP — Digital Signature (E-Sign) Database Migration
--  Jalankan dalam phpMyAdmin: database tbba_erp → SQL tab
-- ============================================================

USE `tbba_erp`;

-- ─── Table: signatures ────────────────────────────────────────────────────────
-- Simpan setiap signature event dengan full audit trail
CREATE TABLE IF NOT EXISTS `signatures` (
    `id`             INT(11) NOT NULL AUTO_INCREMENT,
    `document_type`  VARCHAR(50) NOT NULL          COMMENT 'leave_request / expense_claim / dll.',
    `document_id`    INT(11) NOT NULL              COMMENT 'ID rekod dalam jadual berkenaan',
    `user_id`        INT(11) NOT NULL              COMMENT 'User yang membuat signature',
    `signer_role`    ENUM('applicant','approver') NOT NULL DEFAULT 'approver'
                                                   COMMENT 'applicant=staff sign, approver=manager sign',
    `signature_url`  VARCHAR(255) NOT NULL         COMMENT 'Path relatif ke PNG file (uploads/signatures/...)',
    `signature_hash` VARCHAR(64) NOT NULL          COMMENT 'SHA-256 hash PNG file untuk integrity check',
    `signed_at`      DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Masa tepat signature dibuat',
    `ip_address`     VARCHAR(45) DEFAULT NULL      COMMENT 'IP address penanda tangan',
    `user_agent`     VARCHAR(500) DEFAULT NULL     COMMENT 'Browser/device info',

    PRIMARY KEY (`id`),
    -- Setiap user hanya boleh ada SATU signature per role per dokumen
    UNIQUE KEY `uniq_sig` (`document_type`, `document_id`, `user_id`, `signer_role`),
    KEY `idx_doc`    (`document_type`, `document_id`),
    KEY `idx_user`   (`user_id`),
    KEY `idx_signed` (`signed_at`),

    CONSTRAINT `fk_sig_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='E-Sign digital signatures audit trail';


-- ─── Alter: leave_requests ────────────────────────────────────────────────────
-- Tambah column untuk path PDF yang sudah digenerate dengan signatures
ALTER TABLE `leave_requests`
    ADD COLUMN `signed_pdf_path` VARCHAR(255) NULL
        COMMENT 'Path ke PDF rasmi yang telah diembedkan kedua-dua signatures'
        AFTER `rejection_reason`;


-- ─── Alter: expense_claims ────────────────────────────────────────────────────
-- Tambah column yang sama untuk expense claims
ALTER TABLE `expense_claims`
    ADD COLUMN `signed_pdf_path` VARCHAR(255) NULL
        COMMENT 'Path ke PDF rasmi yang telah diembedkan kedua-dua signatures'
        AFTER `notes`;


-- ─── Semak hasil ─────────────────────────────────────────────────────────────
DESCRIBE `signatures`;
SHOW COLUMNS FROM `leave_requests` LIKE 'signed_pdf_path';
SHOW COLUMNS FROM `expense_claims` LIKE 'signed_pdf_path';

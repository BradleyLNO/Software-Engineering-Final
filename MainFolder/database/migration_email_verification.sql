-- ─── Migration: Email Verification & Password Reset ───────────────────────────
-- Run this script once against your `schooldb` database.
-- It adds the 5 new columns needed for both features to both user tables.

-- ── university_users ──────────────────────────────────────────────────────────
ALTER TABLE `university_users`
    ADD COLUMN `email_verified`                TINYINT(1)   NOT NULL DEFAULT 0          AFTER `role`,
    ADD COLUMN `verification_token`            VARCHAR(64)  NULL     DEFAULT NULL        AFTER `email_verified`,
    ADD COLUMN `verification_token_expires_at` DATETIME     NULL     DEFAULT NULL        AFTER `verification_token`,
    ADD COLUMN `reset_token`                   VARCHAR(64)  NULL     DEFAULT NULL        AFTER `verification_token_expires_at`,
    ADD COLUMN `reset_token_expires_at`        DATETIME     NULL     DEFAULT NULL        AFTER `reset_token`,
    ADD INDEX `idx_university_verify_token` (`verification_token`),
    ADD INDEX `idx_university_reset_token`  (`reset_token`);

-- ── security_personnel ────────────────────────────────────────────────────────
ALTER TABLE `security_personnel`
    ADD COLUMN `email_verified`                TINYINT(1)   NOT NULL DEFAULT 0          AFTER `duty_status`,
    ADD COLUMN `verification_token`            VARCHAR(64)  NULL     DEFAULT NULL        AFTER `email_verified`,
    ADD COLUMN `verification_token_expires_at` DATETIME     NULL     DEFAULT NULL        AFTER `verification_token`,
    ADD COLUMN `reset_token`                   VARCHAR(64)  NULL     DEFAULT NULL        AFTER `verification_token_expires_at`,
    ADD COLUMN `reset_token_expires_at`        DATETIME     NULL     DEFAULT NULL        AFTER `reset_token`,
    ADD INDEX `idx_security_verify_token` (`verification_token`),
    ADD INDEX `idx_security_reset_token`  (`reset_token`);

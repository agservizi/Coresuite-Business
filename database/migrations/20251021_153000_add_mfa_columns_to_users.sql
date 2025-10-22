ALTER TABLE users
    ADD COLUMN mfa_secret VARCHAR(128) NULL AFTER password,
    ADD COLUMN mfa_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER mfa_secret,
    ADD COLUMN mfa_recovery_codes TEXT NULL AFTER mfa_enabled,
    ADD COLUMN mfa_enabled_at DATETIME NULL AFTER mfa_recovery_codes;

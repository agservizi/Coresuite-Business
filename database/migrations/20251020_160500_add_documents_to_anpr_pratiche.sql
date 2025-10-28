ALTER TABLE anpr_pratiche
    ADD COLUMN delega_path VARCHAR(255) NULL AFTER certificato_caricato_at,
    ADD COLUMN delega_hash CHAR(64) NULL AFTER delega_path,
    ADD COLUMN delega_caricato_at DATETIME NULL AFTER delega_hash,
    ADD COLUMN documento_path VARCHAR(255) NULL AFTER delega_caricato_at,
    ADD COLUMN documento_hash CHAR(64) NULL AFTER documento_path,
    ADD COLUMN documento_caricato_at DATETIME NULL AFTER documento_hash;
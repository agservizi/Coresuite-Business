ALTER TABLE anpr_pratiche
    ADD COLUMN spid_verificato_at DATETIME NULL AFTER documento_caricato_at,
    ADD COLUMN spid_operatore_id INT UNSIGNED NULL AFTER spid_verificato_at,
    ADD COLUMN certificato_inviato_at DATETIME NULL AFTER spid_operatore_id,
    ADD COLUMN certificato_inviato_via ENUM('email','pec') NULL AFTER certificato_inviato_at,
    ADD COLUMN certificato_inviato_destinatario VARCHAR(190) NULL AFTER certificato_inviato_via,
    ADD CONSTRAINT fk_anpr_spid_operatore
        FOREIGN KEY (spid_operatore_id) REFERENCES users(id) ON DELETE SET NULL;
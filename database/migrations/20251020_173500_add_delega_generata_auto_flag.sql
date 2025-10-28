ALTER TABLE anpr_pratiche
    ADD COLUMN delega_generata_auto TINYINT(1) NOT NULL DEFAULT 0 AFTER delega_caricato_at;

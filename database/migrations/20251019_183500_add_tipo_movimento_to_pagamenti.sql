ALTER TABLE pagamenti
    ADD COLUMN IF NOT EXISTS tipo_movimento ENUM('Entrata','Uscita') NOT NULL DEFAULT 'Entrata' AFTER cliente_id,
    ADD INDEX IF NOT EXISTS idx_pagamenti_tipo (tipo_movimento);

UPDATE pagamenti
SET tipo_movimento = 'Entrata'
WHERE tipo_movimento IS NULL OR tipo_movimento = '';

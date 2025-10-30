-- Schema Coresuite Business
-- Generato il 2025-10-19

-- Cleanup tabelle legacy pagoPA rimosse dal progetto
DROP TABLE IF EXISTS pagopa_avvisi;
DROP TABLE IF EXISTS pagopa_avvisi_eventi;
DROP TABLE IF EXISTS pagopa_bollettini;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    email VARCHAR(160) NOT NULL UNIQUE,
    nome VARCHAR(80) NOT NULL DEFAULT '',
    cognome VARCHAR(80) NOT NULL DEFAULT '',
    password VARCHAR(255) NOT NULL,
    mfa_secret VARCHAR(128) NULL,
    mfa_enabled TINYINT(1) NOT NULL DEFAULT 0,
    mfa_recovery_codes TEXT NULL,
    mfa_enabled_at DATETIME NULL,
    ruolo ENUM('Admin','Manager','Operatore','Cliente') NOT NULL DEFAULT 'Operatore',
    theme_preference ENUM('dark','light') NOT NULL DEFAULT 'dark',
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configurazioni (
    chiave VARCHAR(120) PRIMARY KEY,
    valore TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS log_attivita (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    modulo VARCHAR(120) NOT NULL,
    azione VARCHAR(160) NOT NULL,
    dettagli TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_modulo (modulo),
    INDEX idx_log_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clienti (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ragione_sociale VARCHAR(160) NOT NULL DEFAULT '',
    nome VARCHAR(80) NOT NULL,
    cognome VARCHAR(80) NOT NULL,
    cf_piva VARCHAR(32) NULL,
    email VARCHAR(160) NULL,
    telefono VARCHAR(40) NULL,
    indirizzo VARCHAR(255) NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_clienti_ragione (ragione_sociale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entrate_uscite (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NULL,
    tipo_movimento ENUM('Entrata','Uscita') NOT NULL DEFAULT 'Entrata',
    descrizione VARCHAR(180) NOT NULL,
    riferimento VARCHAR(80) NULL,
    metodo VARCHAR(60) NOT NULL DEFAULT 'Bonifico',
    stato VARCHAR(40) NOT NULL DEFAULT 'In lavorazione',
    importo DECIMAL(10,2) NOT NULL DEFAULT 0,
    data_scadenza DATE NULL,
    data_pagamento DATE NULL,
    note TEXT NULL,
    allegato_path VARCHAR(255) NULL,
    allegato_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_entrate_uscite_cliente (cliente_id),
    INDEX idx_entrate_uscite_stato (stato),
    INDEX idx_entrate_uscite_scadenza (data_scadenza),
    INDEX idx_entrate_uscite_pagamento (data_pagamento),
    INDEX idx_entrate_uscite_cliente_stato (cliente_id, stato),
    INDEX idx_entrate_uscite_tipo (tipo_movimento),
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS servizi_appuntamenti (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    titolo VARCHAR(160) NOT NULL,
    tipo_servizio VARCHAR(80) NOT NULL,
    responsabile VARCHAR(120) NULL,
    luogo VARCHAR(160) NULL,
    stato VARCHAR(40) NOT NULL DEFAULT 'Programmato',
    data_inizio DATETIME NOT NULL,
    data_fine DATETIME NULL,
    reminder_sent_at DATETIME NULL,
    google_event_id VARCHAR(128) NULL,
    google_event_synced_at DATETIME NULL,
    google_event_sync_error TEXT NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_appuntamenti_cliente (cliente_id),
    INDEX idx_appuntamenti_stato (stato),
    INDEX idx_appuntamenti_responsabile (responsabile),
    INDEX idx_appuntamenti_inizio (data_inizio),
    INDEX idx_appuntamenti_reminder_sent (reminder_sent_at),
    INDEX idx_appuntamenti_google_event (google_event_id),
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS daily_financial_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_date DATE NOT NULL UNIQUE,
    total_entrate DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_uscite DECIMAL(12,2) NOT NULL DEFAULT 0,
    saldo DECIMAL(12,2) NOT NULL DEFAULT 0,
    file_path VARCHAR(255) NOT NULL,
    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_daily_reports_date (report_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS servizi_digitali (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    tipo VARCHAR(60) NOT NULL,
    stato VARCHAR(40) NOT NULL,
    note TEXT NULL,
    documento_path VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_digitali_cliente (cliente_id),
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS curriculum (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    titolo VARCHAR(150) NOT NULL,
    professional_summary TEXT NULL,
    key_competences TEXT NULL,
    digital_competences TEXT NULL,
    driving_license VARCHAR(120) NULL,
    additional_information TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Bozza',
    last_generated_at DATETIME NULL,
    generated_file VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_curriculum_cliente (cliente_id),
    INDEX idx_curriculum_status (status),
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS curriculum_experiences (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curriculum_id INT UNSIGNED NOT NULL,
    role_title VARCHAR(160) NOT NULL,
    employer VARCHAR(160) NOT NULL,
    city VARCHAR(120) NULL,
    country VARCHAR(120) NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    description TEXT NULL,
    ordering SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (curriculum_id) REFERENCES curriculum(id) ON DELETE CASCADE,
    INDEX idx_curriculum_experiences_curriculum (curriculum_id),
    INDEX idx_curriculum_experiences_order (curriculum_id, ordering)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS curriculum_education (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curriculum_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    institution VARCHAR(180) NOT NULL,
    city VARCHAR(120) NULL,
    country VARCHAR(120) NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    qualification_level VARCHAR(120) NULL,
    description TEXT NULL,
    ordering SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (curriculum_id) REFERENCES curriculum(id) ON DELETE CASCADE,
    INDEX idx_curriculum_education_curriculum (curriculum_id),
    INDEX idx_curriculum_education_order (curriculum_id, ordering)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS curriculum_languages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curriculum_id INT UNSIGNED NOT NULL,
    language VARCHAR(120) NOT NULL,
    overall_level VARCHAR(60) NOT NULL,
    listening VARCHAR(60) NULL,
    reading VARCHAR(60) NULL,
    interaction VARCHAR(60) NULL,
    production VARCHAR(60) NULL,
    writing VARCHAR(60) NULL,
    certification VARCHAR(160) NULL,
    FOREIGN KEY (curriculum_id) REFERENCES curriculum(id) ON DELETE CASCADE,
    INDEX idx_curriculum_languages_curriculum (curriculum_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS curriculum_skills (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curriculum_id INT UNSIGNED NOT NULL,
    category VARCHAR(80) NOT NULL,
    skill VARCHAR(160) NOT NULL,
    level VARCHAR(60) NULL,
    description TEXT NULL,
    ordering SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (curriculum_id) REFERENCES curriculum(id) ON DELETE CASCADE,
    INDEX idx_curriculum_skills_curriculum (curriculum_id),
    INDEX idx_curriculum_skills_order (curriculum_id, ordering)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spedizioni (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    tipo_spedizione VARCHAR(80) NOT NULL,
    mittente VARCHAR(160) NOT NULL,
    destinatario VARCHAR(160) NOT NULL,
    tracking_number VARCHAR(120) NULL,
    stato VARCHAR(40) NOT NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_spedizioni_cliente (cliente_id),
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NULL,
    titolo VARCHAR(180) NOT NULL,
    descrizione TEXT NOT NULL,
    stato VARCHAR(40) NOT NULL DEFAULT 'Aperto',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ticket_cliente (cliente_id),
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_messaggi (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    utente_id INT UNSIGNED NOT NULL,
    messaggio TEXT NOT NULL,
    allegato_path VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ticket_messaggi_ticket (ticket_id),
    FOREIGN KEY (ticket_id) REFERENCES ticket(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titolo VARCHAR(200) NOT NULL,
    descrizione TEXT NULL,
    cliente_id INT UNSIGNED NULL,
    modulo VARCHAR(80) NOT NULL DEFAULT 'Altro',
    stato VARCHAR(40) NOT NULL DEFAULT 'Bozza',
    owner_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_documents_cliente (cliente_id),
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE SET NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_versions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id INT UNSIGNED NOT NULL,
    versione INT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_document_version (document_id, versione),
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_tag_map (
    document_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (document_id, tag_id),
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES document_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Utente amministratore di default
INSERT INTO users (username, email, password, ruolo)
SELECT 'admin', 'admin@example.com', '$2y$12$2xHnRJMh1zsmC1WmvMRGcuE9zraFMvx6bMpiKFFitvolG/GpNZgb2', 'Admin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');

-- Configurazioni di base
INSERT INTO configurazioni (chiave, valore) VALUES
    ('ragione_sociale', 'Coresuite Business SRL'),
    ('indirizzo', 'Via Plinio 72, Milano'),
    ('telefono', '+39 02 1234567'),
    ('email', 'info@coresuitebusiness.com')
ON DUPLICATE KEY UPDATE valore = VALUES(valore);

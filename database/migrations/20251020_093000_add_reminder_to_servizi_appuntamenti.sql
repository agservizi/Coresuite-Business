ALTER TABLE servizi_appuntamenti
    ADD COLUMN reminder_sent_at DATETIME NULL AFTER data_fine,
    ADD INDEX idx_appuntamenti_reminder_sent (reminder_sent_at);

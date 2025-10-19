# Sicurezza & Compliance

## Migliorie Introdotte
- Matrice ruoli/capabilità centralizzata (`App\Auth\Authorization`) e helper `require_capability`.
- Audit tentativi login (`login_audit`) con log IP/User Agent e note.
- Password login minimo 8 caratteri e normalizzazione IP.
- Logger di sicurezza (`App\Security\SecurityAuditLogger`) riutilizzabile.
- Cifratura opzionale backup tramite variabili `.env` (`BACKUP_ENCRYPTION_KEY`, `BACKUP_ENCRYPTION_CIPHER`).

## Prossime Azioni
- Implementare 2FA (TOTP) usando `user_security` table (da definire) con enrollment e recovery codes.
- Monitorare `login_audit` con dashboard/admin view e alert per anomalie (es. troppi fallimenti da IP).
- Integrare rate limiting per IP + username e captcha progressivo.
- Aggiungere scansione antivirus (ClamAV) per file caricati (`SettingsService` e moduli documentali).
- Aggiornare privacy policy e data retention per log.

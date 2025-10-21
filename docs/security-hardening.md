# Sicurezza & Compliance

## Migliorie Introdotte
- Matrice ruoli/capabilità centralizzata (`App\Auth\Authorization`) e helper `require_capability`.
- Audit tentativi login (`login_audit`) con log IP/User Agent e note.
- Password login minimo 8 caratteri e normalizzazione IP.
- Logger di sicurezza (`App\Security\SecurityAuditLogger`) riutilizzabile.
- Cifratura opzionale backup tramite variabili `.env` (`BACKUP_ENCRYPTION_KEY`, `BACKUP_ENCRYPTION_CIPHER`).
- Supporto Single Sign-On OpenID Connect (flusso Authorization Code) con fallback login locale.

### Configurazione SSO (`.env`)

```
OIDC_ENABLED=true
OIDC_ISSUER=https://login.microsoftonline.com/<tenant>/v2.0
OIDC_CLIENT_ID=<client-id>
OIDC_CLIENT_SECRET=<client-secret>
OIDC_REDIRECT_URI=https://business.coresuite.it/sso/callback.php
OIDC_SCOPES="openid profile email"
OIDC_PROVIDER_NAME=Azure AD
OIDC_END_SESSION_ENDPOINT=https://login.microsoftonline.com/<tenant>/oauth2/v2.0/logout
OIDC_POST_LOGOUT_REDIRECT_URI=https://business.coresuite.it/index.php
OIDC_VERIFY_HOST=true
OIDC_VERIFY_PEER=true
# opzionali
# OIDC_PROMPT=login
# OIDC_MAX_AGE=3600
```

- L'utente SSO viene associato per `email` oppure `preferred_username` a un record esistente in `users` (nessun provisioning automatico).
- I token `id_token`/`access_token` vengono salvati in sessione per consentire logout federato (`logout.php`).
- Tutti i tentativi (successo/fallimento) sono tracciati su `login_audit` con nota `sso`/`sso_failed`.

## Prossime Azioni
- Implementare 2FA (TOTP) usando `user_security` table (da definire) con enrollment e recovery codes.
- Monitorare `login_audit` con dashboard/admin view e alert per anomalie (es. troppi fallimenti da IP).
- Integrare rate limiting per IP + username e captcha progressivo.
- Aggiungere scansione antivirus (ClamAV) per file caricati (`SettingsService` e moduli documentali).
- Aggiornare privacy policy e data retention per log.

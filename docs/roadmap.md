# Roadmap Modulare Coresuite Business

Data: 19/10/2025

## Obiettivi principali
- Consolidare l'architettura con servizi riutilizzabili e autoload centralizzato.
- Migliorare sicurezza e governance (permessi, auditing, backup).
- Abilitare API e automazioni per integrazioni terze.
- Potenziare UX con componenti coerenti e workflow configurabili.

## Milestone

### 1. Rifattorizzazione Architetturale
- Introdurre autoload PSR-4 e struttura `app/` per servizi, repository, support.
- Creare servizi di dominio (es. `SettingsService`, `ClientService`).
- Mappare dipendenze comuni in un bootstrap condiviso.

### 2. Sicurezza & Compliance
- Matrix permessi ruoli/capabilities, logging centralizzato.
- Hardened auth (2FA opzionale, policy password, lock IP-aware).
- Validazione upload con antivirus e firma file.

### 3. Dati & Automazioni
- Formalizzare schema con migrazioni versionate.
- Automatizzare backup (manuale e schedulato) con cifratura.
- Esporre API REST e documentarle (OpenAPI), rate limiting.

### 4. UX & Productivity
- Componenti layout riusabili (Blade/Plates o partial migliorati).
- Workflow engine per servizi con SLA, reminder, notifiche.
- Dashboard personalizzabile e aggiornamenti realtime.

### 5. Qualità & DevOps
- Suite test (unit, feature, end-to-end) e analisi statica.
- Pipeline CI/CD con linting, test, deploy staging.
- Documentazione runbook, manuali e linee guida coding.

## Sequenza Fasi
1. Implementare bootstrap/autoload e primo servizio.
2. Spostare logica impostazioni nel servizio, coprire test di base.
3. Definire struttura migrazioni e backup cifrati.
4. Configurare pipeline CI e guidelines.
5. Estendere approach agli altri moduli (clienti, servizi, ticket).

## Rischi & Mitigazioni
- **Compatibilità legacy**: procedere iterativamente e mantenere fallback.
- **Permessi filesystem**: verificare path upload/backup prima del deploy.
- **Dipendenze esterne**: documentare requisiti (Composer, PHP 8+).

## KPI Iniziali
- Copertura test > 60% nelle aree refactorate entro Q1 2026.
- Tempo medio deploy < 10 minuti dopo pipeline CI.
- Riduzione errori runtime impostazioni del 80% grazie al servizio dedicato.

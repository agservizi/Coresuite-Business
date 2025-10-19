# Strategia Test Coresuite Business

## Obiettivi
- Coprire i servizi critici (impostazioni, autenticazione, ticket) con test automatici.
- Integrare test end-to-end per i flussi amministrativi principali.
- Eseguire i test nella pipeline CI su ogni merge request.

## Livelli
- **Unit**: classi in `app/` (es. `SettingsService`) con PHPUnit e mock di PDO.
- **Feature/API**: endpoint in `api/` usando un mini bootstrap per richieste HTTP simulate.
- **End-to-End**: scenari UI con Playwright/Cypress contro ambiente di staging.

## Roadmap Test
1. Configurare PHPUnit (`phpunit.xml.dist`) con bootstrap minino (`tests/bootstrap.php`).
2. Scrivere test unitari per `SettingsService` coprendo validazione, upload, backup cifrato (mock filesystem con vfsStream).
3. Creare suite feature per `api/dashboard.php` con risposte JSON attese.
4. Introdurre smoke test CLI (`tools/migrate.php`, generazione backup) usando container MySQL dedicato.
5. Automatizzare report coverage in CI (phpunit `--coverage-clover`).

## Strumenti Suggeriti
- PHPUnit ^10
- Mockery o Prophecy (facoltativo)
- vfsStream per filesystem
- Playwright + `@playwright/test` per E2E

## Metriche
- Copertura unit > 60% su `app/` entro Q1 2026.
- Test feature per tutti gli endpoint critici entro Q2 2026.
- E2E per login, creazione ticket, generazione backup entro Q3 2026.

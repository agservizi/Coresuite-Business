# Linee Guida di Coding

## Principi
- Preferire servizi e repository in `app/` con dipendenze esplicite.
- Mantenere le pagine PHP sottili: solo orchestrazione, nessuna logica SQL diretta.
- Validare sempre input utente con metodi dedicati e array normalizzati.

## Stile
- PSR-12 come riferimento base.
- Strict types nei nuovi file (`declare(strict_types=1);`) dove possibile.
- Commenti solo per blocchi non immediati; evitare commenti ridondanti.

## Sicurezza
- Tutti i form devono usare CSRF token (`csrf_token`, `require_valid_csrf`).
- Sanificare output con `sanitize_output`/`htmlspecialchars`.
- Gestire upload via servizi con whitelist MIME ed eliminazione sicura dei file.

## Versionamento
- Ogni nuova funzionalità deve includere test o note tecniche in `docs/`.
- Commit coerenti: `feat:`, `fix:`, `refactor:`, `docs:` prefix consigliati.
- Evitare commit misti (logica + formattazione).

## Revisione
- Code review incentrata su rischio (sicurezza, regressioni, performance).
- Richiedere evidenza test (screenshot o log CI) prima del merge.
- Documentare decisioni architetturali in `docs/adr/` (creare quando necessario).

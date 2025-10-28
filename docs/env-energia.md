# Configurazione ambiente per il modulo Energia

Queste variabili consentono di personalizzare il comportamento del modulo "Contratti energia". Tutte le opzioni sono facoltative: in assenza di impostazione vengono applicati i valori indicati di seguito.

```
# Indirizzo email che riceve notifiche e reminder
ENERGIA_NOTIFICATION_EMAIL=energia@newprojectmobile.it

# Abilita o disabilita l'invio automatico dei reminder (true/false)
ENERGIA_REMINDERS_ENABLED=true

# Ore lavorative da attendere prima dell'invio automatico del reminder
ENERGIA_REMINDER_HOURS=24

# Numero massimo di reminder inviati per singola esecuzione del bootstrap
ENERGIA_REMINDER_BATCH_LIMIT=5
```

> Nota: i reminder automatici vengono valutati durante il bootstrap dell'applicazione; non è necessario configurare un cron esterno. Vengono conteggiate solo ore lavorative (lun–ven), saltando i weekend.

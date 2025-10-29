<?php

declare(strict_types=1);

return [
    [
        'slug' => 'prenotazione-cie',
        'title' => "Prenotazione Carta d'Identità Elettronica (CIE)",
        'description' => 'Accedi al portale ufficiale del Ministero dell\'Interno per prenotare un appuntamento presso lo sportello anagrafico e richiedere la carta d\'identità elettronica.',
        'cta_label' => 'Apri portale prenotazione CIE',
        'cta_url' => 'https://www.prenotazionicie.interno.gov.it/cittadino/n/sc/wizardAppuntamentoCittadino/sceltaComune',
        'icon' => 'fa-solid fa-id-card',
        'tags' => ['Identità', 'Comune', 'Ministero dell\'Interno'],
        'notes' => [
            'Autenticazione richiesta tramite SPID, CIE o CNS.',
            'Servizio disponibile solo per i Comuni aderenti al progetto CIE.',
        ],
    ],
];

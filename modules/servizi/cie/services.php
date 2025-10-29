<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/functions.php';

require_role('Admin', 'Operatore', 'Manager');
$pageTitle = 'Catalogo Servizi CIE';

$stats = cie_fetch_stats($pdo);
$statuses = cie_status_map();

$servicePackages = [
    [
        'name' => 'Richiesta CIE Standard',
        'code' => 'CIE-S1',
        'icon' => 'fa-id-card',
        'description' => 'Gestione completa della richiesta di Carta d\'Identità Elettronica con appuntamento nel comune di appartenenza.',
        'target' => 'Cittadini con documenti in scadenza o deteriorati.',
        'includes' => [
            'Verifica documentazione e requisiti',
            'Prenotazione appuntamento presso l\'ufficio anagrafe',
            'Monitoraggio avanzamento pratica',
            'Assistenza post-appuntamento fino alla consegna',
        ],
        'sla' => 'Completamento entro 30 giorni lavorativi dalla richiesta',
    ],
    [
        'name' => 'Richiesta CIE Urgente',
        'code' => 'CIE-U2',
        'icon' => 'fa-bolt',
        'description' => 'Corsia preferenziale per casi di reale urgenza con prenotazione in disponibilità straordinaria o fuori comune.',
        'target' => 'Cittadini con comprovate esigenze urgenti (viaggi, motivi di salute, lavoro).',
        'includes' => [
            'Analisi prioritaria della pratica',
            'Ricerca disponibilità in comuni limitrofi',
            'Affiancamento nella gestione dei documenti giustificativi',
            'Aggiornamenti al cliente in tempo reale',
        ],
        'sla' => 'Completamento entro 10 giorni lavorativi dalla richiesta',
    ],
    [
        'name' => 'Supporto Post-Appuntamento',
        'code' => 'CIE-A3',
        'icon' => 'fa-life-ring',
        'description' => 'Assistenza dedicata successiva all\'appuntamento per eventuali integrazioni o problematiche.',
        'target' => 'Pratiche con appuntamento assegnato che richiedono ulteriore supporto.',
        'includes' => [
            'Verifica esito e documentazione prodotta',
            'Gestione eventuali integrazioni richieste dal comune',
            'Supporto nel ritiro del documento',
            'Report conclusivo per il cliente',
        ],
        'sla' => 'Supporto attivo fino alla consegna della carta',
    ],
];

$operationalSteps = [
    [
        'title' => 'Raccolta dati',
        'description' => 'Compilazione scheda cittadino e caricamento dei documenti previsti dalle regole CIE.',
        'icon' => 'fa-clipboard-list',
    ],
    [
        'title' => 'Prenotazione',
        'description' => 'Ricerca disponibilità presso il comune selezionato e invio conferma al cittadino.',
        'icon' => 'fa-calendar-check',
    ],
    [
        'title' => 'Appuntamento',
        'description' => 'Preparazione del cittadino e verifica dei documenti prima della presentazione allo sportello.',
        'icon' => 'fa-user-check',
    ],
    [
        'title' => 'Follow-up',
        'description' => 'Monitoraggio stato pratica e gestione delle comunicazioni fino al rilascio del documento.',
        'icon' => 'fa-envelope-open-text',
    ],
];

$faqs = [
    [
        'question' => 'Quali documenti servono per avviare la pratica?',
        'answer' => 'Documento di identità valido o scaduto da non oltre 6 mesi, codice fiscale, stato civile aggiornato e due fotografie fronte/mezzo busto formato tessera.',
    ],
    [
        'question' => 'Quanto tempo serve per ottenere l\'appuntamento?',
        'answer' => 'Dipende dal carico del comune. In media 15-20 giorni per la richiesta standard e 3-5 giorni per la richiesta urgente con disponibilità straordinaria.',
    ],
    [
        'question' => 'È possibile richiedere il servizio per un minorenne?',
        'answer' => 'Sì, con presenza dei genitori o di chi esercita la responsabilità genitoriale e con firma del modulo di assenso.',
    ],
    [
        'question' => 'Come viene notificato il cliente?',
        'answer' => 'Ogni fase genera una notifica via email e SMS. Le comunicazioni sono tracciate nello storico della prenotazione.',
    ],
];

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/../../../includes/topbar.php'; ?>
    <main class="content-wrapper">
        <div class="page-toolbar mb-4 d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h1 class="h3 mb-1">Catalogo Servizi CIE</h1>
                <p class="text-muted mb-0">Panoramica delle soluzioni disponibili per la gestione delle carte d'identità elettroniche.</p>
            </div>
            <div class="toolbar-actions d-flex gap-2">
                <a class="btn btn-outline-warning" href="index.php"><i class="fa-solid fa-rotate me-2"></i>Torna alla gestione pratiche</a>
                <a class="btn btn-warning text-dark" href="create.php"><i class="fa-solid fa-circle-plus me-2"></i>Nuova richiesta</a>
            </div>
        </div>

        <section class="row g-3 mb-4">
            <div class="col-12 col-lg-3">
                <div class="card ag-card h-100">
                    <div class="card-body">
                        <p class="text-muted mb-1">Richieste totali</p>
                        <h3 class="fw-bold mb-0"><?php echo (int) ($stats['total'] ?? 0); ?></h3>
                        <p class="small text-muted mb-0">Somma delle pratiche gestite nel portale CIE.</p>
                    </div>
                </div>
            </div>
            <?php foreach ($statuses as $key => $config): ?>
                <div class="col-6 col-lg-2">
                    <div class="card ag-card h-100">
                        <div class="card-body">
                            <p class="text-muted mb-1"><?php echo sanitize_output($config['label']); ?></p>
                            <h4 class="fw-bold mb-0"><?php echo (int) ($stats['by_status'][$key] ?? 0); ?></h4>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="row g-4 mb-5">
            <?php foreach ($servicePackages as $package): ?>
                <div class="col-12 col-lg-4">
                    <div class="card ag-card h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <span class="badge bg-warning text-dark">Codice <?php echo sanitize_output($package['code']); ?></span>
                                <i class="fa-solid <?php echo sanitize_output($package['icon']); ?> fa-2x text-warning"></i>
                            </div>
                            <h3 class="h5 fw-semibold mb-2"><?php echo sanitize_output($package['name']); ?></h3>
                            <p class="text-muted mb-3 flex-grow-1"><?php echo sanitize_output($package['description']); ?></p>
                            <p class="small text-muted mb-2"><strong>Ideale per:</strong> <?php echo sanitize_output($package['target']); ?></p>
                            <ul class="list-unstyled mb-3">
                                <?php foreach ($package['includes'] as $item): ?>
                                    <li class="d-flex align-items-start gap-2 mb-2">
                                        <i class="fa-solid fa-check text-warning mt-1"></i>
                                        <span><?php echo sanitize_output($item); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="mt-auto">
                                <div class="small text-muted mb-3"><i class="fa-solid fa-hourglass-half me-2"></i><?php echo sanitize_output($package['sla']); ?></div>
                                <a class="btn btn-warning text-dark w-100" href="create.php?package=<?php echo urlencode($package['code']); ?>">Avvia pratica</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="card ag-card mb-5">
            <div class="card-body">
                <div class="row g-4 align-items-center">
                    <div class="col-12 col-lg-4">
                        <h2 class="h4 fw-semibold mb-3">Workflow operativo</h2>
                        <p class="text-muted mb-0">Ogni pratica segue un flusso controllato con log attività e notifiche automatiche al cittadino.</p>
                    </div>
                    <div class="col-12 col-lg-8">
                        <div class="row g-3">
                            <?php foreach ($operationalSteps as $step): ?>
                                <div class="col-12 col-sm-6">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="d-flex align-items-center gap-3 mb-2">
                                            <span class="badge bg-warning text-dark"><i class="fa-solid <?php echo sanitize_output($step['icon']); ?>"></i></span>
                                            <h3 class="h6 mb-0 fw-semibold"><?php echo sanitize_output($step['title']); ?></h3>
                                        </div>
                                        <p class="text-muted small mb-0"><?php echo sanitize_output($step['description']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="card ag-card">
            <div class="card-body">
                <h2 class="h4 fw-semibold mb-4">Domande frequenti</h2>
                <div class="accordion" id="cieFaq">
                    <?php foreach ($faqs as $index => $faq): ?>
                        <div class="accordion-item bg-transparent border border-secondary rounded-3 mb-2">
                            <h3 class="accordion-header" id="heading<?php echo $index; ?>">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" aria-expanded="false" aria-controls="collapse<?php echo $index; ?>">
                                    <?php echo sanitize_output($faq['question']); ?>
                                </button>
                            </h3>
                            <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse" aria-labelledby="heading<?php echo $index; ?>" data-bs-parent="#cieFaq">
                                <div class="accordion-body text-muted">
                                    <?php echo sanitize_output($faq['answer']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </main>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
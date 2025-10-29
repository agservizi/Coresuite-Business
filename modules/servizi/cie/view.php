<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/functions.php';

require_role('Admin', 'Operatore', 'Manager');

$bookingId = (int) ($_GET['id'] ?? 0);
if ($bookingId <= 0) {
    add_flash('warning', 'Prenotazione CIE non valida.');
    header('Location: index.php');
    exit;
}

$booking = cie_fetch_booking($pdo, $bookingId);
if ($booking === null) {
    add_flash('warning', 'Prenotazione CIE non trovata.');
    header('Location: index.php');
    exit;
}

$pageTitle = 'Prenotazione CIE #' . $bookingId;
$flashes = get_flashes();
$bookingCode = cie_booking_code($booking);
$statusMap = cie_status_map();

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/../../../includes/topbar.php'; ?>
    <main class="content-wrapper">
        <div class="page-toolbar mb-4 d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h1 class="h3 mb-1">Prenotazione CIE #<?php echo (int) $booking['id']; ?></h1>
                <p class="text-muted mb-0">
                    Codice richiesta <strong><?php echo sanitize_output($bookingCode); ?></strong>
                    · Stato <span class="<?php echo sanitize_output(cie_status_badge((string) ($booking['stato'] ?? 'nuova'))); ?> ms-1"><?php echo sanitize_output(cie_status_label((string) ($booking['stato'] ?? 'nuova'))); ?></span>
                </p>
            </div>
            <div class="toolbar-actions d-flex gap-2">
                <a class="btn btn-outline-light" href="index.php"><i class="fa-solid fa-arrow-left me-2"></i>Indietro</a>
                <a class="btn btn-outline-warning" href="open_portal.php?id=<?php echo (int) $booking['id']; ?>" target="_blank" rel="noopener"><i class="fa-solid fa-up-right-from-square me-2"></i>Portale ministeriale</a>
                <a class="btn btn-warning text-dark" href="create.php"><i class="fa-solid fa-circle-plus me-2"></i>Nuova richiesta</a>
            </div>
        </div>

        <?php if ($flashes): ?>
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'warning'; ?>" role="alert">
                    <?php echo sanitize_output($flash['message']); ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card ag-card mb-4">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0">Dettagli prenotazione</h2>
                        <span class="badge bg-secondary text-uppercase">Overview</span>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Creato il</dt>
                            <dd class="col-sm-8"><?php echo sanitize_output(format_datetime_locale((string) ($booking['created_at'] ?? ''))); ?></dd>

                            <dt class="col-sm-4">Aggiornato il</dt>
                            <dd class="col-sm-8"><?php echo sanitize_output(format_datetime_locale((string) ($booking['updated_at'] ?? ''))); ?></dd>

                            <dt class="col-sm-4">Operatore</dt>
                            <dd class="col-sm-8">
                                <?php if (!empty($booking['created_by_username'])): ?>
                                    <span class="d-block">Creato da <?php echo sanitize_output((string) $booking['created_by_username']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($booking['updated_by_username'])): ?>
                                    <span class="d-block">Ultimo aggiornamento di <?php echo sanitize_output((string) $booking['updated_by_username']); ?></span>
                                <?php endif; ?>
                                <?php if (empty($booking['created_by_username']) && empty($booking['updated_by_username'])): ?>
                                    <span class="text-muted">N/D</span>
                                <?php endif; ?>
                            </dd>

                            <dt class="col-sm-4">Cliente ANPR</dt>
                            <dd class="col-sm-8">
                                <?php if (!empty($booking['cliente_id'])): ?>
                                    <?php echo sanitize_output(trim((string) ($booking['cliente_cognome'] ?? '') . ' ' . ($booking['cliente_nome'] ?? ''))); ?><br>
                                    <?php if (!empty($booking['cliente_cf'])): ?>
                                        <small class="text-muted">CF/P.IVA: <?php echo sanitize_output((string) $booking['cliente_cf']); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Nessun cliente collegato</span>
                                <?php endif; ?>
                            </dd>

                            <dt class="col-sm-4">Esito</dt>
                            <dd class="col-sm-8"><?php echo $booking['esito'] ? nl2br(sanitize_output((string) $booking['esito'])) : '<span class="text-muted">—</span>'; ?></dd>

                            <dt class="col-sm-4">Note interne</dt>
                            <dd class="col-sm-8"><?php echo $booking['note'] ? nl2br(sanitize_output((string) $booking['note'])) : '<span class="text-muted">Nessuna nota</span>'; ?></dd>
                        </dl>
                    </div>
                </div>

                <div class="card ag-card mb-4">
                    <div class="card-header bg-transparent border-0">
                        <h2 class="h5 mb-0">Dati del cittadino</h2>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Nome e cognome</dt>
                            <dd class="col-sm-8"><?php echo sanitize_output(trim((string) ($booking['cittadino_cognome'] ?? '') . ' ' . ($booking['cittadino_nome'] ?? ''))); ?></dd>

                            <dt class="col-sm-4">Codice fiscale</dt>
                            <dd class="col-sm-8"><?php echo $booking['cittadino_cf'] ? sanitize_output((string) $booking['cittadino_cf']) : '<span class="text-muted">—</span>'; ?></dd>

                            <dt class="col-sm-4">Email</dt>
                            <dd class="col-sm-8"><?php echo $booking['cittadino_email'] ? sanitize_output((string) $booking['cittadino_email']) : '<span class="text-muted">—</span>'; ?></dd>

                            <dt class="col-sm-4">Telefono</dt>
                            <dd class="col-sm-8"><?php echo $booking['cittadino_telefono'] ? sanitize_output((string) $booking['cittadino_telefono']) : '<span class="text-muted">—</span>'; ?></dd>

                            <dt class="col-sm-4">Data di nascita</dt>
                            <dd class="col-sm-8"><?php echo $booking['data_nascita'] ? sanitize_output(format_date_locale((string) $booking['data_nascita'])) : '<span class="text-muted">—</span>'; ?></dd>

                            <dt class="col-sm-4">Luogo di nascita</dt>
                            <dd class="col-sm-8"><?php echo $booking['luogo_nascita'] ? sanitize_output((string) $booking['luogo_nascita']) : '<span class="text-muted">—</span>'; ?></dd>

                            <dt class="col-sm-4">Residenza</dt>
                            <dd class="col-sm-8">
                                <?php
                                $residenceParts = array_filter([
                                    $booking['residenza_indirizzo'] ?? null,
                                    $booking['residenza_cap'] ?? null,
                                    $booking['residenza_citta'] ?? null,
                                    $booking['residenza_provincia'] ?? null,
                                ]);
                                ?>
                                <?php if ($residenceParts): ?>
                                    <?php echo sanitize_output(implode(', ', $residenceParts)); ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </dd>
                        </dl>
                    </div>
                </div>

                <div class="card ag-card">
                    <div class="card-header bg-transparent border-0">
                        <h2 class="h5 mb-0">Appuntamento</h2>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Comune richiesta</dt>
                            <dd class="col-sm-8"><?php echo sanitize_output((string) ($booking['comune_richiesta'] ?? '')); ?></dd>

                            <dt class="col-sm-4">Preferenza data</dt>
                            <dd class="col-sm-8"><?php echo $booking['disponibilita_data'] ? sanitize_output(format_date_locale((string) $booking['disponibilita_data'])) : '<span class="text-muted">—</span>'; ?></dd>

                            <dt class="col-sm-4">Fascia oraria</dt>
                            <dd class="col-sm-8"><?php echo $booking['disponibilita_fascia'] ? sanitize_output((string) $booking['disponibilita_fascia']) : '<span class="text-muted">—</span>'; ?></dd>

                            <dt class="col-sm-4">Data appuntamento</dt>
                            <dd class="col-sm-8"><?php echo $booking['appuntamento_data'] ? sanitize_output(format_date_locale((string) $booking['appuntamento_data'])) : '<span class="text-muted">In attesa</span>'; ?></dd>

                            <dt class="col-sm-4">Orario appuntamento</dt>
                            <dd class="col-sm-8"><?php echo $booking['appuntamento_orario'] ? sanitize_output((string) $booking['appuntamento_orario']) : '<span class="text-muted">—</span>'; ?></dd>

                            <dt class="col-sm-4">Numero prenotazione</dt>
                            <dd class="col-sm-8"><?php echo $booking['appuntamento_numero'] ? sanitize_output((string) $booking['appuntamento_numero']) : '<span class="text-muted">—</span>'; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card ag-card mb-4">
                    <div class="card-header bg-transparent border-0">
                        <h2 class="h5 mb-0">Documentazione</h2>
                    </div>
                    <div class="card-body">
                        <?php
                        $attachments = [
                            'Documento identità' => [
                                'path' => $booking['documento_identita_path'] ?? null,
                                'name' => $booking['documento_identita_nome'] ?? 'documento_identita',
                            ],
                            'Foto cittadino' => [
                                'path' => $booking['foto_cittadino_path'] ?? null,
                                'name' => $booking['foto_cittadino_nome'] ?? 'foto_cittadino',
                            ],
                            'Ricevuta portale' => [
                                'path' => $booking['ricevuta_path'] ?? null,
                                'name' => $booking['ricevuta_nome'] ?? 'ricevuta',
                            ],
                        ];
                        ?>
                        <?php $hasAttachment = false; ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($attachments as $label => $attachment): ?>
                                <?php if (!empty($attachment['path'])): ?>
                                    <?php $hasAttachment = true; ?>
                                    <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center">
                                        <div>
                                            <a class="text-warning text-decoration-none" href="<?php echo sanitize_output(base_url((string) $attachment['path'])); ?>" target="_blank" rel="noopener">
                                                <i class="fa-solid fa-paperclip me-2"></i><?php echo sanitize_output($label); ?>
                                            </a>
                                            <div class="small text-muted"><?php echo sanitize_output((string) $attachment['name']); ?></div>
                                        </div>
                                        <a class="btn btn-sm btn-outline-warning" href="<?php echo sanitize_output(base_url((string) $attachment['path'])); ?>" download>Scarica</a>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (!$hasAttachment): ?>
                            <p class="text-muted mb-0">Nessun documento caricato.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card ag-card">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0">Storico notifiche</h2>
                        <span class="badge bg-secondary text-uppercase">Log</span>
                    </div>
                    <div class="card-body">
                        <?php $notifications = $booking['notification_history'] ?? []; ?>
                        <?php if ($notifications): ?>
                            <?php
                            $channelLabels = [
                                'email' => 'Email conferma',
                                'email_reminder' => 'Email promemoria',
                                'whatsapp' => 'WhatsApp',
                                'sms' => 'SMS',
                            ];
                            ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Data invio</th>
                                            <th>Canale</th>
                                            <th>Oggetto</th>
                                            <th>Note</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($notifications as $notification): ?>
                                            <tr>
                                                <td><?php echo sanitize_output(format_datetime_locale((string) ($notification['sent_at'] ?? ''))); ?></td>
                                                <td><?php echo sanitize_output($channelLabels[$notification['channel']] ?? ucfirst((string) ($notification['channel'] ?? ''))); ?></td>
                                                <td><?php echo sanitize_output((string) ($notification['message_subject'] ?? '')); ?></td>
                                                <td><?php echo $notification['notes'] ? sanitize_output((string) $notification['notes']) : '<span class="text-muted">—</span>'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">Nessuna notifica registrata.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

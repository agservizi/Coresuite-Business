<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/mailer.php';
require_once __DIR__ . '/functions.php';

require_role('Admin', 'Operatore', 'Manager');

$contractId = (int) ($_GET['id'] ?? 0);
if ($contractId <= 0) {
    add_flash('warning', 'Contratto energia non valido.');
    header('Location: index.php');
    exit;
}

$contract = energia_fetch_contract($pdo, $contractId);
if ($contract === null) {
    add_flash('warning', 'Contratto energia non trovato.');
    header('Location: index.php');
    exit;
}

$pageTitle = 'Dettaglio contratto #' . $contractId;
$csrfToken = csrf_token();
$flashes = get_flashes();

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/../../../includes/topbar.php'; ?>
    <main class="content-wrapper">
        <div class="page-toolbar mb-4">
            <div>
                <h1 class="h3 mb-0">Contratto energia #<?php echo (int) $contract['id']; ?></h1>
                <p class="text-muted mb-0">Richiesta per <?php echo sanitize_output($contract['nominativo'] ?? ''); ?>.</p>
            </div>
            <div class="toolbar-actions d-flex gap-2">
                <a class="btn btn-outline-warning" href="index.php"><i class="fa-solid fa-arrow-left me-2"></i>Indietro</a>
                <form method="post" action="index.php" class="d-inline">
                    <input type="hidden" name="_token" value="<?php echo sanitize_output($csrfToken); ?>">
                    <input type="hidden" name="id" value="<?php echo (int) $contract['id']; ?>">
                    <input type="hidden" name="action" value="send_email">
                    <button class="btn btn-outline-warning" type="submit" <?php echo !empty($contract['email_sent_at']) ? 'disabled' : ''; ?>><i class="fa-solid fa-paper-plane me-2"></i>Invia email</button>
                </form>
                <form method="post" action="index.php" class="d-inline">
                    <input type="hidden" name="_token" value="<?php echo sanitize_output($csrfToken); ?>">
                    <input type="hidden" name="id" value="<?php echo (int) $contract['id']; ?>">
                    <input type="hidden" name="action" value="send_reminder">
                    <button class="btn btn-outline-warning" type="submit" <?php echo empty($contract['email_sent_at']) ? 'disabled' : ''; ?>><i class="fa-solid fa-bell me-2"></i>Invia reminder</button>
                </form>
            </div>
        </div>

        <?php if ($flashes): ?>
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'warning'; ?>"><?php echo sanitize_output($flash['message']); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card ag-card">
                    <div class="card-header bg-transparent border-0">
                        <h2 class="h5 mb-0">Dettagli richiesta</h2>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Nominativo</dt>
                            <dd class="col-sm-8"><?php echo sanitize_output($contract['nominativo'] ?? ''); ?></dd>

                            <dt class="col-sm-4">Codice contratto</dt>
                            <dd class="col-sm-8">
                                <?php if (!empty($contract['contract_code'])): ?>
                                    <?php echo sanitize_output($contract['contract_code']); ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </dd>

                            <dt class="col-sm-4">Codice fiscale</dt>
                            <dd class="col-sm-8"><?php echo sanitize_output($contract['codice_fiscale'] ?? '—'); ?></dd>

                            <dt class="col-sm-4">Email referente</dt>
                            <dd class="col-sm-8"><?php echo sanitize_output($contract['email'] ?? ''); ?></dd>

                            <dt class="col-sm-4">Telefono</dt>
                            <dd class="col-sm-8"><?php echo sanitize_output($contract['telefono'] ?? '—'); ?></dd>

                            <dt class="col-sm-4">Fornitura</dt>
                            <dd class="col-sm-8"><?php echo sanitize_output($contract['fornitura'] ?? ''); ?></dd>

                            <dt class="col-sm-4">Operazione</dt>
                            <dd class="col-sm-8"><?php echo sanitize_output($contract['operazione'] ?? ''); ?></dd>

                            <dt class="col-sm-4">Stato</dt>
                            <dd class="col-sm-8"><span class="badge ag-badge text-uppercase"><?php echo sanitize_output($contract['stato'] ?? ''); ?></span></dd>

                            <dt class="col-sm-4">Creato il</dt>
                            <dd class="col-sm-8"><?php echo sanitize_output(format_datetime_locale($contract['created_at'] ?? '')); ?></dd>

                            <dt class="col-sm-4">Creato da</dt>
                            <dd class="col-sm-8"><?php echo sanitize_output($contract['created_by_username'] ?? 'Sistema'); ?></dd>

                            <dt class="col-sm-4">Email inviata</dt>
                            <dd class="col-sm-8"><?php echo !empty($contract['email_sent_at']) ? sanitize_output(format_datetime_locale($contract['email_sent_at'])) : '<span class="text-muted">Non inviata</span>'; ?></dd>

                            <dt class="col-sm-4">Reminder</dt>
                            <dd class="col-sm-8"><?php echo !empty($contract['reminder_sent_at']) ? sanitize_output(format_datetime_locale($contract['reminder_sent_at'])) : '<span class="text-muted">Mai inviato</span>'; ?></dd>
                        </dl>
                        <div class="mt-3">
                            <h3 class="h6 text-uppercase text-muted">Note operative</h3>
                            <p class="mb-0"><?php echo $contract['note'] ? nl2br(sanitize_output($contract['note'])) : '<span class="text-muted">Nessuna nota</span>'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card ag-card">
                    <div class="card-header bg-transparent border-0">
                        <h2 class="h5 mb-0">Allegati</h2>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($contract['attachments'])): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($contract['attachments'] as $attachment): ?>
                                    <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center">
                                        <div>
                                            <a href="<?php echo sanitize_output(base_url($attachment['file_path'])); ?>" target="_blank" rel="noopener" class="text-warning text-decoration-none">
                                                <i class="fa-solid fa-paperclip me-2"></i><?php echo sanitize_output($attachment['file_name']); ?>
                                            </a>
                                            <div class="text-muted small"><?php echo sanitize_output(energia_format_bytes((int) $attachment['file_size'])); ?></div>
                                        </div>
                                        <a class="btn btn-sm btn-outline-warning" href="<?php echo sanitize_output(base_url($attachment['file_path'])); ?>" download>Scarica</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted mb-0">Nessun allegato disponibile.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card ag-card">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0">Storico email</h2>
                        <span class="badge bg-secondary text-uppercase">Log</span>
                    </div>
                    <div class="card-body">
                        <?php $emailHistory = $contract['email_history'] ?? []; ?>
                        <?php if ($emailHistory): ?>
                            <?php
                                $eventLabels = [
                                    'initial' => 'Invio iniziale',
                                    'reminder' => 'Reminder',
                                ];
                                $channelLabels = [
                                    'manual' => 'Manuale',
                                    'scheduler' => 'Scheduler',
                                ];
                            ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Data invio</th>
                                            <th>Evento</th>
                                            <th>Canale</th>
                                            <th>Destinatario</th>
                                            <th>Soggetto</th>
                                            <th>Esito</th>
                                            <th>Operatore</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($emailHistory as $entry): ?>
                                            <tr>
                                                <td><?php echo sanitize_output(format_datetime_locale($entry['sent_at'] ?? '')); ?></td>
                                                <td><?php echo sanitize_output($eventLabels[$entry['event_type']] ?? ucfirst((string) ($entry['event_type'] ?? ''))); ?></td>
                                                <td><?php echo sanitize_output($channelLabels[$entry['send_channel']] ?? ucfirst((string) ($entry['send_channel'] ?? ''))); ?></td>
                                                <td>
                                                    <span class="d-block"><?php echo sanitize_output($entry['recipient'] ?? ''); ?></span>
                                                    <?php if (!empty($entry['error_message']) && ($entry['status'] ?? '') === 'failed'): ?>
                                                        <span class="small text-muted">Errore: <?php echo sanitize_output($entry['error_message']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo sanitize_output($entry['subject'] ?? ''); ?></td>
                                                <td>
                                                    <?php if (($entry['status'] ?? '') === 'sent'): ?>
                                                        <span class="badge bg-success">Inviata</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Fallita</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($entry['sent_by_username'])): ?>
                                                        <span><?php echo sanitize_output($entry['sent_by_username']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Sistema</span>
                                                    <?php endif; ?>
                                                    <?php if (($entry['send_channel'] ?? '') === 'scheduler'): ?>
                                                        <div class="small text-muted">Automazione</div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">Nessuna email registrata finora.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

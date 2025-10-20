<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';

require_role('Admin', 'Operatore', 'Manager');
$pageTitle = 'Dettaglio appuntamento';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT sa.*, c.nome, c.cognome, c.email, c.telefono, c.indirizzo FROM servizi_appuntamenti sa LEFT JOIN clienti c ON sa.cliente_id = c.id WHERE sa.id = :id');
$stmt->execute([':id' => $id]);
$record = $stmt->fetch();
if (!$record) {
    header('Location: index.php?notfound=1');
    exit;
}

$reminderStatus = '<span class="text-muted">Non previsto</span>';
$reminderSchedule = null;
try {
    $isActive = in_array($record['stato'], ['Programmato', 'In corso'], true);
    $clientHasEmail = !empty($record['email']);
    if ($isActive && $clientHasEmail) {
        if (!empty($record['reminder_sent_at'])) {
            $sentAt = new DateTimeImmutable((string) $record['reminder_sent_at']);
            $reminderStatus = sprintf(
                '<span class="text-success">Inviato il %s</span>',
                sanitize_output($sentAt->format('d/m/Y H:i'))
            );
        } elseif (!empty($record['data_inizio'])) {
            $startAt = new DateTimeImmutable((string) $record['data_inizio']);
            $scheduledAt = $startAt->sub(new DateInterval('PT2H'));
            $reminderSchedule = $scheduledAt;
            $now = new DateTimeImmutable('now');

            if ($scheduledAt <= $now) {
                $reminderStatus = '<span class="text-warning">In attesa di invio alla prossima esecuzione</span>';
            } else {
                $reminderStatus = sprintf(
                    '<span class="text-muted">Programmato per il %s</span>',
                    sanitize_output($scheduledAt->format('d/m/Y H:i'))
                );
            }
        }
    } elseif ($isActive) {
        $reminderStatus = '<span class="text-danger">Email cliente mancante</span>';
    }
} catch (Throwable $exception) {
    $reminderStatus = '<span class="text-warning">Impossibile determinare lo stato del promemoria</span>';
}

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/../../../includes/topbar.php'; ?>
    <main class="content-wrapper">
        <div class="page-toolbar mb-4">
            <a class="btn btn-outline-warning" href="index.php"><i class="fa-solid fa-arrow-left"></i> Tutti gli appuntamenti</a>
            <div class="toolbar-actions">
                <a class="btn btn-warning text-dark" href="edit.php?id=<?php echo $id; ?>"><i class="fa-solid fa-pen"></i> Modifica</a>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card ag-card h-100">
                    <div class="card-header bg-transparent border-0">
                        <h5 class="card-title mb-0">Dettagli appuntamento</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5">ID</dt>
                            <dd class="col-sm-7">#<?php echo (int) $record['id']; ?></dd>
                            <dt class="col-sm-5">Titolo</dt>
                            <dd class="col-sm-7"><?php echo sanitize_output($record['titolo'] ?? ''); ?></dd>
                            <dt class="col-sm-5">Tipologia</dt>
                            <dd class="col-sm-7"><?php echo sanitize_output($record['tipo_servizio'] ?? ''); ?></dd>
                            <dt class="col-sm-5">Responsabile</dt>
                            <dd class="col-sm-7"><?php echo $record['responsabile'] ? sanitize_output($record['responsabile']) : '<span class="text-muted">N/D</span>'; ?></dd>
                            <dt class="col-sm-5">Luogo</dt>
                            <dd class="col-sm-7"><?php echo $record['luogo'] ? sanitize_output($record['luogo']) : '<span class="text-muted">N/D</span>'; ?></dd>
                            <dt class="col-sm-5">Inizio</dt>
                            <dd class="col-sm-7"><?php echo sanitize_output(format_datetime_locale($record['data_inizio'] ?? '')); ?></dd>
                            <dt class="col-sm-5">Fine</dt>
                            <dd class="col-sm-7"><?php echo $record['data_fine'] ? sanitize_output(format_datetime_locale($record['data_fine'])) : '<span class="text-muted">—</span>'; ?></dd>
                            <dt class="col-sm-5">Stato</dt>
                            <dd class="col-sm-7"><span class="badge ag-badge text-uppercase"><?php echo sanitize_output($record['stato']); ?></span></dd>
                            <dt class="col-sm-5">Promemoria email</dt>
                            <dd class="col-sm-7">
                                <?php echo $reminderStatus; ?>
                                <?php if ($reminderSchedule instanceof DateTimeImmutable && empty($record['reminder_sent_at'])): ?>
                                    <br><small class="text-muted">Invio automatico previsto due ore prima dell'appuntamento.</small>
                                <?php endif; ?>
                            </dd>
                            <?php if (!empty($record['note'])): ?>
                                <dt class="col-sm-5">Note</dt>
                                <dd class="col-sm-7"><?php echo nl2br(sanitize_output($record['note'])); ?></dd>
                            <?php endif; ?>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card ag-card h-100">
                    <div class="card-header bg-transparent border-0">
                        <h5 class="card-title mb-0">Cliente</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5">Nome</dt>
                            <dd class="col-sm-7"><?php echo sanitize_output(trim(($record['cognome'] ?? '') . ' ' . ($record['nome'] ?? '')) ?: 'N/D'); ?></dd>
                            <dt class="col-sm-5">Email</dt>
                            <dd class="col-sm-7"><?php echo $record['email'] ? '<a class="link-warning" href="mailto:' . sanitize_output($record['email']) . '">' . sanitize_output($record['email']) . '</a>' : '<span class="text-muted">N/D</span>'; ?></dd>
                            <dt class="col-sm-5">Telefono</dt>
                            <dd class="col-sm-7"><?php echo $record['telefono'] ? '<a class="link-warning" href="tel:' . sanitize_output($record['telefono']) . '">' . sanitize_output($record['telefono']) . '</a>' : '<span class="text-muted">N/D</span>'; ?></dd>
                            <dt class="col-sm-5">Indirizzo</dt>
                            <dd class="col-sm-7"><?php echo $record['indirizzo'] ? sanitize_output($record['indirizzo']) : '<span class="text-muted">N/D</span>'; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

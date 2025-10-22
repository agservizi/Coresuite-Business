<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/../../../includes/helpers.php';

require_role('Admin', 'Operatore', 'Manager');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    add_flash('warning', 'Curriculum non trovato.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT cv.*, c.nome, c.cognome, c.email, c.telefono, c.ragione_sociale
    FROM curriculum cv
    LEFT JOIN clienti c ON c.id = cv.cliente_id
    WHERE cv.id = :id');
$stmt->execute([':id' => $id]);
$curriculum = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$curriculum) {
    add_flash('warning', 'Curriculum non trovato.');
    header('Location: index.php');
    exit;
}

$sections = [];

$sections['experiences'] = $pdo->prepare('SELECT * FROM curriculum_experiences WHERE curriculum_id = :id ORDER BY ordering ASC, id ASC');
$sections['experiences']->execute([':id' => $id]);
$experiences = $sections['experiences']->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sections['education'] = $pdo->prepare('SELECT * FROM curriculum_education WHERE curriculum_id = :id ORDER BY ordering ASC, id ASC');
$sections['education']->execute([':id' => $id]);
$education = $sections['education']->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sections['languages'] = $pdo->prepare('SELECT * FROM curriculum_languages WHERE curriculum_id = :id ORDER BY language ASC, id ASC');
$sections['languages']->execute([':id' => $id]);
$languages = $sections['languages']->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sections['skills'] = $pdo->prepare('SELECT * FROM curriculum_skills WHERE curriculum_id = :id ORDER BY ordering ASC, id ASC');
$sections['skills']->execute([':id' => $id]);
$skills = $sections['skills']->fetchAll(PDO::FETCH_ASSOC) ?: [];

$pageTitle = 'Dettaglio curriculum';
$clientDisplay = trim(($curriculum['cognome'] ?? '') . ' ' . ($curriculum['nome'] ?? ''));
if ($clientDisplay === '') {
    $clientDisplay = $curriculum['ragione_sociale'] ?? 'Cliente #' . (int) $curriculum['cliente_id'];
}

$summaryBlocks = array_filter([
    $curriculum['key_competences'] ? 'Competenze chiave: ' . (string) $curriculum['key_competences'] : null,
    $curriculum['digital_competences'] ? 'Competenze digitali: ' . (string) $curriculum['digital_competences'] : null,
    $curriculum['driving_license'] ? 'Patente: ' . (string) $curriculum['driving_license'] : null,
    $curriculum['additional_information'] ? (string) $curriculum['additional_information'] : null,
]);

$csrfToken = csrf_token();

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<div class="flex-grow-1 d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/../../../includes/topbar.php'; ?>
    <main class="content-wrapper">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
            <div>
                <h1 class="h3 mb-1"><?php echo sanitize_output($curriculum['titolo'] ?? 'Curriculum'); ?></h1>
                <div class="text-muted">
                    <span class="me-3"><i class="fa-solid fa-user"></i> <?php echo sanitize_output($clientDisplay); ?></span>
                    <span class="me-3"><i class="fa-solid fa-clock"></i> Aggiornato il <?php echo sanitize_output(format_datetime_locale($curriculum['updated_at'] ?? '')); ?></span>
                    <span class="badge ag-badge text-uppercase <?php echo $curriculum['status'] === 'Pubblicato' ? 'bg-success' : ($curriculum['status'] === 'Archiviato' ? 'bg-secondary' : 'bg-warning text-dark'); ?>"><?php echo sanitize_output($curriculum['status']); ?></span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-warning" href="wizard.php?id=<?php echo (int) $id; ?>"><i class="fa-solid fa-pen me-2"></i>Modifica</a>
                <form method="post" action="publish.php" class="d-inline">
                    <input type="hidden" name="_token" value="<?php echo sanitize_output($csrfToken); ?>">
                    <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
                    <button class="btn btn-warning text-dark" type="submit"><i class="fa-solid fa-file-pdf me-2"></i>Genera PDF Europass</button>
                </form>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="card ag-card h-100">
                    <div class="card-header bg-transparent border-0">
                        <h2 class="h5 mb-0">Profilo professionale</h2>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($curriculum['professional_summary'])): ?>
                            <p class="mb-0"><?php echo nl2br(sanitize_output($curriculum['professional_summary'])); ?></p>
                        <?php else: ?>
                            <p class="text-muted mb-0">Profilo non ancora compilato.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card ag-card h-100">
                    <div class="card-header bg-transparent border-0">
                        <h2 class="h5 mb-0">Contatti</h2>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <?php if (!empty($curriculum['email'])): ?>
                                <li class="mb-2"><i class="fa-solid fa-envelope me-2"></i><a class="link-warning text-decoration-none" href="mailto:<?php echo sanitize_output($curriculum['email']); ?>"><?php echo sanitize_output($curriculum['email']); ?></a></li>
                            <?php endif; ?>
                            <?php if (!empty($curriculum['telefono'])): ?>
                                <li class="mb-2"><i class="fa-solid fa-phone me-2"></i><a class="link-warning text-decoration-none" href="tel:<?php echo sanitize_output($curriculum['telefono']); ?>"><?php echo sanitize_output($curriculum['telefono']); ?></a></li>
                            <?php endif; ?>
                            <?php if ($summaryBlocks): ?>
                                <li class="mt-3">
                                    <?php foreach ($summaryBlocks as $block): ?>
                                        <p class="small mb-2"><?php echo nl2br(sanitize_output($block)); ?></p>
                                    <?php endforeach; ?>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="card ag-card mb-4">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">Esperienza professionale</h2>
                <a class="btn btn-sm btn-outline-warning" href="wizard.php?id=<?php echo (int) $id; ?>#experiences-container"><i class="fa-solid fa-pen me-2"></i>Modifica</a>
            </div>
            <div class="card-body">
                <?php if ($experiences): ?>
                    <div class="timeline">
                        <?php foreach ($experiences as $experience): ?>
                            <div class="timeline-item mb-4">
                                <h3 class="h6 mb-1"><?php echo sanitize_output(trim(($experience['role_title'] ?? '') . ' — ' . ($experience['employer'] ?? ''))); ?></h3>
                                <div class="text-muted small mb-2">
                                    <?php
                                    $start = format_date_locale($experience['start_date'] ?? null);
                                    $end = $experience['is_current'] ? 'Presente' : format_date_locale($experience['end_date'] ?? null);
                                    $period = trim(($start ?: '') . ' - ' . ($end ?: ''));
                                    ?>
                                    <span class="me-2"><i class="fa-solid fa-calendar"></i> <?php echo sanitize_output($period ?: 'Periodo non indicato'); ?></span>
                                    <?php if (!empty($experience['city']) || !empty($experience['country'])): ?>
                                        <span><i class="fa-solid fa-location-dot"></i> <?php echo sanitize_output(trim(($experience['city'] ?? '') . ' ' . ($experience['country'] ?? ''))); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($experience['description'])): ?>
                                    <p class="mb-0"><?php echo nl2br(sanitize_output($experience['description'])); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Nessuna esperienza registrata.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card ag-card mb-4">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">Istruzione e formazione</h2>
                <a class="btn btn-sm btn-outline-warning" href="wizard.php?id=<?php echo (int) $id; ?>#education-container"><i class="fa-solid fa-pen me-2"></i>Modifica</a>
            </div>
            <div class="card-body">
                <?php if ($education): ?>
                    <div class="timeline">
                        <?php foreach ($education as $item): ?>
                            <div class="timeline-item mb-4">
                                <h3 class="h6 mb-1"><?php echo sanitize_output(trim(($item['title'] ?? '') . ' — ' . ($item['institution'] ?? ''))); ?></h3>
                                <div class="text-muted small mb-2">
                                    <?php
                                    $start = format_date_locale($item['start_date'] ?? null);
                                    $end = format_date_locale($item['end_date'] ?? null);
                                    $period = trim(($start ?: '') . ' - ' . ($end ?: ''));
                                    ?>
                                    <span class="me-2"><i class="fa-solid fa-calendar"></i> <?php echo sanitize_output($period ?: 'Periodo non indicato'); ?></span>
                                    <?php if (!empty($item['city']) || !empty($item['country'])): ?>
                                        <span><i class="fa-solid fa-location-dot"></i> <?php echo sanitize_output(trim(($item['city'] ?? '') . ' ' . ($item['country'] ?? ''))); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($item['qualification_level'])): ?>
                                    <p class="small text-muted mb-1">Livello: <?php echo sanitize_output($item['qualification_level']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($item['description'])): ?>
                                    <p class="mb-0"><?php echo nl2br(sanitize_output($item['description'])); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Nessun percorso formativo registrato.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card ag-card mb-4">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">Competenze linguistiche</h2>
                <a class="btn btn-sm btn-outline-warning" href="wizard.php?id=<?php echo (int) $id; ?>#languages-container"><i class="fa-solid fa-pen me-2"></i>Modifica</a>
            </div>
            <div class="card-body">
                <?php if ($languages): ?>
                    <div class="row g-3">
                        <?php foreach ($languages as $language): ?>
                            <div class="col-md-6">
                                <div class="border border-warning-subtle rounded-3 p-3 h-100">
                                    <h3 class="h6 mb-2"><i class="fa-solid fa-language me-2"></i><?php echo sanitize_output($language['language']); ?></h3>
                                    <?php if (!empty($language['overall_level'])): ?>
                                        <p class="small text-muted mb-2">Livello complessivo: <?php echo sanitize_output($language['overall_level']); ?></p>
                                    <?php endif; ?>
                                    <ul class="list-unstyled small mb-2">
                                        <?php
                                        $skillsMap = [
                                            'listening' => 'Ascolto',
                                            'reading' => 'Lettura',
                                            'interaction' => 'Interazione',
                                            'production' => 'Produzione',
                                            'writing' => 'Scrittura',
                                        ];
                                        foreach ($skillsMap as $key => $label):
                                            if (empty($language[$key])) {
                                                continue;
                                            }
                                            ?>
                                            <li><strong><?php echo $label; ?>:</strong> <?php echo sanitize_output($language[$key]); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php if (!empty($language['certification'])): ?>
                                        <p class="small mb-0">Certificazione: <?php echo sanitize_output($language['certification']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Competenze linguistiche non inserite.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card ag-card mb-5">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">Competenze personali</h2>
                <a class="btn btn-sm btn-outline-warning" href="wizard.php?id=<?php echo (int) $id; ?>#skills-container"><i class="fa-solid fa-pen me-2"></i>Modifica</a>
            </div>
            <div class="card-body">
                <?php if ($skills): ?>
                    <div class="row g-3">
                        <?php foreach ($skills as $skill): ?>
                            <div class="col-md-6">
                                <div class="border border-warning-subtle rounded-3 p-3 h-100">
                                    <h3 class="h6 mb-2"><?php echo sanitize_output($skill['category']); ?></h3>
                                    <p class="fw-semibold mb-2"><?php echo sanitize_output($skill['skill']); ?></p>
                                    <?php if (!empty($skill['level'])): ?>
                                        <p class="small text-muted mb-2">Livello: <?php echo sanitize_output($skill['level']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($skill['description'])): ?>
                                        <p class="mb-0"><?php echo nl2br(sanitize_output($skill['description'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Nessuna competenza registrata.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

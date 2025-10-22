<?php
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$currentPath = basename(parse_url($currentUri, PHP_URL_PATH) ?? '');
$role = $_SESSION['role'] ?? '';

if (!function_exists('nav_active')) {
    function nav_active(string $needle, string $currentPath): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if ($needle === $currentPath) {
            return 'active';
        }
        return str_contains($uri, $needle) ? 'active' : '';
    }
}

if (!function_exists('nav_is_active')) {
    function nav_is_active(string $needle, string $currentPath): bool
    {
        return nav_active($needle, $currentPath) === 'active';
    }
}

$serviziItems = [
    [
        'needle' => 'modules/servizi/entrate-uscite',
        'label' => 'Entrate/Uscite',
        'icon' => 'fa-solid fa-arrow-trend-up',
        'href' => base_url('modules/servizi/entrate-uscite/index.php'),
        'color' => 'sky',
    ],
    [
        'needle' => 'modules/servizi/ricariche',
        'label' => 'Appuntamenti',
        'icon' => 'fa-solid fa-calendar-check',
        'href' => base_url('modules/servizi/ricariche/index.php'),
        'color' => 'violet',
    ],
    [
        'needle' => 'modules/servizi/fedelta',
        'label' => 'Programma Fedeltà',
        'icon' => 'fa-solid fa-gift',
        'href' => base_url('modules/servizi/fedelta/index.php'),
        'color' => 'amber',
    ],
    [
        'needle' => 'modules/servizi/telefonia',
        'label' => 'Telefonia',
        'icon' => 'fa-solid fa-phone-volume',
        'href' => base_url('modules/servizi/telefonia/index.php'),
        'color' => 'emerald',
    ],
    [
        'needle' => 'modules/servizi/logistici',
        'label' => 'Servizi Logistici',
        'icon' => 'fa-solid fa-truck-fast',
        'href' => base_url('modules/servizi/logistici/index.php'),
        'color' => 'orange',
    ],
];

$serviziMenuOpen = false;
foreach ($serviziItems as $item) {
    if (nav_is_active($item['needle'], $currentPath)) {
        $serviziMenuOpen = true;
        break;
    }
}

$sidebarLogoRelative = 'assets/uploads/branding/sidebar-logo.png';
$sidebarLogoAvailable = is_file(public_path($sidebarLogoRelative));
?>
<nav id="sidebarMenu" class="sidebar border-end" aria-label="Menu principale">
    <div class="px-3 py-4 sidebar-inner">
        <div class="sidebar-brand mb-4">
            <a class="sidebar-brand-link" href="<?php echo base_url('dashboard.php'); ?>" aria-label="Coresuite Business">
                <span class="sidebar-logo" aria-hidden="true">
                    <?php if ($sidebarLogoAvailable): ?>
                        <img class="sidebar-logo-img" src="<?php echo asset($sidebarLogoRelative); ?>" alt="">
                    <?php else: ?>
                        <i class="fa-solid fa-building"></i>
                    <?php endif; ?>
                </span>
                <span class="sidebar-brand-text">
                    <span class="sidebar-brand-title">Coresuite Business</span>
                    <span class="sidebar-brand-subtitle">CRM Aziendale</span>
                </span>
            </a>
        </div>
        <ul class="nav nav-pills flex-column gap-1" role="list">
            <?php $dashboardActive = nav_active('dashboard.php', $currentPath); ?>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center <?php echo $dashboardActive; ?>" href="<?php echo base_url('dashboard.php'); ?>" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Dashboard" aria-label="Dashboard"<?php echo $dashboardActive ? ' aria-current="page"' : ''; ?>>
                    <span class="nav-icon" data-color="sky" aria-hidden="true">
                        <i class="fa-solid fa-gauge-high"></i>
                    </span>
                    <span class="nav-label">Dashboard</span>
                </a>
            </li>

            <?php if ($role !== 'Cliente'): ?>
                <?php $clientiActive = nav_active('modules/clienti', $currentPath); ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center <?php echo $clientiActive; ?>" href="<?php echo base_url('modules/clienti/index.php'); ?>" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Clienti" aria-label="Clienti"<?php echo $clientiActive ? ' aria-current="page"' : ''; ?>>
                        <span class="nav-icon" data-color="emerald" aria-hidden="true">
                            <i class="fa-solid fa-users"></i>
                        </span>
                        <span class="nav-label">Clienti</span>
                    </a>
                </li>

                <?php $serviziButtonActive = $serviziMenuOpen ? 'active' : ''; ?>
                <li class="nav-item">
                    <button class="nav-link nav-link-toggle d-flex align-items-center w-100 text-start <?php echo $serviziButtonActive; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarServices" aria-expanded="<?php echo $serviziMenuOpen ? 'true' : 'false'; ?>" aria-controls="sidebarServices" data-bs-placement="right" data-bs-title="Servizi" aria-label="Servizi">
                        <span class="nav-icon" data-color="violet" aria-hidden="true">
                            <i class="fa-solid fa-briefcase"></i>
                        </span>
                        <span class="nav-label">Servizi</span>
                        <span class="nav-caret" aria-hidden="true">
                            <i class="fa-solid fa-chevron-down"></i>
                        </span>
                    </button>
                    <div class="collapse<?php echo $serviziMenuOpen ? ' show' : ''; ?>" id="sidebarServices">
                        <ul class="nav flex-column ms-3 border-start ps-3" role="list">
                            <?php foreach ($serviziItems as $item): ?>
                                <?php $itemActive = nav_active($item['needle'], $currentPath); ?>
                                <li>
                                    <a class="nav-link d-flex align-items-center <?php echo $itemActive; ?>"
                                       href="<?php echo $item['href']; ?>"
                                       data-bs-toggle="tooltip"
                                       data-bs-placement="right"
                                       data-bs-trigger="hover focus"
                                       data-bs-container="#sidebarMenu"
                                       data-bs-title="<?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>"<?php echo $itemActive ? ' aria-current="page"' : ''; ?>>
                                        <span class="nav-subicon" data-color="<?php echo htmlspecialchars($item['color'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true">
                                            <i class="<?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                                        </span>
                                        <span class="nav-sub-label"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </li>

                <?php $documentiActive = nav_active('modules/documenti', $currentPath); ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center <?php echo $documentiActive; ?>" href="<?php echo base_url('modules/documenti/index.php'); ?>" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Documenti" aria-label="Documenti"<?php echo $documentiActive ? ' aria-current="page"' : ''; ?>>
                        <span class="nav-icon" data-color="amber" aria-hidden="true">
                            <i class="fa-solid fa-folder-open"></i>
                        </span>
                        <span class="nav-label">Documenti</span>
                    </a>
                </li>

                <?php $ticketActive = nav_active('modules/ticket', $currentPath); ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center <?php echo $ticketActive; ?>" href="<?php echo base_url('modules/ticket/index.php'); ?>" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Ticket" aria-label="Ticket"<?php echo $ticketActive ? ' aria-current="page"' : ''; ?>>
                        <span class="nav-icon" data-color="crimson" aria-hidden="true">
                            <i class="fa-solid fa-life-ring"></i>
                        </span>
                        <span class="nav-label">Ticket</span>
                    </a>
                </li>

                <?php $reportActive = nav_active('modules/report', $currentPath); ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center <?php echo $reportActive; ?>" href="<?php echo base_url('modules/report/index.php'); ?>" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Report" aria-label="Report"<?php echo $reportActive ? ' aria-current="page"' : ''; ?>>
                        <span class="nav-icon" data-color="teal" aria-hidden="true">
                            <i class="fa-solid fa-chart-pie"></i>
                        </span>
                        <span class="nav-label">Report</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php $settingsActive = nav_active('modules/impostazioni', $currentPath); ?>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center <?php echo $settingsActive; ?>" href="<?php echo base_url('modules/impostazioni/index.php'); ?>" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Impostazioni" aria-label="Impostazioni"<?php echo $settingsActive ? ' aria-current="page"' : ''; ?>>
                    <span class="nav-icon" data-color="orange" aria-hidden="true">
                        <i class="fa-solid fa-gear"></i>
                    </span>
                    <span class="nav-label">Impostazioni</span>
                </a>
            </li>
        </ul>
        <div class="sidebar-footer" aria-label="Versione applicazione">
            v. 1.0.0
        </div>
    </div>
</nav>

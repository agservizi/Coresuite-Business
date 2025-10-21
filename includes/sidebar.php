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
    ],
    [
        'needle' => 'modules/servizi/ricariche',
        'label' => 'Appuntamenti',
        'icon' => 'fa-solid fa-calendar-check',
        'href' => base_url('modules/servizi/ricariche/index.php'),
    ],
    [
        'needle' => 'modules/servizi/fedelta',
        'label' => 'Programma Fedeltà',
        'icon' => 'fa-solid fa-gift',
        'href' => base_url('modules/servizi/fedelta/index.php'),
    ],
    [
        'needle' => 'modules/servizi/telefonia',
        'label' => 'Telefonia',
        'icon' => 'fa-solid fa-phone-volume',
        'href' => base_url('modules/servizi/telefonia/index.php'),
    ],
    [
        'needle' => 'modules/servizi/logistici',
        'label' => 'Servizi Logistici',
        'icon' => 'fa-solid fa-truck-fast',
        'href' => base_url('modules/servizi/logistici/index.php'),
    ],
];

$serviziMenuOpen = false;
foreach ($serviziItems as $item) {
    if (nav_is_active($item['needle'], $currentPath)) {
        $serviziMenuOpen = true;
        break;
    }
}
?>
<nav id="sidebarMenu" class="sidebar border-end" aria-label="Menu principale">
    <div class="px-3 py-4 sidebar-inner">
        <div class="sidebar-brand mb-4">
            <a class="sidebar-brand-link" href="<?php echo base_url('dashboard.php'); ?>" aria-label="Coresuite Business">
                <span class="sidebar-logo" aria-hidden="true">
                    <svg class="sidebar-logo-mark" viewBox="0 0 64 64" role="presentation" focusable="false">
                        <path class="sidebar-logo-arrow" d="M18 42l14-14 7.4 7.4L50 20" fill="none"></path>
                        <path class="sidebar-logo-bars" d="M21 48V30a2.5 2.5 0 0 1 5 0v18zm12 0V26a2.5 2.5 0 0 1 5 0v22zm12-8V20a2.5 2.5 0 0 1 5 0v20z"></path>
                        <polygon class="sidebar-logo-arrowhead" points="46 15 60 12 57 26"></polygon>
                    </svg>
                </span>
            </a>
        </div>
        <ul class="nav nav-pills flex-column gap-1" role="list">
            <?php $dashboardActive = nav_active('dashboard.php', $currentPath); ?>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center <?php echo $dashboardActive; ?>" href="<?php echo base_url('dashboard.php'); ?>" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Dashboard" aria-label="Dashboard"<?php echo $dashboardActive ? ' aria-current="page"' : ''; ?>>
                    <span class="nav-icon" aria-hidden="true">
                        <i class="fa-solid fa-gauge-high"></i>
                    </span>
                    <span class="nav-label">Dashboard</span>
                </a>
            </li>

            <?php if ($role !== 'Cliente'): ?>
                <?php $clientiActive = nav_active('modules/clienti', $currentPath); ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center <?php echo $clientiActive; ?>" href="<?php echo base_url('modules/clienti/index.php'); ?>" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Clienti" aria-label="Clienti"<?php echo $clientiActive ? ' aria-current="page"' : ''; ?>>
                        <span class="nav-icon" aria-hidden="true">
                            <i class="fa-solid fa-users"></i>
                        </span>
                        <span class="nav-label">Clienti</span>
                    </a>
                </li>

                <?php $serviziButtonActive = $serviziMenuOpen ? 'active' : ''; ?>
                <li class="nav-item">
                    <button class="nav-link nav-link-toggle d-flex align-items-center w-100 text-start <?php echo $serviziButtonActive; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarServices" aria-expanded="<?php echo $serviziMenuOpen ? 'true' : 'false'; ?>" aria-controls="sidebarServices" data-bs-placement="right" data-bs-title="Servizi" aria-label="Servizi">
                        <span class="nav-icon" aria-hidden="true">
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
                                    <a class="nav-link d-flex align-items-center <?php echo $itemActive; ?>" href="<?php echo $item['href']; ?>"<?php echo $itemActive ? ' aria-current="page"' : ''; ?>>
                                        <i class="<?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?> me-2" aria-hidden="true"></i>
                                        <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </li>

                <?php $documentiActive = nav_active('modules/documenti', $currentPath); ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center <?php echo $documentiActive; ?>" href="<?php echo base_url('modules/documenti/index.php'); ?>" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Documenti" aria-label="Documenti"<?php echo $documentiActive ? ' aria-current="page"' : ''; ?>>
                        <span class="nav-icon" aria-hidden="true">
                            <i class="fa-solid fa-folder-open"></i>
                        </span>
                        <span class="nav-label">Documenti</span>
                    </a>
                </li>

                <?php $ticketActive = nav_active('modules/ticket', $currentPath); ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center <?php echo $ticketActive; ?>" href="<?php echo base_url('modules/ticket/index.php'); ?>" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Ticket" aria-label="Ticket"<?php echo $ticketActive ? ' aria-current="page"' : ''; ?>>
                        <span class="nav-icon" aria-hidden="true">
                            <i class="fa-solid fa-life-ring"></i>
                        </span>
                        <span class="nav-label">Ticket</span>
                    </a>
                </li>

                <?php $reportActive = nav_active('modules/report', $currentPath); ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center <?php echo $reportActive; ?>" href="<?php echo base_url('modules/report/index.php'); ?>" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Report" aria-label="Report"<?php echo $reportActive ? ' aria-current="page"' : ''; ?>>
                        <span class="nav-icon" aria-hidden="true">
                            <i class="fa-solid fa-chart-pie"></i>
                        </span>
                        <span class="nav-label">Report</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php $settingsActive = nav_active('modules/impostazioni', $currentPath); ?>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center <?php echo $settingsActive; ?>" href="<?php echo base_url('modules/impostazioni/index.php'); ?>" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Impostazioni" aria-label="Impostazioni"<?php echo $settingsActive ? ' aria-current="page"' : ''; ?>>
                    <span class="nav-icon" aria-hidden="true">
                        <i class="fa-solid fa-gear"></i>
                    </span>
                    <span class="nav-label">Impostazioni</span>
                </a>
            </li>
        </ul>
    </div>
</nav>

<?php
$username = current_user_display_name();
$role = $_SESSION['role'] ?? '';
?>
<header class="topbar border-bottom sticky-top">
    <div class="container-fluid">
        <div class="topbar-toolbar">
            <div class="topbar-left">
                <button class="btn btn-outline-warning d-lg-none" type="button" id="sidebarMobileToggle" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Apri menu laterale">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <button class="btn btn-outline-warning d-none d-lg-inline-flex" type="button" id="sidebarToggle" aria-label="Riduci barra laterale" aria-expanded="true">
                    <i class="fa-solid fa-angles-left"></i>
                </button>
            </div>

            <div class="topbar-search">
                <div class="live-search" data-live-search>
                    <span class="live-search-icon"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input class="form-control live-search-input" type="search" name="globalSearch" placeholder="Cerca rapido clienti, ticket o documenti" autocomplete="off" spellcheck="false" aria-label="Ricerca rapida" aria-expanded="false" aria-controls="topbarLiveSearchResults" role="combobox" aria-haspopup="listbox" aria-autocomplete="list">
                    <button class="btn btn-link live-search-clear" type="button" aria-label="Pulisci ricerca">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    <span class="live-search-spinner" aria-hidden="true"></span>
                    <div class="live-search-results" id="topbarLiveSearchResults" role="listbox" hidden></div>
                </div>
            </div>

            <div class="topbar-actions">
                <?php if ($role !== 'Cliente'): ?>
                    <div class="topbar-quick-actions d-none d-md-flex">
                        <a class="btn btn-outline-warning" href="<?php echo base_url('modules/clienti/create.php'); ?>" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-title="Nuovo cliente" data-bs-tooltip="global" aria-label="Crea un nuovo cliente">
                            <i class="fa-solid fa-user-plus"></i>
                            <span class="d-none d-xxl-inline">Nuovo cliente</span>
                        </a>
                        <a class="btn btn-outline-warning" href="<?php echo base_url('modules/ticket/create.php'); ?>" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-title="Nuovo ticket" data-bs-tooltip="global" aria-label="Apri un nuovo ticket">
                            <i class="fa-solid fa-ticket"></i>
                            <span class="d-none d-xxl-inline">Nuovo ticket</span>
                        </a>
                        <a class="btn btn-outline-warning" href="<?php echo base_url('modules/documenti/create.php'); ?>" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-title="Carica documento" data-bs-tooltip="global" aria-label="Carica un nuovo documento">
                            <i class="fa-solid fa-upload"></i>
                            <span class="d-none d-xxl-inline">Carica documento</span>
                        </a>
                    </div>
                    <div class="dropdown d-md-none">
                        <button class="btn btn-outline-warning" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Azioni rapide">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo base_url('modules/clienti/create.php'); ?>"><i class="fa-solid fa-user-plus me-2"></i>Nuovo cliente</a></li>
                            <li><a class="dropdown-item" href="<?php echo base_url('modules/ticket/create.php'); ?>"><i class="fa-solid fa-ticket me-2"></i>Nuovo ticket</a></li>
                            <li><a class="dropdown-item" href="<?php echo base_url('modules/documenti/create.php'); ?>"><i class="fa-solid fa-upload me-2"></i>Carica documento</a></li>
                        </ul>
                    </div>
                <?php endif; ?>
                <div class="dropdown">
                    <button class="btn btn-outline-warning dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-user-circle me-1"></i>
                        <?php echo sanitize_output($username); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="dropdown-header">
                            <span class="text-muted small">Ruolo</span>
                            <div class="fw-semibold text-capitalize"><?php echo sanitize_output($role); ?></div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo base_url('modules/impostazioni/profile.php'); ?>"><i class="fa-solid fa-id-badge me-2"></i>Profilo</a></li>
                        <li><a class="dropdown-item" href="<?php echo base_url('logout.php'); ?>"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</header>

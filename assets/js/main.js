document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebarMenu');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarMobileToggle = document.getElementById('sidebarMobileToggle');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const mobileBreakpoint = window.matchMedia('(max-width: 991.98px)');
    const sidebarTooltipSelector = '#sidebarMenu [data-bs-toggle="tooltip"], #sidebarMenu [data-bs-title], #sidebarMenu [aria-label]';
    // Track sidebar tooltip instances so we can rebuild them cleanly on collapse/expand.
    let sidebarTooltipInstances = [];

    const resolveTooltipTitle = (element) => (
        element.getAttribute('data-bs-title')
        || element.getAttribute('title')
        || element.getAttribute('aria-label')
        || ''
    ).trim();

    const parseOffset = (value) => {
        if (!value) {
            return null;
        }
        const parts = value.split(',').map((part) => Number.parseInt(part.trim(), 10));
        if (parts.every((number) => Number.isFinite(number))) {
            return [parts[0], parts[1] ?? 0];
        }
        return null;
    };

    const parseFallbackPlacements = (value) => {
        if (!value) {
            return [];
        }
        return value.split(',')
            .map((part) => part.trim())
            .filter((part) => part.length > 0);
    };

    const ensureTooltipInstance = (element, overrides = {}) => {
        // eslint-disable-next-line no-undef
        if (!element || typeof bootstrap === 'undefined' || !bootstrap?.Tooltip) {
            return null;
        }
        const resolvedTitle = resolveTooltipTitle(element);
        if (resolvedTitle && !element.getAttribute('data-bs-title')) {
            element.setAttribute('data-bs-title', resolvedTitle);
        }
        if (element.hasAttribute('title')) {
            element.removeAttribute('title');
        }

        const placement = element.getAttribute('data-bs-placement')
            || overrides.placement
            || 'top';
        const trigger = element.getAttribute('data-bs-trigger')
            || overrides.trigger
            || 'hover focus';
        const containerSelector = element.getAttribute('data-bs-container');
        const container = containerSelector
            ? document.querySelector(containerSelector) || document.body
            : overrides.container || document.body;
        const fallbackFromAttr = parseFallbackPlacements(element.getAttribute('data-bs-fallback'));
        const fallbackPlacements = fallbackFromAttr.length > 0
            ? fallbackFromAttr
            : overrides.fallbackPlacements || [placement];
        const offset = parseOffset(element.getAttribute('data-bs-offset'))
            || overrides.offset;

        // eslint-disable-next-line no-undef
        const existing = bootstrap.Tooltip.getInstance(element);
        if (existing) {
            existing.dispose();
        }

        const config = {
            trigger,
            placement,
            title: element.getAttribute('data-bs-title') || resolvedTitle,
            container,
            boundary: 'viewport',
            fallbackPlacements
        };

        if (offset) {
            config.popperConfig = {
                modifiers: [
                    {
                        name: 'offset',
                        options: {
                            offset
                        }
                    }
                ]
            };
        }

        // eslint-disable-next-line no-undef
        return bootstrap.Tooltip.getOrCreateInstance(element, config);
    };

    const getSidebarTooltipElements = () => Array.from(
        document.querySelectorAll(sidebarTooltipSelector)
    ).filter((element) => resolveTooltipTitle(element).length > 0);

    const disposeSidebarTooltips = () => {
        sidebarTooltipInstances.forEach((instance) => {
            if (!instance) {
                return;
            }
            instance.hide();
            instance.dispose();
        });
        sidebarTooltipInstances = [];
    };

    const createSidebarTooltips = () => {
        const elements = getSidebarTooltipElements();
        sidebarTooltipInstances = elements.map((element) => ensureTooltipInstance(element, {
            placement: 'right',
            container: document.body,
            fallbackPlacements: ['right', 'left', 'top', 'bottom'],
            offset: [0, 12]
        }));
    };

    const globalTooltipElements = Array.from(document.querySelectorAll('[data-bs-tooltip="global"]'));
    globalTooltipElements.forEach((element) => {
        ensureTooltipInstance(element, {
            placement: element.getAttribute('data-bs-placement') || 'top',
            fallbackPlacements: ['top', 'bottom', 'left', 'right'],
            offset: [0, 12]
        });
    });

    const syncSidebarTooltips = () => {
        if (!sidebar) {
            return;
        }
        const isCollapsed = sidebar.classList.contains('collapsed');
        if (isCollapsed) {
            if (sidebarTooltipInstances.length === 0) {
                createSidebarTooltips();
            }
            sidebarTooltipInstances.forEach((instance) => {
                if (!instance) {
                    return;
                }
                instance.enable();
                instance.update();
            });
        } else if (sidebarTooltipInstances.length > 0) {
            disposeSidebarTooltips();
        }
    };

    const closeSidebarSubmenus = () => {
        if (!sidebar) {
            return;
        }
        sidebar.querySelectorAll('.collapse.show').forEach((submenu) => {
            // eslint-disable-next-line no-undef
            const collapseInstance = bootstrap.Collapse.getInstance(submenu);
            if (collapseInstance) {
                collapseInstance.hide();
            } else {
                submenu.classList.remove('show');
            }
        });
    };

    const updateSidebarToggleIcon = () => {
        const icon = sidebarToggle?.querySelector('i');
        if (!icon) {
            return;
        }
        if (sidebar?.classList.contains('collapsed')) {
            icon.classList.remove('fa-angles-left');
            icon.classList.add('fa-angles-right');
        } else {
            icon.classList.remove('fa-angles-right');
            icon.classList.add('fa-angles-left');
        }
    };

    const applySidebarState = () => {
        if (!sidebar) {
            return;
        }
        const shouldCollapse = localStorage.getItem('csSidebar') === 'collapsed';
        if (mobileBreakpoint.matches) {
            sidebar.classList.remove('collapsed');
            sidebarToggle?.setAttribute('aria-expanded', 'false');
            sidebarMobileToggle?.setAttribute('aria-expanded', sidebar.classList.contains('open') ? 'true' : 'false');
        } else {
            sidebar.classList.toggle('collapsed', shouldCollapse);
            sidebarToggle?.setAttribute('aria-expanded', String(!shouldCollapse));
            if (sidebar.classList.contains('collapsed')) {
                closeSidebarSubmenus();
            }
        }
        syncSidebarTooltips();
        updateSidebarToggleIcon();
    };

    const syncSidebarMode = () => {
        if (!sidebar) {
            return;
        }
        if (!mobileBreakpoint.matches) {
            document.body.classList.remove('offcanvas-active');
            sidebar.classList.remove('open');
            sidebarMobileToggle?.setAttribute('aria-expanded', 'false');
            sidebarToggle?.setAttribute('aria-expanded', String(!sidebar.classList.contains('collapsed')));
        }
        applySidebarState();
        updateSidebarToggleIcon();
    };

    syncSidebarMode();
    const breakpointListener = mobileBreakpoint.addEventListener ? 'addEventListener' : 'addListener';
    mobileBreakpoint[breakpointListener]('change', syncSidebarMode);

    sidebarToggle?.addEventListener('click', () => {
        if (!sidebar) {
            return;
        }
        if (mobileBreakpoint.matches) {
            const isOpen = sidebar.classList.toggle('open');
            document.body.classList.toggle('offcanvas-active', isOpen);
            sidebarToggle?.setAttribute('aria-expanded', String(isOpen));
            sidebarMobileToggle?.setAttribute('aria-expanded', String(isOpen));
            return;
        }
        const shouldCollapse = !sidebar.classList.contains('collapsed');
        sidebar.classList.toggle('collapsed', shouldCollapse);
        localStorage.setItem('csSidebar', shouldCollapse ? 'collapsed' : 'expanded');
        sidebarToggle?.setAttribute('aria-expanded', String(!shouldCollapse));
        if (sidebar.classList.contains('collapsed')) {
            closeSidebarSubmenus();
        }
        syncSidebarTooltips();
        updateSidebarToggleIcon();
    });

    sidebarMobileToggle?.addEventListener('click', () => {
        if (!sidebar) {
            return;
        }
        const isOpen = sidebar.classList.toggle('open');
        document.body.classList.toggle('offcanvas-active', isOpen);
        sidebarMobileToggle.setAttribute('aria-expanded', String(isOpen));
        syncSidebarTooltips();
        updateSidebarToggleIcon();
    });

    if (sidebar) {
        sidebar.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                if (!mobileBreakpoint.matches) {
                    return;
                }
                sidebar.classList.remove('open');
                document.body.classList.remove('offcanvas-active');
                syncSidebarTooltips();
            });
        });
    }

    document.querySelectorAll('[data-datatable="true"]').forEach((table) => {
        // eslint-disable-next-line no-undef
        new DataTable(table, {
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/it-IT.json'
            }
        });
    });

    if (csrfToken) {
        document.querySelectorAll('form').forEach((form) => {
            if ((form.method || '').toLowerCase() === 'post' && !form.querySelector('input[name="_token"]')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = '_token';
                input.value = csrfToken;
                form.appendChild(input);
            }
        });
    }

    const dashboardRoot = document.querySelector('[data-dashboard-root]');
    if (dashboardRoot) {
        const endpoint = dashboardRoot.getAttribute('data-dashboard-endpoint') || 'api/dashboard.php';
        const refreshInterval = Number.parseInt(dashboardRoot.getAttribute('data-refresh-interval'), 10) || 60000;
        const statusBanner = document.getElementById('dashboardStatus');
        const statusText = statusBanner?.querySelector('.dashboard-status-text');
        const retryButton = document.getElementById('dashboardRetry');
        const ticketsBody = document.getElementById('dashboardTicketsBody');
        const remindersList = document.getElementById('dashboardReminders');
        const statElements = Array.from(document.querySelectorAll('[data-dashboard-stat]'));
        const numberFormatter = new Intl.NumberFormat('it-IT');
        const currencyFormatter = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' });
        const hasDynamicWidgets = statElements.length > 0 || ticketsBody || remindersList;
        if (!hasDynamicWidgets) {
            return;
        }
        let refreshTimer = null;
        let inFlight = false;
        let lastSuccess = 0;

        const setDashboardState = (state) => {
            dashboardRoot.setAttribute('data-dashboard-state', state);
        };

        const clearStatus = () => {
            if (!statusBanner || !statusText) {
                return;
            }
            statusBanner.hidden = true;
            statusText.textContent = '';
            if (retryButton) {
                retryButton.hidden = true;
                retryButton.disabled = true;
            }
        };

        const updateStatus = (variant, message, allowRetry = false) => {
            if (!statusBanner || !statusText) {
                return;
            }
            statusBanner.classList.remove('alert-warning', 'alert-danger', 'alert-info', 'alert-success');
            statusBanner.classList.add(`alert-${variant}`);
            statusText.textContent = message;
            statusBanner.hidden = false;
            if (retryButton) {
                retryButton.hidden = !allowRetry;
                retryButton.disabled = !allowRetry;
            }
        };

        const formatValue = (value, format) => {
            if (format === 'currency') {
                const amount = Number.parseFloat(value) || 0;
                return currencyFormatter.format(amount);
            }
            if (format === 'number') {
                const numeric = Number.parseFloat(value) || 0;
                return numberFormatter.format(numeric);
            }
            return typeof value === 'string' ? value : String(value ?? '');
        };

        const applyStats = (stats = {}) => {
            statElements.forEach((element) => {
                const key = element.getAttribute('data-dashboard-stat');
                if (!key || !(key in stats)) {
                    return;
                }
                const format = element.getAttribute('data-format');
                element.textContent = formatValue(stats[key], format);
            });
        };

        const formatDate = (value) => {
            if (!value) {
                return '—';
            }
            const parsed = new Date(value);
            if (Number.isNaN(parsed.getTime())) {
                return value;
            }
            return parsed.toLocaleDateString('it-IT');
        };

        const renderTickets = (tickets = []) => {
            if (!ticketsBody) {
                return;
            }
            if (!Array.isArray(tickets) || tickets.length === 0) {
                ticketsBody.innerHTML = '';
                const emptyRow = document.createElement('tr');
                const emptyCell = document.createElement('td');
                emptyCell.colSpan = 4;
                emptyCell.className = 'text-center text-muted py-4';
                emptyCell.textContent = 'Nessun ticket disponibile.';
                emptyRow.appendChild(emptyCell);
                ticketsBody.appendChild(emptyRow);
                return;
            }
            ticketsBody.innerHTML = '';
            const fragment = document.createDocumentFragment();
            tickets.forEach((ticket) => {
                const row = document.createElement('tr');

                const idCell = document.createElement('td');
                if (ticket.id !== undefined && ticket.id !== null && ticket.id !== '') {
                    idCell.textContent = `#${ticket.id}`;
                } else {
                    idCell.textContent = '—';
                }
                row.appendChild(idCell);

                const titleCell = document.createElement('td');
                titleCell.textContent = ticket.title || '—';
                row.appendChild(titleCell);

                const statusCell = document.createElement('td');
                const statusBadge = document.createElement('span');
                statusBadge.className = 'badge ag-badge text-uppercase';
                statusBadge.textContent = ticket.status || '—';
                statusCell.appendChild(statusBadge);
                row.appendChild(statusCell);

                const dateCell = document.createElement('td');
                dateCell.textContent = formatDate(ticket.createdAt);
                row.appendChild(dateCell);

                fragment.appendChild(row);
            });
            ticketsBody.appendChild(fragment);
        };

        const renderReminders = (reminders = []) => {
            if (!remindersList) {
                return;
            }
            if (!Array.isArray(reminders) || reminders.length === 0) {
                remindersList.innerHTML = '<li class="text-muted">Nessun promemoria attivo.</li>';
                return;
            }
            const fragment = document.createDocumentFragment();
            reminders.forEach((reminder) => {
                const item = document.createElement('li');
                item.className = 'reminder-item d-flex align-items-start';

                const badge = document.createElement('span');
                badge.className = 'badge ag-badge me-3';
                const icon = document.createElement('i');
                icon.className = `fa-solid ${reminder.icon || 'fa-bell'}`;
                badge.appendChild(icon);
                item.appendChild(badge);

                const content = document.createElement('div');
                const title = document.createElement('div');
                title.className = 'fw-semibold';
                if (reminder.url) {
                    const link = document.createElement('a');
                    link.className = 'link-warning';
                    link.href = reminder.url;
                    link.textContent = reminder.title || 'Promemoria';
                    title.appendChild(link);
                } else {
                    title.textContent = reminder.title || 'Promemoria';
                }
                content.appendChild(title);

                if (reminder.detail) {
                    const detail = document.createElement('small');
                    detail.className = 'text-muted';
                    detail.textContent = reminder.detail;
                    content.appendChild(detail);
                }

                item.appendChild(content);
                fragment.appendChild(item);
            });

            remindersList.innerHTML = '';
            remindersList.appendChild(fragment);
        };

        const getChartInstance = (canvas) => {
            const chartLib = window.Chart;
            if (!canvas || !chartLib) {
                return null;
            }
            if (typeof chartLib.getChart === 'function') {
                const found = chartLib.getChart(canvas);
                if (found) {
                    return found;
                }
            }
            if (canvas.chart || canvas._chart) {
                return canvas.chart || canvas._chart;
            }
            if (window.CSCharts) {
                if (canvas.id === 'chartRevenue' && window.CSCharts.revenue) {
                    return window.CSCharts.revenue;
                }
                if (canvas.id === 'chartServices' && window.CSCharts.services) {
                    return window.CSCharts.services;
                }
            }
            return null;
        };

        const updateCharts = (charts = {}) => {
            const revenueChart = getChartInstance(document.getElementById('chartRevenue'));
            const servicesChart = getChartInstance(document.getElementById('chartServices'));

            if (revenueChart && Array.isArray(revenueChart.data?.datasets)) {
                revenueChart.data.labels = charts.revenue?.labels ?? [];
                if (revenueChart.data.datasets[0]) {
                    revenueChart.data.datasets[0].data = charts.revenue?.values ?? [];
                }
                revenueChart.update('none');
            }

            if (servicesChart && Array.isArray(servicesChart.data?.datasets)) {
                servicesChart.data.labels = charts.services?.labels ?? [];
                if (servicesChart.data.datasets[0]) {
                    servicesChart.data.datasets[0].data = charts.services?.values ?? [];
                }
                servicesChart.update('none');
            }
        };

        const handlePayload = (payload = {}) => {
            applyStats(payload.stats);
            renderTickets(payload.tickets);
            renderReminders(payload.reminders);
            updateCharts(payload.charts);
            lastSuccess = Date.now();
            clearStatus();
            setDashboardState('ready');
        };

        const formatTime = (timestamp) => {
            if (!timestamp) {
                return '';
            }
            return new Date(timestamp).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
        };

        const refreshDashboard = async () => {
            if (inFlight) {
                return;
            }
            inFlight = true;
            setDashboardState('loading');

            try {
                const response = await fetch(endpoint, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store'
                });

                if (!response.ok) {
                    throw new Error('Aggiornamento non disponibile.');
                }

                const payload = await response.json();
                if (payload?.error) {
                    throw new Error(payload.error);
                }

                handlePayload(payload);
            } catch (error) {
                const staleSuffix = lastSuccess ? ` Ultimo dato valido alle ${formatTime(lastSuccess)}.` : '';
                const fallbackMessage = `Impossibile aggiornare la dashboard.${staleSuffix}`;
                const message = error?.name === 'SyntaxError' ? fallbackMessage : (error?.message ? `${error.message}${staleSuffix}` : fallbackMessage);
                updateStatus('danger', message, true);
                setDashboardState('stale');
            } finally {
                inFlight = false;
            }
        };

        const startPolling = () => {
            if (refreshTimer) {
                clearInterval(refreshTimer);
            }
            if (refreshInterval > 0) {
                refreshTimer = setInterval(() => {
                    refreshDashboard();
                }, refreshInterval);
            }
        };

        retryButton?.addEventListener('click', () => {
            clearStatus();
            refreshDashboard();
        });

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                refreshDashboard();
                startPolling();
            } else if (refreshTimer) {
                clearInterval(refreshTimer);
                refreshTimer = null;
            }
        });

        dashboardRoot.addEventListener('refreshDashboard', () => {
            refreshDashboard();
        });

        setDashboardState('ready');
        refreshDashboard();
        startPolling();
    }

    if (Array.isArray(window.CS_INITIAL_FLASHES)) {
        window.CS_INITIAL_FLASHES.forEach((flash) => {
            if (flash?.message) {
                const type = flash.type ?? 'info';
                FlashModal.show(flash.message, type);
            }
        });
    }

    syncSidebarTooltips();
});

const Toast = {
    show(message, type = 'info') {
        const container = document.querySelector('.toast-container') || createToastContainer();
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-bg-${type} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        container.appendChild(toast);
        // eslint-disable-next-line no-undef
        const bootstrapToast = new bootstrap.Toast(toast, { delay: 4000 });
        bootstrapToast.show();
    }
};

function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    document.body.appendChild(container);
    return container;
}

window.CSToast = Toast;

const FlashModal = (() => {
    const queue = [];
    let isShowing = false;

    const typeConfig = {
        success: { title: 'Operazione completata', headerClass: 'text-bg-success' },
        danger: { title: 'Operazione non riuscita', headerClass: 'text-bg-danger' },
        warning: { title: 'Attenzione', headerClass: 'text-bg-warning text-dark' },
        info: { title: 'Informazione', headerClass: 'text-bg-info text-dark' }
    };

    const createModal = () => {
        const modal = document.createElement('div');
        modal.id = 'csFlashModal';
        modal.className = 'modal fade';
        modal.tabIndex = -1;
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5">Avviso</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                    </div>
                    <div class="modal-body"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-warning text-dark" data-bs-dismiss="modal">Chiudi</button>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(modal);
        return modal;
    };

    const getModalElement = () => document.getElementById('csFlashModal') || createModal();

    const applyTypeStyles = (modalElement, type) => {
        const { title, headerClass } = typeConfig[type] ?? typeConfig.info;
        const header = modalElement.querySelector('.modal-header');
        const titleEl = modalElement.querySelector('.modal-title');
        const bodyEl = modalElement.querySelector('.modal-body');
        if (!header || !titleEl || !bodyEl) {
            return;
        }

        header.className = 'modal-header';
        if (headerClass) {
            header.classList.add(...headerClass.split(' '));
        }
        titleEl.textContent = title;
    };

    const showNext = () => {
        if (queue.length === 0) {
            isShowing = false;
            return;
        }

        isShowing = true;
        const { message, type } = queue.shift();
        const modalElement = getModalElement();
        applyTypeStyles(modalElement, type);
        const bodyEl = modalElement.querySelector('.modal-body');
        if (bodyEl) {
            bodyEl.textContent = message;
        }

    // eslint-disable-next-line no-undef
    const modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);
        modalElement.addEventListener('hidden.bs.modal', () => {
            showNext();
        }, { once: true });
        modalInstance.show();
    };

    return {
        show(message, type = 'info') {
            queue.push({ message, type });
            if (!isShowing) {
                showNext();
            }
        }
    };
})();

window.CSFlashModal = FlashModal;

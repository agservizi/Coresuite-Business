document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebarMenu');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarMobileToggle = document.getElementById('sidebarMobileToggle');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const mobileBreakpoint = window.matchMedia('(max-width: 991.98px)');
    const tooltipElements = Array.from(document.querySelectorAll('#sidebarMenu [data-bs-toggle="tooltip"], #sidebarMenu [data-bs-title]'));
    const tooltipInstances = tooltipElements.map((element) => {
        const placement = element.getAttribute('data-bs-placement') || 'right';
        const title = element.getAttribute('data-bs-title') || element.getAttribute('title') || '';
        if (title && !element.getAttribute('data-bs-title')) {
            element.setAttribute('data-bs-title', title);
        }
        // eslint-disable-next-line no-undef
        return bootstrap.Tooltip.getOrCreateInstance(element, {
            trigger: 'hover focus',
            placement,
            title: element.getAttribute('data-bs-title') || element.getAttribute('title') || ''
        });
    });

    const globalTooltipElements = Array.from(document.querySelectorAll('[data-bs-tooltip="global"]'));
    globalTooltipElements.forEach((element) => {
        const placement = element.getAttribute('data-bs-placement') || 'top';
        const title = element.getAttribute('data-bs-title') || element.getAttribute('title') || '';
        if (title && !element.getAttribute('data-bs-title')) {
            element.setAttribute('data-bs-title', title);
        }
        // eslint-disable-next-line no-undef
        bootstrap.Tooltip.getOrCreateInstance(element, {
            trigger: 'hover focus',
            placement,
            title: element.getAttribute('data-bs-title') || element.getAttribute('title') || ''
        });
    });

    const syncSidebarTooltips = () => {
        const enableTooltips = !!sidebar && sidebar.classList.contains('collapsed');
        tooltipInstances.forEach((instance) => {
            if (!instance) {
                return;
            }
            if (enableTooltips) {
                instance.enable();
            } else {
                instance.hide();
                instance.disable();
            }
        });
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

    const notificationsRoots = Array.from(document.querySelectorAll('[data-notifications-root]'));
    if (notificationsRoots.length > 0) {
        const configs = notificationsRoots.map((root) => ({
            root,
            list: root.querySelector('[data-notifications-list]'),
            badge: root.querySelector('[data-notifications-badge]'),
            total: root.querySelector('[data-notifications-total]'),
            updated: root.querySelector('[data-notifications-updated]'),
            refreshButton: root.querySelector('[data-notifications-refresh]'),
            toggleButton: root.querySelector('[data-notifications-toggle]'),
            limit: Number.parseInt(root.getAttribute('data-limit'), 10) || 8,
            refreshInterval: Number.parseInt(root.getAttribute('data-refresh-interval'), 10) || 60000
        }));

        const endpoint = configs.find((config) => config.root.getAttribute('data-notifications-endpoint'))?.root.getAttribute('data-notifications-endpoint') || 'api/notifications.php';
        const limitCandidates = configs
            .map((config) => config.limit)
            .filter((value) => Number.isFinite(value) && value > 0);
        const limit = limitCandidates.length > 0 ? Math.max(...limitCandidates) : 8;

        const intervalCandidates = configs
            .map((config) => config.refreshInterval)
            .filter((value) => Number.isFinite(value) && value > 0);
        const refreshInterval = intervalCandidates.length > 0 ? Math.min(...intervalCandidates) : 60000;

        let refreshTimer = null;
        let controller = null;
        let isLoading = false;

        const setBadgeCount = (config, count) => {
            if (config.badge) {
                if (count > 0) {
                    config.badge.textContent = count > 99 ? '99+' : String(count);
                    config.badge.hidden = false;
                } else {
                    config.badge.textContent = '';
                    config.badge.hidden = true;
                }
            }
            if (config.total) {
                if (count > 0) {
                    config.total.textContent = String(count);
                    config.total.hidden = false;
                } else {
                    config.total.textContent = '0';
                    config.total.hidden = true;
                }
            }
        };

        const showMessage = (config, message, className) => {
            if (!config.list) {
                return;
            }
            config.list.innerHTML = '';
            const placeholder = document.createElement('div');
            placeholder.className = className;
            placeholder.textContent = message;
            config.list.appendChild(placeholder);
        };

        const renderItems = (config, items) => {
            if (!config.list) {
                return;
            }
            config.list.innerHTML = '';
            const fragment = document.createDocumentFragment();
            if (!Array.isArray(items) || items.length === 0) {
                showMessage(config, 'Nessuna notifica disponibile.', 'topbar-notifications-empty text-muted small');
                return;
            }
            items.forEach((item) => {
                const elementTag = item.url ? 'a' : 'div';
                const wrapper = document.createElement(elementTag);
                wrapper.className = 'topbar-notification-item';
                if (item.url) {
                    wrapper.href = item.url;
                }
                wrapper.dataset.notificationId = item.id || '';

                const icon = document.createElement('span');
                icon.className = 'topbar-notification-icon';
                const iconInner = document.createElement('i');
                iconInner.className = `fa-solid ${item.icon || 'fa-bell'}`;
                iconInner.setAttribute('aria-hidden', 'true');
                icon.appendChild(iconInner);
                wrapper.appendChild(icon);

                const content = document.createElement('span');
                content.className = 'topbar-notification-content';

                const title = document.createElement('span');
                title.className = 'topbar-notification-title';
                title.textContent = item.title || 'Notifica';
                content.appendChild(title);

                if (item.message) {
                    const message = document.createElement('span');
                    message.className = 'topbar-notification-message';
                    message.textContent = item.message;
                    content.appendChild(message);
                }

                const metaParts = [];
                if (item.meta) {
                    metaParts.push(item.meta);
                }
                if (item.timeLabel) {
                    metaParts.push(item.timeLabel);
                }
                if (metaParts.length > 0) {
                    const meta = document.createElement('span');
                    meta.className = 'topbar-notification-meta';
                    meta.textContent = metaParts.join(' | ');
                    content.appendChild(meta);
                }

                wrapper.appendChild(content);
                fragment.appendChild(wrapper);
            });
            config.list.appendChild(fragment);
        };

        const updateUpdatedLabel = (config, isoString) => {
            if (!config.updated) {
                return;
            }
            if (!isoString) {
                config.updated.textContent = 'Aggiornamento non disponibile';
                return;
            }
            const parsed = new Date(isoString);
            if (Number.isNaN(parsed.getTime())) {
                config.updated.textContent = `Aggiornato ${isoString}`;
                return;
            }
            const formatted = parsed.toLocaleString('it-IT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            config.updated.textContent = `Aggiornato ${formatted}`;
        };

        const refresh = async (fromUser = false) => {
            if (isLoading) {
                return;
            }
            if (controller) {
                controller.abort();
            }
            controller = new AbortController();
            isLoading = true;
            configs.forEach((config) => {
                if (config.refreshButton) {
                    config.refreshButton.disabled = true;
                }
            });
            try {
                const response = await fetch(`${endpoint}?limit=${limit}`, {
                    headers: {
                        Accept: 'application/json'
                    },
                    credentials: 'same-origin',
                    signal: controller.signal
                });
                if (!response.ok) {
                    throw new Error(`Request failed: ${response.status}`);
                }
                const payload = await response.json();
                const items = Array.isArray(payload.items) ? payload.items : [];
                const totalCount = payload.total ?? items.length ?? 0;
                configs.forEach((config) => {
                    renderItems(config, items);
                    setBadgeCount(config, totalCount);
                    updateUpdatedLabel(config, payload.refreshedAt || '');
                });
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }
                console.error('Notifications refresh failed', error);
                if (fromUser) {
                    configs.forEach((config) => {
                        showMessage(config, 'Impossibile aggiornare le notifiche.', 'topbar-notifications-error text-danger small');
                    });
                }
            } finally {
                configs.forEach((config) => {
                    if (config.refreshButton) {
                        config.refreshButton.disabled = false;
                    }
                });
                isLoading = false;
                controller = null;
            }
        };

        const scheduleRefresh = () => {
            if (refreshTimer) {
                window.clearInterval(refreshTimer);
            }
            if (refreshInterval > 0) {
                refreshTimer = window.setInterval(() => {
                    refresh(false);
                }, refreshInterval);
            }
        };

        configs.forEach((config) => {
            config.refreshButton?.addEventListener('click', () => {
                refresh(true);
            });

            config.toggleButton?.addEventListener('show.bs.dropdown', () => {
                refresh(false);
            });
        });

        scheduleRefresh();
        refresh(false);
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

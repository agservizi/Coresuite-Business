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

    const liveSearch = document.querySelector('[data-live-search]');
    if (liveSearch) {
        const input = liveSearch.querySelector('.live-search-input');
        const clearButton = liveSearch.querySelector('.live-search-clear');
        const resultsPanel = liveSearch.querySelector('.live-search-results');
        const minChars = 2;
        const groups = [
            { key: 'clients', label: 'Clienti', icon: 'fa-users' },
            { key: 'tickets', label: 'Ticket', icon: 'fa-life-ring' },
            { key: 'documents', label: 'Documenti', icon: 'fa-file-lines' }
        ];

        let debounceId;
        let controller;
        let focusableItems = [];
        let activeResultIndex = -1;
        let itemIdSequence = 0;

        const resetResults = () => {
            resultsPanel.innerHTML = '';
            resultsPanel.hidden = true;
            liveSearch.classList.remove('is-open');
            input?.setAttribute('aria-expanded', 'false');
            focusableItems = [];
            activeResultIndex = -1;
            itemIdSequence = 0;
            input?.removeAttribute('aria-activedescendant');
        };

        const showMessage = (message) => {
            resultsPanel.innerHTML = '';
            const messageEl = document.createElement('div');
            messageEl.className = 'live-search-empty';
            messageEl.textContent = message;
            resultsPanel.appendChild(messageEl);
            resultsPanel.hidden = false;
            liveSearch.classList.add('is-open');
            input?.setAttribute('aria-expanded', 'true');
            focusableItems = [];
            activeResultIndex = -1;
            input?.removeAttribute('aria-activedescendant');
        };

        const clearActiveResult = () => {
            if (activeResultIndex >= 0 && focusableItems[activeResultIndex]) {
                focusableItems[activeResultIndex].classList.remove('is-active');
                focusableItems[activeResultIndex].setAttribute('aria-selected', 'false');
            }
            activeResultIndex = -1;
            input?.removeAttribute('aria-activedescendant');
        };

        const setActiveResult = (index, { scroll = true } = {}) => {
            if (!focusableItems.length) {
                clearActiveResult();
                return;
            }

            const itemCount = focusableItems.length;
            const normalizedIndex = index === -1 ? -1 : ((index % itemCount) + itemCount) % itemCount;

            if (normalizedIndex === -1) {
                clearActiveResult();
                return;
            }

            if (activeResultIndex >= 0 && focusableItems[activeResultIndex]) {
                focusableItems[activeResultIndex].classList.remove('is-active');
                focusableItems[activeResultIndex].setAttribute('aria-selected', 'false');
            }

            const nextItem = focusableItems[normalizedIndex];
            if (!nextItem) {
                clearActiveResult();
                return;
            }

            nextItem.classList.add('is-active');
            nextItem.setAttribute('aria-selected', 'true');
            input?.setAttribute('aria-activedescendant', nextItem.id);
            if (scroll) {
                nextItem.scrollIntoView({ block: 'nearest' });
            }
            activeResultIndex = normalizedIndex;
        };

        const setActiveResultByElement = (element) => {
            const nextIndex = focusableItems.indexOf(element);
            if (nextIndex === -1) {
                return;
            }
            setActiveResult(nextIndex, { scroll: false });
        };

        const renderResults = (payload) => {
            resultsPanel.innerHTML = '';
            let hasAny = false;
            clearActiveResult();
            focusableItems = [];
            itemIdSequence = 0;

            groups.forEach((group) => {
                const items = Array.isArray(payload?.[group.key]) ? payload[group.key] : [];
                if (!items.length) {
                    return;
                }
                hasAny = true;
                const groupEl = document.createElement('div');
                groupEl.className = 'live-search-group';

                const labelEl = document.createElement('div');
                labelEl.className = 'live-search-group-label';
                const iconEl = document.createElement('i');
                iconEl.className = `fa-solid ${group.icon}`;
                labelEl.appendChild(iconEl);
                const labelText = document.createElement('span');
                labelText.textContent = group.label;
                labelEl.appendChild(labelText);
                groupEl.appendChild(labelEl);

                items.forEach((item) => {
                    const link = document.createElement('a');
                    link.className = 'live-search-item';
                    if (typeof item.url === 'string' && item.url !== '') {
                        link.href = item.url;
                    } else {
                        link.href = '#';
                    }
                    link.setAttribute('role', 'option');
                    link.setAttribute('aria-selected', 'false');
                    link.tabIndex = -1;
                    const optionId = `live-search-option-${itemIdSequence++}`;
                    link.id = optionId;

                    const content = document.createElement('div');
                    content.className = 'live-search-item-content';

                    const title = document.createElement('div');
                    title.className = 'live-search-item-title';
                    title.textContent = item.title || 'Elemento senza titolo';
                    content.appendChild(title);

                    if (item.subtitle) {
                        const subtitle = document.createElement('div');
                        subtitle.className = 'live-search-item-subtitle';
                        subtitle.textContent = item.subtitle;
                        content.appendChild(subtitle);
                    }

                    link.appendChild(content);

                    if (item.badge) {
                        const badge = document.createElement('span');
                        badge.className = 'live-search-item-badge';
                        badge.textContent = item.badge;
                        link.appendChild(badge);
                    }

                    link.addEventListener('mouseenter', () => {
                        setActiveResultByElement(link);
                    });
                    link.addEventListener('focus', () => {
                        setActiveResultByElement(link);
                    });

                    groupEl.appendChild(link);
                    focusableItems.push(link);
                });

                resultsPanel.appendChild(groupEl);
            });

            if (!hasAny) {
                showMessage('Nessun risultato trovato.');
                return;
            }

            resultsPanel.hidden = false;
            liveSearch.classList.add('is-open');
            input?.setAttribute('aria-expanded', 'true');
            clearActiveResult();
        };

        const performSearch = async (term) => {
            if (controller) {
                controller.abort();
            }
            controller = new AbortController();
            liveSearch.classList.add('is-loading');

            try {
                const response = await fetch(`api/search.php?q=${encodeURIComponent(term)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.signal
                });

                if (!response.ok) {
                    throw new Error('Response not ok');
                }

                const data = await response.json();
                if (data?.error) {
                    showMessage(data.error);
                    return;
                }
                renderResults(data?.results ?? {});
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }
                showMessage('Ricerca non disponibile al momento.');
            } finally {
                liveSearch.classList.remove('is-loading');
            }
        };

        input?.addEventListener('input', () => {
            const value = input.value.trim();
            liveSearch.classList.toggle('has-value', value.length > 0);

            if (value.length < minChars) {
                if (controller) {
                    controller.abort();
                }
                if (debounceId) {
                    clearTimeout(debounceId);
                }
                resetResults();
                return;
            }

            if (debounceId) {
                clearTimeout(debounceId);
            }

            debounceId = setTimeout(() => {
                performSearch(value);
            }, 220);
        });

        input?.addEventListener('focus', () => {
            if (!resultsPanel.hidden) {
                liveSearch.classList.add('is-open');
                input?.setAttribute('aria-expanded', 'true');
            }
        });

        input?.addEventListener('keydown', (event) => {
            switch (event.key) {
            case 'ArrowDown':
                if (!focusableItems.length) {
                    return;
                }
                event.preventDefault();
                if (resultsPanel.hidden) {
                    resultsPanel.hidden = false;
                    liveSearch.classList.add('is-open');
                    input?.setAttribute('aria-expanded', 'true');
                }
                setActiveResult(activeResultIndex + 1);
                break;
            case 'ArrowUp':
                if (!focusableItems.length) {
                    return;
                }
                event.preventDefault();
                if (resultsPanel.hidden) {
                    resultsPanel.hidden = false;
                    liveSearch.classList.add('is-open');
                    input?.setAttribute('aria-expanded', 'true');
                }
                setActiveResult(activeResultIndex === -1 ? focusableItems.length - 1 : activeResultIndex - 1);
                break;
            case 'Enter':
                if (activeResultIndex >= 0 && focusableItems[activeResultIndex]) {
                    event.preventDefault();
                    focusableItems[activeResultIndex].click();
                    resetResults();
                    input?.blur();
                }
                break;
            case 'Escape':
                clearButton?.click();
                event.preventDefault();
                break;
            default:
                break;
            }
        });

        clearButton?.addEventListener('click', () => {
            if (controller) {
                controller.abort();
            }
            if (debounceId) {
                clearTimeout(debounceId);
            }
            input.value = '';
            liveSearch.classList.remove('has-value');
            resetResults();
            input.focus();
        });

        document.addEventListener('click', (event) => {
            if (!liveSearch.contains(event.target)) {
                resetResults();
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

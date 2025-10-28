/**
 * JavaScript principale per il Pickup Portal
 */

// Configurazione globale
window.PickupPortal = {
    config: window.portalConfig || {},
    apiUrl: function(endpoint) {
        const baseUrl = typeof this.config.apiBaseUrl === 'string' && this.config.apiBaseUrl.length > 0
            ? this.config.apiBaseUrl
            : 'api/';
        return baseUrl + endpoint;
    },
    
    // Utility per le chiamate API
    api: {
        call: async function(endpoint, options = {}) {
            const defaultOptions = {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.portalConfig?.csrfToken || ''
                }
            };
            
            const finalOptions = { ...defaultOptions, ...options };
            
            if (finalOptions.body && typeof finalOptions.body === 'object') {
                finalOptions.body = JSON.stringify(finalOptions.body);
            }
            
            try {
                const response = await fetch(window.PickupPortal.apiUrl(endpoint), finalOptions);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                return await response.json();
            } catch (error) {
                console.error('API Error:', error);
                window.PickupPortal.showAlert('Errore di comunicazione con il server', 'danger');
                throw error;
            }
        },
        
        get: function(endpoint) {
            return this.call(endpoint);
        },
        
        post: function(endpoint, data) {
            return this.call(endpoint, {
                method: 'POST',
                body: data
            });
        },
        
        put: function(endpoint, data) {
            return this.call(endpoint, {
                method: 'PUT',
                body: data
            });
        },
        
        delete: function(endpoint) {
            return this.call(endpoint, {
                method: 'DELETE'
            });
        }
    },
    
    // Utility per gli alert
    showAlert: function(message, type = 'info', duration = 5000) {
        const alertContainer = document.getElementById('global-alert-container');
        const alertElement = document.getElementById('global-alert');
        const alertMessage = document.getElementById('global-alert-message');
        
        if (!alertContainer || !alertElement || !alertMessage) {
            const fallbackContainer = document.getElementById('alert-container');
            if (!fallbackContainer) {
                console.warn('Alert container not found');
                return;
            }
            const wrapper = document.createElement('div');
            wrapper.className = `alert alert-${type} alert-dismissible fade show shadow-sm`;
            wrapper.innerHTML = `
                <div>${message}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>
            `;
            fallbackContainer.appendChild(wrapper);
            if (duration > 0) {
                setTimeout(() => {
                    wrapper.classList.remove('show');
                    setTimeout(() => wrapper.remove(), 300);
                }, duration);
            }
            return;
        }
        
        // Rimuovi classi precedenti
        alertElement.className = 'alert alert-dismissible fade show';
        alertElement.classList.add(`alert-${type}`);
        
        alertMessage.textContent = message;
        alertContainer.style.display = 'block';
        
        // Auto-hide dopo duration
        if (duration > 0) {
            setTimeout(() => {
                alertContainer.style.display = 'none';
            }, duration);
        }
    },
    
    // Utility per il loading
    showLoading: function(element) {
        if (typeof element === 'string') {
            element = document.getElementById(element);
        }
        
        if (element) {
            element.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Caricamento...</span></div></div>';
        }
    },
    
    // Formattazione date
    formatDate: function(dateString, options = {}) {
        const defaultOptions = {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        };
        
        const finalOptions = { ...defaultOptions, ...options };
        
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('it-IT', finalOptions);
        } catch (error) {
            console.error('Date formatting error:', error);
            return dateString;
        }
    },
    
    // Formattazione relative time
    formatRelativeTime: function(dateString) {
        try {
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffSecs = Math.floor(diffMs / 1000);
            const diffMins = Math.floor(diffSecs / 60);
            const diffHours = Math.floor(diffMins / 60);
            const diffDays = Math.floor(diffHours / 24);
            
            if (diffSecs < 60) return 'Ora';
            if (diffMins < 60) return `${diffMins}m fa`;
            if (diffHours < 24) return `${diffHours}h fa`;
            if (diffDays < 7) return `${diffDays}g fa`;
            
            return this.formatDate(dateString, { year: 'numeric', month: '2-digit', day: '2-digit' });
        } catch (error) {
            console.error('Relative time formatting error:', error);
            return dateString;
        }
    },
    
    // Copia testo negli appunti
    copyToClipboard: function(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text).then(() => {
                this.showAlert('Copiato negli appunti!', 'success', 2000);
            }).catch(err => {
                console.error('Errore copia:', err);
                this.showAlert('Errore durante la copia', 'danger', 3000);
            });
        } else {
            // Fallback per browser più vecchi
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                textArea.remove();
                this.showAlert('Copiato negli appunti!', 'success', 2000);
            } catch (err) {
                console.error('Errore copia fallback:', err);
                textArea.remove();
                this.showAlert('Errore durante la copia', 'danger', 3000);
            }
        }
    }
};

// Inizializzazione login
function initializeLogin() {
    const loginForm = document.getElementById('loginForm');
    const otpForm = document.getElementById('otpForm');
    
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
    
    if (otpForm) {
        otpForm.addEventListener('submit', handleOtpVerification);
        
        // Auto-submit OTP quando raggiunge 6 cifre
        const otpInput = document.getElementById('otp');
        if (otpInput) {
            otpInput.addEventListener('input', function() {
                const value = this.value.replace(/\D/g, ''); // Solo numeri
                this.value = value;
                
                if (value.length === 6) {
                    setTimeout(() => handleOtpVerification(null), 500);
                }
            });
        }
        
        // Resend OTP
        const resendBtn = document.getElementById('resendOtp');
        if (resendBtn) {
            resendBtn.addEventListener('click', handleResendOtp);
        }
    }
}

// Gestione login
async function handleLogin(event) {
    if (event) event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const email = (formData.get('email') || '').toString().trim();
    const csrfToken = formData.get('csrf_token');
    const data = {
        email,
        csrf_token: csrfToken
    };
    
    // Validazione base
    if (email === '') {
        window.PickupPortal.showAlert('Email richiesta', 'danger');
        return;
    }
    
    showLoginStep('loading');
    
    try {
    const response = await window.PickupPortal.api.post('auth/login.php', data);
        
        if (response.success) {
            // Mostra form OTP
            document.getElementById('customer_id').value = response.customer_id;
            document.getElementById('otp-destination-text').textContent = 
                `Abbiamo inviato un codice di 6 cifre a ${response.destination}`;
            
            showLoginStep('otp-form');
            startOtpCountdown(response.expires_in || 300);
            
            // Focus su input OTP
            document.getElementById('otp').focus();
        } else {
            throw new Error(response.message || 'Errore durante il login');
        }
    } catch (error) {
        console.error('Login error:', error);
        window.PickupPortal.showAlert(error.message || 'Errore durante il login', 'danger');
        showLoginStep('login-form');
    }
}

// Gestione verifica OTP
async function handleOtpVerification(event) {
    if (event) event.preventDefault();
    
    const otpInput = document.getElementById('otp');
    const customerIdInput = document.getElementById('customer_id');
    
    if (!otpInput.value || otpInput.value.length !== 6) {
        window.PickupPortal.showAlert('Inserisci il codice di 6 cifre', 'danger');
        otpInput.focus();
        return;
    }
    
    showLoginStep('loading');
    
    try {
        const response = await window.PickupPortal.api.post('auth/verify-otp.php', {
            customer_id: customerIdInput.value,
            otp: otpInput.value,
            csrf_token: window.portalConfig.csrfToken
        });
        
        if (response.success) {
            window.PickupPortal.showAlert('Accesso effettuato con successo!', 'success');
            setTimeout(() => {
                window.location.href = 'dashboard.php';
            }, 1000);
        } else {
            throw new Error(response.message || 'Codice non valido');
        }
    } catch (error) {
        console.error('OTP verification error:', error);
        window.PickupPortal.showAlert(error.message || 'Codice non valido', 'danger');
        showLoginStep('otp-form');
        otpInput.focus();
        otpInput.select();
    }
}

// Gestione reinvio OTP
async function handleResendOtp() {
    const customerIdInput = document.getElementById('customer_id');
    const resendBtn = document.getElementById('resendOtp');
    
    if (!customerIdInput.value) {
        window.PickupPortal.showAlert('Errore: riprova dall\'inizio', 'danger');
        showLoginStep('login-form');
        return;
    }
    
    resendBtn.disabled = true;
    resendBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Invio...';
    
    try {
        const response = await window.PickupPortal.api.post('auth/resend-otp.php', {
            customer_id: customerIdInput.value,
            csrf_token: window.portalConfig.csrfToken
        });
        
        if (response.success) {
            window.PickupPortal.showAlert('Nuovo codice inviato!', 'success');
            startOtpCountdown(response.expires_in || 300);
            document.getElementById('otp').value = '';
            document.getElementById('otp').focus();
        } else {
            throw new Error(response.message || 'Errore durante il reinvio');
        }
    } catch (error) {
        console.error('Resend OTP error:', error);
        window.PickupPortal.showAlert(error.message || 'Errore durante il reinvio', 'danger');
    } finally {
        resendBtn.disabled = false;
        resendBtn.innerHTML = '<i class="fas fa-redo me-2"></i>Invia di nuovo';
    }
}

// Mostra step specifico del login
function showLoginStep(stepId) {
    const steps = document.querySelectorAll('.login-step');
    steps.forEach(step => {
        step.classList.remove('active');
    });
    
    const targetStep = document.getElementById(stepId);
    if (targetStep) {
        targetStep.classList.add('active');
    }
}

// Countdown per OTP
function startOtpCountdown(seconds) {
    const countdownElement = document.getElementById('countdown-text');
    if (!countdownElement) return;
    
    let remaining = seconds;
    
    const updateCountdown = () => {
        const minutes = Math.floor(remaining / 60);
        const secs = remaining % 60;
        countdownElement.textContent = `Codice valido per ${minutes}:${secs.toString().padStart(2, '0')}`;
        
        if (remaining <= 0) {
            countdownElement.textContent = 'Codice scaduto';
            countdownElement.classList.add('text-danger');
        } else {
            remaining--;
            setTimeout(updateCountdown, 1000);
        }
    };
    
    updateCountdown();
}

// Inizializzazione generale
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebarMenu');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarMobileToggle = document.getElementById('sidebarMobileToggle');
    const mobileBreakpoint = window.matchMedia('(max-width: 991.98px)');

    const updateSidebarToggleState = (isCollapsed) => {
        if (!sidebarToggle) {
            return;
        }
        sidebarToggle.setAttribute('aria-expanded', String(!isCollapsed));
        sidebarToggle.setAttribute('aria-label', isCollapsed ? 'Espandi barra laterale' : 'Riduci barra laterale');

        const toggleIcon = sidebarToggle.querySelector('i');
        if (toggleIcon) {
            toggleIcon.classList.toggle('fa-angles-left', !isCollapsed);
            toggleIcon.classList.toggle('fa-angles-right', isCollapsed);
        }
    };

    const syncSidebarState = () => {
        if (!sidebar) {
            return;
        }
        if (mobileBreakpoint.matches) {
            sidebar.classList.remove('collapsed');
            sidebar.classList.remove('open');
            document.body.classList.remove('offcanvas-active');
            document.body.classList.remove('sidebar-collapsed');
            updateSidebarToggleState(false);
            sidebarMobileToggle?.setAttribute('aria-expanded', 'false');
        } else {
            const storedState = localStorage.getItem('pickupPortalSidebar');
            const shouldCollapse = storedState === 'collapsed';
            sidebar.classList.toggle('collapsed', shouldCollapse);
            document.body.classList.toggle('sidebar-collapsed', shouldCollapse);
            updateSidebarToggleState(shouldCollapse);
        }
    };

    const toggleDesktopSidebar = () => {
        if (!sidebar || mobileBreakpoint.matches) {
            return;
        }
        const shouldCollapse = !sidebar.classList.contains('collapsed');
        sidebar.classList.toggle('collapsed', shouldCollapse);
        document.body.classList.toggle('sidebar-collapsed', shouldCollapse);
        updateSidebarToggleState(shouldCollapse);
        localStorage.setItem('pickupPortalSidebar', shouldCollapse ? 'collapsed' : 'expanded');
    };

    const toggleMobileSidebar = () => {
        if (!sidebar) {
            return;
        }
        const willOpen = !sidebar.classList.contains('open');
        sidebar.classList.toggle('open', willOpen);
        document.body.classList.toggle('offcanvas-active', willOpen);
        if (willOpen) {
            document.body.classList.remove('sidebar-collapsed');
        }
        updateSidebarToggleState(!willOpen && sidebar?.classList.contains('collapsed'));
        sidebarMobileToggle?.setAttribute('aria-expanded', String(willOpen));
    };

    syncSidebarState();

    const breakpointListener = mobileBreakpoint.addEventListener ? 'addEventListener' : 'addListener';
    mobileBreakpoint[breakpointListener]('change', syncSidebarState);

    sidebarToggle?.addEventListener('click', () => {
        if (mobileBreakpoint.matches) {
            toggleMobileSidebar();
        } else {
            toggleDesktopSidebar();
        }
    });

    sidebarMobileToggle?.addEventListener('click', toggleMobileSidebar);

    sidebar?.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (!mobileBreakpoint.matches) {
                return;
            }
            sidebar.classList.remove('open');
            document.body.classList.remove('offcanvas-active');
            document.body.classList.remove('sidebar-collapsed');
            updateSidebarToggleState(false);
            sidebarMobileToggle?.setAttribute('aria-expanded', 'false');
        });
    });

    // Inizializza tooltips Bootstrap
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Inizializza popovers Bootstrap
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Auto-hide alerts
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        if (!alert.querySelector('.btn-close')) {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        }
    });
    
    // Gestione click tracking codes per copia
    const trackingCodes = document.querySelectorAll('.package-tracking, .tracking-code');
    trackingCodes.forEach(element => {
        element.addEventListener('click', function() {
            const text = this.textContent.trim();
            window.PickupPortal.copyToClipboard(text);
        });
        
        element.style.cursor = 'pointer';
        element.title = 'Clicca per copiare';
    });
    
    // Gestione form con validazione
    const forms = document.querySelectorAll('form[data-validate="true"]');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
    
    console.log('Pickup Portal initialized successfully');
});

// Service Worker registration (se disponibile)
if (
    'serviceWorker' in navigator &&
    window.PickupPortal &&
    window.PickupPortal.config &&
    window.PickupPortal.config.enableServiceWorker
) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then((registration) => {
                console.log('SW registered: ', registration);
            })
            .catch((registrationError) => {
                console.log('SW registration failed: ', registrationError);
            });
    });
}
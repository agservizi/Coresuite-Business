document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('.pickup-module');
    if (!root) {
        return;
    }

    const statusForms = root.querySelectorAll('[data-pickup-status-form]');
    statusForms.forEach(form => {
        const select = form.querySelector('select[name="status"]');
        if (!select) {
            return;
        }

        select.addEventListener('change', async event => {
            event.preventDefault();
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
            }
            select.disabled = true;

            try {
                const targetUrl = typeof form.action === 'string' && form.action.trim() !== '' ? form.action : window.location.href;
                const response = await fetch(targetUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                    },
                    body: new FormData(form),
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('Aggiornamento stato fallito');
                }

                const raw = await response.text();
                let payload;
                try {
                    payload = raw ? JSON.parse(raw) : {};
                } catch (parseError) {
                    console.error('Pickup status update non-JSON response:', raw);
                    throw new Error('Risposta non valida dal server.');
                }
                if (payload?.success) {
                    const badge = form.closest('tr')?.querySelector('[data-status-badge]');
                    if (badge) {
                        badge.dataset.status = payload.statusKey || '';
                        badge.textContent = payload.statusLabel || '';
                    }
                    const updatedAtCell = form.closest('tr')?.querySelector('[data-updated-at]');
                    if (updatedAtCell) {
                        updatedAtCell.textContent = payload.updatedAt || '';
                    }
                } else {
                    throw new Error(payload?.message || 'Aggiornamento stato non riuscito');
                }
            } catch (error) {
                console.error(error);
                form.classList.add('shake');
                setTimeout(() => form.classList.remove('shake'), 700);
                alert(error.message);
            } finally {
                select.disabled = false;
                if (submitButton) {
                    submitButton.disabled = false;
                }
            }
        });
    });

    const notificationForms = root.querySelectorAll('[data-pickup-notification-form]');
    notificationForms.forEach(form => {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const submit = form.querySelector('button[type="submit"]');
            if (submit) {
                submit.disabled = true;
            }
            try {
                const targetUrl = typeof form.action === 'string' && form.action.trim() !== '' ? form.action : window.location.href;
                const response = await fetch(targetUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                    },
                    body: new FormData(form),
                    credentials: 'same-origin',
                });

                const raw = await response.text();
                let payload;
                try {
                    payload = raw ? JSON.parse(raw) : {};
                } catch (parseError) {
                    console.error('Pickup notification non-JSON response:', raw);
                    throw new Error('Risposta non valida dal server.');
                }

                if (!response.ok || !payload?.success) {
                    throw new Error(payload?.message || 'Invio notifica fallito');
                }

                const logTarget = document.querySelector('[data-notification-log]');
                if (logTarget && payload.entryHtml) {
                    logTarget.insertAdjacentHTML('afterbegin', payload.entryHtml);
                }
                if (payload.fallbackUrl) {
                    const opened = window.open(payload.fallbackUrl, '_blank');
                    if (!opened) {
                        alert((payload.message || 'Apri WhatsApp per completare l\'invio') + '\n\nLink: ' + payload.fallbackUrl);
                        return;
                    }
                }
                alert(payload.message || 'Notifica inviata');
            } catch (error) {
                console.error(error);
                alert(error.message);
            } finally {
                if (submit) {
                    submit.disabled = false;
                }
            }
        });
    });

    const packageSelects = root.querySelectorAll('[data-pickup-package-select]');
    packageSelects.forEach(select => {
        const form = select.closest('form');
        if (!form) {
            return;
        }
        const channelField = form.querySelector('input[name="channel"]');
        const isWhatsApp = channelField && channelField.value === 'whatsapp';
        const phoneField = isWhatsApp ? form.querySelector('input[name="recipient"]') : null;
        const messageField = isWhatsApp ? form.querySelector('textarea[name="message"]') : null;

        const updateFromSelection = () => {
            const option = select.options[select.selectedIndex];
            if (!option) {
                return;
            }

            if (option.value === '') {
                if (isWhatsApp) {
                    if (phoneField) {
                        phoneField.value = '';
                    }
                    if (messageField) {
                        messageField.value = '';
                    }
                }
                return;
            }

            if (isWhatsApp && phoneField && option.dataset.phone) {
                phoneField.value = option.dataset.phone;
            }

            if (isWhatsApp && messageField && option.dataset.message) {
                messageField.value = option.dataset.message;
            }
        };

        select.addEventListener('change', updateFromSelection);
        updateFromSelection();
    });

    const archiveButton = document.querySelector('[data-pickup-archive-button]');
    if (archiveButton) {
        archiveButton.addEventListener('click', async event => {
            event.preventDefault();
            const days = archiveButton.dataset.days || '30';
            if (!confirm(`Archiviare i pacchi ritirati da più di ${days} giorni?`)) {
                return;
            }
            archiveButton.disabled = true;
            try {
                const formData = new FormData();
                formData.append('action', 'archive_packages');
                formData.append('_token', archiveButton.dataset.csrf || '');

                const response = await fetch(archiveButton.dataset.action || window.location.href, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                    },
                    body: formData,
                    credentials: 'same-origin',
                });
                const raw = await response.text();
                let payload;
                try {
                    payload = raw ? JSON.parse(raw) : {};
                } catch (parseError) {
                    console.error('Pickup archive non-JSON response:', raw);
                    throw new Error('Risposta non valida dal server.');
                }
                if (!response.ok || !payload?.success) {
                    throw new Error(payload?.message || 'Archiviazione non riuscita');
                }
                alert(payload.message || 'Archiviazione completata');
                window.location.reload();
            } catch (error) {
                console.error(error);
                alert(error.message);
            } finally {
                archiveButton.disabled = false;
            }
        });
    }
});

import './bootstrap';
import { renderBarcodeSvg } from './barcode';


const root = document.documentElement;
const applyTheme = (theme) => {
    const resolved = theme === 'system' ? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : theme;
    root.dataset.theme = resolved;
    localStorage.setItem('theme', theme);
};
applyTheme(localStorage.getItem('theme') || document.body?.dataset.defaultTheme || 'system');

const updateTimeGreetings = (scope = document) => {
    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';

    scope.querySelectorAll('[data-time-greeting]').forEach((element) => {
        element.textContent = `${greeting}, ${element.dataset.userName}`;
    });
};

const initializeAuthVisualRotator = (scope = document) => {
    const rotator = scope.querySelector('[data-auth-visual-rotator]');
    if (!rotator || rotator.dataset.rotatorInitialized === 'true' || matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const messages = [
        {
            kicker: 'Secure RHU inventory',
            title: 'Essential medicines.<br>Always accounted for.',
            description: 'Proverbs 17:22 — “A cheerful heart is good medicine, but a crushed spirit dries up the bones.”',
        },
        {
            kicker: 'Care starts with readiness',
            title: 'Ready shelves.<br>Ready for every patient.',
            description: 'Colossians 3:23 — “Whatever you do, work at it with all your heart, as working for the Lord.”',
        },
        {
            kicker: 'Built for your RHU team',
            title: 'Better inventory.<br>Stronger community care.',
            description: 'Luke 10:9 — “Heal the sick who are there and tell them, ‘The kingdom of God has come near to you.’”',
        },
    ];
    const kicker = rotator.querySelector('[data-auth-visual-kicker]');
    const title = rotator.querySelector('[data-auth-visual-title]');
    const description = rotator.querySelector('[data-auth-visual-description]');
    let currentMessage = 0;

    const updateMessage = () => {
        currentMessage = (currentMessage + 1) % messages.length;
        const message = messages[currentMessage];
        rotator.classList.add('is-changing');

        window.setTimeout(() => {
            kicker.textContent = message.kicker;
            title.innerHTML = message.title;
            description.textContent = message.description;
            window.requestAnimationFrame(() => rotator.classList.remove('is-changing'));
        }, 440);
    };

    rotator.dataset.rotatorInitialized = 'true';
    window.setInterval(updateMessage, 6000);
};

const initializeMedicineBulkSelection = (scope = document) => {
    scope.querySelectorAll('[data-medicine-bulk-selection]').forEach((form) => {
        if (form.dataset.selectionInitialized === 'true') return;

        const selectAll = form.querySelector('[data-medicine-selection-all]');
        const selectionCount = form.querySelector('[data-medicine-selection-count]');
        const clearButton = form.querySelector('[data-medicine-selection-clear]');
        const archiveButton = form.querySelector('[data-medicine-selection-archive]');
        const selectionItems = [...form.querySelectorAll('[data-medicine-selection-item]')];
        const toggleButton = scope.querySelector(`[data-medicine-selection-toggle="${form.id}"]`);

        const updateSelection = () => {
            const selectedItems = selectionItems.filter((item) => item.checked);
            const selectedCount = selectedItems.length;

            selectionCount.textContent = selectedCount;
            archiveButton.disabled = selectedCount === 0;
            clearButton.disabled = selectedCount === 0;
            selectAll.checked = selectionItems.length > 0 && selectedCount === selectionItems.length;
            selectAll.indeterminate = selectedCount > 0 && selectedCount < selectionItems.length;
            selectionItems.forEach((item) => item.closest('tr')?.classList.toggle('is-selected', item.checked));
        };

        selectAll?.addEventListener('change', () => {
            selectionItems.forEach((item) => { item.checked = selectAll.checked; });
            updateSelection();
        });
        selectionItems.forEach((item) => item.addEventListener('change', updateSelection));
        clearButton?.addEventListener('click', () => {
            selectionItems.forEach((item) => { item.checked = false; });
            updateSelection();
        });
        toggleButton?.addEventListener('click', () => {
            const isManaging = !form.classList.contains('is-managing');
            form.classList.toggle('is-managing', isManaging);
            toggleButton.textContent = isManaging ? 'Done' : 'Manage medicines';
            toggleButton.setAttribute('aria-pressed', isManaging ? 'true' : 'false');

            if (!isManaging) {
                selectionItems.forEach((item) => { item.checked = false; });
                updateSelection();
            }
        });

        form.dataset.selectionInitialized = 'true';
        updateSelection();
    });
};

const initializeDispenseRecipientFields = (scope = document) => {
    scope.querySelectorAll('[data-dispense-type]').forEach((select) => {
        if (select.dataset.recipientInitialized === 'true') return;

        const recipientField = document.querySelector(select.dataset.recipientTarget);
        const recipientInput = recipientField?.querySelector('input[name="recipient"]');
        if (!recipientField || !recipientInput) return;

        const updateRecipientField = () => {
            const isPatientDispensing = select.value === 'dispensed';

            recipientField.hidden = !isPatientDispensing;
            recipientInput.required = isPatientDispensing;
        };

        select.addEventListener('change', updateRecipientField);
        select.dataset.recipientInitialized = 'true';
        updateRecipientField();
    });
};

const initializeBoxReleaseAvailability = (scope = document) => {
    scope.querySelectorAll('[data-box-release-medicine]').forEach((medicineSelect) => {
        if (medicineSelect.dataset.boxReleaseInitialized === 'true') return;

        const quantityUnitSelect = document.querySelector(medicineSelect.dataset.boxUnitTarget);
        const helpText = document.querySelector(medicineSelect.dataset.boxHelpTarget);
        const reminder = document.querySelector(medicineSelect.dataset.boxReminderTarget);
        const reminderMessage = reminder?.querySelector('[data-box-quantity-message]');
        const quantityInput = medicineSelect.form?.querySelector('input[name="quantity"]');
        const boxOption = quantityUnitSelect?.querySelector('option[value="box"]');
        if (!quantityUnitSelect || !boxOption) return;

        const updateBoxReminder = () => {
            const selectedMedicine = medicineSelect.selectedOptions[0];
            const isBoxQuantity = quantityUnitSelect.value === 'box';
            const unitsPerBox = Number(selectedMedicine?.dataset.boxSize || 0);
            const availableUnits = Number(selectedMedicine?.dataset.availableUnits || 0);
            const inventoryUnit = selectedMedicine?.dataset.inventoryUnit || 'units';
            const enteredBoxes = Number(quantityInput?.value || 0);

            if (!reminder || !reminderMessage) return;

            reminder.hidden = !isBoxQuantity;
            if (!isBoxQuantity) return;

            const fullBoxesAvailable = Math.floor(availableUnits / unitsPerBox);
            const requestedUnits = enteredBoxes > 0 ? ` ${enteredBoxes} box${enteredBoxes === 1 ? '' : 'es'} = ${enteredBoxes * unitsPerBox} ${inventoryUnit}.` : '';
            reminderMessage.textContent = `Box reminder: 1 box = ${unitsPerBox} ${inventoryUnit}. Available: ${availableUnits} ${inventoryUnit} (${fullBoxesAvailable} full box${fullBoxesAvailable === 1 ? '' : 'es'}).${requestedUnits}`;
        };

        const updateBoxAvailability = () => {
            const selectedMedicine = medicineSelect.selectedOptions[0];
            const boxesAvailable = selectedMedicine?.dataset.boxesAvailable === 'true';

            boxOption.disabled = !boxesAvailable;
            if (!boxesAvailable && quantityUnitSelect.value === 'box') {
                quantityUnitSelect.value = 'unit';
            }

            if (helpText) {
                helpText.textContent = boxesAvailable
                    ? 'Boxes are available while at least 100 non-expired units are in stock.'
                    : 'Boxes are unavailable because this medicine has fewer than 100 non-expired units or no units-per-box setting.';
            }

            updateBoxReminder();
        };

        medicineSelect.addEventListener('change', updateBoxAvailability);
        quantityUnitSelect.addEventListener('change', updateBoxReminder);
        quantityInput?.addEventListener('input', updateBoxReminder);
        medicineSelect.dataset.boxReleaseInitialized = 'true';
        updateBoxAvailability();
    });
};

const initializeBoxReceiptReminder = (scope = document) => {
    scope.querySelectorAll('[data-box-receipt-medicine]').forEach((medicineSelect) => {
        if (medicineSelect.dataset.boxReceiptInitialized === 'true') return;

        const quantityUnitSelect = document.querySelector(medicineSelect.dataset.boxUnitTarget);
        const helpText = document.querySelector(medicineSelect.dataset.boxHelpTarget);
        const reminder = document.querySelector(medicineSelect.dataset.boxReminderTarget);
        const reminderMessage = reminder?.querySelector('[data-box-receipt-message]');
        const quantityInput = medicineSelect.form?.querySelector('input[name="quantity"]');
        const boxOption = quantityUnitSelect?.querySelector('option[value="box"]');
        if (!quantityUnitSelect || !boxOption) return;

        const updateReminder = () => {
            const selectedMedicine = medicineSelect.selectedOptions[0];
            const unitsPerBox = Number(selectedMedicine?.dataset.boxSize || 0);
            const inventoryUnit = selectedMedicine?.dataset.inventoryUnit || 'units';
            const enteredBoxes = Number(quantityInput?.value || 0);
            const isBoxQuantity = quantityUnitSelect.value === 'box';

            if (!reminder || !reminderMessage) return;

            reminder.hidden = !isBoxQuantity;
            if (!isBoxQuantity) return;

            const receivedUnits = enteredBoxes > 0
                ? ` Receiving ${enteredBoxes} box${enteredBoxes === 1 ? '' : 'es'} adds ${enteredBoxes * unitsPerBox} ${inventoryUnit}.`
                : '';
            reminderMessage.textContent = `Box reminder: 1 box = ${unitsPerBox} ${inventoryUnit}.${receivedUnits} Unit cost is per individual inventory unit, not per box.`;
        };

        const updateBoxAvailability = () => {
            const selectedMedicine = medicineSelect.selectedOptions[0];
            const unitsPerBox = Number(selectedMedicine?.dataset.boxSize || 0);
            const boxesConfigured = Number.isInteger(unitsPerBox) && unitsPerBox > 0;

            boxOption.disabled = !boxesConfigured;
            if (!boxesConfigured && quantityUnitSelect.value === 'box') {
                quantityUnitSelect.value = 'unit';
            }

            if (helpText) {
                helpText.textContent = boxesConfigured
                    ? 'Boxes are converted using the medicine\'s units-per-box setting. Unit cost remains per individual inventory unit.'
                    : 'Boxes are unavailable until this medicine has a units-per-box setting.';
            }

            updateReminder();
        };

        medicineSelect.addEventListener('change', updateBoxAvailability);
        quantityUnitSelect.addEventListener('change', updateReminder);
        quantityInput?.addEventListener('input', updateReminder);
        medicineSelect.dataset.boxReceiptInitialized = 'true';
        updateBoxAvailability();
    });
};

const submitFilterWithoutReloadingLayout = (input) => {
    const form = input.form;
    if (!form) return;

    if (form.method.toUpperCase() !== 'GET' || typeof window.medistockNavigateSection !== 'function') {
        form.requestSubmit();
        return;
    }

    const destination = new URL(form.action || window.location.href, window.location.href);
    const formData = new FormData(form);
    destination.search = '';

    formData.forEach((value, key) => {
        if (value instanceof File || value === '') return;
        destination.searchParams.append(key, value);
    });

    void window.medistockNavigateSection(destination.href);
};

const initializeAutoSubmitFilters = (scope = document) => {
    scope.querySelectorAll('[data-auto-submit]').forEach((input) => {
        if (input.dataset.autoSubmitInitialized === 'true') return;

        input.addEventListener('change', () => submitFilterWithoutReloadingLayout(input));
        input.dataset.autoSubmitInitialized = 'true';
    });
};

const initializePageContent = (scope) => {
    updateTimeGreetings(scope);
    scope.querySelectorAll('[data-barcode-preview]').forEach((svg) => {
        const barcodeType = renderBarcodeSvg(svg, svg.dataset.barcodeValue);
        const barcodeHint = document.getElementById(svg.dataset.barcodeHint);

        if (barcodeHint) {
            barcodeHint.textContent = barcodeType === 'Unsupported barcode'
                ? 'This barcode value cannot be rendered.'
                : `${barcodeType} barcode - scan-ready from this screen or a printed label.`;
        }
    });

    scope.querySelectorAll('[data-dismiss]').forEach((button) => button.addEventListener('click', () => button.closest('[data-dismissible]')?.remove()));
    scope.querySelectorAll('[data-tabs]').forEach((tabs) => tabs.querySelectorAll('[data-tab]').forEach((tab) => tab.addEventListener('click', () => {
        tabs.querySelectorAll('[data-tab]').forEach((item) => item.classList.toggle('active', item === tab));
        tabs.parentElement.querySelectorAll('[data-tab-panel]').forEach((panel) => panel.classList.toggle('active', panel.dataset.tabPanel === tab.dataset.tab));
    })));
    scope.querySelectorAll('[data-medicine-select]').forEach((select) => {
        const batchSelect = document.querySelector(select.dataset.batchTarget);
        const update = () => {
            if (!batchSelect) return;
            [...batchSelect.options].forEach((option) => { option.hidden = option.value && option.dataset.medicine !== select.value; });
            if (batchSelect.selectedOptions[0]?.hidden) batchSelect.value = '';
        };
        select.addEventListener('change', update);
        update();
    });
    initializeAutoSubmitFilters(scope);
    initializeMedicineBulkSelection(scope);
    initializeDispenseRecipientFields(scope);
    initializeBoxReleaseAvailability(scope);
    initializeBoxReceiptReminder(scope);
    renderCharts(scope);
};

document.addEventListener('DOMContentLoaded', () => {
    updateTimeGreetings();
    window.setInterval(updateTimeGreetings, 60000);
    initializeAuthVisualRotator();
    document.querySelectorAll('[data-barcode-preview]').forEach((svg) => {
        const barcodeType = renderBarcodeSvg(svg, svg.dataset.barcodeValue);
        const barcodeHint = document.getElementById(svg.dataset.barcodeHint);

        if (barcodeHint) {
            barcodeHint.textContent = barcodeType === 'Unsupported barcode'
                ? 'This barcode value cannot be rendered.'
                : `${barcodeType} barcode - scan-ready from this screen or a printed label.`;
        }
    });

    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => button.addEventListener('click', () => {
        if (innerWidth <= 980) document.body.classList.toggle('sidebar-open');
        else {
            document.body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebar-collapsed', document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
        }
    }));
    if (innerWidth > 980 && localStorage.getItem('sidebar-collapsed') === '1') document.body.classList.add('sidebar-collapsed');
    document.querySelector('[data-mobile-overlay]')?.addEventListener('click', () => document.body.classList.remove('sidebar-open'));
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => button.addEventListener('click', () => { applyTheme(root.dataset.theme === 'dark' ? 'light' : 'dark'); renderCharts(); }));

    document.querySelectorAll('[data-dropdown]').forEach((wrapper) => {
        const trigger = wrapper.querySelector('[data-dropdown-trigger]');
        const menu = wrapper.querySelector('[data-dropdown-menu]');
        trigger?.addEventListener('click', (event) => {
            event.stopPropagation();
            const willOpen = menu.hidden;
            document.querySelectorAll('[data-dropdown-menu]').forEach((other) => {
                other.hidden = true;
                other.closest('[data-dropdown]')?.querySelector('[data-dropdown-trigger]')?.setAttribute('aria-expanded', 'false');
            });
            menu.hidden = !willOpen;
            trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
    });
    document.addEventListener('click', (event) => document.querySelectorAll('[data-dropdown-menu]').forEach((menu) => {
        if (!menu.closest('[data-dropdown]')?.contains(event.target)) {
            menu.hidden = true;
            menu.closest('[data-dropdown]')?.querySelector('[data-dropdown-trigger]')?.setAttribute('aria-expanded', 'false');
        }
    }));

    const medicineListPath = window.location.pathname.replace(/\/$/, '');
    if (medicineListPath === '/medicines') {
        if (new URLSearchParams(window.location.search).get('expiration') === 'expired') {
            document.querySelectorAll('.data-table .badge-success').forEach((badge) => {
                badge.classList.remove('badge-success');
                badge.classList.add('badge-danger');
                badge.textContent = 'Expired';
            });
        }

        const stockStatusTarget = (badge) => {
            const status = badge.textContent.trim().toLowerCase();
            if (status === 'normal') return badge.closest('tr')?.querySelector('.table-primary')?.href ?? null;
            if (!['critical', 'out of stock', 'expired'].includes(status)) return null;

            const target = new URL(window.location.href);
            target.search = '';
            target.hash = '';
            if (status === 'expired') {
                target.searchParams.set('expiration', 'expired');
            } else {
                target.searchParams.set('stock_status', status === 'critical' ? 'critical' : 'out');
            }

            return target.href;
        };

        document.querySelectorAll('.data-table .badge-success, .data-table .badge-danger').forEach((badge) => {
            const target = stockStatusTarget(badge);
            if (!target) return;

            badge.classList.add('badge-clickable');
            badge.setAttribute('role', 'link');
            badge.setAttribute('tabindex', '0');
            badge.setAttribute('title', badge.textContent.trim().toLowerCase() === 'normal' ? 'View medicine information' : 'Manage medicines with this stock status');
            badge.addEventListener('click', () => { window.location.href = target; });
            badge.addEventListener('keydown', (event) => {
                if (!['Enter', ' '].includes(event.key)) return;
                event.preventDefault();
                window.location.href = target;
            });
        });
    }
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('[data-dropdown-menu]').forEach((menu) => {
            menu.hidden = true;
            menu.closest('[data-dropdown]')?.querySelector('[data-dropdown-trigger]')?.setAttribute('aria-expanded', 'false');
        });
    });
    document.querySelectorAll('[data-dismiss]').forEach((button) => button.addEventListener('click', () => button.closest('[data-dismissible]')?.remove()));

    const confirmDialog = document.querySelector('#confirm-dialog'); let pendingForm = null;
    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-confirm]');
        if (!form || form.dataset.confirmed === '1') return;
        event.preventDefault(); pendingForm = form;
        confirmDialog.querySelector('[data-confirm-message]').textContent = form.dataset.confirm || 'This action cannot be undone.';
        confirmDialog.showModal();
    });
    confirmDialog?.querySelector('[data-confirm-submit]')?.addEventListener('click', () => { if (pendingForm) { pendingForm.dataset.confirmed = '1'; pendingForm.requestSubmit(); } confirmDialog.close(); });
    confirmDialog?.querySelector('[data-confirm-cancel]')?.addEventListener('click', () => confirmDialog.close());

    document.querySelectorAll('[data-tabs]').forEach((tabs) => tabs.querySelectorAll('[data-tab]').forEach((tab) => tab.addEventListener('click', () => {
        tabs.querySelectorAll('[data-tab]').forEach((item) => item.classList.toggle('active', item === tab));
        tabs.parentElement.querySelectorAll('[data-tab-panel]').forEach((panel) => panel.classList.toggle('active', panel.dataset.tabPanel === tab.dataset.tab));
    })));

    const search = document.querySelector('[data-global-search]'); const results = document.querySelector('[data-search-results]'); let timer;
    search?.addEventListener('input', () => {
        clearTimeout(timer); const query = search.value.trim(); if (query.length < 2) { results.hidden = true; return; }
        timer = setTimeout(async () => {
            try {
                const response = await fetch(`${search.dataset.url}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
                const groups = await response.json(); const items = Object.values(groups).flat();
                results.innerHTML = items.length ? items.map((item) => `<a class="search-result" href="${item.url}"><span class="notification-icon">⌕</span><div><strong>${escapeHtml(item.label)}</strong><span>${escapeHtml(item.meta)}</span></div></a>`).join('') : '<div class="empty-state" style="padding:24px"><p>No matching records found.</p></div>';
                results.hidden = false;
            } catch (_) { results.hidden = true; }
        }, 220);
    });
    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); search?.focus(); }
        if (event.key === 'Escape') { if (results) results.hidden = true; if (confirmDialog?.open) confirmDialog.close(); }
    });

    document.querySelectorAll('[data-medicine-select]').forEach((select) => {
        const batchSelect = document.querySelector(select.dataset.batchTarget);
        const update = () => { if (!batchSelect) return; [...batchSelect.options].forEach((option) => { option.hidden = option.value && option.dataset.medicine !== select.value; }); if (batchSelect.selectedOptions[0]?.hidden) batchSelect.value = ''; };
        select.addEventListener('change', update); update();
    });
    initializeAutoSubmitFilters();
    initializeMedicineBulkSelection();
    initializeDispenseRecipientFields();
    initializeBoxReleaseAvailability();
    initializeBoxReceiptReminder();

    const liveSearch = document.querySelector('[data-live-global-search]');
    const liveSearchResults = document.querySelector('[data-live-search-results]');
    let liveSearchTimer;
    let liveSearchRequest;
    let activeSearchResult = -1;

    const setLiveSearchVisibility = (isVisible) => {
        if (!liveSearch || !liveSearchResults) return;
        liveSearchResults.hidden = !isVisible;
        liveSearch.setAttribute('aria-expanded', isVisible ? 'true' : 'false');
    };
    const renderSearchState = (message, isLoading = false) => {
        if (!liveSearchResults) return;
        liveSearchResults.innerHTML = `<div class="search-state${isLoading ? ' is-loading' : ''}">${isLoading ? '<span class="search-spinner" aria-hidden="true"></span>' : ''}<span>${escapeHtml(message)}</span></div>`;
        setLiveSearchVisibility(true);
    };
    const searchIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>';
    const renderLiveSearchResults = (groups) => {
        if (!liveSearchResults) return;
        const groupLabels = { medicines: 'Medicines', batches: 'Batches', suppliers: 'Suppliers' };
        const content = Object.entries(groups).map(([group, items]) => {
            if (!Array.isArray(items) || items.length === 0) return '';
            const links = items.map((item) => `<a class="search-result" href="${escapeHtml(item.url)}" role="option"><span class="search-result-icon">${searchIcon}</span><span class="search-result-copy"><strong>${escapeHtml(item.label)}</strong><span>${escapeHtml(item.meta)}</span></span></a>`).join('');
            return `<section class="search-group"><div class="search-group-label">${escapeHtml(groupLabels[group] || group)}</div>${links}</section>`;
        }).join('');
        liveSearchResults.innerHTML = content || '<div class="search-state"><span>No matching records found.</span></div>';
        activeSearchResult = -1;
        setLiveSearchVisibility(true);
    };

    liveSearch?.addEventListener('input', () => {
        clearTimeout(liveSearchTimer);
        liveSearchRequest?.abort();
        const query = liveSearch.value.trim();
        if (!query) {
            liveSearchResults.innerHTML = '';
            setLiveSearchVisibility(false);
            return;
        }
        renderSearchState('Searching…', true);
        liveSearchTimer = setTimeout(async () => {
            liveSearchRequest = new AbortController();
            try {
                const response = await fetch(`${liveSearch.dataset.url}?q=${encodeURIComponent(query)}`, {
                    headers: { Accept: 'application/json' },
                    signal: liveSearchRequest.signal,
                });
                if (!response.ok) throw new Error('Search request failed.');
                renderLiveSearchResults(await response.json());
            } catch (error) {
                if (error.name !== 'AbortError') renderSearchState('Search is temporarily unavailable. Please try again.');
            }
        }, 220);
    });
    liveSearch?.addEventListener('focus', () => {
        if (liveSearch.value.trim() && liveSearchResults?.innerHTML) setLiveSearchVisibility(true);
    });
    liveSearch?.addEventListener('keydown', (event) => {
        const options = [...(liveSearchResults?.querySelectorAll('.search-result') || [])];
        if (!options.length || !['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)) return;
        if (event.key === 'Enter' && activeSearchResult >= 0) {
            event.preventDefault();
            options[activeSearchResult].click();
            return;
        }
        if (event.key === 'Enter') return;
        event.preventDefault();
        activeSearchResult = event.key === 'ArrowDown'
            ? (activeSearchResult + 1) % options.length
            : (activeSearchResult <= 0 ? options.length - 1 : activeSearchResult - 1);
        options.forEach((option, index) => option.classList.toggle('is-active', index === activeSearchResult));
        options[activeSearchResult].scrollIntoView({ block: 'nearest' });
    });
    document.addEventListener('click', (event) => {
        if (!event.target.closest('.global-search')) setLiveSearchVisibility(false);
    });
    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            liveSearch?.focus();
        }
        if (event.key === 'Escape') setLiveSearchVisibility(false);
    });

    document.querySelectorAll('form.card.filter-bar[method="GET"]').forEach((form) => {
        const input = form.querySelector('input[name="search"], input[name="q"]');
        if (!input) return;
        let filterTimer;
        input.addEventListener('input', () => {
            clearTimeout(filterTimer);
            const query = input.value.trim();
            form.classList.add('is-live-searching');
            filterTimer = setTimeout(() => {
                sessionStorage.setItem('live-search-focus', window.location.pathname);
                form.requestSubmit();
            }, 450);
        });
        if (sessionStorage.getItem('live-search-focus') === window.location.pathname) {
            sessionStorage.removeItem('live-search-focus');
            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);
        }
    });

    document.querySelectorAll('[data-password-toggle]').forEach((button) => button.addEventListener('click', () => {
        const input = button.parentElement.querySelector('input');
        if (!input) return;
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        button.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
    }));

    document.querySelectorAll('[data-password-strength]').forEach((input) => {
        const indicator = input.closest('.field')?.querySelector('[data-password-strength-status]');
        const updateStrength = () => {
            if (!indicator) return;
            const value = input.value;
            const score = [value.length >= 8, /[a-z]/.test(value), /[A-Z]/.test(value), /\d/.test(value), /[^\w\s]/.test(value)].filter(Boolean).length;
            indicator.dataset.strength = !value ? '' : score <= 2 ? 'weak' : score === 3 ? 'fair' : score === 4 ? 'good' : 'strong';
        };
        input.addEventListener('input', updateStrength);
        updateStrength();
    });

    document.querySelectorAll('form[data-auth-form]').forEach((form) => form.addEventListener('submit', () => {
        if (form.checkValidity()) form.classList.add('is-submitting');
    }));

    document.querySelectorAll('[data-auth-switch]').forEach((link) => link.addEventListener('click', (event) => {
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const card = document.querySelector('[data-auth-card]');
        if (!card) return;
        event.preventDefault(); card.style.animation = 'none'; card.offsetHeight; card.style.animation = 'auth-card-out .22s ease forwards';
        setTimeout(() => { window.location.href = link.href; }, 180);
    }));

    const sidebarNavigation = document.querySelector('[data-sidebar-navigation]');
    const navigationLoader = document.getElementById('page-navigation-loader');
    let pageNavigationController = null;
    let pageNavigationSequence = 0;

    const setPageNavigationLoading = (isLoading) => {
        if (!navigationLoader) return;
        navigationLoader.hidden = !isLoading;
        requestAnimationFrame(() => navigationLoader.classList.toggle('is-visible', isLoading));
    };

    const updateSidebarNavigation = (incomingDocument, destination) => {
        const incomingNavigation = incomingDocument.querySelector('[data-sidebar-navigation]');
        if (!sidebarNavigation || !incomingNavigation) return;

        const incomingLinks = [...incomingNavigation.querySelectorAll('.nav-link')];
        sidebarNavigation.querySelectorAll('.nav-link').forEach((link) => {
            const matchingLink = incomingLinks.find((incomingLink) => incomingLink.href === link.href);
            link.classList.toggle('active', matchingLink?.classList.contains('active') ?? link.href === destination);
        });
    };

    const runIncomingPageScripts = (incomingDocument) => {
        incomingDocument.querySelectorAll('body > script:not([src])').forEach((sourceScript) => {
            const script = document.createElement('script');
            script.textContent = sourceScript.textContent;
            document.body.append(script);
            script.remove();
        });
    };

    const navigateSidebarSection = async (url, shouldPushHistory = true) => {
        const destination = new URL(url, window.location.href);
        if (shouldPushHistory && destination.href === window.location.href) return;

        pageNavigationController?.abort();
        pageNavigationController = new AbortController();
        const navigationSequence = ++pageNavigationSequence;
        const startedAt = performance.now();
        setPageNavigationLoading(true);

        try {
            const response = await fetch(destination, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: pageNavigationController.signal,
            });
            if (!response.ok) throw new Error('Section request failed.');

            const incomingDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
            const incomingPage = incomingDocument.querySelector('.app-main > .page');
            const currentPage = document.querySelector('.app-main > .page');
            if (!incomingPage || !currentPage) throw new Error('Section content was not found.');

            window.medistockScannerCleanup?.();
            currentPage.replaceWith(incomingPage);
            document.title = incomingDocument.title;
            updateSidebarNavigation(incomingDocument, destination.href);
            initializePageContent(incomingPage);
            runIncomingPageScripts(incomingDocument);
            document.body.classList.remove('sidebar-open');
            window.scrollTo({ top: 0, behavior: 'smooth' });

            if (shouldPushHistory) {
                window.history.pushState({ sidebarSection: true }, '', destination);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                window.location.assign(destination.href);
            }
        } finally {
            const remainingTime = Math.max(0, 220 - (performance.now() - startedAt));
            window.setTimeout(() => {
                if (navigationSequence === pageNavigationSequence) setPageNavigationLoading(false);
            }, remainingTime);
        }
    };

    window.medistockNavigateSection = navigateSidebarSection;

    sidebarNavigation?.addEventListener('click', (event) => {
        const link = event.target.closest('.nav-link');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target || link.hasAttribute('download')) return;
        if (new URL(link.href).origin !== window.location.origin) return;

        event.preventDefault();
        void navigateSidebarSection(link.href);
    });

    window.addEventListener('popstate', () => {
        void navigateSidebarSection(window.location.href, false);
    });

    renderCharts();
});

const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#39;', '"':'&quot;' })[character]);
function renderCharts(scope = document) { scope.querySelectorAll('canvas[data-chart]').forEach((canvas) => drawChart(canvas, JSON.parse(canvas.dataset.chart))); }
function drawChart(canvas, config) {
    const ratio = devicePixelRatio || 1; const rect = canvas.getBoundingClientRect(); if (!rect.width || !rect.height) return;
    canvas.width = rect.width * ratio; canvas.height = rect.height * ratio; const context = canvas.getContext('2d'); context.scale(ratio, ratio);
    const width = rect.width; const height = rect.height; const styles = getComputedStyle(root); const grid = styles.getPropertyValue('--border').trim(); const muted = styles.getPropertyValue('--muted').trim();
    context.font = '10px system-ui'; context.fillStyle = muted; if (config.type === 'doughnut') return drawDoughnut(context, width, height, config, muted);
    const padding = { top:15, right:12, bottom:34, left:39 }; const chartWidth = width - padding.left - padding.right; const chartHeight = height - padding.top - padding.bottom;
    const values = config.datasets.flatMap((dataset) => dataset.data.map(Number)); const maximum = Math.max(...values, 1) * 1.15; context.strokeStyle = grid;
    for (let i = 0; i <= 4; i++) { const y = padding.top + chartHeight * i / 4; context.beginPath(); context.moveTo(padding.left, y); context.lineTo(width - padding.right, y); context.stroke(); context.fillStyle = muted; context.textAlign = 'left'; context.fillText(Math.round(maximum * (1 - i / 4)), 3, y + 3); }
    const groupWidth = chartWidth / Math.max(config.labels.length, 1);
    config.labels.forEach((label, index) => { if (config.labels.length <= 8 || index % Math.ceil(config.labels.length / 7) === 0) { context.fillStyle = muted; context.textAlign = 'center'; context.fillText(label, padding.left + groupWidth * index + groupWidth / 2, height - 10); } });
    if (config.type === 'line') config.datasets.forEach((dataset) => { context.strokeStyle = dataset.color; context.lineWidth = 2; context.beginPath(); dataset.data.forEach((value, index) => { const x = padding.left + groupWidth * index + groupWidth / 2; const y = padding.top + chartHeight - Number(value) / maximum * chartHeight; index ? context.lineTo(x, y) : context.moveTo(x, y); }); context.stroke(); });
    else {
        const bars = []; const barWidth = Math.min(24, groupWidth * .68 / config.datasets.length);
        config.datasets.forEach((dataset, datasetIndex) => dataset.data.forEach((value, index) => {
            const numericValue = Number(value); const barHeight = numericValue / maximum * chartHeight; const x = padding.left + groupWidth * index + (groupWidth - barWidth * config.datasets.length) / 2 + barWidth * datasetIndex; const y = padding.top + chartHeight - barHeight; const renderedWidth = Math.max(barWidth - 2, 2);
            context.fillStyle = dataset.color; context.beginPath(); context.roundRect(x, y, renderedWidth, barHeight, 4); context.fill();
            if (numericValue > 0) bars.push({ color: dataset.color, date: config.labels[index], label: dataset.label || `Series ${datasetIndex + 1}`, value: numericValue, x, y, width: renderedWidth, height: barHeight });
        }));
        attachBarChartTooltip(canvas, bars);
    }
}
function attachBarChartTooltip(canvas, bars) {
    const chartWrap = canvas.parentElement; if (!chartWrap) return;
    chartWrap.querySelector(':scope > .chart-tooltip')?.remove();
    const tooltip = document.createElement('div'); tooltip.className = 'chart-tooltip'; tooltip.hidden = true; chartWrap.append(tooltip);
    const hideTooltip = () => { tooltip.hidden = true; canvas.style.cursor = ''; };
    canvas.addEventListener('mouseleave', hideTooltip);
    canvas.addEventListener('mousemove', (event) => {
        const bounds = canvas.getBoundingClientRect(); const x = event.clientX - bounds.left; const y = event.clientY - bounds.top;
        const bar = bars.find((item) => x >= item.x - 5 && x <= item.x + item.width + 5 && y >= item.y - 3 && y <= item.y + item.height + 3);
        if (!bar) return hideTooltip();
        canvas.style.cursor = 'pointer'; tooltip.hidden = false;
        tooltip.innerHTML = `<span class="chart-tooltip-label"><i style="background:${escapeHtml(bar.color)}"></i>${escapeHtml(bar.label)}</span><strong>${bar.value.toLocaleString()} units</strong><span>${escapeHtml(bar.date)}</span>`;
        const tooltipWidth = tooltip.offsetWidth; tooltip.style.left = `${Math.min(Math.max(bar.x + bar.width / 2 - tooltipWidth / 2, 8), chartWrap.clientWidth - tooltipWidth - 8)}px`; tooltip.style.top = `${Math.max(bar.y - tooltip.offsetHeight - 10, 8)}px`;
    });
}
function drawDoughnut(context, width, height, config, muted) {
    const x = width / 2; const y = height / 2; const radius = Math.min(width, height) * .34; const total = config.data.reduce((sum, value) => sum + Number(value), 0) || 1; let angle = -Math.PI / 2;
    config.data.forEach((value, index) => { const slice = Number(value) / total * Math.PI * 2; context.beginPath(); context.strokeStyle = config.colors[index]; context.lineWidth = Math.max(15, radius * .28); context.arc(x, y, radius, angle, angle + slice - .025); context.stroke(); angle += slice; });
    context.textAlign = 'center'; context.fillStyle = muted; context.font = '11px system-ui'; context.fillText(config.centerLabel || 'Total', x, y - 5); context.fillStyle = getComputedStyle(root).getPropertyValue('--text'); context.font = '700 22px system-ui'; context.fillText(config.data.reduce((sum, value) => sum + Number(value), 0), x, y + 20);
}

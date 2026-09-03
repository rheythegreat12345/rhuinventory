import './bootstrap';

window.loadBarcodeScanner = () => import('@zxing/browser');

const root = document.documentElement;
const applyTheme = (theme) => {
    const resolved = theme === 'system' ? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : theme;
    root.dataset.theme = resolved;
    localStorage.setItem('theme', theme);
};
applyTheme(localStorage.getItem('theme') || document.body?.dataset.defaultTheme || 'system');

document.addEventListener('DOMContentLoaded', () => {
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
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('[data-dropdown-menu]').forEach((menu) => {
            menu.hidden = true;
            menu.closest('[data-dropdown]')?.querySelector('[data-dropdown-trigger]')?.setAttribute('aria-expanded', 'false');
        });
    });
    document.querySelectorAll('[data-dismiss]').forEach((button) => button.addEventListener('click', () => button.closest('[data-dismissible]')?.remove()));

    const confirmDialog = document.querySelector('#confirm-dialog'); let pendingForm = null;
    document.querySelectorAll('form[data-confirm]').forEach((form) => form.addEventListener('submit', (event) => {
        if (form.dataset.confirmed === '1') return;
        event.preventDefault(); pendingForm = form;
        confirmDialog.querySelector('[data-confirm-message]').textContent = form.dataset.confirm || 'This action cannot be undone.';
        confirmDialog.showModal();
    }));
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
    document.querySelectorAll('[data-auto-submit]').forEach((input) => input.addEventListener('change', () => input.form.submit()));

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
    renderCharts();
});

const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#39;', '"':'&quot;' })[character]);
function renderCharts() { document.querySelectorAll('canvas[data-chart]').forEach((canvas) => drawChart(canvas, JSON.parse(canvas.dataset.chart))); }
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
    else { const barWidth = Math.min(24, groupWidth * .68 / config.datasets.length); config.datasets.forEach((dataset, datasetIndex) => dataset.data.forEach((value, index) => { const barHeight = Number(value) / maximum * chartHeight; const x = padding.left + groupWidth * index + (groupWidth - barWidth * config.datasets.length) / 2 + barWidth * datasetIndex; context.fillStyle = dataset.color; context.beginPath(); context.roundRect(x, padding.top + chartHeight - barHeight, Math.max(barWidth - 2, 2), barHeight, 4); context.fill(); })); }
}
function drawDoughnut(context, width, height, config, muted) {
    const x = width / 2; const y = height / 2; const radius = Math.min(width, height) * .34; const total = config.data.reduce((sum, value) => sum + Number(value), 0) || 1; let angle = -Math.PI / 2;
    config.data.forEach((value, index) => { const slice = Number(value) / total * Math.PI * 2; context.beginPath(); context.strokeStyle = config.colors[index]; context.lineWidth = Math.max(15, radius * .28); context.arc(x, y, radius, angle, angle + slice - .025); context.stroke(); angle += slice; });
    context.textAlign = 'center'; context.fillStyle = muted; context.font = '11px system-ui'; context.fillText(config.centerLabel || 'Total', x, y - 5); context.fillStyle = getComputedStyle(root).getPropertyValue('--text'); context.font = '700 22px system-ui'; context.fillText(config.data.reduce((sum, value) => sum + Number(value), 0), x, y + 20);
}

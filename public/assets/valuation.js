(() => {
    'use strict';
    const form = document.querySelector('[data-valuation-form]');
    if (!form) return;
    const status = form.querySelector('[data-valuation-change]');
    const results = document.querySelector('#valuation-results');
    const info = document.querySelector('#valuation-info');
    let timer, active, sequence = 0, lastFocus;
    const showDialog = dialog => { if (!dialog) return; lastFocus = document.activeElement; dialog.showModal(); dialog.querySelector('[data-valuation-close]')?.focus(); };
    document.addEventListener('click', event => {
        const breakdown = event.target.closest('.valuation-steps > summary');
        if (breakdown) {
            const dialog = document.getElementById('valuation-breakdown');
            const rows = breakdown.parentElement.querySelector('dl');
            if (dialog && rows) { event.preventDefault(); dialog.querySelector('[data-valuation-breakdown-title]').textContent = 'Rechenweg · ' + breakdown.closest('.valuation-result').querySelector('h2').textContent; dialog.querySelector('[data-valuation-breakdown-body]').replaceChildren(rows.cloneNode(true)); showDialog(dialog); }
        }
        const help = event.target.closest('[data-valuation-info]');
        if (help && info) {
            const template = document.getElementById('valuation-help-' + help.dataset.valuationInfo);
            const label = help.closest('.field')?.querySelector('label')?.textContent || (help.dataset.valuationInfo === 'priceAssumption' ? 'Preisorientierung' : 'Rechenmodell');
            info.querySelector('[data-valuation-info-title]').textContent = label;
            info.querySelector('[data-valuation-info-body]').replaceChildren(template?.content.cloneNode(true) || document.createTextNode('Keine weiteren Angaben.'));
            showDialog(info);
        }
        const opener = event.target.closest('[data-valuation-open]');
        if (opener) showDialog(document.getElementById(opener.dataset.valuationOpen));
        const closer = event.target.closest('[data-valuation-close]');
        if (closer) closer.closest('dialog')?.close();
    });
    document.querySelectorAll('.valuation-dialog').forEach(dialog => {
        dialog.addEventListener('close', () => lastFocus?.focus());
        dialog.addEventListener('click', event => { if (event.target === dialog) { const r = dialog.getBoundingClientRect(); if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) dialog.close(); } });
    });
    const tabs = [...form.querySelectorAll('[data-valuation-method]')];
    const panelKey = 'chase-valuation-method:' + (form.querySelector('[name="propertyId"]')?.value || 'free');
    function chooseMethod(method, focus = false) {
        if (!tabs.some(tab => tab.dataset.valuationMethod === method)) method = 'sw';
        tabs.forEach(tab => { const selected = tab.dataset.valuationMethod === method; tab.setAttribute('aria-selected', String(selected)); tab.tabIndex = selected ? 0 : -1; if (selected && focus) tab.focus(); });
        form.querySelectorAll('[data-valuation-panel]').forEach(panel => panel.hidden = panel.dataset.valuationPanel !== method);
        form.querySelectorAll('[data-valuation-basis]').forEach(field => field.hidden = !field.dataset.valuationBasis.split(' ').includes(method));
        try { sessionStorage.setItem(panelKey, method); } catch (_) {}
    }
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => chooseMethod(tab.dataset.valuationMethod));
        tab.addEventListener('keydown', event => {
            let next;
            if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
            if (event.key === 'ArrowLeft') next = (index + tabs.length - 1) % tabs.length;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = tabs.length - 1;
            if (next !== undefined) { event.preventDefault(); chooseMethod(tabs[next].dataset.valuationMethod, true); }
        });
    });
    let selectedMethod = 'sw';
    try { selectedMethod = sessionStorage.getItem(panelKey) || 'sw'; } catch (_) {}
    chooseMethod(selectedMethod);
    const parse = value => Number(String(value).replace(/\s/g, '').includes(',') ? String(value).replace(/\s/g, '').replace(/\./g, '').replace(',', '.') : String(value).replace(/\s/g, ''));
    form.querySelectorAll('[data-source-for]').forEach(select => {
        const input = document.getElementById(select.dataset.sourceFor);
        if (!input) return;
        select.addEventListener('change', () => { const option = select.selectedOptions[0]; if (select.value && option?.dataset.value) { input.value = option.dataset.value; input.dispatchEvent(new Event('input', {bubbles: true})); } });
        input.addEventListener('input', () => { const option = select.selectedOptions[0]; if (select.value && parse(input.value) !== Number(option?.dataset.value)) select.value = ''; });
    });
    async function calculate() {
        const current = ++sequence;
        active?.abort();
        active = new AbortController();
        const data = new FormData(form); data.set('action', 'calculate');
        if (status) status.textContent = 'Berechnet …';
        results?.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(form.getAttribute('action') || location.href, {method: 'POST', body: data, headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, credentials: 'same-origin', signal: active.signal});
            if (response.redirected || response.status === 401 || response.status === 403) { if (status) status.textContent = 'Bitte neu anmelden oder die Seite neu laden.'; return; }
            const payload = await response.json();
            if (current !== sequence) return;
            if (payload.error) { if (status) status.textContent = payload.error; results?.setAttribute('data-stale', 'true'); return; }
            if (!response.ok) throw new Error('Berechnung nicht verfügbar');
            if (results && typeof payload.html === 'string') { results.innerHTML = payload.html; results.removeAttribute('data-stale'); }
            if (status) status.textContent = 'Aktuell · lokal berechnet';
        } catch (error) { if (error.name !== 'AbortError' && status) status.textContent = 'Berechnung nicht verfügbar. Bitte erneut versuchen.'; }
        finally { if (current === sequence) results?.removeAttribute('aria-busy'); }
    }
    const schedule = () => { clearTimeout(timer); active?.abort(); if (status) status.textContent = 'Eingaben geändert …'; timer = setTimeout(calculate, 320); };
    form.addEventListener('input', schedule);
    form.addEventListener('change', schedule);
    form.addEventListener('submit', () => { clearTimeout(timer); active?.abort(); });
    if (window.fetch && window.AbortController) { form.classList.add('is-live'); schedule(); }
})();

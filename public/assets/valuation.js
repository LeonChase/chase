(() => {
    'use strict';
    const form = document.querySelector('[data-valuation-form]');
    if (!form) return;
    const changed = form.querySelector('[data-valuation-change]');
    form.addEventListener('input', () => { if (changed) changed.textContent = 'Eingaben geändert. Bitte erneut berechnen, um die Ergebnisanzeige zu aktualisieren.'; });
    const parse = value => Number(String(value).replace(/\s/g, '').includes(',') ? String(value).replace(/\s/g, '').replace(/\./g, '').replace(',', '.') : String(value).replace(/\s/g, ''));
    form.querySelectorAll('[data-source-for]').forEach(select => {
        const input = document.getElementById(select.dataset.sourceFor);
        if (!input) return;
        select.addEventListener('change', () => {
            const option = select.selectedOptions[0];
            if (select.value && option?.dataset.value) { input.value = option.dataset.value; input.dispatchEvent(new Event('input', {bubbles: true})); }
        });
        input.addEventListener('input', () => {
            const option = select.selectedOptions[0];
            if (select.value && parse(input.value) !== Number(option?.dataset.value)) select.value = '';
        });
    });
})();

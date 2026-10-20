(() => {
    'use strict';
    const ready = () => {
        document.querySelectorAll('[data-property-text]').forEach(input => {
            const counter = document.querySelector(`[data-character-count-for="${input.id}"]`);
            const update = () => { if (counter) counter.textContent = `${Array.from(input.value).length} / 2.000`; };
            input.addEventListener('input', update); update();
        });
        document.querySelectorAll('[data-copy-target]').forEach(button => button.addEventListener('click', async () => {
            const input = document.getElementById(button.dataset.copyTarget);
            const feedback = button.closest('[data-property-ai-section]')?.querySelector('[data-property-ai-feedback]');
            if (!input) return;
            try { await navigator.clipboard.writeText(input.value); if (feedback) feedback.textContent = 'Text kopiert.'; }
            catch { input.focus(); input.select(); if (feedback) feedback.textContent = 'Text markiert. Mit Strg+C oder ⌘C kopieren.'; }
        }));
        document.querySelectorAll('[data-image-workflow]').forEach(form => {
            const container = form.querySelector('[data-local-image-preview]');
            const brightness = form.querySelector('[data-local-brightness]');
            const contrast = form.querySelector('[data-local-contrast]');
            const controls = form.querySelectorAll('input[name="mediaIds[]"]');
            const render = () => {
                container.replaceChildren();
                const selected = Array.from(controls).filter(input => input.checked);
                selected.forEach(input => { const figure = document.createElement('figure'); figure.style.margin = '0'; const image = document.createElement('img'); image.src = input.dataset.sourceUrl; image.alt = input.dataset.sourceLabel; image.style.cssText = `max-width:100%;max-height:300px;object-fit:contain;filter:brightness(${brightness.value}%) contrast(${contrast.value}%)`; const caption = document.createElement('figcaption'); caption.textContent = `${input.dataset.sourceLabel} · lokale Bildschirmvorschau`; figure.append(image, caption); container.append(figure); });
                if (!selected.length) { const hint = document.createElement('p'); hint.className = 'muted'; hint.textContent = 'Wähle oben mindestens ein gespeichertes Objektbild für die lokale Vorschau.'; container.append(hint); }
                form.querySelector('[data-brightness-label]').textContent = `${brightness.value} %`;
                form.querySelector('[data-contrast-label]').textContent = `${contrast.value} %`;
            };
            controls.forEach(input => input.addEventListener('change', render)); brightness.addEventListener('input', render); contrast.addEventListener('input', render);
        });
        document.querySelectorAll('[data-social-workflow]').forEach(form => {
            const container = form.querySelector('[data-object-storyboard]');
            const button = form.querySelector('[data-storyboard-play]');
            const controls = form.querySelectorAll('input[name="mediaIds[]"]');
            let timer = null, sources = [], frame = 0;
            const stop = () => { if (timer) clearInterval(timer); timer = null; button.textContent = 'Bildersequenz ansehen'; };
            const show = index => { container.replaceChildren(); if (!sources[index]) return; const image = document.createElement('img'); image.src = sources[index].dataset.sourceUrl; image.alt = sources[index].dataset.sourceLabel; image.style.cssText = 'max-width:100%;max-height:340px;object-fit:contain;border-radius:12px'; const label = document.createElement('p'); label.textContent = `Bild ${index + 1} von ${sources.length} · ${sources[index].dataset.sourceLabel}`; container.append(image, label); };
            controls.forEach(input => input.addEventListener('change', () => { stop(); sources = Array.from(controls).filter(control => control.checked); frame = 0; button.disabled = !sources.length; show(0); }));
            button.addEventListener('click', () => { if (timer) { stop(); return; } if (!sources.length) return; show(frame); button.textContent = 'Sequenz anhalten'; timer = setInterval(() => { frame = (frame + 1) % sources.length; show(frame); }, 2500); });
            window.addEventListener('pagehide', stop);
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready); else ready();
})();

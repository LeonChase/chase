(() => {
    'use strict';
    const root = document.documentElement;
    const storage = { get(key) { try { return localStorage.getItem(key); } catch { return null; } }, set(key, value) { try { localStorage.setItem(key, value); } catch {} } };
    const toast = (message) => {
        const region = document.querySelector('.toast-region');
        if (!region) return;
        const item = document.createElement('div'); item.className = 'toast'; item.textContent = message; region.append(item);
        setTimeout(() => { item.classList.add('toast-out'); setTimeout(() => item.remove(), 250); }, 3300);
    };
    const updateThemeLabel = () => document.querySelectorAll('[data-toggle-theme]').forEach(button => { const dark = root.dataset.theme === 'dark'; button.setAttribute('aria-label', dark ? 'Hellen Modus aktivieren' : 'Dunklen Modus aktivieren'); button.setAttribute('aria-pressed', dark ? 'true' : 'false'); });
    updateThemeLabel();
    document.querySelectorAll('[data-toggle-theme]').forEach(button => button.addEventListener('click', () => {
        const theme = root.dataset.theme === 'dark' ? 'light' : 'dark'; root.dataset.theme = theme; storage.set('chase-theme', theme); updateThemeLabel();
        if (document.querySelector('.app-shell')) {
            const body = new URLSearchParams({theme, _token: document.querySelector('meta[name="portal-csrf"]')?.content || ''});
            fetch('/profile/theme', {method: 'POST', body, headers: {'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json'}, credentials: 'same-origin'}).catch(() => {});
        }
    }));
    const sidebar = document.querySelector('.sidebar');
    const syncSidebar = () => {
        if (!sidebar) return;
        const hidden = window.innerWidth <= 800 && !document.body.classList.contains('nav-open');
        sidebar.inert = hidden; sidebar.setAttribute('aria-hidden', String(hidden));
    };
    const sidebarState = (open) => { const wasOpen = document.body.classList.contains('nav-open'); document.body.classList.toggle('nav-open', open); document.querySelector('[data-open-sidebar]')?.setAttribute('aria-expanded', String(open)); syncSidebar(); if (open) (document.querySelector('.sidebar .active') || document.querySelector('.sidebar a'))?.focus(); else if (wasOpen) document.querySelector('[data-open-sidebar]')?.focus(); };
    syncSidebar(); window.addEventListener('resize', syncSidebar);
    document.querySelector('[data-open-sidebar]')?.addEventListener('click', () => sidebarState(!document.body.classList.contains('nav-open')));
    document.querySelectorAll('[data-close-sidebar]').forEach(button => button.addEventListener('click', () => sidebarState(false)));
    document.querySelectorAll('.sidebar a').forEach(link => link.addEventListener('click', () => { if (window.innerWidth <= 800) sidebarState(false); }));
    const collapse = document.querySelector('[data-toggle-sidebar]');
    const updateCollapse = () => { const collapsed = root.classList.contains('sidebar-collapsed'); collapse?.setAttribute('aria-expanded', String(!collapsed)); collapse?.setAttribute('aria-label', collapsed ? 'Seitenleiste ausklappen' : 'Seitenleiste einklappen'); };
    updateCollapse(); collapse?.addEventListener('click', () => { root.classList.toggle('sidebar-collapsed'); storage.set('chase-sidebar', root.classList.contains('sidebar-collapsed') ? 'collapsed' : 'expanded'); updateCollapse(); });
    const profileButton = document.querySelector('[data-profile-toggle]'); const profileMenu = document.querySelector('#profile-menu');
    const closeProfile = () => { if (profileMenu) profileMenu.hidden = true; profileButton?.setAttribute('aria-expanded', 'false'); };
    profileButton?.addEventListener('click', () => { if (!profileMenu) return; const open = profileMenu.hidden; profileMenu.hidden = !open; profileButton.setAttribute('aria-expanded', String(open)); });
    document.addEventListener('click', (event) => { if (!event.target.closest('.profile-dropdown')) closeProfile(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') { closeProfile(); sidebarState(false); } if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); document.querySelector('#global-search')?.focus(); } if (event.key === 'Tab' && document.body.classList.contains('nav-open')) { const links = [...(sidebar?.querySelectorAll('a[href],button:not([disabled])') || [])].filter(item => item.getClientRects().length); const first = links[0], last = links[links.length - 1]; if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); } else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); } } });
    document.querySelectorAll('[data-dismiss-alert]').forEach(button => button.addEventListener('click', () => button.closest('.alert')?.remove()));
    document.querySelectorAll('[data-toggle-password]').forEach(button => button.addEventListener('click', () => { const input = document.getElementById(button.dataset.togglePassword); if (!input) return; input.type = input.type === 'password' ? 'text' : 'password'; button.setAttribute('aria-label', input.type === 'password' ? 'Passwort anzeigen' : 'Passwort verbergen'); button.setAttribute('aria-pressed', String(input.type === 'text')); }));
    document.querySelectorAll('[data-copy-target], [data-copy], [data-copy-text]').forEach(button => button.addEventListener('click', async () => {
        const selector = button.dataset.copyTarget || button.dataset.copy;
        let target = null; if (selector) { try { target = document.querySelector(selector); } catch {} target = target || document.getElementById(selector); }
        const value = button.dataset.copyText || target?.value || target?.innerText;
        if (!value) { toast('Es ist noch kein Text zum Kopieren vorhanden.'); return; }
        try { await navigator.clipboard.writeText(value); toast('Text in die Zwischenablage kopiert.'); }
        catch { if (target && typeof target.select === 'function') { target.select(); toast('Text markiert. Kopieren Sie ihn mit Strg/Cmd + C.'); } else { toast('Die Zwischenablage ist nicht verfügbar. Bitte markieren Sie den Text.'); } }
    }));
    document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => { if (!window.confirm(form.dataset.confirm)) event.preventDefault(); }));
    document.querySelectorAll('[data-local-preview]').forEach(input => input.addEventListener('change', () => {
        const target = document.getElementById(input.dataset.localPreview); const file = input.files?.[0]; if (!target || !file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) { target.hidden = true; toast('Vorschau für JPG, PNG und WebP verfügbar.'); return; }
        if (target.dataset.objectUrl) URL.revokeObjectURL(target.dataset.objectUrl); const url = URL.createObjectURL(file); target.src = url; target.dataset.objectUrl = url; target.hidden = false;
    }));
})();

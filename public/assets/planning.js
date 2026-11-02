(() => {
    'use strict';
    const workspace = () => document.querySelector('.planning-workspace-root');
    const announce = (message, error = false) => { const target = workspace()?.querySelector('[data-planning-status]'); if (target) { target.textContent = message; target.classList.toggle('is-error', error); } };
    const closeMenus = (except = null) => document.querySelectorAll('.planning-menu[open]').forEach(menu => { if (menu !== except) menu.open = false; });
    const showDialog = dialog => { if (dialog && !dialog.open) dialog.showModal(); };
    const loadRecord = async (href, focus = true) => {
        const dialog = document.getElementById('planning-record-dialog');
        if (!dialog) { location.href = href; return; }
        const target = dialog.querySelector('[data-record-content]');
        const title = dialog.querySelector('#planning-record-title');
        const url = new URL(href, location.origin); url.searchParams.set('fragment', '1');
        const anchor = url.hash.slice(1); url.hash = '';
        document.querySelectorAll('.planning-dialog[open]').forEach(open => { if (open !== dialog) open.close(); });
        target.textContent = 'Wird geladen …'; title.textContent = 'Bearbeiten'; showDialog(dialog);
        try {
            const response = await fetch(url, {headers: {Accept: 'text/html'}, credentials: 'same-origin', cache: 'no-store'});
            if (!response.ok || new URL(response.url).pathname === '/login') throw new Error('Dieser Eintrag ist nicht verfügbar.');
            target.innerHTML = await response.text();
            title.textContent = target.querySelector('[data-record-title]')?.dataset.recordTitle || 'Bearbeiten';
            if (anchor) { const section = target.querySelector('#' + CSS.escape(anchor)); if (section instanceof HTMLDetailsElement) section.open = true; }
            if (focus) (target.querySelector('input:not([type=hidden]),select,textarea,button') || dialog.querySelector('[data-close-dialog]'))?.focus();
        } catch (error) { target.textContent = error.message; }
    };
    const refreshWorkspace = async message => {
        const root = workspace();
        if (!root) return;
        const openTasks = [...root.querySelectorAll('details[data-task-editor][open]')].map(item => item.dataset.taskEditor);
        const panes = [...root.querySelectorAll('[data-pane-scroll]')].map(item => ({key:item.dataset.paneScroll,top:item.scrollTop,left:item.scrollLeft}));
        const response = await fetch(location.href, {headers: {Accept: 'text/html'},credentials: 'same-origin',cache:'no-store'});
        if (!response.ok) throw new Error('Die Ansicht konnte nicht aktualisiert werden. Bitte neu laden.');
        const html = new DOMParser().parseFromString(await response.text(), 'text/html');
        const next = html.querySelector('.planning-workspace-root');
        if (!next) throw new Error('Bitte erneut anmelden oder die Seite neu laden.');
        root.querySelectorAll('dialog[open]').forEach(dialog => dialog.close());
        root.replaceWith(next);
        openTasks.forEach(id => { const details = next.querySelector('[data-task-editor="' + CSS.escape(id) + '"]'); if (details) details.open = true; });
        panes.forEach(saved => { const pane = next.querySelector('[data-pane-scroll="' + CSS.escape(saved.key) + '"]'); if (pane) { pane.scrollTop=saved.top; pane.scrollLeft=saved.left; } });
        announce(message);
    };
    document.addEventListener('click', async event => {
        const open = event.target.closest('[data-open-dialog]');
        if (open) { event.preventDefault(); closeMenus(); showDialog(document.getElementById(open.dataset.openDialog)); return; }
        const close = event.target.closest('[data-close-dialog]');
        if (close) { event.preventDefault(); close.closest('dialog')?.close(); return; }
        const editor = event.target.closest('a[data-record-editor]');
        if (editor && !event.ctrlKey && !event.metaKey && !event.shiftKey && event.button === 0) { event.preventDefault(); closeMenus(); await loadRecord(editor.href); return; }
        const copy = event.target.closest('[data-planning-copy]');
        if (copy) { const input = document.querySelector(copy.dataset.planningCopy); if (input) { try { await navigator.clipboard.writeText(input.value); copy.textContent='Kopiert'; } catch { input.focus(); input.select(); copy.textContent='Strg/Cmd + C'; } } return; }
        if (!event.target.closest('.planning-menu')) closeMenus();
    });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenus(); });
    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-planning-form],[data-layout-form]') || event.defaultPrevented) return;
        event.preventDefault();
        if (form.dataset.planningConfirm && !confirm(form.dataset.planningConfirm)) return;
        const error = form.querySelector('[data-form-error]'); if (error) error.textContent='';
        const submitter = event.submitter;
        const data = new FormData(form); if (submitter?.name) data.append(submitter.name,submitter.value);
        if (submitter) submitter.disabled=true;
        try {
            const response = await fetch(form.action,{method:form.method || 'POST',body:data,headers:{Accept:'application/json'},credentials:'same-origin'});
            const json = (response.headers.get('content-type') || '').includes('application/json') ? await response.json() : null;
            if (!response.ok) throw new Error(json?.errors?.join(' ') || (response.status===403?'Die Sitzung ist abgelaufen. Bitte neu laden.':'Die Änderung konnte nicht gespeichert werden.'));
            if (new URL(response.url).pathname === '/login') throw new Error('Bitte erneut anmelden.');
            if (form.matches('[data-layout-form]') && json?.layout) {
                workspace().dataset.taskLayout=json.layout;
                form.querySelectorAll('button[name=layout]').forEach(button => { const active=button.value===json.layout; button.classList.toggle('active',active); button.setAttribute('aria-pressed',String(active)); });
                return;
            }
            if (form.dataset.refreshDialog && form.closest('#planning-record-dialog')) { await loadRecord(form.dataset.refreshDialog,false); return; }
            const quick = form.classList.contains('task-quick-add');
            await refreshWorkspace('Gespeichert.');
            if (quick) document.getElementById('quick-task-title')?.focus();
        } catch (failure) { if (error) error.textContent=failure.message; else announce(failure.message,true); }
        finally { if (submitter?.isConnected) submitter.disabled=false; }
    });
    document.addEventListener('close', event => {
        if (event.target.id !== 'planning-record-dialog') return;
        const url = new URL(location.href);
        if (url.searchParams.has('task')) { url.searchParams.delete('task'); history.replaceState(null,'',url); }
    },true);
    const task = new URL(location.href).searchParams.get('task');
    if (task && location.pathname==='/tasks') loadRecord('/tasks/'+encodeURIComponent(task)+'/edit');
})();

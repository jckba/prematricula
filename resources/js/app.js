import { auth, login, logout, requireRole, homeFor } from './api';
import { $, escape, flash, showError, busy, icon, label } from './ui';

async function startLogin() {
    const form = $('#login-form'); if (!form) return;
    if (auth.token()) {
        try {
            const user = await requireRole(auth.user()?.rol);
            if (user) { window.location.replace(homeFor(user.rol)); return; }
        } catch (error) { if (![401, 403].includes(error.status)) showError(error); else auth.clear(); }
    }
    form.addEventListener('submit', event => {
        event.preventDefault();
        if (!form.reportValidity()) return;
        form.querySelectorAll('[data-error]').forEach(el => el.textContent = '');
        form.querySelectorAll('[aria-invalid]').forEach(el => el.removeAttribute('aria-invalid'));
        busy($('[type=submit]', form), async () => {
            try {
                const user = await login(form.elements.identifier.value.trim(), form.elements.password.value);
                form.elements.password.value = '';
                window.location.assign(homeFor(user.rol));
            } catch (error) { showError(error, form); }
        });
    });
    $('#toggle-password')?.addEventListener('click', event => {
        const input = form.elements.password;
        input.type = input.type === 'password' ? 'text' : 'password';
        const label = input.type === 'password' ? 'Mostrar' : 'Ocultar';
        event.currentTarget.querySelector('[data-password-label]').textContent = label;
        event.currentTarget.setAttribute('aria-label', label + ' contraseña');
        event.currentTarget.setAttribute('aria-pressed', input.type === 'text' ? 'true' : 'false');
    });
}
async function startApp() {
    const shell = $('#app'); if (!shell) return;
    const role = shell.dataset.role;
    const splash = $('#splash');
    let user;
    try { user = await requireRole(role); }
    catch (error) {
        splash.innerHTML = '<div class="card"><h1>No se pudo abrir la sesión</h1><p>' + escape(error.message) + '</p><div class="toolbar"><button class="btn" id="retry-session">Volver a intentar</button><button class="btn btn-secondary" id="reset-session">Volver al login</button></div></div>';
        $('#retry-session').onclick = () => window.location.reload();
        $('#reset-session').onclick = () => { auth.clear(); window.location.assign('/login'); };
        return;
    }
    if (!user) return;
    $('#user-email').textContent = user.correo_institucional;
    $('#user-role').textContent = label(user.cargo || user.rol);
    $('#user-avatar').textContent = user.correo_institucional.slice(0, 1).toUpperCase();
    document.querySelectorAll('[data-section][data-icon]').forEach(link => link.insertAdjacentHTML('afterbegin', icon(link.dataset.icon)));
    document.querySelectorAll('[data-shell-icon]').forEach(element => element.innerHTML = icon(element.dataset.shellIcon));
    splash.remove(); shell.hidden = false;
    $('#logout-btn').onclick = event => busy(event.currentTarget, logout);
    const toggle = $('#menu-toggle'); const sidebar = $('#sidebar');
    const mobileMenu = window.matchMedia('(max-width: 960px)');
    function closeMenu(returnFocus = false) {
        sidebar.classList.remove('is-open'); sidebar.inert = mobileMenu.matches;
        toggle.setAttribute('aria-expanded', 'false'); $('#menu-backdrop').hidden = true;
        if (returnFocus) toggle.focus();
    }
    toggle.onclick = () => {
        const open = sidebar.classList.toggle('is-open');
        sidebar.inert = !open && mobileMenu.matches;
        toggle.setAttribute('aria-expanded', String(open)); $('#menu-backdrop').hidden = !open;
        if (open) sidebar.querySelector('button,a')?.focus();
    };
    $('#menu-backdrop').onclick = () => closeMenu(true);
    $('#menu-close').onclick = () => closeMenu(true);
    mobileMenu.addEventListener('change', () => closeMenu()); closeMenu();
    document.addEventListener('keydown', event => {
        if (!sidebar.classList.contains('is-open')) return;
        if (event.key === 'Escape') closeMenu(true);
        if (event.key === 'Tab') {
            const links = [...sidebar.querySelectorAll('a[href],button:not([disabled])')];
            const first = links[0], last = links.at(-1);
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    const module = role === 'ESTUDIANTE' ? await import('./student') : await import('./coordinator');
    let generation = 0;
    async function navigate() {
        const current = ++generation;
        document.querySelectorAll('dialog').forEach(dialog => dialog.close());
        const key = window.location.hash.slice(1) || 'dashboard';
        const links = [...document.querySelectorAll('[data-section]')];
        const active = links.find(link => link.dataset.section === key);
        if (!active) { window.location.hash = 'dashboard'; return; }
        links.forEach(link => link.toggleAttribute('aria-current', link === active));
        if (active) active.setAttribute('aria-current', 'page');
        $('#breadcrumb').textContent = active.textContent.trim();
        closeMenu(); $('#flash').hidden = true;
        const previous = $('#screen');
        const root = document.createElement('section'); root.id = 'screen'; root.setAttribute('aria-busy', 'true');
        root.innerHTML = '<div class="empty" role="status">Cargando información académica…</div>'; previous.replaceWith(root);
        try { await module.render(key, root, user); }
        catch (error) {
            if (current !== generation) return;
            root.innerHTML = '<div class="empty"><h1>No se pudo cargar esta sección</h1><p>' + escape(error.message) + '</p><button class="btn" id="retry-screen">Volver a intentar</button></div>';
            $('#retry-screen', root).onclick = navigate; showError(error);
        } finally {
            if (current === generation) {
                root.removeAttribute('aria-busy');
                root.querySelectorAll('h1').forEach(el => el.setAttribute('tabindex', '-1'));
                root.querySelector('h1')?.focus();
            }
        }
    }
    window.addEventListener('hashchange', navigate);
    window.addEventListener('storage', event => { if (event.key === 'pm_token' && !event.newValue) window.location.assign('/login'); });
    const expiry = auth.expiresAt();
    if (expiry) {
        const delay = new Date(expiry).getTime() - Date.now();
        if (delay > 0) setTimeout(() => { flash('Tu sesión está próxima a vencer. Guarda los cambios; el servidor puede solicitar un nuevo inicio de sesión.', 'info'); }, Math.max(0, delay - 5 * 60 * 1000));
    }
    await navigate();
}
document.addEventListener('DOMContentLoaded', () => { startLogin().catch(showError); startApp().catch(showError); });

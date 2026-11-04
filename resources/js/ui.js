export const $ = (selector, root = document) => root.querySelector(selector);
export const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
const labels = { ESTUDIANTE: 'Estudiante', COORDINADOR: 'Coordinación', DIRECTOR_ESCUELA: 'Dirección de escuela', SECRETARIA: 'Secretaría', MANANA: 'Mañana', TARDE: 'Tarde', SIN_PREFERENCIA: 'Sin preferencia', ACTIVO: 'Activo', INACTIVO: 'Inactivo', BORRADOR: 'Borrador', ENVIADA: 'Enviada', APROBADA: 'Aprobada (histórico)', RECHAZADA: 'Rechazada (histórico)', VALIDA: 'Válida', PENDIENTE_RESULTADOS: 'Pendiente de resultados', REQUIERE_AJUSTE: 'Requiere ajuste', SIN_VALIDACION: 'Sin validación', PLANIFICACION: 'Planificación', PREMATRICULA: 'Prematrícula', EN_CURSO: 'En curso', CERRADO: 'Cerrado', APROBADO: 'Aprobado', DESAPROBADO: 'Desaprobado', RETIRADO: 'Retirado', ORDINARIA: 'Ordinaria', EXTRAORDINARIA: 'Extraordinaria' };
export const label = value => value == null ? 'Sin dato' : labels[value] || String(value).replaceAll('_', ' ');
export function date(value) {
    if (!value) return 'Sin dato';
    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return value.split('-').reverse().join('/');
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? String(value) : new Intl.DateTimeFormat('es-PE', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'America/Lima' }).format(parsed);
}
export function badge(value) {
    const tone = ['ACTIVO', 'VALIDA', 'APROBADO', 'ENVIADA'].includes(value) ? 'success' : ['INACTIVO', 'REQUIERE_AJUSTE', 'DESAPROBADO', 'RECHAZADA'].includes(value) ? 'danger' : 'info';
    return '<span class="badge badge-' + tone + '">' + escape(label(value)) + '</span>';
}
export function flash(message, type = 'success') {
    const el = $('#flash'); if (!el) return;
    el.className = 'notice notice-' + type; el.textContent = message; el.hidden = false;
    el.setAttribute('role', type === 'danger' ? 'alert' : 'status');
}
export function showError(error, scope = document) {
    if (error.name === 'AbortError') return;
    const details = Object.values(error.errors || {}).flat().join(' ');
    const retry = error.retryAfter ? ' Reintenta después de ' + error.retryAfter + ' segundos.' : '';
    const message = error.message + (details ? ' ' + details : '') + retry;
    flash(message, 'danger');
    const modal = scope === document ? document.querySelector('dialog[open]') : null;
    if (modal) {
        let alert = modal.querySelector('[data-dialog-alert]');
        if (!alert) { alert = document.createElement('div'); alert.dataset.dialogAlert = ''; alert.className = 'notice notice-danger'; alert.setAttribute('role', 'alert'); modal.prepend(alert); }
        alert.textContent = message;
    }
    Object.entries(error.errors || {}).forEach(([key, messages]) => {
        const target = [...scope.querySelectorAll('[data-error]')].find(el => el.dataset.error === key);
        if (target) target.textContent = messages.join(' ');
        const input = [...scope.querySelectorAll('[name]')].find(el => el.name === key);
        if (input) input.setAttribute('aria-invalid', 'true');
    });
    scope.querySelector('[aria-invalid="true"]')?.focus();
}
export async function busy(button, action) {
    if (button?.disabled) return;
    const text = button?.textContent;
    if (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); }
    try { return await action(); } catch (error) { showError(error); }
    finally { if (button?.isConnected) { button.disabled = false; button.removeAttribute('aria-busy'); button.textContent = text; } }
}
export function heading(title, description = '') {
    return '<div class="page-heading"><h1 tabindex="-1">' + escape(title) + '</h1><p>' + escape(description) + '</p></div>';
}
export function empty(text = 'No hay registros para esta consulta.') { return '<div class="empty"><strong>Sin registros</strong><p>' + escape(text) + '</p></div>'; }
export function table(columns, rows, actions) {
    if (!rows?.length) return empty();
    return '<div class="table-scroll" role="region" aria-label="Tabla de resultados" tabindex="0"><table><thead><tr>' + columns.map(column => '<th scope="col">' + escape(column[0]) + '</th>').join('') + (actions ? '<th scope="col">Acciones</th>' : '') + '</tr></thead><tbody>' + rows.map(row => '<tr>' + columns.map(column => '<td>' + (column[2] ? column[2](row) : escape(column[1].split('.').reduce((value, key) => value?.[key], row) ?? 'Sin dato')) + '</td>').join('') + (actions ? '<td><div class="row-actions">' + actions(row) + '</div></td>' : '') + '</tr>').join('') + '</tbody></table></div>';
}
export function pagination(meta) {
    if (!meta || meta.last_page <= 1) return '';
    return '<nav class="pagination" aria-label="Paginación"><button data-page="' + (meta.current_page - 1) + '" ' + (meta.current_page <= 1 ? 'disabled' : '') + '>Anterior</button><span>Página ' + meta.current_page + ' de ' + meta.last_page + ' · ' + meta.total + ' registros</span><button data-page="' + (meta.current_page + 1) + '" ' + (meta.current_page >= meta.last_page ? 'disabled' : '') + '>Siguiente</button></nav>';
}
export function action(text, type, id, extra = '') { return '<button type="button" class="btn btn-small btn-secondary" data-action="' + escape(type) + '" data-id="' + escape(id) + '" ' + extra + '>' + escape(text) + '</button>'; }
export async function confirmAction(title, text, proceed = 'Confirmar') {
    const dialog = document.createElement('dialog'); dialog.className = 'modal';
    dialog.innerHTML = '<form method="dialog"><h2>' + escape(title) + '</h2><p>' + escape(text) + '</p><div class="toolbar"><button value="cancel" class="btn btn-secondary" autofocus>Cancelar</button><button value="confirm" class="btn">' + escape(proceed) + '</button></div></form>';
    dialog.setAttribute('aria-label', title); document.body.append(dialog); dialog.showModal();
    return new Promise(resolve => dialog.addEventListener('close', () => { const result = dialog.returnValue === 'confirm'; dialog.remove(); resolve(result); }, { once: true }));
}
export function detail(title, data) {
    const dialog = document.createElement('dialog'); dialog.className = 'modal modal-wide';
    function render(value) {
        if (value == null) return '<span class="muted">Sin dato</span>';
        if (Array.isArray(value)) return value.length ? '<div class="detail-list">' + value.map(item => '<div class="card">' + render(item) + '</div>').join('') + '</div>' : empty();
        if (typeof value === 'object') return '<dl class="details">' + Object.entries(value).filter(([key]) => !['snapshot', 'sha256', 'pdf_sha256'].includes(key)).map(([key, val]) => '<div><dt>' + escape(label(key)) + '</dt><dd>' + render(val) + '</dd></div>').join('') + '</dl>';
        return escape(String(value));
    }
    dialog.innerHTML = '<div class="modal-header"><h2>' + escape(title) + '</h2><button class="btn btn-secondary" data-close>Cerrar</button></div>' + render(data);
    dialog.setAttribute('aria-label', title); document.body.append(dialog);
    dialog.querySelector('[data-close]').onclick = () => dialog.close();
    dialog.addEventListener('close', () => dialog.remove(), { once: true }); dialog.showModal(); return dialog;
}
export function icon(name) {
    const paths = {
        home: '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/>',
        book: '<path d="M12 5v16M12 5C9 3 5 3 2 4v15c3-1 7-1 10 2 3-3 7-3 10-2V4c-3-1-7-1-10 1Z"/>',
        history: '<path d="M3 11a9 9 0 1 1 2 7M3 4v7h7M12 7v5l3 2"/>',
        file: '<path d="M14 2H5v20h14V7ZM14 2v6h5M8 12h8M8 16h6"/>',
        calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18M8 15h2M14 15h2M8 18h2"/>',
        layers: '<path d="m12 3 10 5-10 5L2 8ZM2 12l10 5 10-5M2 16l10 5 10-5"/>',
        users: '<circle cx="9" cy="7" r="4"/><path d="M2 21v-2a7 7 0 0 1 14 0v2M17 4a4 4 0 0 1 0 8M18 15a6 6 0 0 1 4 6"/>',
        user: '<circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
        chart: '<path d="M4 3v18h17M8 16v-5M13 16V7M18 16V4"/>',
        check: '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="m8 12 3 3 5-6"/>',
        credits: '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v7c0 4 16 4 16 0V5M4 12v7c0 4 16 4 16 0"/>',
        arrow: '<path d="M4 12h16m-6-6 6 6-6 6"/>',
        logout: '<path d="M9 4H4v16h5M10 12h11m-5-5 5 5-5 5"/>',
        menu: '<path d="M4 6h16M4 12h16M4 18h16"/>',
    };
    return '<svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (paths[name] || paths.file) + '</svg>';
}
export function quickLink(href, title, description, symbol) {
    return '<a class="quick-access" href="' + escape(href) + '"><span class="quick-access-icon">' + icon(symbol) + '</span><strong>' + escape(title) + '</strong><span>' + escape(description) + '</span></a>';
}

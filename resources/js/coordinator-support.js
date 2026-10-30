import { api } from './api';
import { escape as esc, label, heading, table, pagination, showError, busy, flash, confirmAction, detail } from './ui';

export const states = ['ACTIVO', 'INACTIVO'];
export const periodStates = ['PLANIFICACION', 'PREMATRICULA', 'EN_CURSO', 'CERRADO'];
export const preferences = ['MANANA', 'TARDE', 'SIN_PREFERENCIA'];
export const validationStates = ['VALIDA', 'PENDIENTE_RESULTADOS', 'REQUIERE_AJUSTE', 'SIN_VALIDACION'];
export const options = values => values.map(value => [value, label(value)]);
export const text = (name, title, extra = {}) => ({ name, label: title, type: 'text', ...extra });
export const number = (name, title, extra = {}) => ({ name, label: title, type: 'number', min: 1, ...extra });
export const select = (name, title, values, extra = {}) => ({ name, label: title, type: 'select', options: options(values), ...extra });
export const button = (title, kind, id = '', extra = '') => '<button type="button" class="btn btn-secondary" data-action="' + esc(kind) + '" data-id="' + esc(id) + '" ' + extra + '>' + esc(title) + '</button>';

export function context(root) {
    root._coordinatorController?.abort();
    const controller = new AbortController();
    root._coordinatorController = controller;
    const current = () => root.isConnected && !controller.signal.aborted;
    const request = (path, opts = {}) => {
        if (!current()) return Promise.reject(new DOMException('Vista cerrada.', 'AbortError'));
        return api(path, { ...opts, signal: controller.signal });
    };
    const on = (event, callback) => root.addEventListener(event, e => {
        if (!current()) return;
        if (event === 'submit' && e.target.matches('[data-filters]')) e.preventDefault();
        Promise.resolve().then(() => callback(e)).catch(error => {
            if (current() && error.name !== 'AbortError') showError(error);
        });
    }, { signal: controller.signal });
    return { root, signal: controller.signal, current, request, on };
}

export function shell(ctx, title, description, toolbar = '') {
    if (!ctx.current()) return;
    ctx.root.innerHTML = heading(title, description) + toolbar + '<div data-content aria-live="polite"></div>';
}

export function filterForm(fields, values = {}, extra = '') {
    const controls = fields.map(field => {
        const value = values[field.name] ?? '';
        const id = 'filter-' + field.name;
        let control;
        if (field.options) {
            control = '<select id="' + esc(id) + '" name="' + esc(field.name) + '"' + (field.required ? ' required' : '') + '><option value="">' + (field.required ? 'Seleccione' : 'Todos') + '</option>' +
                field.options.map(([key, title]) => '<option value="' + esc(key) + '"' + (String(value) === String(key) ? ' selected' : '') + '>' + esc(title) + '</option>').join('') + '</select>';
        } else {
            control = '<input id="' + esc(id) + '" name="' + esc(field.name) + '" type="' + esc(field.type || 'text') + '" value="' + esc(value) + '"' +
                (field.required ? ' required' : '') +
                (field.min !== undefined ? ' min="' + esc(field.min) + '"' : '') +
                (field.minlength ? ' minlength="' + field.minlength + '"' : '') + '>';
        }
        return '<label class="field" for="' + esc(id) + '"><span>' + esc(field.label) + '</span>' + control + '</label>';
    }).join('');
    return '<form data-filters class="filters">' + controls + '<button class="btn" type="submit">Consultar</button>' + extra + '</form>';
}

export function queryFrom(form) {
    return Object.fromEntries([...new FormData(form)].filter(([, value]) => String(value).trim() !== ''));
}

export function listing(ctx, config) {
    let query = { ...(config.query || {}) };
    let page = 1;
    let revision = 0;
    const load = async () => {
        const ticket = ++revision;
        const target = ctx.root.querySelector('[data-content]');
        target?.setAttribute('aria-busy', 'true');
        try {
            const response = await ctx.request(typeof config.path === 'function' ? config.path(query) : config.path, { query: { ...query, page } });
            if (!ctx.current() || ticket !== revision) return;
            const adapted = config.adapt ? config.adapt(response) : { rows: response.data || [], meta: response.meta || response };
            target.innerHTML = (config.before?.(response) || '') + table(config.columns, adapted.rows, config.actions) + pagination(adapted.meta);
        } catch (error) {
            if (ctx.current() && ticket === revision && error.name !== 'AbortError') {
                target.innerHTML = '<div class="notice">No se pudo cargar la consulta. Use Consultar para volver a intentar.</div>';
                showError(error);
            }
        } finally {
            if (ctx.current() && ticket === revision) target?.removeAttribute('aria-busy');
        }
    };
    ctx.on('submit', event => {
        if (!event.target.matches('[data-filters]')) return;
        event.preventDefault();
        query = queryFrom(event.target);
        page = 1;
        return load();
    });
    ctx.on('click', event => {
        const element = event.target.closest('[data-content] [data-page]');
        if (!element) return;
        page = Number(element.dataset.page);
        return load();
    });
    return { load, getQuery: () => ({ ...query }), reset: () => { page = 1; return load(); } };
}

export async function allOptions(ctx, path, display) {
    const result = [];
    let page = 1;
    let last = 1;
    do {
        const response = await ctx.request(path, { query: path === '/academic-periods' ? { page } : { page, per_page: 100 } });
        result.push(...(response.data || []).map(row => [row.id, display(row), row]));
        last = response.meta?.last_page || response.last_page || 1;
        page++;
    } while (page <= last && ctx.current());
    return result;
}

export function periodFilter(periods, required = false) {
    return { name: 'academic_period_id', label: 'Período académico', type: 'select', options: periods, required };
}

export async function mutate(ctx, title, description, path, method, refresh) {
    if (!await confirmAction(title, description, 'Confirmar')) return;
    if (!ctx.current()) return;
    await ctx.request(path, { method });
    if (!ctx.current()) return;
    flash('Operación completada.');
    await refresh();
}

export async function pagedDetail(ctx, title, path, columns, query = {}, before = '') {
    const dialog = detail(title, {});
    const container = dialog.querySelector('[data-detail-content]') || dialog.querySelector('.dialog-body') || dialog.querySelector('[data-dialog-body]') || dialog;
    const content = document.createElement('div');
    container.append(content);
    let ticket = 0;
    const load = async page => {
        const current = ++ticket;
        const response = await ctx.request(path, { query: { ...query, page } });
        if (!ctx.current() || current !== ticket || !dialog.open) return;
        content.innerHTML = before + table(columns, response.data || [], null) + pagination(response.meta || response);
    };
    content.addEventListener('click', event => {
        const page = event.target.closest('[data-page]');
        if (page) load(Number(page.dataset.page)).catch(showError);
    });
    try { await load(1); } catch (error) { dialog.close(); throw error; }
    return dialog;
}

export { esc, label, table, pagination, showError, busy, flash, confirmAction, detail };


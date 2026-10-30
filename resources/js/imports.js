import { api } from './api';
import { escape as esc, label, table, pagination, flash, showError, confirmAction } from './ui';

export async function renderImport(root, kind, signal) {
    const history = kind === 'history';
    const path = history ? '/coordinator/academic-history' : '/coordinator/curriculum';
    let file = null;
    let report = null;
    let page = 1;
    let pending = false;
    const active = () => root.isConnected && !signal?.aborted;
    const request = (suffix, options = {}) => api(path + suffix, { ...options, signal });
    root.innerHTML = '<section class="panel"><h2>' + (history ? 'Importar resultados oficiales' : 'Importar malla curricular') +
        '</h2><p>Descargue la plantilla, complete los datos y valide el archivo antes de confirmar. Máximo 5 MiB. No se admiten fórmulas.</p>' +
        '<p>Los resultados de validación no guardan cambios. La confirmación aplica el archivo validado.</p>' +
        '<div class="toolbar"><button type="button" class="btn btn-secondary" data-import="template">Descargar plantilla XLSX</button>' +
        (!history ? '<button type="button" class="btn btn-secondary" data-import="export">Exportar malla XLSX</button>' : '') + '</div>' +
        '<form data-import-form><label class="field"><span>Archivo Excel (.xlsx)</span><input type="file" name="file" accept=".xlsx" required></label>' +
        (history ? '<label class="field"><span>Motivo de importación</span><textarea name="motivo" maxlength="2000" required></textarea></label>' : '') +
        '<div class="toolbar"><button class="btn" type="submit">Validar archivo</button><button class="btn" type="button" data-import="confirm" disabled>Confirmar importación</button></div></form>' +
        '<div data-import-report aria-live="polite"></div></section>';
    const form = root.querySelector('form');
    const confirmButton = root.querySelector('[data-import="confirm"]');
    const reportRoot = root.querySelector('[data-import-report]');
    const lock = state => {
        pending = state;
        form.setAttribute('aria-busy', String(state));
        root.querySelectorAll('button,input,textarea').forEach(control => { control.disabled = state; });
        confirmButton.disabled = state || !report?.valid;
    };
    const display = () => {
        if (!active()) return;
        if (!report) { reportRoot.innerHTML = ''; return; }
        const errors = report.errors || [];
        const changes = report.changes || [];
        const rows = errors.length ? errors : changes;
        const columns = errors.length
            ? [['Fila', 'row'], ['Columna', 'column'], ['Mensaje', 'message']]
            : [['Fila', 'row'], ['Código', 'codigo', row => esc(row.codigo ?? row.codigo_curso ?? '')],
                ['Estudiante', 'codigo_universitario'], ['Período', 'codigo_periodo'],
                ['Acción', 'action', row => esc(label(row.action))],
                ['Cambios', 'fields', row => esc((row.fields || []).join(', '))],
                ['Antes / después', 'before', row => row.before || row.after ? '<details><summary>Ver cambios</summary><pre>' + esc(JSON.stringify({ antes: row.before, despues: row.after }, null, 2)) + '</pre></details>' : '—']];
        const totalPages = Math.max(1, Math.ceil(rows.length / 20));
        page = Math.min(page, totalPages);
        reportRoot.innerHTML = '<div class="notice"><strong>' + (report.valid ? 'Validación correcta' : 'Revise los errores del archivo') +
            '</strong><p>Creados: ' + esc(report.summary?.created ?? 0) + ' · Actualizados: ' + esc(report.summary?.updated ?? 0) +
            ' · Sin cambios: ' + esc(report.summary?.unchanged ?? 0) + '</p></div>' +
            table(columns, rows.slice((page - 1) * 20, page * 20)) +
            pagination({ current_page: page, last_page: totalPages, total: rows.length, per_page: 20 });
    };
    form.addEventListener('change', event => {
        if (event.target.name !== 'file') return;
        file = null;
        report = null;
        confirmButton.disabled = true;
        display();
    }, { signal });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (pending || !form.reportValidity()) return;
        file = form.elements.file.files[0];
        report = null;
        if (!file || !file.name.toLowerCase().endsWith('.xlsx') || file.size > 5 * 1024 * 1024) {
            showError(new Error('Seleccione un archivo .xlsx de hasta 5 MiB.'));
            return;
        }
        const body = new FormData();
        body.set('file', file);
        lock(true);
        try {
            const response = await request('/validate', { method: 'POST', body });
            if (active()) { report = response.data; page = 1; display(); }
        } catch (error) {
            if (!active() || error.name === 'AbortError') return;
            const payload = error.payload || error.body || error.data;
            report = payload?.data?.errors ? payload.data : null;
            if (!report && error.report) report = error.report;
            if (report) { page = 1; display(); }
            showError(error);
        } finally { if (active()) lock(false); }
    }, { signal });
    root.addEventListener('click', async event => {
        const pageButton = event.target.closest('[data-page]');
        if (pageButton) { page = Number(pageButton.dataset.page); display(); return; }
        const trigger = event.target.closest('[data-import]');
        if (!trigger || pending) return;
        const operation = trigger.dataset.import;
        try {
            if (operation === 'template' || operation === 'export') {
                lock(true);
                await request('/' + operation, { download: true, filename: history ? 'plantilla-resultados.xlsx' : operation === 'template' ? 'plantilla-malla.xlsx' : 'malla-curricular.xlsx' });
                return;
            }
            if (operation !== 'confirm' || !report?.valid || !file || !form.reportValidity()) return;
            lock(true);
            if (!await confirmAction('Confirmar importación', 'Se aplicarán los cambios del archivo validado. Esta operación modifica registros académicos.', 'Importar')) return;
            if (!active()) return;
            const body = new FormData();
            body.set('file', file);
            body.set('sha256', report.sha256);
            body.set(history ? 'history_fingerprint' : 'catalog_fingerprint', report[history ? 'history_fingerprint' : 'catalog_fingerprint']);
            if (history) body.set('motivo', form.elements.motivo.value);
            lock(true);
            const response = await request('/import', { method: 'POST', body });
            if (!active()) return;
            report = response.data;
            display();
            report = null;
            file = null;
            form.reset();
            flash(response.message || 'Importación completada.');
            root.dispatchEvent(new CustomEvent('academic:imported', { bubbles: true }));
        } catch (error) {
            if (!active() || error.name === 'AbortError') return;
            if (error.status === 409) { report = null; display(); }
            const payload = error.payload || error.body || error.data;
            if (payload?.data?.errors) { report = payload.data; display(); }
            showError(error);
        } finally { if (active()) lock(false); }
    }, { signal });
}


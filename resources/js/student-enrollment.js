import { api } from './api';
import { escape, label, date, badge, flash, showError, busy, heading, empty, table, pagination, action, confirmAction, detail } from './ui';

const base = '/student/pre-enrollment';
const requests = '/student/pre-enrollments';
const preferences = ['SIN_PREFERENCIA', 'MANANA', 'TARDE'];
const preferenceOptions = selected => preferences.map(value => '<option value="' + value + '" ' + (selected === value ? 'selected' : '') + '>' + escape(label(value)) + '</option>').join('');

export async function enrollment(root) {
    const [history, availability] = await Promise.allSettled([api(requests), api(base + '/available-courses')]);
    if (!root.isConnected) return;
    if (history.status === 'rejected') throw history.reason;
    let available = availability.status === 'fulfilled' ? availability.value.data : null;
    const matching = available ? history.value.data.find(row => row.periodo.id === available.periodo.id) : history.value.data[0];
    let selected = matching ? (await api(requests + '/' + matching.id)).data : null;
    let step = 1;
    let pending = false;
    let courseQuery = '';
    if (!root.isConnected) return;
    function writable() { return selected?.estado === 'BORRADOR' && available && selected.periodo.id === available.periodo.id; }
    function draw() {
        if (!root.isConnected) return;
        const editing = writable();
        const activeStep = selected?.estado === 'ENVIADA' ? 3 : step;
        root.innerHTML = heading('Mi prematrícula', 'Selecciona asignaturas, revisa tu carga y confirma el registro. Las preferencias no constituyen horarios.') +
            '<ol class="steps" aria-label="Pasos de prematrícula"><li ' + (activeStep === 1 ? 'aria-current="step"' : '') + '>Selección de cursos</li><li ' + (activeStep === 2 ? 'aria-current="step"' : '') + '>Revisión de carga</li><li ' + (activeStep === 3 ? 'aria-current="step"' : '') + '>Constancia</li></ol>' +
            (!available ? '<div class="notice notice-info" role="status">' + escape(availability.status === 'rejected' ? availability.reason.message : 'No hay selección disponible.') + ' Puedes consultar tus solicitudes y constancias anteriores.</div>' : '') +
            (selected ? summary(selected, available?.creditos_maximos) : '<section class="card"><h2>Inicia tu prematrícula</h2><p>' + (available ? 'Período habilitado: ' + escape(available.periodo.codigo) + '. ' : '') + 'Inicia tu borrador para seleccionar asignaturas. Podrás revisarlo antes de enviar.</p></section>') +
            (available && (!selected || selected.periodo.id !== available.periodo.id) ? '<div class="toolbar"><button class="btn" data-action="begin">Iniciar prematrícula del período ' + escape(available.periodo.codigo) + '</button></div>' : '') +
            (selected ? (editing ? (step === 1 ? selection(selected, available) : review(selected)) : readOnly(selected)) : '') +
            '<div class="toolbar"><a class="btn btn-secondary" href="#enrollments">Solicitudes y constancias</a><button class="btn btn-secondary" data-action="refresh">Actualizar información</button></div>';
        const search = root.querySelector('#course-search');
        if (search) {
            search.value = courseQuery;
            const filterCourses = () => {
                const normalize = text => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
                const query = normalize(courseQuery.trim());
                let matches = 0;
                root.querySelectorAll('[data-course-cycle]').forEach(group => {
                    let visible = 0;
                    group.querySelectorAll('[data-course-search]').forEach(row => {
                        row.hidden = !normalize(row.dataset.courseSearch).includes(query);
                        if (!row.hidden) visible++;
                    });
                    group.hidden = visible === 0;
                    if (query && visible) group.open = true;
                    matches += visible;
                });
                const emptyState = root.querySelector('[data-course-empty]');
                emptyState.hidden = matches > 0;
                emptyState.textContent = query ? 'No hay asignaturas que coincidan con tu búsqueda.' : 'No hay asignaturas habilitadas disponibles.';
            };
            search.oninput = () => { courseQuery = search.value; filterCourses(); };
            filterCourses();
        }
        root.querySelectorAll('[data-action]').forEach(button => button.onclick = () => busy(button, () => handle(button)));
        root.querySelectorAll('[data-preference]').forEach(input => input.onchange = () => {
            if (pending) return;
            const old = selected.detalles.find(row => String(row.id) === input.dataset.preference)?.preferencia_turno;
            input.disabled = true;
            mutate(() => api(base + '/courses/' + input.dataset.preference + '/preference', { method: 'PATCH', body: { preferencia_turno: input.value } }), 'Preferencia guardada.').catch(error => {
                input.value = old; showError(error);
            }).finally(() => { if (input.isConnected) input.disabled = false; });
        });
    }
    async function reload() {
        const refreshed = await api(requests + '/' + selected.id);
        selected = refreshed.data;
        try { available = (await api(base + '/available-courses')).data; } catch { available = null; }
        draw();
    }
    async function mutate(operation, message) {
        if (pending) return;
        pending = true;
        root.setAttribute('aria-busy', 'true');
        const controls = [...root.querySelectorAll('button, select, input')].map(control => [control, control.disabled]);
        controls.forEach(([control]) => { control.disabled = true; });
        try {
            await operation();
            await reload();
            flash(message);
        } finally {
            pending = false;
            root.removeAttribute('aria-busy');
            controls.forEach(([control, disabled]) => { if (control.isConnected) control.disabled = disabled; });
        }
    }
    async function handle(button) {
        const type = button.dataset.action;
        if (type === 'begin') {
            if (!await confirmAction('Iniciar prematrícula', 'Abrirás tu borrador del período habilitado. Podrás guardar tu selección y revisarla antes de enviar.', 'Continuar')) return;
            const response = await api(base, { method: 'POST' });
            selected = response.data;
            await reload();
            flash('Borrador disponible.');
        }
        if (type === 'refresh') return enrollment(root);
        if (type === 'add') {
            const courseId = Number(button.dataset.id);
            const preference = root.querySelector('[data-course-preference="' + courseId + '"]').value;
            await mutate(async () => {
                const response = await api(base + '/courses', { method: 'POST', body: { course_id: courseId, preferencia_turno: preference } });
                if (!response.data?.detail_id) throw new Error('No pudimos confirmar que la asignatura se haya agregado. Actualiza tu solicitud antes de continuar.');
            }, 'Asignatura agregada a tu borrador.');
        }
        if (type === 'remove') {
            if (!await confirmAction('Retirar asignatura', 'Se retirará esta asignatura del borrador. Puedes volver a seleccionarla si sigue siendo elegible.', 'Retirar')) return;
            await mutate(() => api(base + '/courses/' + button.dataset.id, { method: 'DELETE' }), 'Asignatura retirada.');
        }
        if (type === 'review') { step = 2; draw(); root.querySelector('h1')?.focus(); }
        if (type === 'back') { step = 1; draw(); root.querySelector('h1')?.focus(); }
        if (type === 'submit') {
            if (!await confirmAction('Confirmar envío de prematrícula', 'Se comprobará tu selección y se registrará el envío con su constancia. La validación académica se muestra por separado.', 'Confirmar envío')) return;
            await mutate(async () => { selected = (await api(base + '/submit', { method: 'POST' })).data; }, 'Prematrícula registrada correctamente.');
        }
        if (type === 'receipt') await api(requests + '/' + selected.id + '/receipt', { download: true, filename: 'constancia.pdf' });
        if (type === 'correction') {
            if (!await confirmAction('Iniciar corrección', 'Solo se permite durante una ventana extraordinaria activa. Tu constancia vigente se conserva hasta volver a enviar.', 'Iniciar corrección')) return;
            await mutate(async () => { selected = (await api(requests + '/' + selected.id + '/corrections', { method: 'POST' })).data; step = 1; }, 'Borrador de corrección disponible. La revisión registrada se conserva.');
        }
        if (type === 'revisions') await enrollmentDetail(root, selected.id);
    }
    draw();
}
function summary(selected, maximum) {
    return '<section class="card"><div class="toolbar"><h2>Período ' + escape(selected.periodo.codigo) + '</h2>' + badge(selected.estado) + (selected.validacion_academica ? badge(selected.validacion_academica.estado) : '') + '</div>' +
        '<p>' + escape(selected.estudiante.nombres + ' ' + selected.estudiante.apellidos) + ' · Código ' + escape(selected.estudiante.codigo_universitario) + '</p>' +
        '<p><strong>' + escape(selected.creditos_totales) + ' créditos seleccionados</strong>' + (maximum != null ? ' · Máximo personal: ' + escape(maximum) : '') + '</p>' +
        (maximum != null ? '<progress aria-label="Créditos seleccionados respecto al máximo personal" value="' + escape(selected.creditos_totales) + '" max="' + escape(maximum) + '">' + escape(selected.creditos_totales) + ' / ' + escape(maximum) + '</progress>' : '') +
        (selected.correccion_en_progreso ? '<p class="notice notice-info">Corrección en progreso. Los cambios del borrador aún no reemplazan la revisión y constancia registradas.</p>' : '') +
        (selected.validacion_academica ? '<p>Evaluación académica: ' + escape(date(selected.validacion_academica.evaluated_at)) + '</p>' +
            '<details><summary>Ver observaciones académicas</summary>' + table([['Código', 'codigo'], ['Estado académico', '', row => badge(row.estado)], ['Motivo', 'motivo']], selected.validacion_academica.cursos || []) + '</details>' : '') + '</section>';
}
function selection(selected, available) {
    const selectedIds = new Set(selected.detalles.map(row => row.curso.id));
    const groups = available.cursos.reduce((map, course) => {
        const rows = map.get(course.ciclo) || [];
        rows.push(course);
        map.set(course.ciclo, rows);
        return map;
    }, new Map());
    const courses = [...groups.entries()].sort((a, b) => a[0] - b[0]).map(([cycle, rows]) =>
        '<details class="course-cycle" data-course-cycle open><summary><span>Ciclo ' + escape(cycle) + '</span><small>' + rows.length + (rows.length === 1 ? ' asignatura' : ' asignaturas') + '</small></summary>' +
        '<div class="table-scroll" role="region" aria-label="Asignaturas del ciclo ' + escape(cycle) + '" tabindex="0"><table><thead><tr><th>Código</th><th>Asignatura</th><th>Créditos</th><th>Preferencia de turno</th><th>Selección</th></tr></thead><tbody>' +
        rows.map(course => '<tr data-course-search="' + escape(course.codigo + ' ' + course.nombre) + '"><td>' + escape(course.codigo) + '</td><td><strong>' + escape(course.nombre) + '</strong>' +
            (course.prerrequisitos?.length ? '<details class="course-prerequisites"><summary>Prerrequisitos</summary><p>' + escape(course.prerrequisitos.map(row => row.codigo).join(', ')) + '</p></details>' : '') +
            '</td><td>' + escape(course.creditos) + '</td>' +
            (selectedIds.has(course.id) ? '<td>En tu selección</td><td><span class="badge badge-success">Seleccionada</span></td>' :
                '<td data-label="Preferencia de turno"><label class="sr-only" for="preference-' + escape(course.id) + '">Preferencia de turno para ' + escape(course.nombre) + '</label><select id="preference-' + escape(course.id) + '" data-course-preference="' + escape(course.id) + '">' + preferenceOptions('SIN_PREFERENCIA') + '</select></td><td>' +
                action('Agregar', 'add', course.id, 'aria-label="Agregar ' + escape(course.nombre) + '"') + '</td>') + '</tr>').join('') +
        '</tbody></table></div></details>').join('');
    return '<div class="workspace-grid"><section aria-label="Selección de asignaturas"><div class="course-search"><label for="course-search">Buscar asignatura</label><input id="course-search" type="search" placeholder="Nombre o código de asignatura" autocomplete="off" maxlength="100" aria-controls="eligible-courses"></div>' +
        '<p class="help-text">Asignaturas habilitadas para ti. La preferencia de turno no reserva un horario.</p><div id="eligible-courses">' + courses +
        '<p class="empty" data-course-empty hidden>No hay asignaturas que coincidan con tu búsqueda.</p></div></section>' +
        '<aside class="selection-panel card"><h2>Mi carga seleccionada</h2><p><strong>' + escape(selected.creditos_totales) + ' / ' + escape(available.creditos_maximos) + ' créditos</strong></p>' +
        (available.creditos_maximos > 0 ? '<progress aria-label="Créditos seleccionados respecto al máximo personal" value="' + escape(selected.creditos_totales) + '" max="' + escape(available.creditos_maximos) + '"></progress>' : '') +
        (selected.detalles.length ? selected.detalles.map(row => '<article class="card"><h3>' + escape(row.curso.nombre) + '</h3><p>' + escape(row.curso.codigo) + ' · ' + escape(row.curso.creditos) + ' créditos</p>' +
            '<label class="field">Preferencia<select data-preference="' + escape(row.id) + '">' + preferenceOptions(row.preferencia_turno) + '</select></label>' + action('Retirar', 'remove', row.id) + '</article>').join('') : empty('Agrega asignaturas para construir tu carga.')) +
        '<button class="btn" data-action="review" ' + (!selected.detalles.length ? 'disabled' : '') + '>Revisar carga →</button></aside></div>';
}
function courseTable(selected) {
    return table([['Código', 'curso.codigo'], ['Asignatura', 'curso.nombre'], ['Créditos', 'curso.creditos'], ['Preferencia', '', row => escape(label(row.preferencia_turno))]], selected.detalles);
}
function review(selected) {
    return '<section class="card"><h2>Revisa tu selección antes de enviar</h2>' + courseTable(selected) +
        '<p class="notice notice-info">La prematrícula no constituye matrícula definitiva. Antes de registrar el envío se comprobarán tus créditos y requisitos.</p><div class="toolbar"><button class="btn btn-secondary" data-action="back">Volver a seleccionar</button><button class="btn" data-action="submit">Confirmar envío</button></div></section>';
}
function readOnly(selected) {
    return '<section class="card"><h2>Selección de la solicitud</h2>' + courseTable(selected) +
        '<div class="toolbar">' + (selected.revision_vigente?.receipt_available ? action('Descargar constancia vigente', 'receipt', selected.id) : '<p class="muted">No hay constancia verificable disponible para esta solicitud.</p>') +
        (selected.estado === 'ENVIADA' ? action('Solicitar corrección extraordinaria', 'correction', selected.id) : '') + action('Ver revisiones', 'revisions', selected.id) + '</div>' +
        (selected.estado === 'ENVIADA' ? '<p class="muted">La corrección solo se autoriza durante una ventana extraordinaria activa del período.</p>' : '') + '</section>';
}

export async function enrollmentDetail(root, id) {
    const response = await api(requests + '/' + id);
    if (!root.isConnected) return;
    const selected = response.data;
    root.innerHTML = '<section class="card"><button class="btn btn-secondary" data-return-detail>' + (root.id === 'screen' ? 'Volver a mi prematrícula' : 'Cerrar detalle') + '</button>' + heading('Detalle de solicitud', 'Consulta tus asignaturas, revisiones y constancias registradas.') + summary(selected) +
        '<h2>Asignaturas de la solicitud</h2>' + courseTable(selected) +
        (selected.revision_vigente?.receipt_available ? '<button class="btn" data-current-receipt>Descargar constancia vigente</button>' : '<p class="notice notice-info">Esta solicitud no tiene una constancia vigente disponible.</p>') +
        (selected.estado === 'ENVIADA' ? '<p>Solo puede corregirse la solicitud del período habilitado durante una ventana extraordinaria activa. Comprueba el período al ingresar.</p><p><a class="btn btn-secondary" href="#pre-enrollment">Ir a la prematrícula del período habilitado</a></p>' : '') +
        '<h2>Revisiones registradas</h2><div data-revisions aria-live="polite"></div></section>';
    root.querySelector('[data-return-detail]').onclick = event => busy(event.currentTarget, async () => {
        if (root.id === 'screen') await enrollment(root);
        else { root.innerHTML = ''; document.querySelector('[data-action="detail"][data-id="' + id + '"]')?.focus(); }
    });
    root.querySelector('[data-current-receipt]')?.addEventListener('click', event => busy(event.currentTarget, () => api(requests + '/' + id + '/receipt', { download: true, filename: 'constancia.pdf' })));
    const area = root.querySelector('[data-revisions]');
    let sequence = 0;
    const load = async (page = 1) => {
        const current = ++sequence;
        area.setAttribute('aria-busy', 'true');
        try {
            const response = await api(requests + '/' + id + '/revisions', { query: { page } });
            if (!root.isConnected || current !== sequence) return;
            area.innerHTML = table([['Revisión', 'revision_number'], ['Envío', '', row => escape(date(row.submitted_at))], ['Constancia', 'receipt_code'], ['Disponibilidad', '', row => row.receipt_available ? 'PDF disponible' : 'Sin PDF verificable']], response.data,
                row => action('Ver revisión', 'revision', row.id) + (row.receipt_available ? action('Descargar PDF', 'pdf', row.id) : '')) + pagination(response.meta);
            area.querySelectorAll('[data-page]').forEach(button => button.onclick = () => busy(button, () => load(button.dataset.page)));
            area.querySelectorAll('[data-action="pdf"]').forEach(button => button.onclick = () => busy(button, () => api(requests + '/' + id + '/revisions/' + button.dataset.id + '/receipt', { download: true, filename: 'constancia-revision.pdf' })));
            area.querySelectorAll('[data-action="revision"]').forEach(button => button.onclick = () => {
                const revision = response.data.find(row => String(row.id) === button.dataset.id);
                detail('Revisión ' + revision.revision_number, { revision: revision.revision_number, enviado: date(revision.submitted_at), constancia: revision.receipt_code, motivo: revision.motivo, datos_registrados: revision.snapshot });
            });
        } finally { area.removeAttribute('aria-busy'); }
    };
    await load();
}

import { api } from './api';
import { escape, date, badge, flash, busy, heading, empty, table, pagination, action, confirmAction, detail, icon, openPdf, successDialog } from './ui';

const base = '/student/pre-enrollment';
const requests = '/student/pre-enrollments';
// TURNO-OCULTO: la preferencia de turno no se muestra por ahora. Al no enviarla, el backend guarda SIN_PREFERENCIA.
// Para reactivarla: descomentar estas líneas, la columna en selection()/selectionPanel()/courseTable() y el handler [data-preference].
// const preferences = ['SIN_PREFERENCIA', 'MANANA', 'TARDE'];
// const preferenceOptions = selected => preferences.map(value => '<option value="' + value + '" ' + (selected === value ? 'selected' : '') + '>' + escape(label(value)) + '</option>').join('');

function startCard(available) {
    const feature = (symbol, text) => '<li>' + icon(symbol) + '<span>' + escape(text) + '</span></li>';
    return '<section class="card start-card" aria-labelledby="start-heading">' +
        '<p class="start-pill">' + icon('calendar') + 'Período ' + escape(available.periodo.codigo) + ' habilitado</p>' +
        '<h2 id="start-heading">Aún no empiezas tu prematrícula</h2>' +
        '<p class="start-text">Empieza un borrador: puedes cambiarlo antes de enviarlo. La prematrícula no es la matrícula definitiva.</p>' +
        '<ul class="start-features">' +
        (available.creditos_maximos != null ? feature('credits', 'Hasta ' + available.creditos_maximos + ' créditos') : '') +
        feature('eye', 'Revisas todo antes de enviar') + feature('file', 'Recibes una constancia en PDF') + '</ul>' +
        '<button class="btn btn-raised" data-action="begin">Empezar prematrícula</button></section>';
}

export async function enrollment(root) {
    const [history, availability] = await Promise.allSettled([api(requests), api(base + '/available-courses')]);
    if (!root.isConnected) return;
    if (history.status === 'rejected') throw history.reason;
    let available = availability.status === 'fulfilled' ? availability.value.data : null;
    const matching = available ? history.value.data.find(row => row.periodo.id === available.periodo.id) : history.value.data[0];
    let selected = matching ? (await api(requests + '/' + matching.id)).data : null;
    let pending = false;
    // Ciclo elegido en el selector (1–10).
    let cycle = null;
    // Agregar/retirar se encolan: el wrapper de api() rechaza dos POST idénticos simultáneos y así el panel nunca muestra un estado viejo.
    let queue = Promise.resolve();
    if (!root.isConnected) return;
    function writable() { return selected?.estado === 'BORRADOR' && available && selected.periodo.id === available.periodo.id; }
    function bind(scope) {
        scope.querySelectorAll('[data-action]').forEach(button => button.onclick = () => busy(button, () => handle(button)));
    }
    function draw() {
        if (!root.isConnected) return;
        const editing = writable();
        root.innerHTML = heading('Mi prematrícula', 'Elige tus cursos, revisa tus créditos y confirma tu registro.') +
            (!available ? '<div class="notice notice-info" role="status">' + escape(availability.status === 'rejected' ? availability.reason.message : 'No hay selección disponible.') + ' Puedes consultar tus solicitudes y constancias anteriores.</div>' : '') +
            // Mientras elige cursos no se muestra la tarjeta de resumen: el panel "Mi carga seleccionada" ya muestra los créditos.
            (selected ? (editing ? (selected.correccion_en_progreso ? '<p class="notice notice-info">Corrección en progreso. Los cambios del borrador aún no reemplazan la revisión y constancia registradas.</p>' : '') : selected.estado === 'ENVIADA' ? '' : summary(selected, available?.creditos_maximos)) : available ? startCard(available) : '') +
            (available && selected && selected.periodo.id !== available.periodo.id ? '<div class="toolbar"><button class="btn" data-action="begin">Iniciar prematrícula del período ' + escape(available.periodo.codigo) + '</button></div>' : '') +
            (selected ? (editing ? selection(selected, available, cycle) : selected.estado === 'ENVIADA' ? registered(selected) : readOnly(selected)) : '');
        bind(root);
        bindCycles();
        // TURNO-OCULTO: handler de cambio de preferencia (PATCH /pre-enrollment/courses/{id}/preference).
        // root.querySelectorAll('[data-preference]').forEach(input => input.onchange = () => { ... });
    }
    // Al elegir un ciclo solo se cambia la tabla de cursos; no se redibuja la página ni se llama a la API
    // (los cursos habilitados ya se cargaron una vez en /available-courses).
    function bindCycles() {
        const picker = root.querySelector('[data-cycle-select]');
        if (picker) picker.onchange = () => { cycle = Number(picker.value) || null; showCycle(); };
    }
    function showCycle() {
        const list = root.querySelector('[data-cycle-courses]');
        if (list) { list.outerHTML = cycleCourses(selected, available, cycle); bind(root.querySelector('[data-cycle-courses]')); }
    }
    // Actualiza solo lo que cambia al agregar o retirar: el panel "Mi carga seleccionada", el texto de las opciones de ciclo y la fila del curso tocado.
    function updateSelection() {
        if (!root.isConnected) return;
        const picker = root.querySelector('[data-cycle-select]');
        if (picker) { picker.innerHTML = cycleOptions(selected, available, cycle); }
        const panel = root.querySelector('[data-selection-panel]');
        if (panel) {
            panel.outerHTML = selectionPanel(selected, available);
            bind(root.querySelector('[data-selection-panel]'));
        }
        const chosen = new Set(selected.detalles.map(row => row.curso.id));
        root.querySelectorAll('[data-course-row]').forEach(row => {
            const isChosen = chosen.has(Number(row.dataset.courseRow));
            if (row.dataset.chosen === String(isChosen)) return;
            const course = available.cursos.find(item => item.id === Number(row.dataset.courseRow));
            row.dataset.chosen = String(isChosen);
            row.querySelector('[data-course-action]').innerHTML = courseAction(course, isChosen);
            bind(row);
        });
    }
    // Solo se vuelve a pedir el borrador: la lista de cursos habilitados depende del historial, no de lo seleccionado,
    // así que no se recarga /available-courses en cada clic.
    function change(operation, message) {
        const run = queue.then(async () => {
            await operation();
            const refreshed = (await api(requests + '/' + selected.id)).data;
            if (!root.isConnected) return;
            selected = refreshed;
            updateSelection();
            flash(message);
        });
        queue = run.catch(() => {});
        return run;
    }
    // Recarga completa: solo para cambios de etapa (iniciar, enviar, corregir).
    async function reload() {
        const refreshed = await api(requests + '/' + selected.id);
        selected = refreshed.data;
        try { available = (await api(base + '/available-courses')).data; } catch { available = null; }
        draw();
    }
    async function mutate(operation, message) {
        if (pending) return;
        pending = true;
        await queue;
        root.setAttribute('aria-busy', 'true');
        const controls = [...root.querySelectorAll('button, select, input')].map(control => [control, control.disabled]);
        controls.forEach(([control]) => { control.disabled = true; });
        try {
            await operation();
            await reload();
            if (message) flash(message);
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
        if (type === 'add') {
            const courseId = Number(button.dataset.id);
            await change(async () => {
                // TURNO-OCULTO: antes se enviaba también preferencia_turno.
                const response = await api(base + '/courses', { method: 'POST', body: { course_id: courseId } });
                if (!response.data?.detail_id) throw new Error('No pudimos confirmar que la asignatura se haya agregado. Actualiza tu solicitud antes de continuar.');
            }, 'Asignatura agregada a tu borrador.');
            root.querySelector('[data-course-row="' + courseId + '"] [data-course-status]')?.focus({ preventScroll: true });
        }
        if (type === 'remove') {
            if (!await confirmAction('Retirar asignatura', 'Se retirará esta asignatura del borrador. Puedes volver a seleccionarla si sigue siendo elegible.', 'Retirar')) return;
            await change(() => api(base + '/courses/' + button.dataset.id, { method: 'DELETE' }), 'Asignatura retirada.');
            root.querySelector('[data-selection-panel] h2')?.focus({ preventScroll: true });
        }
        // "Registrar prematrícula": confirma, registra el envío, muestra el modal de éxito y luego la caja para generar la constancia PDF.
        if (type === 'submit') {
            const count = selected.detalles.length;
            if (!await confirmAction('Registrar prematrícula', 'Se registrará tu prematrícula del período ' + selected.periodo.codigo + ' con ' + count + (count === 1 ? ' curso' : ' cursos') + ' (' + selected.creditos_totales + ' créditos). La prematrícula no constituye matrícula definitiva.', 'Registrar prematrícula')) return;
            await mutate(async () => { selected = (await api(base + '/submit', { method: 'POST' })).data; }, null);
            if (selected.estado !== 'ENVIADA' || !root.isConnected) return;
            const notice = document.querySelector('#flash'); if (notice) notice.hidden = true;
            await successDialog('¡Éxito!', 'Tu prematrícula se registró con éxito.', 'Período ' + selected.periodo.codigo + ' · ' + count + (count === 1 ? ' curso' : ' cursos') + ' · ' + selected.creditos_totales + ' créditos');
            root.querySelector('[data-action="receipt"]')?.focus();
        }
        if (type === 'receipt') await openPdf(requests + '/' + selected.id + '/receipt');
        if (type === 'correction') {
            if (!await confirmAction('Iniciar corrección', 'Solo se permite durante una ventana extraordinaria activa. Tu constancia vigente se conserva hasta volver a enviar.', 'Iniciar corrección')) return;
            await mutate(async () => { selected = (await api(requests + '/' + selected.id + '/corrections', { method: 'POST' })).data; }, 'Borrador de corrección disponible. La revisión registrada se conserva.');
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
function courseAction(course, chosen) {
    return chosen ? '<span class="course-selected" data-course-status tabindex="-1">En tu selección</span>'
        : '<button type="button" class="btn btn-small btn-add" data-action="add" data-id="' + escape(course.id) + '" aria-label="Agregar ' + escape(course.nombre) + '">Agregar</button>';
}
const ROMAN = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];
// Opciones del selector de ciclos I–X: cuántos cursos habilitados tiene cada ciclo y cuántos ya elegiste.
function cycleOptions(selected, available, cycle) {
    const chosen = new Set(selected.detalles.map(row => row.curso.id));
    return '<option value=""' + (cycle ? '' : ' selected') + '>Seleccionar ciclo</option>' +
        ROMAN.map((roman, index) => {
            const number = index + 1;
            const courses = available.cursos.filter(course => course.ciclo === number);
            const picked = courses.filter(course => chosen.has(course.id)).length;
            return '<option value="' + number + '"' + (cycle === number ? ' selected' : '') + '>Ciclo ' + roman + ' · ' + courses.length + (courses.length === 1 ? ' curso' : ' cursos') +
                (picked ? ' (' + picked + (picked === 1 ? ' elegido' : ' elegidos') + ')' : '') + '</option>';
        }).join('');
}
// Tabla de cursos del ciclo elegido. Antes de elegir un ciclo no se muestra ningún curso.
function cycleCourses(selected, available, cycle) {
    const rows = cycle ? available.cursos.filter(course => course.ciclo === cycle) : [];
    const chosen = new Set(selected.detalles.map(row => row.curso.id));
    const message = !cycle ? 'Selecciona un ciclo para ver sus cursos disponibles.' : 'No tienes cursos disponibles en el ciclo ' + ROMAN[cycle - 1] + '. Prueba con otro ciclo.';
    // TURNO-OCULTO: columna "Preferencia de turno" retirada de esta tabla.
    // Columnas "N° vez" y "Tipo" del sistema anterior omitidas: no existen en el modelo de datos actual.
    return '<div class="table-scroll course-table" data-cycle-courses role="region" aria-live="polite" aria-label="' + (cycle ? 'Cursos disponibles del ciclo ' + ROMAN[cycle - 1] : 'Cursos disponibles') + '" tabindex="0"><table>' +
        '<thead><tr><th>Ciclo</th><th>Código</th><th>Nombre del curso</th><th>N° créditos</th><th>Seleccionar</th></tr></thead><tbody>' +
        (rows.length ? rows.map(course => '<tr data-course-row="' + escape(course.id) + '" data-chosen="' + chosen.has(course.id) + '"><td>' + ROMAN[course.ciclo - 1] + '</td><td>' + escape(course.codigo) + '</td><td><strong>' + escape(course.nombre) + '</strong>' +
            (course.prerrequisitos?.length ? '<details class="course-prerequisites"><summary>Prerrequisitos</summary><p>' + escape(course.prerrequisitos.map(row => row.codigo).join(', ')) + '</p></details>' : '') +
            '</td><td>' + escape(course.creditos) + '</td><td data-course-action>' + courseAction(course, chosen.has(course.id)) + '</td></tr>').join('')
            : '<tr class="course-table-empty"><td colspan="5">' + escape(message) + '</td></tr>') +
        '</tbody></table></div>';
}
function selection(selected, available, cycle) {
    return '<div class="workspace-grid"><section class="card courses-card" aria-labelledby="courses-title">' +
        '<div class="courses-card-header"><h2 id="courses-title">Cursos disponibles</h2>' +
        '<label class="sr-only" for="cycle-select">Seleccionar ciclo</label><select id="cycle-select" data-cycle-select>' + cycleOptions(selected, available, cycle) + '</select></div>' +
        '<p class="help-text">Puedes cambiar de ciclo cuando quieras: los cursos que elijas se guardan en tu selección.</p>' +
        cycleCourses(selected, available, cycle) + '</section>' +
        selectionPanel(selected, available) + '</div>';
}
// TEXTO-PENDIENTE(carga): "Mi carga seleccionada" y "construir tu carga" dependen de la confirmación del término.
function selectionPanel(selected, available) {
    return '<aside class="selection-panel card" data-selection-panel aria-live="polite"><h2 tabindex="-1">Mi carga seleccionada</h2><p><strong>' + escape(selected.creditos_totales) + ' / ' + escape(available.creditos_maximos) + ' créditos</strong></p>' +
        (available.creditos_maximos > 0 ? '<progress aria-label="Créditos seleccionados respecto al máximo personal" value="' + escape(selected.creditos_totales) + '" max="' + escape(available.creditos_maximos) + '"></progress>' : '') +
        // Cada curso en una fila compacta (nombre y datos a la izquierda, botón a la derecha) para que entren ~6 cursos por ciclo sin alargar demasiado el panel.
        (selected.detalles.length ? '<ul class="selected-courses">' + selected.detalles.map(row => '<li class="selected-course"><div class="selected-course-info"><h3>' + escape(row.curso.nombre) + '</h3><p>' + escape(row.curso.codigo) + ' · ' + escape(row.curso.creditos) + ' créditos</p></div>' +
            // TURNO-OCULTO: '<label class="field">Preferencia<select data-preference="' + escape(row.id) + '">' + preferenceOptions(row.preferencia_turno) + '</select></label>' +
            '<button type="button" class="btn btn-small btn-remove" data-action="remove" data-id="' + escape(row.id) + '" aria-label="Retirar ' + escape(row.curso.nombre) + '">Retirar</button></li>').join('') + '</ul>' : empty('Agrega asignaturas para construir tu carga.')) +
        '<button class="btn" data-action="submit" ' + (!selected.detalles.length ? 'disabled' : '') + '>Registrar prematrícula</button></aside>';
}
export function courseTable(selected) {
    // TURNO-OCULTO: columna ['Preferencia', '', row => escape(label(row.preferencia_turno))] retirada.
    return table([['CÓDIGO', 'curso.codigo'], ['NOMBRE DEL CURSO', 'curso.nombre'], ['N° CRÉDITOS', 'curso.creditos']], selected.detalles) +
        (selected.detalles.length ? '<p class="credits-total"><span>Total de créditos</span><strong>' + escape(selected.creditos_totales) + '</strong></p>' : '');
}
// Prematrícula registrada: caja destacada con la opción de generar la constancia PDF y, debajo, los cursos registrados.
function registered(selected) {
    const receipt = selected.revision_vigente?.receipt_available;
    return '<section class="card receipt-card" aria-labelledby="receipt-title">' +
        '<span class="receipt-card-icon" aria-hidden="true">' + icon('check-circle') + '</span>' +
        '<div class="receipt-card-body"><h2 id="receipt-title">Tu prematrícula está registrada</h2>' +
        '<ul class="receipt-meta">' +
        '<li>' + icon('calendar') + 'Período ' + escape(selected.periodo.codigo) + '</li>' +
        '<li>' + icon('layers') + escape(selected.detalles.length) + (selected.detalles.length === 1 ? ' curso' : ' cursos') + '</li>' +
        '<li>' + icon('credits') + escape(selected.creditos_totales) + ' créditos</li>' +
        (selected.fecha_envio ? '<li>Registrada el ' + escape(date(selected.fecha_envio)) + '</li>' : '') + '</ul>' +
        (receipt ? '<p class="muted">Genera tu constancia: se abrirá en una pestaña nueva, desde donde puedes imprimirla o descargarla.</p>' : '<p class="muted">La constancia aún no está disponible para esta solicitud.</p>') + '</div>' +
        (receipt ? '<button class="btn btn-raised receipt-card-action" data-action="receipt">' + icon('file') + 'Generar constancia PDF</button>' : '') + '</section>' +
        '<section class="card"><h2>Cursos registrados</h2>' + courseTable(selected) + '</section>';
}
function readOnly(selected) {
    return '<section class="card"><h2>Selección de la solicitud</h2>' + courseTable(selected) +
        '<div class="toolbar">' + (selected.revision_vigente?.receipt_available ? action('Ver constancia vigente (PDF)', 'receipt', selected.id) : '<p class="muted">No hay constancia verificable disponible para esta solicitud.</p>') +
        (selected.estado === 'ENVIADA' ? action('Solicitar corrección extraordinaria', 'correction', selected.id) : '') + action('Ver revisiones', 'revisions', selected.id) + '</div>' +
        (selected.estado === 'ENVIADA' ? '<p class="muted">La corrección solo se autoriza durante una ventana extraordinaria activa del período.</p>' : '') + '</section>';
}

export async function enrollmentDetail(root, id) {
    const response = await api(requests + '/' + id);
    if (!root.isConnected) return;
    const selected = response.data;
    root.innerHTML = '<section class="card"><button class="btn btn-secondary" data-return-detail>' + (root.id === 'screen' ? 'Volver a mi prematrícula' : 'Cerrar detalle') + '</button>' + heading('Detalle de solicitud', 'Consulta tus asignaturas, revisiones y constancias registradas.') + summary(selected) +
        '<h2>Asignaturas de la solicitud</h2>' + courseTable(selected) +
        (selected.revision_vigente?.receipt_available ? '<button class="btn" data-current-receipt>Ver constancia vigente (PDF)</button>' : '<p class="notice notice-info">Esta solicitud no tiene una constancia vigente disponible.</p>') +
        (selected.estado === 'ENVIADA' ? '<p>Solo puede corregirse la solicitud del período habilitado durante una ventana extraordinaria activa. Comprueba el período al ingresar.</p><p><a class="btn btn-secondary" href="#pre-enrollment">Ir a la prematrícula del período habilitado</a></p>' : '') +
        '<h2>Revisiones registradas</h2><div data-revisions aria-live="polite"></div></section>';
    root.querySelector('[data-return-detail]').onclick = event => busy(event.currentTarget, async () => {
        if (root.id === 'screen') await enrollment(root);
        else { root.innerHTML = ''; document.querySelector('[data-action="detail"][data-id="' + id + '"]')?.focus(); }
    });
    root.querySelector('[data-current-receipt]')?.addEventListener('click', event => busy(event.currentTarget, () => openPdf(requests + '/' + id + '/receipt')));
    const area = root.querySelector('[data-revisions]');
    let sequence = 0;
    const load = async (page = 1) => {
        const current = ++sequence;
        area.setAttribute('aria-busy', 'true');
        try {
            const response = await api(requests + '/' + id + '/revisions', { query: { page } });
            if (!root.isConnected || current !== sequence) return;
            area.innerHTML = table([['Revisión', 'revision_number'], ['Envío', '', row => escape(date(row.submitted_at))], ['Constancia', 'receipt_code'], ['Disponibilidad', '', row => row.receipt_available ? 'PDF disponible' : 'Sin PDF verificable']], response.data,
                row => action('Ver revisión', 'revision', row.id) + (row.receipt_available ? action('Ver PDF', 'pdf', row.id) : '')) + pagination(response.meta);
            area.querySelectorAll('[data-page]').forEach(button => button.onclick = () => busy(button, () => load(button.dataset.page)));
            area.querySelectorAll('[data-action="pdf"]').forEach(button => button.onclick = () => busy(button, () => openPdf(requests + '/' + id + '/revisions/' + button.dataset.id + '/receipt', 'Constancia de revisión')));
            area.querySelectorAll('[data-action="revision"]').forEach(button => button.onclick = () => {
                const revision = response.data.find(row => String(row.id) === button.dataset.id);
                detail('Revisión ' + revision.revision_number, { revision: revision.revision_number, enviado: date(revision.submitted_at), constancia: revision.receipt_code, motivo: revision.motivo, datos_registrados: revision.snapshot });
            });
        } finally { area.removeAttribute('aria-busy'); }
    };
    await load();
}

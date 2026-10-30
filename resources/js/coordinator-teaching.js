import { openForm } from './forms';
import { badge, date, heading, empty } from './ui';
import { states, options, text, number, select, button, shell, filterForm, queryFrom, listing, allOptions, periodFilter, mutate, pagedDetail, esc, label, table, pagination, detail, showError, flash, busy } from './coordinator-support';

const hours = ['teoria', 'practica', 'laboratorio'];
const assignedFields = () => hours.map(type => text('horas_' + type + '_asignadas', 'Horas de ' + type, { value: '0.00', required: true, pattern: '[0-9]{1,3}([.][0-9]{1,2})?', help: 'Horas semanales, hasta dos decimales. Al menos un tipo debe ser positivo.' }));
const assignedColumns = [['Teoría', 'horas_teoria_asignadas'], ['Práctica', 'horas_practica_asignadas'], ['Laboratorio', 'horas_laboratorio_asignadas'], ['Total semanal', 'total_semanal']];
const assignmentColumns = [['ID', 'id'], ['Período (ID)', 'academic_period_id'], ['Docente', 'docente.apellidos', row => esc((row.docente?.nombres || '') + ' ' + (row.docente?.apellidos || ''))], ['Curso', 'curso.nombre'], ...assignedColumns];

export async function teachers(ctx) {
    shell(ctx, 'Docentes', 'Gestione fichas docentes y límites de carga semanal. No se crean cuentas de acceso.',
        filterForm([text('q', 'Código o nombres', { minlength: 2 }), select('estado', 'Estado', states)], {}, button('Registrar docente', 'create')));
    const list = listing(ctx, { path: '/coordinator/teachers',
        columns: [['Código', 'codigo_institucional'], ['Nombres', 'nombres'], ['Apellidos', 'apellidos'], ['Correo', 'correo_institucional'], ['Máximo semanal', 'max_horas_semanales'], ['Estado', 'estado', row => badge(row.estado)]],
        actions: row => button('Detalle', 'detail', row.id) + button('Editar', 'edit', row.id) + (row.estado === 'ACTIVO' ? button('Desactivar', 'deactivate', row.id) : '') });
    ctx.on('click', async event => {
        const trigger = event.target.closest('[data-action]');
        if (!trigger) return;
        const kind = trigger.dataset.action;
        const id = trigger.dataset.id;
        if (kind === 'deactivate') return busy(trigger, () => mutate(ctx, 'Desactivar docente', 'La ficha y las asignaciones se conservan para revisión. No se libera automáticamente su carga.', '/coordinator/teachers/' + id, 'DELETE', list.load));
        if (!['create', 'edit', 'detail'].includes(kind)) return;
        const initial = kind === 'create' ? {} : (await ctx.request('/coordinator/teachers/' + id)).data;
        if (!ctx.current()) return;
        if (kind === 'detail') { detail('Ficha del docente', initial); return; }
        openForm(kind === 'create' ? 'Registrar docente' : 'Editar docente', [
            text('codigo_institucional', 'Código institucional', { required: true, maxlength: 30 }),
            text('nombres', 'Nombres', { required: true, maxlength: 100 }),
            text('apellidos', 'Apellidos', { required: true, maxlength: 150 }),
            text('correo_institucional', 'Correo institucional', { type: 'email', nullable: true, maxlength: 255 }),
            text('max_horas_semanales', 'Máximo de horas semanales (opcional)', { nullable: true, pattern: '[0-9]{1,5}([.][0-9]{1,2})?', help: 'Dejar vacío indica que no se ha configurado un límite.' }),
            select('estado', 'Estado', states, { required: true }),
        ], initial, async body => {
            await ctx.request('/coordinator/teachers' + (kind === 'create' ? '' : '/' + id), { method: kind === 'create' ? 'POST' : 'PATCH', body });
            if (ctx.current()) await list.load();
        });
    });
    await list.load();
}

export async function assignments(ctx) {
    const [periods, teachersOptions, courses] = await Promise.all([
        allOptions(ctx, '/academic-periods', row => row.codigo + ' · ' + label(row.estado)),
        allOptions(ctx, '/coordinator/teachers', row => row.codigo_institucional + ' · ' + row.nombres + ' ' + row.apellidos + ' · ' + label(row.estado)),
        allOptions(ctx, '/courses', row => row.codigo + ' · ' + row.nombre + ' · ' + label(row.estado)),
    ]);
    if (!ctx.current()) return;
    const periodById = new Map(periods.map(([id, , row]) => [String(id), row]));
    const openPeriods = periods.filter(([, , row]) => row.estado !== 'CERRADO');
    const activeTeachers = teachersOptions.filter(([, , row]) => row.estado === 'ACTIVO');
    const activeCourses = courses.filter(([, , row]) => row.estado === 'ACTIVO');
    const isClosed = row => periodById.get(String(row.academic_period_id))?.estado === 'CERRADO';
    const closedAttributes = 'disabled title="Período cerrado: solo consulta e historial"';
    shell(ctx, 'Asignaciones académicas', 'Distribución de horas semanales por período, curso y docente. Los vínculos de una asignación no se modifican.',
        filterForm([periodFilter(periods), { name: 'teacher_id', label: 'Docente', options: teachersOptions }, { name: 'course_id', label: 'Curso', options: courses }], {}, button('Crear asignación', 'create', '', !openPeriods.length || !activeTeachers.length || !activeCourses.length ? 'disabled title="Se requieren un período abierto, un docente activo y un curso activo"' : '')));
    const list = listing(ctx, { path: '/coordinator/teaching-assignments', columns: [assignmentColumns[0], ['Período', 'academic_period_id', row => esc(periodById.get(String(row.academic_period_id))?.codigo || row.academic_period_id)], ...assignmentColumns.slice(2)],
        actions: row => button('Editar horas', 'edit', row.id, isClosed(row) ? closedAttributes : '') + button('Historial', 'history', row.id) + button('Dar de baja', 'delete', row.id, isClosed(row) ? closedAttributes : '') });
    ctx.on('click', async event => {
        const trigger = event.target.closest('[data-action]');
        if (!trigger) return;
        const kind = trigger.dataset.action, id = trigger.dataset.id;
        if (kind === 'delete') return busy(trigger, () => mutate(ctx, 'Dar de baja la asignación', 'Se libera capacidad y se conserva la auditoría. Un período cerrado no admite esta operación.', '/coordinator/teaching-assignments/' + id, 'DELETE', list.load));
        if (kind === 'history') return pagedDetail(ctx, 'Historial de asignación', '/coordinator/teaching-assignments/' + id + '/history',
            [['Acción', 'accion'], ['Fecha', 'created_at', row => esc(date(row.created_at))], ['Actor (ID)', 'actor_id'], ['Cambios', 'id', row => '<details><summary>Antes / después</summary><pre>' + esc(JSON.stringify({ antes: row.before, despues: row.after }, null, 2)) + '</pre></details>']]);
        if (!['create', 'edit'].includes(kind)) return;
        const initial = kind === 'create' ? {} : (await ctx.request('/coordinator/teaching-assignments/' + id)).data;
        if (!ctx.current()) return;
        if (kind === 'edit' && isClosed(initial)) { detail('Asignación de solo lectura: período cerrado', initial); return; }
        openForm(kind === 'create' ? 'Crear asignación semanal' : 'Editar horas semanales', [
            ...(kind === 'create' ? [
                { ...periodFilter([['', 'Seleccione un período abierto'], ...openPeriods], true) },
                { name: 'teacher_id', label: 'Docente', type: 'select', options: [['', 'Seleccione un docente activo'], ...activeTeachers], required: true },
                { name: 'course_id', label: 'Curso', type: 'select', options: [['', 'Seleccione un curso activo'], ...activeCourses], required: true },
            ] : []),
            ...assignedFields(),
        ], initial, async body => {
            await ctx.request('/coordinator/teaching-assignments' + (kind === 'create' ? '' : '/' + id), { method: kind === 'create' ? 'POST' : 'PATCH', body });
            if (ctx.current()) await list.load();
        });
    });
    await list.load();
}

function statCards(items) {
    return '<div class="stat-grid">' + items.map(([title, value]) => '<article class="stat-card"><p>' + esc(title) + '</p><strong>' + esc(value ?? 'Sin dato') + '</strong></article>').join('') + '</div>';
}

export async function demand(ctx) {
    const periods = await allOptions(ctx, '/academic-periods', row => row.codigo);
    if (!ctx.current()) return;
    if (!periods.length) { shell(ctx, 'Demanda académica', 'Primero registre un período académico.'); return; }
    const fields = [periodFilter(periods, true), text('q', 'Código o nombre de curso', { minlength: 2 }), number('ciclo', 'Ciclo'),
        select('estado_validacion', 'Validación académica', ['VALIDA', 'PENDIENTE_RESULTADOS', 'REQUIERE_AJUSTE', 'SIN_VALIDACION']),
        select('preferencia_turno', 'Preferencia', ['MANANA', 'TARDE', 'SIN_PREFERENCIA']),
        select('sort', 'Ordenar por', ['course_id', 'codigo', 'nombre', 'ciclo', 'creditos', 'registrada', 'valida', 'pendiente_resultados', 'requiere_ajuste']),
        select('direction', 'Dirección', ['asc', 'desc'])];
    let query = { academic_period_id: String(periods[0][0]) }, page = 1, ticket = 0;
    shell(ctx, 'Demanda académica estimada', 'Las preferencias declaradas no son horarios ni grupos. Las cifras no constituyen matrícula definitiva.',
        filterForm(fields, query, button('Exportar CSV', 'export')));
    const columns = [['Código', 'codigo'], ['Curso', 'nombre'], ['Ciclo', 'ciclo'], ['Créditos', 'creditos'], ['Registrada', 'registrada'], ['Válida', 'valida'], ['Pendiente', 'pendiente_resultados'], ['Requiere ajuste', 'requiere_ajuste'], ['Sin validación', 'sin_validacion'], ['Mañana', 'manana'], ['Tarde', 'tarde'], ['Sin preferencia', 'sin_preferencia']];
    const load = async () => {
        const sequence = ++ticket;
        const { academic_period_id: period, ...filters } = query;
        if (!period) throw new Error('Seleccione un período académico.');
        const [response, summary] = await Promise.all([
            ctx.request('/coordinator/academic-periods/' + period + '/demand', { query: { ...filters, page } }),
            ctx.request('/coordinator/academic-periods/' + period + '/summary', { query: filters }),
        ]);
        if (!ctx.current() || sequence !== ticket) return;
        ctx.root.querySelector('[data-content]').innerHTML = '<div class="notice">' + esc(response.data.advertencia) + '</div>' +
            statCards([['Estudiantes que enviaron', summary.data.alumnos_enviaron], ['Estudiantes con ajuste', summary.data.alumnos_requieren_ajuste], ['Sin validación', summary.data.alumnos_sin_validacion], ['Población académica', summary.data.poblacion_academica], ['No enviaron', summary.data.alumnos_no_enviaron]]) +
            '<p class="muted">' + esc(summary.data.fuente_poblacion) + '</p><h2>Demanda por curso</h2>' +
            table(columns, response.data.cursos, row => button('Interesados', 'interested', row.course_id)) + pagination(response.data.paginacion) +
            '<h2>Resumen por ciclo</h2>' + table([['Ciclo', 'ciclo'], ['Estudiantes que enviaron', 'alumnos_enviaron'], ['Selecciones registradas', 'registrada'], ['Selecciones válidas', 'valida'], ['Pendientes', 'pendiente_resultados'], ['Con ajuste', 'requiere_ajuste']], summary.data.ciclos) +
            '<p class="muted">Un estudiante puede aparecer en varios ciclos; no sume esos conteos para obtener alumnos únicos.</p>';
    };
    ctx.on('submit', async event => {
        if (!event.target.matches('[data-filters]')) return;
        event.preventDefault(); query = queryFrom(event.target); page = 1; await load();
    });
    ctx.on('click', async event => {
        const pageButton = event.target.closest('[data-page]');
        if (pageButton) { page = Number(pageButton.dataset.page); await load(); return; }
        const trigger = event.target.closest('[data-action]');
        if (!trigger) return;
        const { academic_period_id: period, ...filters } = query;
        if (trigger.dataset.action === 'export') {
            await busy(trigger, () => ctx.request('/coordinator/academic-periods/' + period + '/demand/export', { query: filters, download: true, filename: 'demanda-' + period + '.csv' }));
        }
        if (trigger.dataset.action === 'interested') await pagedDetail(ctx, 'Estudiantes interesados', '/coordinator/academic-periods/' + period + '/demand/courses/' + trigger.dataset.id + '/students',
            [['Código', 'codigo_universitario'], ['Nombres', 'nombres'], ['Apellidos', 'apellidos'], ['Revisión', 'revision_id'], ['Preferencia', 'preferencia_turno', row => esc(label(row.preferencia_turno))], ['Validación', 'estado_validacion', row => badge(row.estado_validacion)]],
            { estado_validacion: filters.estado_validacion, preferencia_turno: filters.preferencia_turno });
    });
    await load();
}

export async function loadReport(ctx) {
    const periods = await allOptions(ctx, '/academic-periods', row => row.codigo);
    if (!ctx.current()) return;
    if (!periods.length) { shell(ctx, 'Carga docente', 'Primero registre un período académico.'); return; }
    let query = { academic_period_id: String(periods[0][0]), view: 'teachers' }, page = 1, ticket = 0;
    shell(ctx, 'Carga docente semanal', 'Una unidad por curso y período. No representa paralelos, horarios, turnos ni conflictos de tiempo.',
        filterForm([periodFilter(periods, true), { name: 'view', label: 'Vista del reporte', options: [['teachers', 'Por docente'], ['courses', 'Por curso']] }, text('q', 'Buscar código o nombre', { minlength: 2 }), number('ciclo', 'Ciclo (vista por curso)'), select('estado', 'Estado', states)], query));
    const teacherColumns = [['Código', 'docente.codigo_institucional'], ['Docente', 'docente.nombres', row => esc(row.docente.nombres + ' ' + row.docente.apellidos)], ['Estado', 'docente.estado', row => badge(row.docente.estado)], ['Cursos', 'cantidad_cursos'], ...assignedColumns, ['Máximo semanal', 'docente.max_horas_semanales'], ['Disponible', 'horas_disponibles'], ['Excede límite', 'excede_limite', row => row.excede_limite ? 'Sí' : 'No']];
    const courseColumns = [['Código', 'codigo'], ['Curso', 'nombre'], ['Ciclo', 'ciclo'], ['Estado', 'estado', row => badge(row.estado)], ['Docentes', 'cantidad_docentes'], ...assignedColumns, ['Teoría pendiente', 'horas_teoria_pendientes'], ['Práctica pendiente', 'horas_practica_pendientes'], ['Laboratorio pendiente', 'horas_laboratorio_pendientes'], ['Demanda registrada', 'demanda_registrada'], ['Demanda válida', 'demanda_valida']];
    const load = async () => {
        const sequence = ++ticket;
        const { academic_period_id: period, view = 'teachers', ...filters } = query;
        if (!period) throw new Error('Seleccione un período académico.');
        const [response, summary] = await Promise.all([
            ctx.request('/coordinator/academic-periods/' + period + '/teaching-load/' + view, { query: { ...Object.fromEntries(Object.entries(filters).filter(([key]) => view === 'courses' || key !== 'ciclo')), page } }),
            ctx.request('/coordinator/academic-periods/' + period + '/teaching-load'),
        ]);
        if (!ctx.current() || sequence !== ticket) return;
        const totals = summary.data.totales;
        ctx.root.querySelector('[data-content]').innerHTML = '<div class="notice">' + esc(summary.data.advertencia) + '<p>' + esc(summary.data.advertencia_demanda) + '</p></div>' +
            statCards([['Horas semanales asignadas', totals.total_semanal], ['Docentes con carga', totals.docentes_con_carga], ['Cursos sin asignación', totals.cursos_sin_asignacion], ['Docentes excedidos', totals.docentes_exceden_limite]]) +
            table(view === 'teachers' ? teacherColumns : courseColumns, response.data, row => button('Detalle de carga', 'load-detail', view === 'teachers' ? row.docente.id : row.course_id)) + pagination(response.meta);
    };
    ctx.on('submit', async event => {
        if (!event.target.matches('[data-filters]')) return;
        event.preventDefault(); query = queryFrom(event.target); page = 1; await load();
    });
    ctx.on('click', async event => {
        const pageButton = event.target.closest('[data-page]');
        if (pageButton) { page = Number(pageButton.dataset.page); await load(); return; }
        const trigger = event.target.closest('[data-action="load-detail"]');
        if (!trigger) return;
        const path = '/coordinator/academic-periods/' + query.academic_period_id + '/teaching-load/' + (query.view || 'teachers') + '/' + trigger.dataset.id;
        const response = await ctx.request(path);
        if (!ctx.current()) return;
        const dialog = detail('Detalle de carga semanal', { carga: response.data.carga, advertencia: response.data.advertencia, advertencia_demanda: response.data.advertencia_demanda });
        const content = document.createElement('section'); dialog.append(content);
        const paint = data => { content.innerHTML = '<h3>Asignaciones vigentes</h3>' + table(assignmentColumns, data.asignaciones) + pagination(data.paginacion); };
        paint(response.data);
        content.addEventListener('click', async click => {
            const target = click.target.closest('[data-page]'); if (!target) return;
            try { const result = await ctx.request(path, { query: { page: target.dataset.page } }); if (ctx.current() && dialog.open) paint(result.data); }
            catch (error) { showError(error); }
        });
    });
    await load();
}

export { statCards };


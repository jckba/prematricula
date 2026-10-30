import { openForm } from './forms';
import { renderImport } from './imports';
import { badge, date } from './ui';
import { states, periodStates, options, text, number, select, button, shell, filterForm, listing, allOptions, periodFilter, mutate, pagedDetail, esc, table, pagination, detail, showError, busy } from './coordinator-support';

const periodColumns = [['Período', 'codigo'], ['Estado', 'estado', row => badge(row.estado)], ['Inicio de clases', 'fecha_inicio', row => esc(date(row.fecha_inicio))], ['Fin de clases', 'fecha_fin', row => esc(date(row.fecha_fin))]];
const historyColumns = [['Curso', 'curso.nombre'], ['Código', 'curso.codigo'], ['Período', 'periodo.codigo'], ['Nota oficial', 'nota_final'], ['Estado', 'estado', row => badge(row.estado)]];
const enrollmentColumns = [['Solicitud', 'id'], ['Estudiante', 'estudiante.nombres', row => esc(row.estudiante?.nombres + ' ' + row.estudiante?.apellidos)], ['Código', 'estudiante.codigo_universitario'], ['Período', 'periodo.codigo'], ['Estado operativo', 'estado', row => badge(row.estado)], ['Validación académica', 'validacion_academica.estado', row => badge(row.validacion_academica?.estado)], ['Enviada', 'fecha_envio', row => esc(date(row.fecha_envio))]];

export async function accounts(ctx) {
    shell(ctx, 'Cuentas estudiantiles', 'Administración de acceso. Cambiar credenciales o estado revoca las sesiones del estudiante.', '<div class="toolbar">' + button('Registrar estudiante', 'create') + '</div>');
    const list = listing(ctx, {
        path: '/coordinator/accounts',
        columns: [['ID de cuenta', 'id'], ['Correo institucional', 'correo_institucional'], ['Estado', 'estado', row => badge(row.estado)]],
        actions: row => button('Editar acceso', 'edit', row.id) + button('Consultar', 'detail', row.id),
    });
    ctx.on('click', async event => {
        const trigger = event.target.closest('[data-action]');
        if (!trigger) return;
        if (trigger.dataset.action === 'detail') {
            const response = await ctx.request('/coordinator/accounts/' + trigger.dataset.id);
            if (ctx.current()) detail('Identidad pública de la cuenta', response.data);
            return;
        }
        const create = trigger.dataset.action === 'create';
        if (!create && trigger.dataset.action !== 'edit') return;
        const initial = create ? {} : (await ctx.request('/coordinator/accounts/' + trigger.dataset.id)).data;
        const fields = [
            text('correo_institucional', 'Correo institucional', { type: 'email', required: true, maxlength: 255 }),
            text('password', create ? 'Contraseña inicial' : 'Nueva contraseña (opcional)', { type: 'password', required: create, minlength: 8, maxlength: 72, help: 'Máximo 72 bytes; algunos caracteres ocupan más de un byte.' }),
            ...(create ? [
                text('estudiante.codigo_universitario', 'Código universitario', { required: true, maxlength: 30 }),
                text('estudiante.nombres', 'Nombres', { required: true, maxlength: 100 }),
                text('estudiante.apellidos', 'Apellidos', { required: true, maxlength: 150 }),
                number('estudiante.creditos_maximos', 'Créditos máximos', { value: 22, max: 32767, required: true }),
            ] : [select('estado', 'Estado de cuenta', states, { required: true })]),
        ];
        if (!ctx.current()) return;
        openForm(create ? 'Registrar cuenta y perfil estudiantil' : 'Actualizar acceso estudiantil', fields, initial, async body => {
            await ctx.request('/coordinator/accounts' + (create ? '' : '/' + initial.id), { method: create ? 'POST' : 'PATCH', body });
            if (ctx.current()) await list.load();
        });
    });
    await list.load();
}

export async function students(ctx) {
    const periods = await allOptions(ctx, '/academic-periods', row => row.codigo);
    if (!ctx.current()) return;
    shell(ctx, 'Consulta académica de estudiantes', 'Busque por código exacto o nombres. La consulta de situación académica requiere seleccionar un período.',
        filterForm([text('codigo_universitario', 'Código universitario exacto'), text('q', 'Código parcial o nombres', { minlength: 2 }), select('estado', 'Estado de perfil', states), select('sort', 'Ordenar por', ['codigo_universitario', 'nombres', 'apellidos']), select('direction', 'Dirección', ['asc', 'desc'])]) +
        '<label class="field"><span>Período para consulta académica</span><select data-academic-period><option value="">Seleccione un período</option>' + periods.map(([id, title]) => '<option value="' + esc(id) + '">' + esc(title) + '</option>').join('') + '</select></label>');
    const list = listing(ctx, {
        path: '/coordinator/students',
        columns: [['Código', 'codigo_universitario'], ['Nombres', 'nombres'], ['Apellidos', 'apellidos'], ['Estado de perfil', 'estado', row => badge(row.estado)], ['Créditos máximos', 'creditos_maximos']],
        actions: row => button('Situación académica', 'academic', row.codigo_universitario),
    });
    ctx.on('click', async event => {
        const trigger = event.target.closest('[data-action="academic"]');
        if (!trigger) return;
        const period = ctx.root.querySelector('[data-academic-period]');
        if (!period.value) { period.focus(); throw new Error('Seleccione el período para consultar la situación académica.'); }
        let page = 1;
        const response = await ctx.request('/coordinator/students/' + encodeURIComponent(trigger.dataset.id) + '/academic', { query: { academic_period_id: period.value } });
        if (!ctx.current()) return;
        const dialog = detail('Situación académica', { estudiante: response.data.estudiante, periodo: response.data.periodo, prematricula: response.data.prematricula });
        const content = document.createElement('section');
        dialog.append(content);
        const paint = data => {
            content.innerHTML = '<h3>Historial oficial anterior al período</h3>' + table(historyColumns, data.historial_academico.data) + pagination(data.historial_academico.paginacion);
        };
        paint(response.data);
        content.addEventListener('click', async click => {
            const next = click.target.closest('[data-page]');
            if (!next) return;
            page = Number(next.dataset.page);
            try {
                const result = await ctx.request('/coordinator/students/' + encodeURIComponent(trigger.dataset.id) + '/academic', { query: { academic_period_id: period.value, page } });
                if (dialog.open && ctx.current()) paint(result.data);
            } catch (error) { showError(error); }
        });
    });
    await list.load();
}

export async function periods(ctx) {
    shell(ctx, 'Períodos y ventanas', 'Las fechas de clases y las ventanas de prematrícula son independientes. Las ventanas son globales y no se editan ni eliminan.', '<div class="toolbar">' + button('Crear período', 'create') + '</div>');
    const list = listing(ctx, { path: '/academic-periods', columns: periodColumns,
        actions: row => button('Ventanas', 'windows', row.id) + button('Editar', 'edit', row.id) + button('Eliminar', 'delete', row.id) });
    ctx.on('click', async event => {
        const trigger = event.target.closest('[data-action]');
        if (!trigger) return;
        const kind = trigger.dataset.action;
        const id = trigger.dataset.id;
        if (kind === 'delete') return busy(trigger, () => mutate(ctx, 'Eliminar período', 'Podrás eliminarlo únicamente si aún no tiene registros académicos asociados.', '/coordinator/academic-periods/' + id, 'DELETE', list.load));
        if (kind === 'windows') {
            const [response, periodResponse] = await Promise.all([ctx.request('/academic-periods/' + id + '/windows'), ctx.request('/academic-periods/' + id)]);
            if (!ctx.current()) return;
            const dialog = detail('Ventanas de prematrícula', {});
            const content = document.createElement('section');
            const canCreateWindow = ['PLANIFICACION', 'PREMATRICULA'].includes(periodResponse.data.estado);
            content.innerHTML = '<p>Inicio inclusivo y fin exclusivo. Las fechas se muestran en America/Lima; confirme la zona institucional del período antes de registrar.</p><div class="toolbar">' + button('Crear ventana global', 'window-create', id, canCreateWindow ? '' : 'disabled title="El período no admite nuevas ventanas"') + '</div>' +
                table([['Tipo', 'tipo'], ['Inicio', 'fecha_inicio', row => esc(date(row.fecha_inicio))], ['Fin', 'fecha_fin', row => esc(date(row.fecha_fin))], ['Zona', 'timezone'], ['Motivo', 'motivo']], response.data);
            dialog.append(content);
            content.querySelector('[data-action]').onclick = () => {
                if (!canCreateWindow) return;
                dialog.close();
                openForm('Crear ventana global', [
                    select('tipo', 'Tipo de ventana', ['ORDINARIA', 'EXTRAORDINARIA'], { required: true }),
                    text('fecha_inicio', 'Inicio (hora institucional)', { type: 'datetime-local', required: true, step: 1 }),
                    text('fecha_fin', 'Fin (hora institucional)', { type: 'datetime-local', required: true, step: 1 }),
                    text('motivo', 'Motivo', { type: 'textarea', required: true, maxlength: 2000 }),
                ], {}, async body => {
                    for (const field of ['fecha_inicio', 'fecha_fin']) body[field] = body[field].replace('T', ' ') + (body[field].length === 16 ? ':00' : '');
                    await ctx.request('/coordinator/academic-periods/' + id + '/windows', { method: 'POST', body });
                    if (ctx.current()) await list.load();
                });
            };
            return;
        }
        if (!['create', 'edit'].includes(kind)) return;
        const initial = kind === 'create' ? {} : (await ctx.request('/academic-periods/' + id)).data;
        if (!ctx.current()) return;
        openForm(kind === 'create' ? 'Crear período académico' : 'Actualizar período académico', [
            text('codigo', 'Código del período', { required: true, maxlength: 20 }),
            text('fecha_inicio', 'Inicio de clases', { type: 'date', required: true }),
            text('fecha_fin', 'Fin de clases', { type: 'date', required: true }),
            select('estado', 'Estado', periodStates, { required: true }),
        ], initial, async body => {
            // Unchanged academic fields are omitted to respect protected periods.
            if (kind === 'edit') Object.keys(body).forEach(key => { if (body[key] === initial[key]) delete body[key]; });
            await ctx.request('/coordinator/academic-periods' + (kind === 'create' ? '' : '/' + id), { method: kind === 'create' ? 'POST' : 'PATCH', body });
            if (ctx.current()) await list.load();
        });
    });
    await list.load();
}

export async function curriculum(ctx) {
    shell(ctx, 'Cursos y malla curricular', 'Consulta y actualiza la malla curricular. Los cursos ya utilizados conservan sus datos académicos.',
        filterForm([text('q', 'Código o nombre'), number('ciclo', 'Ciclo'), select('estado', 'Estado', states)]));
    const list = listing(ctx, { path: '/courses',
        columns: [['Código', 'codigo'], ['Curso', 'nombre'], ['Ciclo', 'ciclo'], ['Créditos', 'creditos'], ['Teoría', 'horas_teoria'], ['Práctica', 'horas_practica'], ['Laboratorio', 'horas_laboratorio'], ['Estado', 'estado', row => badge(row.estado)]],
        actions: row => button('Detalle', 'detail', row.id) + button('Editar', 'edit', row.id) });
    ctx.on('click', async event => {
        const trigger = event.target.closest('[data-action]');
        if (!trigger) return;
        const response = await ctx.request('/courses/' + trigger.dataset.id);
        if (!ctx.current()) return;
        if (trigger.dataset.action === 'detail') { detail('Asignatura y prerrequisitos', response.data); return; }
        if (trigger.dataset.action !== 'edit') return;
        const initial = { ...response.data, prerrequisitos: response.data.prerrequisitos.map(course => course.codigo).join(';') };
        openForm('Editar asignatura: ' + initial.codigo, [
            text('nombre', 'Nombre', { required: true, maxlength: 150 }), number('creditos', 'Créditos', { required: true, max: 32767 }),
            number('ciclo', 'Ciclo', { required: true, max: 32767 }),
            ...['teoria', 'practica', 'laboratorio'].map(type => text('horas_' + type, 'Horas de ' + type, { required: true, pattern: '[0-9]{1,3}([.][0-9]{1,2})?', help: 'Horas semanales, hasta dos decimales.' })),
            text('prerrequisitos', 'Códigos de prerrequisitos', { help: 'Separados por punto y coma. Dejar vacío elimina prerrequisitos si el curso permite editarse.' }),
            select('estado', 'Estado', states, { required: true }),
        ], initial, async body => {
            Object.keys(body).forEach(key => { if (body[key] === initial[key]) delete body[key]; });
            if (Object.hasOwn(body, 'prerrequisitos')) body.prerrequisitos = body.prerrequisitos.split(';').map(code => code.trim()).filter(Boolean);
            await ctx.request('/coordinator/courses/' + initial.id, { method: 'PATCH', body });
            if (ctx.current()) await list.load();
        });
    });
    const importRoot = document.createElement('div');
    ctx.root.append(importRoot);
    renderImport(importRoot, 'curriculum', ctx.signal);
    ctx.on('academic:imported', list.load);
    await list.load();
}

export async function results(ctx) {
    const periods = await allOptions(ctx, '/academic-periods', row => row.codigo);
    if (!ctx.current()) return;
    shell(ctx, 'Resultados académicos oficiales', 'La nota y el estado son datos oficiales importados. No se calcula aprobación con un umbral.',
        '<form data-result-student-search class="filters"><label class="field" for="result-student-q"><span>Buscar estudiante por código o nombre</span><input id="result-student-q" name="q" minlength="2" maxlength="150" required type="search"></label><button class="btn btn-secondary">Buscar estudiante</button><span class="muted" data-search-status role="status"></span></form>' +
        filterForm([periodFilter(periods), { name: 'student_id', label: 'Estudiante (opcional)', options: [] }]) + '<div class="toolbar">' + button('Auditoría de importaciones', 'audit') + '</div>');
    const list = listing(ctx, { path: '/coordinator/academic-history', columns: [['Código universitario', 'codigo_universitario'], ...historyColumns] });
    ctx.root.querySelector('[data-result-student-search]').addEventListener('submit', event => {
        event.preventDefault();
        const form = event.currentTarget;
        if (!form.reportValidity()) return;
        busy(event.submitter, async () => {
            const response = await ctx.request('/coordinator/students', { query: { q: form.elements.q.value.trim(), per_page: 100 } });
            if (!ctx.current()) return;
            const select = ctx.root.querySelector('[data-filters] [name="student_id"]');
            select.innerHTML = '<option value="">Todos</option>' + response.data.map(student => '<option value="' + esc(student.id) + '">' + esc(student.codigo_universitario + ' · ' + student.nombres + ' ' + student.apellidos) + '</option>').join('');
            ctx.root.querySelector('[data-search-status]').textContent = response.data.length + ' coincidencias. Selecciona el estudiante y pulsa Consultar.' + ((response.meta?.last_page || 1) > 1 ? ' Precisa la búsqueda para reducir los resultados.' : '');
            select.focus();
        });
    }, { signal: ctx.signal });
    ctx.on('click', async event => {
        if (!event.target.closest('[data-action="audit"]')) return;
        const dialog = await pagedDetail(ctx, 'Importaciones de resultados', '/coordinator/academic-history/imports',
            [['Lote', 'id'], ['Fecha', 'created_at', row => esc(date(row.created_at))], ['Motivo', 'motivo'], ['Auditoría', 'id', row => button('Ver cambios', 'audit-detail', row.id)]]);
        dialog.addEventListener('click', async click => {
            const trigger = click.target.closest('[data-action="audit-detail"]');
            if (!trigger) return;
            try { const response = await ctx.request('/coordinator/academic-history/imports/' + trigger.dataset.id); if (ctx.current()) detail('Auditoría del lote', response.data); }
            catch (error) { showError(error); }
        });
    });
    const importRoot = document.createElement('div');
    ctx.root.append(importRoot);
    renderImport(importRoot, 'history', ctx.signal);
    ctx.on('academic:imported', list.load);
    await list.load();
}

export async function enrollments(ctx) {
    shell(ctx, 'Prematrículas registradas', 'Consulta los envíos registrados y sus constancias. La validación académica se muestra por separado.',
        filterForm([select('estado', 'Estado operativo', ['BORRADOR', 'ENVIADA', 'APROBADA', 'RECHAZADA'])]));
    const list = listing(ctx, { path: '/coordinator/pre-enrollments', columns: enrollmentColumns,
        actions: row => button('Detalle y constancias', 'detail', row.id) });
    ctx.on('click', async event => {
        const trigger = event.target.closest('[data-action="detail"]');
        if (!trigger) return;
        const id = trigger.dataset.id;
        const response = await ctx.request('/coordinator/pre-enrollments/' + id);
        if (!ctx.current()) return;
        const dialog = detail('Prematrícula registrada', response.data);
        const actions = document.createElement('div');
        actions.className = 'toolbar';
        actions.innerHTML = button('Constancia vigente PDF', 'receipt', id) + button('Revisiones', 'revisions', id);
        dialog.append(actions);
        actions.addEventListener('click', async click => {
            const action = click.target.closest('[data-action]');
            if (!action) return;
            try {
                if (action.dataset.action === 'receipt') await busy(action, () => ctx.request('/coordinator/pre-enrollments/' + id + '/receipt', { download: true, filename: 'constancia-' + id + '.pdf' }));
                else {
                    const revisions = await pagedDetail(ctx, 'Revisiones de la prematrícula', '/coordinator/pre-enrollments/' + id + '/revisions',
                        [['Revisión', 'revision_number'], ['Enviada', 'submitted_at', row => esc(date(row.submitted_at))], ['Constancia', 'receipt_code'],
                            ['Descarga', 'id', row => row.receipt_available ? button('PDF histórico', 'revision-receipt', row.id) : 'No disponible']]);
                    revisions.addEventListener('click', async revisionEvent => {
                        const revision = revisionEvent.target.closest('[data-action="revision-receipt"]');
                        if (!revision) return;
                        try { await busy(revision, () => ctx.request('/coordinator/pre-enrollments/' + id + '/revisions/' + revision.dataset.id + '/receipt', { download: true, filename: 'constancia-' + id + '-revision-' + revision.dataset.id + '.pdf' })); }
                        catch (error) { showError(error); }
                    });
                }
            } catch (error) { showError(error); }
        });
    });
    await list.load();
}


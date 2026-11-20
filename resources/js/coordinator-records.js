import { openForm } from './forms';
import { renderImport } from './imports';
import { badge, date, icon, label } from './ui';
import { states, periodStates, options, text, number, select, button, shell, filterForm, listing, allOptions, periodFilter, mutate, pagedDetail, esc, table, pagination, detail, showError, busy, confirmAction, flash } from './coordinator-support';


/** Ventana de solo lectura con el estilo de lista de datos (ícono + etiqueta en mayúsculas + valor). items: [ícono, etiqueta, valor, conservarMayúsculas?][] */
function infoDialog(title, items) {
    const dialog = document.createElement('dialog');
    dialog.className = 'modal';
    dialog.setAttribute('aria-labelledby', 'info-dialog-title');
    dialog.innerHTML = '<div class="modal-header"><h2 id="info-dialog-title">' + esc(title) + '</h2><button class="btn btn-secondary" data-close>Cerrar</button></div>' +
        '<dl class="info-list">' + items.map(([symbol, name, value, keepCase]) =>
            '<div class="info-item"><dt>' + icon(symbol) + esc(name) + '</dt><dd' + (keepCase ? ' class="info-keep-case"' : '') + '>' + esc(value ?? 'Sin dato') + '</dd></div>').join('') + '</dl>';
    document.body.append(dialog);
    dialog.querySelector('[data-close]').onclick = () => dialog.close();
    dialog.addEventListener('close', () => dialog.remove(), { once: true });
    dialog.showModal();
    return dialog;
}

export async function accounts(ctx) {
    shell(ctx, 'Cuentas estudiantiles', 'Administración de acceso. Cambiar credenciales o estado revoca las sesiones del estudiante.', '<div class="toolbar"><button type="button" class="btn btn-raised" data-action="create">' + icon('user-plus') + '<span>Registrar estudiante</span></button></div>');
    // Mismo estilo que la tabla de Estudiantes: tarjeta, filas compactas, estado con acento y acciones pequeñas centradas.
    ctx.root.querySelector('[data-content]')?.classList.add('card', 'history-card');
    const list = listing(ctx, {
        path: '/coordinator/accounts',
        tableClass: 'history-table',
        columns: [
            ['ID de cuenta', 'id', row => '<span class="student-code">' + esc(row.id) + '</span>'],
            ['Correo institucional', 'correo_institucional'],
            ['Estado', 'estado', row => '<span class="history-status ' + (row.estado === 'ACTIVO' ? 'history-status-done' : 'history-status-inactive') + '">' + esc(label(row.estado)) + '</span>'],
        ],
        actions: row =>
            '<button type="button" class="btn btn-small btn-secondary row-action" data-action="edit" data-id="' + esc(row.id) + '" aria-label="Editar acceso de ' + esc(row.correo_institucional) + '">' + icon('user') + '<span>Editar acceso</span></button>' +
            '<button type="button" class="btn btn-small btn-raised history-pdf" data-action="detail" data-id="' + esc(row.id) + '" aria-label="Consultar cuenta de ' + esc(row.correo_institucional) + '">' + icon('eye') + '<span>Consultar</span></button>',
    });
    ctx.on('click', async event => {
        const trigger = event.target.closest('[data-action]');
        if (!trigger) return;
        if (trigger.dataset.action === 'detail') {
            const response = await ctx.request('/coordinator/accounts/' + trigger.dataset.id);
            if (ctx.current()) infoDialog('Información de la cuenta', [
                ['id-card', 'ID de cuenta', response.data.id],
                ['mail', 'Correo institucional', response.data.correo_institucional, true],
                ['user', 'Rol', label(response.data.rol)],
                ['check-circle', 'Estado de cuenta', label(response.data.estado)],
            ]);
            return;
        }
        const create = trigger.dataset.action === 'create';
        if (!create && trigger.dataset.action !== 'edit') return;
        const initial = create ? {} : (await ctx.request('/coordinator/accounts/' + trigger.dataset.id)).data;
        // Registro: solo código, nombres, apellidos y correo. La contraseña inicial es el código universitario
        // y los créditos máximos toman el valor por defecto de la base de datos (22).
        const fields = create ? [
            text('estudiante.codigo_universitario', 'Código universitario', { required: true, minlength: 8, maxlength: 30, wide: true, autocomplete: 'off', placeholder: 'Tu código de matrícula', help: '(Tu código de matrícula será tu contraseña)' }),
            text('estudiante.nombres', 'Nombres', { required: true, maxlength: 100, autocomplete: 'off', placeholder: 'Ingresa tus nombres' }),
            text('estudiante.apellidos', 'Apellidos', { required: true, maxlength: 150, autocomplete: 'off', placeholder: 'Ingresa tus apellidos' }),
            text('correo_institucional', 'Correo institucional', { type: 'email', required: true, maxlength: 255, wide: true, autocomplete: 'off', placeholder: 'usuario@unitru.edu.pe' }),
        ] : [
            text('correo_institucional', 'Correo institucional', { type: 'email', required: true, maxlength: 255, wide: true, autocomplete: 'off', placeholder: 'usuario@unitru.edu.pe' }),
            text('password', 'Nueva contraseña (opcional)', { type: 'password', required: false, minlength: 8, maxlength: 72, placeholder: 'Déjala vacía para no cambiarla', help: 'Entre 8 y 72 caracteres.' }),
            select('estado', 'Estado de cuenta', states, { required: true }),
        ];
        if (!ctx.current()) return;
        openForm(create ? 'Registrar estudiante' : 'Editar acceso', fields, initial, async body => {
            if (create) body.password = body.estudiante.codigo_universitario;
            await ctx.request('/coordinator/accounts' + (create ? '' : '/' + initial.id), { method: create ? 'POST' : 'PATCH', body });
            if (ctx.current()) await list.load();
        }, create ? {
            icon: 'user',
            description: 'Crea la cuenta de acceso y el perfil académico del estudiante.',
            note: '<div class="form-modal-note" role="note">' + icon('lock') + '<p><strong>Contraseña inicial:</strong> será el código universitario del estudiante. Recomiéndale cambiarla al ingresar por primera vez.</p></div>',
            submitLabel: 'Registrar estudiante',
            successMessage: 'El estudiante se registró correctamente.',
        } : {
            icon: 'lock',
            description: 'Actualiza el correo, la contraseña o el estado de la cuenta ' + initial.correo_institucional + '.',
            note: '<div class="form-modal-note form-modal-note-warning" role="note">' + icon('logout') + '<p><strong>Importante:</strong> si cambias el correo, la contraseña o el estado, el estudiante tendrá que volver a iniciar sesión.</p></div>',
            submitLabel: 'Guardar cambios',
            successMessage: 'El acceso de la cuenta se actualizó correctamente.',
        });
    });
    await list.load();
}

export async function students(ctx) {
    shell(ctx, 'Estudiantes', 'Consulta los estudiantes registrados en la escuela, búscalos por código universitario o estado de perfil y revisa su información personal.',
        '<form data-filters class="filters filters-panel" aria-labelledby="student-filters-title">' +
        '<div class="filters-head"><h2 id="student-filters-title">Búsqueda de estudiantes</h2></div>' +
        '<label class="field" for="filter-codigo_universitario"><span>Código universitario</span><span class="input-icon"><input id="filter-codigo_universitario" name="codigo_universitario" type="text" placeholder="Ej. código universitario exacto">' + icon('search') + '</span></label>' +
        '<label class="field" for="filter-estado"><span>Estado de perfil</span><select id="filter-estado" name="estado"><option value="">Todos</option>' + options(states).map(([key, title]) => '<option value="' + esc(key) + '">' + esc(title) + '</option>').join('') + '</select></label>' +
        '<button class="btn btn-raised" type="submit">' + icon('search') + 'Consultar</button>' +
        '</form>');
    // Mismo estilo que la tabla "Historial" del estudiante: tarjeta, filas compactas, acción centrada y botón dorado con ícono.
    ctx.root.querySelector('[data-content]')?.classList.add('card', 'history-card');
    // Filas de la página actual, para mostrar la información personal sin otra petición al servidor.
    const rows = new Map();
    const list = listing(ctx, {
        path: '/coordinator/students',
        tableClass: 'history-table',
        columns: [['Código', 'codigo_universitario', row => '<span class="student-code">' + esc(row.codigo_universitario) + '</span>'], ['Nombres', 'nombres'], ['Apellidos', 'apellidos'], ['Estado de perfil', 'estado', row => '<span class="history-status ' + (row.estado === 'ACTIVO' ? 'history-status-done' : 'history-status-inactive') + '">' + esc(label(row.estado)) + '</span>']],
        actions: row => {
            rows.set(String(row.codigo_universitario), row);
            return '<button type="button" class="btn btn-small btn-raised history-pdf" data-action="info" data-id="' + esc(row.codigo_universitario) + '" aria-label="Ver información personal de ' + esc(row.nombres + ' ' + row.apellidos) + '">' + icon('eye') + '<span>Ver información</span></button>';
        },
    });
    ctx.on('click', event => {
        const trigger = event.target.closest('[data-action="info"]');
        if (!trigger) return;
        const student = rows.get(trigger.dataset.id);
        if (!student) return;
        infoDialog('Información del estudiante', [
            ['user', 'Nombres y apellidos', student.nombres + ' ' + student.apellidos],
            ['id-card', 'Código universitario', student.codigo_universitario],
            ['check-circle', 'Estado de perfil', label(student.estado)],
        ]);
    });
    await list.load();
}

// ===== Períodos y prematrícula =====
// Por dentro, "abrir" crea la ventana ORDINARIA y "extender" crea una EXTRAORDINARIA que empieza donde terminó la última.
const INSTITUTIONAL_TZ = 'America/Lima';
/** ISO (UTC) → "18/10/2026 18:00" en hora institucional. */
const dateTime = (iso, tz = INSTITUTIONAL_TZ) => new Intl.DateTimeFormat('es-PE', { timeZone: tz, day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false }).format(new Date(iso));
/** ISO (UTC) → "YYYY-MM-DD HH:mm:ss" en hora institucional (formato que exige el servidor). */
function institutionalStamp(iso, tz = INSTITUTIONAL_TZ) {
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-CA', { timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false })
        .formatToParts(new Date(iso)).map(part => [part.type, part.value]));
    return parts.year + '-' + parts.month + '-' + parts.day + ' ' + (parts.hour === '24' ? '00' : parts.hour) + ':' + parts.minute + ':' + parts.second;
}
/** Valor de <input type="datetime-local"> → "YYYY-MM-DD HH:mm:ss". */
const fromInput = value => value.replace('T', ' ') + (value.length === 16 ? ':00' : '');
/**
 * El servidor exige fechas de clases (ordena los períodos y elige el período destino de la prematrícula),
 * pero la coordinación no las gestiona: se derivan de la prematrícula. Inicio = día siguiente al cierre de la
 * prematrícula; fin = 4 meses después (duración referencial de un semestre).
 */
function derivedClassDates(enrollmentEnd) {
    const start = new Date(enrollmentEnd.slice(0, 10) + 'T00:00:00Z');
    start.setUTCDate(start.getUTCDate() + 1);
    const end = new Date(start);
    end.setUTCMonth(end.getUTCMonth() + 4);
    return { fecha_inicio: start.toISOString().slice(0, 10), fecha_fin: end.toISOString().slice(0, 10) };
}
/** Ahora en hora institucional, para precargar el inicio ("YYYY-MM-DDTHH:mm"). */
const nowInput = () => institutionalStamp(new Date().toISOString()).slice(0, 16).replace(' ', 'T');

function enrollmentWindows(period) {
    const windows = [...(period.ventanas || [])].sort((a, b) => new Date(a.fecha_inicio) - new Date(b.fecha_inicio));
    const now = Date.now();
    return {
        windows,
        last: windows.at(-1),
        active: windows.find(item => new Date(item.fecha_inicio) <= now && now < new Date(item.fecha_fin)),
        upcoming: windows.find(item => new Date(item.fecha_inicio) > now),
        manageable: ['PLANIFICACION', 'PREMATRICULA'].includes(period.estado),
    };
}
const statusChip = (tone, text) => '<span class="history-status history-status-' + tone + '">' + esc(text) + '</span>';
const periodStateTone = { PLANIFICACION: 'inactive', PREMATRICULA: 'done', EN_CURSO: 'info', CERRADO: 'inactive' };
// Fases: Prematrícula (todos pueden registrarse) → Clases (la prematrícula queda cerrada) → Cerrado.
// PLANIFICACION solo aparece en períodos antiguos que aún no abrieron su prematrícula.
const periodStateLabel = { PLANIFICACION: 'Sin abrir', PREMATRICULA: 'Prematrícula', EN_CURSO: 'Clases', CERRADO: 'Cerrado' };

/** Estado de la prematrícula en lenguaje del usuario: sin abrir, programada, abierta (hasta…), cerrada. */
function enrollmentStatus(period) {
    const { windows, last, active, upcoming } = enrollmentWindows(period);
    const times = windows.length - 1;
    const extended = times > 0 ? '<small class="period-note">Plazo extendido ' + times + (times === 1 ? ' vez' : ' veces') + '</small>' : '';
    if (period.estado === 'EN_CURSO' || period.estado === 'CERRADO') return statusChip('inactive', 'Cerrada') + extended;
    if (!windows.length) return statusChip('inactive', 'Sin abrir');
    if (active && period.estado === 'PREMATRICULA') return statusChip('done', 'Abierta hasta ' + dateTime(active.fecha_fin, active.timezone)) + extended;
    if (upcoming) return statusChip('pending', 'Programada desde ' + dateTime(upcoming.fecha_inicio, upcoming.timezone)) + extended;
    return statusChip('inactive', 'Cerrada el ' + dateTime(last.fecha_fin, last.timezone)) + extended;
}

function periodActions(period) {
    const { windows, manageable } = enrollmentWindows(period);
    const name = esc(period.codigo);
    const id = esc(period.id);
    const primary = !manageable ? '' : windows.length
        ? '<button type="button" class="btn btn-small btn-raised history-pdf" data-action="extend" data-id="' + id + '" aria-label="Extender plazo de prematrícula del período ' + name + '">' + icon('calendar') + '<span>Extender plazo</span></button>'
        : '<button type="button" class="btn btn-small btn-raised history-pdf" data-action="open" data-id="' + id + '" aria-label="Abrir prematrícula del período ' + name + '">' + icon('calendar') + '<span>Abrir prematrícula</span></button>';
    const secondary = (title, action, symbol, aria) => '<button type="button" class="btn btn-small btn-secondary row-action" data-action="' + action + '" data-id="' + id + '" aria-label="' + aria + ' ' + name + '">' + icon(symbol) + '<span>' + title + '</span></button>';
    const phase = period.estado === 'EN_CURSO' ? secondary('Cerrar período', 'close-period', 'lock', 'Cerrar el período') : '';
    return primary + phase +
        (windows.length ? secondary('Ver fechas', 'dates', 'eye', 'Ver fechas de prematrícula del período') : '') +
        (period.estado === 'CERRADO' ? '' : secondary('Editar', 'edit', 'file', 'Editar período')) +
        (windows.length ? '' : secondary('Eliminar', 'delete', 'logout', 'Eliminar período'));
}

/** Historial de fechas de prematrícula del período (apertura y extensiones). */
function datesDialog(period) {
    const { windows } = enrollmentWindows(period);
    const dialog = document.createElement('dialog');
    dialog.className = 'modal form-modal form-modal-import';
    dialog.setAttribute('aria-labelledby', 'dates-dialog-title');
    dialog.innerHTML = '<div class="form-modal-header"><span class="form-modal-icon" aria-hidden="true">' + icon('calendar') + '</span>' +
        '<div class="form-modal-heading"><h2 id="dates-dialog-title">Fechas de prematrícula</h2><p>Período ' + esc(period.codigo) + '</p></div>' +
        '<button type="button" class="form-modal-close" data-close aria-label="Cerrar">×</button></div><div class="form-modal-body">' +
        '<div class="table-scroll history-table" role="region" aria-label="Fechas de prematrícula" tabindex="0"><table><thead><tr><th scope="col">Tipo</th><th scope="col">Desde</th><th scope="col">Hasta</th><th scope="col">Motivo</th></tr></thead><tbody>' +
        windows.map(item => '<tr><td data-label="Tipo">' + (item.tipo === 'ORDINARIA' ? statusChip('done', 'Apertura') : statusChip('pending', 'Extensión')) + '</td>' +
            '<td data-label="Desde">' + esc(dateTime(item.fecha_inicio, item.timezone)) + '</td><td data-label="Hasta">' + esc(dateTime(item.fecha_fin, item.timezone)) + '</td>' +
            '<td data-label="Motivo">' + esc(item.motivo || '—') + '</td></tr>').join('') +
        '</tbody></table></div></div>';
    document.body.append(dialog);
    dialog.querySelector('[data-close]').onclick = () => dialog.close();
    dialog.addEventListener('close', () => dialog.remove(), { once: true });
    dialog.showModal();
}

export async function periods(ctx) {
    shell(ctx, 'Períodos y ventanas', 'Abre la prematrícula de cada período, define hasta cuándo pueden registrarse los estudiantes y extiende el plazo si alguien no alcanzó.',
        '<div class="toolbar"><button type="button" class="btn btn-raised" data-action="create">' + icon('calendar') + '<span>Crear período</span></button></div>');
    ctx.root.querySelector('[data-content]')?.classList.add('card', 'history-card');
    const rows = new Map();
    const list = listing(ctx, {
        path: '/academic-periods',
        tableClass: 'history-table',
        columns: [
            ['Período', 'codigo', row => { rows.set(String(row.id), row); return '<span class="student-code">' + esc(row.codigo) + '</span>'; }],
            ['Fase', 'estado', row => statusChip(periodStateTone[row.estado] || 'inactive', periodStateLabel[row.estado] || label(row.estado))],
            ['Prematrícula', 'ventanas', enrollmentStatus],
        ],
        actions: periodActions,
    });
    ctx.on('click', async event => {
        const trigger = event.target.closest('[data-action]');
        if (!trigger) return;
        const kind = trigger.dataset.action;
        const period = rows.get(trigger.dataset.id);
        const reload = () => ctx.current() && list.load();

        if (kind === 'delete') return busy(trigger, () => mutate(ctx, 'Eliminar período', 'Solo se puede eliminar si aún no tiene fechas de prematrícula, solicitudes ni historial.', '/coordinator/academic-periods/' + period.id, 'DELETE', list.load));
        if (kind === 'dates') return datesDialog(period);
        if (kind === 'start-classes' || kind === 'close-period') {
            const toClasses = kind === 'start-classes';
            const confirmed = await confirmAction(
                toClasses ? 'Iniciar clases del período ' + period.codigo : 'Cerrar el período ' + period.codigo,
                toClasses
                    ? 'La prematrícula se cerrará y los estudiantes ya no podrán registrarse ni modificar su prematrícula. ¿Deseas continuar?'
                    : 'El período quedará cerrado y solo se podrá consultar. ¿Deseas continuar?',
                toClasses ? 'Sí, iniciar clases' : 'Sí, cerrar período',
            );
            if (!confirmed) return;
            // busy() muestra el error si falla y devuelve undefined: solo se confirma el éxito si hubo respuesta.
            const updated = await busy(trigger, () => ctx.request('/coordinator/academic-periods/' + period.id, { method: 'PATCH', body: { estado: toClasses ? 'EN_CURSO' : 'CERRADO' } }));
            if (!updated) return;
            flash(toClasses ? 'El período ' + period.codigo + ' pasó a clases. La prematrícula quedó cerrada.' : 'El período ' + period.codigo + ' quedó cerrado.');
            return reload();
        }

        if (kind === 'open') {
            const opensState = period.estado !== 'PREMATRICULA';
            openForm('Abrir prematrícula', [
                text('fecha_inicio', 'Desde', { type: 'datetime-local', required: true, step: 60 }),
                text('fecha_fin', 'Hasta', { type: 'datetime-local', required: true, step: 60 }),
            ], { fecha_inicio: nowInput() }, async body => {
                const start = fromInput(body.fecha_inicio);
                const end = fromInput(body.fecha_fin);
                if (end <= start) throw new Error('La fecha "Hasta" debe ser posterior a "Desde".');
                await ctx.request('/coordinator/academic-periods/' + period.id + '/windows', { method: 'POST', body: { tipo: 'ORDINARIA', fecha_inicio: start, fecha_fin: end, motivo: 'Apertura de la prematrícula' } });
                // Una ventana solo habilita el registro si el período está en Prematrícula.
                if (opensState) await ctx.request('/coordinator/academic-periods/' + period.id, { method: 'PATCH', body: { estado: 'PREMATRICULA' } });
                await reload();
            }, {
                icon: 'calendar',
                description: 'Período ' + period.codigo + ' · elige desde y hasta cuándo pueden registrarse los estudiantes.',
                note: '<div class="form-modal-note" role="note">' + icon('calendar') + '<p>A la hora de cierre ya no se aceptan registros.' + (opensState ? ' Al abrir, el período pasará a estado <strong>Prematrícula</strong>.' : '') + '</p></div>',
                submitLabel: 'Abrir prematrícula',
                successMessage: 'La prematrícula del período ' + period.codigo + ' quedó abierta.',
            });
            return;
        }

        if (kind === 'extend') {
            const { last } = enrollmentWindows(period);
            const currentEnd = dateTime(last.fecha_fin, last.timezone);
            openForm('Extender plazo', [
                text('fecha_fin', 'Nueva fecha de cierre', { type: 'datetime-local', required: true, step: 60, wide: true, help: 'Debe ser posterior al cierre actual (' + currentEnd + ').' }),
                text('motivo', 'Motivo', { type: 'textarea', required: true, maxlength: 2000, wide: true, placeholder: 'Ej.: Estudiantes que no alcanzaron a registrarse' }),
            ], {}, async body => {
                const start = institutionalStamp(last.fecha_fin, last.timezone);
                const end = fromInput(body.fecha_fin);
                if (end <= start) throw new Error('La nueva fecha de cierre debe ser posterior al cierre actual (' + currentEnd + ').');
                // La extensión empieza justo donde terminó el último plazo: sin huecos ni solapamientos.
                await ctx.request('/coordinator/academic-periods/' + period.id + '/windows', { method: 'POST', body: { tipo: 'EXTRAORDINARIA', fecha_inicio: start, fecha_fin: end, motivo: body.motivo } });
                await reload();
            }, {
                icon: 'calendar',
                description: 'Período ' + period.codigo + ' · cierre actual: ' + currentEnd + '.',
                note: '<div class="form-modal-note" role="note">' + icon('calendar') + '<p>El plazo se reabre para <strong>todos</strong> los estudiantes activos, desde el cierre actual hasta la nueva fecha.</p></div>',
                submitLabel: 'Extender plazo',
                successMessage: 'El plazo de prematrícula se extendió correctamente.',
            });
            return;
        }

        if (!['create', 'edit'].includes(kind)) return;
        const create = kind === 'create';
        const initial = create ? {} : (await ctx.request('/academic-periods/' + period.id)).data;
        if (!ctx.current()) return;
        // Crear: primero las fechas de prematrícula (lo que el coordinador quiere definir) y luego las de clases,
        // separadas por subtítulos para no confundirlas. Editar: las fechas de prematrícula no se editan (se extienden).
        const fields = create ? [
            text('codigo', 'Código del período', { required: true, maxlength: 20, wide: true, autocomplete: 'off', placeholder: 'Ej.: 2027-I' }),
            { section: 'Prematrícula', description: 'Desde y hasta cuándo pueden registrarse los estudiantes.' },
            text('prematricula_inicio', 'Inicio de prematrícula', { type: 'datetime-local', step: 60, required: true }),
            text('prematricula_fin', 'Fin de prematrícula', { type: 'datetime-local', step: 60, required: true }),
        ] : [
            text('codigo', 'Código del período', { required: true, maxlength: 20, wide: true, autocomplete: 'off' }),
        ];
        openForm(create ? 'Crear período' : 'Editar período', fields, initial, async body => {
            if (!create) {
                // Los campos sin cambios se omiten para respetar los períodos protegidos.
                Object.keys(body).forEach(key => { if (body[key] === initial[key]) delete body[key]; });
                await ctx.request('/coordinator/academic-periods/' + period.id, { method: 'PATCH', body });
                await reload();
                return;
            }
            const { prematricula_inicio: from, prematricula_fin: to, ...periodBody } = body;
            if (fromInput(to) <= fromInput(from)) throw new Error('El fin de la prematrícula debe ser posterior a su inicio.');
            const created = (await ctx.request('/coordinator/academic-periods', { method: 'POST', body: { ...periodBody, ...derivedClassDates(to), estado: 'PLANIFICACION' } })).data;
            {
                try {
                    await ctx.request('/coordinator/academic-periods/' + created.id + '/windows', { method: 'POST', body: { tipo: 'ORDINARIA', fecha_inicio: fromInput(from), fecha_fin: fromInput(to), motivo: 'Apertura de la prematrícula' } });
                    // Una ventana solo habilita el registro si el período está en Prematrícula.
                    await ctx.request('/coordinator/academic-periods/' + created.id, { method: 'PATCH', body: { estado: 'PREMATRICULA' } });
                } catch (error) {
                    await reload();
                    throw new Error('El período ' + created.codigo + ' se creó, pero no se pudo abrir su prematrícula: ' + error.message + ' Usa "Abrir prematrícula" en la tabla para intentarlo de nuevo.');
                }
            }
            await reload();
        }, {
            icon: 'calendar',
            className: create ? 'form-modal-2col' : '',
            description: create ? 'Registra el período y define las fechas de su prematrícula.' : 'Período ' + initial.codigo,
            note: create ? '' : '<div class="form-modal-note" role="note">' + icon('calendar') + '<p>Las fechas de prematrícula no se editan: si necesitas más tiempo, usa <strong>Extender plazo</strong> en la tabla.</p></div>',
            submitLabel: create ? 'Crear período' : 'Guardar cambios',
            successMessage: create ? 'El período se creó correctamente.' : 'El período se actualizó correctamente.',
        });
    });
    await list.load();
}

const ROMAN = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII', 'XIII', 'XIV'];
const cycleName = cycle => 'Ciclo ' + (ROMAN[cycle - 1] || cycle);
const hours = value => (value == null || Number(value) === 0) ? '<span class="muted" aria-label="Sin horas">—</span>' : esc(Number(value).toString());

/**
 * Tabla de malla curricular. La primera columna "Ciclo" es una celda combinada (rowspan) que abarca los cursos
 * del ciclo y su fila de total, para agrupar sin cortar la tabla con franjas horizontales.
 * En móvil la celda combinada se oculta y se muestra una franja por ciclo (.malla-cycle-mobile).
 */
function curriculumTable(rows) {
    if (!rows.length) return '<div class="empty"><strong>Sin cursos</strong><p>No hay cursos que coincidan con la búsqueda.</p></div>';
    const cycles = new Map();
    rows.forEach(row => { if (!cycles.has(row.ciclo)) cycles.set(row.ciclo, []); cycles.get(row.ciclo).push(row); });
    const head = '<thead><tr><th scope="col" class="malla-cycle-col">Ciclo</th><th scope="col">Código</th><th scope="col">Curso</th><th scope="col">Prerrequisitos</th>' +
        '<th scope="col" class="num">Créditos</th>' +
        '<th scope="col" class="num"><abbr title="Horas semanales de teoría">H. teoría</abbr></th>' +
        '<th scope="col" class="num"><abbr title="Horas semanales de práctica">H. práctica</abbr></th>' +
        '<th scope="col" class="num"><abbr title="Horas semanales de laboratorio">H. lab.</abbr></th>' +
        '<th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead>';
    const body = [...cycles].map(([cycle, courses]) => {
        const credits = courses.reduce((sum, course) => sum + Number(course.creditos || 0), 0);
        const name = esc(cycleName(cycle));
        // Solo visible en móvil (en escritorio la reemplaza la celda combinada).
        const mobileBand = '<tr class="malla-cycle-mobile" aria-hidden="true"><td colspan="10">' + name + '</td></tr>';
        const cycleCell = '<th scope="rowgroup" rowspan="' + (courses.length + 1) + '" class="malla-cycle-cell">' + name + '</th>';
        // Fila de total al pie del ciclo: la línea doble indica que es la suma de los créditos de arriba.
        const total = '<tr class="malla-total"><th scope="row" colspan="3"><span>Total de créditos</span><span class="visually-hidden"> del ' + name + '</span></th>' +
            '<td class="num" data-label="Total de créditos"><span class="malla-total-value">' + credits + '</span></td><td colspan="5" aria-hidden="true"></td></tr>';
        return '<tbody>' + mobileBand + courses.map((course, index) => {
            const prereqs = course.prerrequisitos?.length
                ? course.prerrequisitos.map(item => '<span class="malla-prereq" title="' + esc(item.nombre) + '">' + esc(item.codigo) + '</span>').join('')
                : '<span class="muted">Ninguno</span>';
            const active = course.estado === 'ACTIVO';
            return '<tr' + (active ? '' : ' class="malla-inactive"') + '>' + (index === 0 ? cycleCell : '') +
                '<td data-label="Código"><span class="student-code">' + esc(course.codigo) + '</span></td>' +
                '<td data-label="Curso" class="malla-name">' + esc(course.nombre) + '</td>' +
                '<td data-label="Prerrequisitos">' + prereqs + '</td>' +
                '<td data-label="Créditos" class="num">' + esc(course.creditos) + '</td>' +
                '<td data-label="H. teoría" class="num">' + hours(course.horas_teoria) + '</td>' +
                '<td data-label="H. práctica" class="num">' + hours(course.horas_practica) + '</td>' +
                '<td data-label="H. lab." class="num">' + hours(course.horas_laboratorio) + '</td>' +
                '<td data-label="Estado"><span class="history-status ' + (active ? 'history-status-done' : 'history-status-inactive') + '">' + esc(label(course.estado)) + '</span></td>' +
                '<td data-label="Acciones"><div class="row-actions">' +
                '<button type="button" class="btn btn-small btn-secondary row-action" data-action="edit" data-id="' + esc(course.id) + '" aria-label="Editar ' + esc(course.nombre) + '">' + icon('file') + '<span>Editar</span></button>' +
                '<button type="button" class="btn btn-small btn-raised history-pdf" data-action="detail" data-id="' + esc(course.id) + '" aria-label="Ver detalle de ' + esc(course.nombre) + '">' + icon('eye') + '<span>Ver detalle</span></button>' +
                '</div></td></tr>';
        }).join('') + total + '</tbody>';
    }).join('');
    return '<div class="table-scroll history-table malla-table" role="region" aria-label="Malla curricular por ciclo" tabindex="0"><table>' + head + body + '</table></div>';
}

export async function curriculum(ctx) {
    shell(ctx, 'Cursos y malla curricular', 'Consulta y actualiza la malla curricular. Los cursos ya utilizados conservan sus datos académicos.',
        '<div class="toolbar"><button type="button" class="btn btn-raised" data-open-import>' + icon('file') + '<span>Importar malla curricular</span></button></div>' +
        filterForm([text('q', 'Código o nombre'), number('ciclo', 'Ciclo'), select('estado', 'Estado', states)]));
    // Malla agrupada por ciclo (franja con total de cursos y créditos), con el estilo de las tablas de Estudiantes y Cuentas.
    ctx.root.querySelector('[data-content]')?.classList.add('card', 'history-card');
    const list = listing(ctx, { path: '/courses', query: { per_page: 100 }, render: curriculumTable });
    ctx.on('click', async event => {
        const trigger = event.target.closest('[data-action]');
        if (!trigger) return;
        const response = await ctx.request('/courses/' + trigger.dataset.id);
        if (!ctx.current()) return;
        if (trigger.dataset.action === 'detail') {
            const course = response.data;
            const h = value => Number(value || 0).toString();
            infoDialog('Detalle del curso', [
                ['book', 'Nombre del curso', course.nombre],
                ['id-card', 'Código', course.codigo, true],
                ['layers', 'Ciclo', cycleName(course.ciclo)],
                ['credits', 'Créditos', course.creditos + (Number(course.creditos) === 1 ? ' crédito' : ' créditos')],
                ['history', 'Horas semanales', h(course.horas_teoria) + ' h teoría · ' + h(course.horas_practica) + ' h práctica · ' + h(course.horas_laboratorio) + ' h laboratorio', true],
                ['file', 'Prerrequisitos', course.prerrequisitos?.length ? course.prerrequisitos.map(item => item.codigo + ' – ' + item.nombre).join(' · ') : 'Ninguno'],
                ['check-circle', 'Estado', label(course.estado)],
                ...(course.en_uso === undefined ? [] : [['lock', 'Uso académico', course.en_uso ? 'En uso en prematrículas o historial académico' : 'Aún no se usa', true]]),
            ]);
            return;
        }
        if (trigger.dataset.action !== 'edit') return;
        const initial = { ...response.data, prerrequisitos: response.data.prerrequisitos.map(course => course.codigo).join(';') };
        // Curso en uso (historial o prematrícula): se puede corregir, pero antes de guardar se pide confirmación
        // explícita y se envía confirmar_correccion; el servidor revalida las prematrículas afectadas.
        const inUse = response.data.en_uso === true;
        const hoursField = (name, title) => text(name, title, { required: true, inputmode: 'decimal', pattern: '[0-9]{1,3}([.][0-9]{1,2})?', placeholder: '0' });
        openForm('Editar curso', [
            { section: 'Datos del curso' },
            text('nombre', 'Nombre del curso', { required: true, maxlength: 150, wide: true }),
            number('creditos', 'Créditos', { required: true, max: 32767 }),
            number('ciclo', 'Ciclo', { required: true, max: 32767, help: 'Número del ciclo (1, 2, 3…).' }),
            { section: 'Horas semanales', description: 'Usa números enteros o con hasta dos decimales, por ejemplo 2 o 1.5. Escribe 0 si no aplica.' },
            hoursField('horas_teoria', 'Teoría'),
            hoursField('horas_practica', 'Práctica'),
            hoursField('horas_laboratorio', 'Laboratorio'),
            { section: 'Requisitos y estado' },
            text('prerrequisitos', 'Prerrequisitos', { wide: true, autocomplete: 'off', placeholder: 'Ej. INF101; MAT101', help: 'Escribe los códigos de los cursos separados por punto y coma. Déjalo vacío si no tiene prerrequisitos.' }),
            select('estado', 'Estado del curso', states, { required: true }),
        ], initial, async body => {
            Object.keys(body).forEach(key => { if (body[key] === initial[key]) delete body[key]; });
            if (Object.hasOwn(body, 'prerrequisitos')) body.prerrequisitos = body.prerrequisitos.split(';').map(code => code.trim()).filter(Boolean);
            const academicChanges = Object.keys(body).filter(key => key !== 'estado');
            if (inUse && academicChanges.length) {
                const confirmed = await confirmAction(
                    'Confirmar corrección del curso',
                    'Este curso ya figura en prematrículas o en el historial académico. Al guardar, los nuevos datos se aplicarán en todo el sistema y se volverán a validar las prematrículas que lo incluyen. Las constancias PDF ya emitidas no cambian. ¿Deseas continuar?',
                    'Sí, corregir curso',
                );
                if (!confirmed) throw new Error('Corrección cancelada. No se guardaron cambios.');
                body.confirmar_correccion = true;
            }
            await ctx.request('/coordinator/courses/' + initial.id, { method: 'PATCH', body });
            if (ctx.current()) await list.load();
        }, {
            icon: 'book',
            description: initial.codigo + ' · ' + initial.nombre,
            className: 'form-modal-3col',
            note: inUse ? '<div class="form-modal-note form-modal-note-warning" role="note">' + icon('lock') + '<p><strong>Curso en uso:</strong> ya figura en prematrículas o en el historial académico. Puedes corregir sus datos si hubo un error; antes de guardar te pediremos confirmación.</p></div>' : '',
            submitLabel: 'Guardar cambios',
            successMessage: 'El curso se actualizó correctamente.',
        });
    });
    // La importación se abre en una ventana desde el botón "Importar malla curricular" (antes era un panel fijo al pie).
    ctx.on('click', event => {
        if (!event.target.closest('[data-open-import]')) return;
        const controller = new AbortController();
        ctx.signal.addEventListener('abort', () => controller.abort(), { once: true });
        const dialog = document.createElement('dialog');
        dialog.className = 'modal form-modal form-modal-import';
        dialog.setAttribute('aria-labelledby', 'import-dialog-title');
        dialog.innerHTML = '<div class="form-modal-header"><span class="form-modal-icon" aria-hidden="true">' + icon('file') + '</span>' +
            '<div class="form-modal-heading"><h2 id="import-dialog-title">Importar malla curricular</h2><p>Carga los cursos desde un archivo Excel (.xlsx).</p></div>' +
            '<button type="button" class="form-modal-close" data-close aria-label="Cerrar">×</button></div><div class="form-modal-body" data-import-root></div>';
        document.body.append(dialog);
        const importRoot = dialog.querySelector('[data-import-root]');
        importRoot.addEventListener('academic:imported', () => { if (ctx.current()) list.load(); });
        dialog.querySelector('[data-close]').onclick = () => dialog.close();
        dialog.addEventListener('close', () => { controller.abort(); dialog.remove(); }, { once: true });
        renderImport(importRoot, 'curriculum', controller.signal, { embedded: true });
        dialog.showModal();
    });
    await list.load();
}

export async function enrollments(ctx) {
    // Título igual al del menú lateral ("Registros y constancias") para que el usuario sepa dónde está.
    shell(ctx, 'Registros y constancias', 'Consulta las prematrículas registradas por los estudiantes y sus constancias.',
        filterForm([select('estado', 'Estado operativo', ['BORRADOR', 'ENVIADA'])]));
    ctx.root.querySelector('[data-content]')?.classList.add('card', 'history-card');
    // Mismo estilo que las demás tablas: tarjeta, códigos en gris pizarra, estados con acento y acción dorada con ícono.
    const operationalTone = { ENVIADA: 'done', BORRADOR: 'pending' };
    // Los nombres llegan con mayúsculas mezcladas ("ALEX…", "steven…"): se muestran todos con la misma capitalización.
    const properName = text => String(text || '').toLocaleLowerCase('es').replace(/(^|[\s'-])(\p{L})/gu, (match, separator, letter) => separator + letter.toLocaleUpperCase('es'));
    const list = listing(ctx, {
        path: '/coordinator/pre-enrollments',
        tableClass: 'history-table',
        columns: [
            ['Solicitud', 'id', row => '<span class="student-code">N.º ' + esc(row.id) + '</span>'],
            ['Estudiante', 'estudiante.nombres', row => esc(properName((row.estudiante?.nombres || '') + ' ' + (row.estudiante?.apellidos || '')))],
            ['Código', 'estudiante.codigo_universitario', row => '<span class="student-code">' + esc(row.estudiante?.codigo_universitario ?? '—') + '</span>'],
            ['Período', 'periodo.codigo'],
            ['Estado operativo', 'estado', row => statusChip(operationalTone[row.estado] || 'inactive', label(row.estado))],
            ['Enviada', 'fecha_envio', row => row.fecha_envio ? '<span class="nowrap">' + esc(dateTime(row.fecha_envio)) + '</span>' : '<span class="muted">—</span>'],
        ],
        actions: row => '<button type="button" class="btn btn-small btn-raised history-pdf" data-action="detail" data-id="' + esc(row.id) + '" aria-label="Ver detalle y constancias de la solicitud ' + esc(row.id) + '">' + icon('eye') + '<span>Ver detalle</span></button>',
    });
    ctx.on('click', async event => {
        const trigger = event.target.closest('[data-action="detail"]');
        if (!trigger) return;
        const id = trigger.dataset.id;
        const response = await ctx.request('/coordinator/pre-enrollments/' + id);
        if (!ctx.current()) return;
        enrollmentDialog(ctx, id, response.data);
    });
    await list.load();
}

/**
 * Ventana "Ver detalle" de una prematrícula: encabezado con estudiante, resumen (estado, envío, créditos),
 * cursos seleccionados con total de créditos y descarga de la constancia PDF.
 */
function enrollmentDialog(ctx, id, data) {
    const properName = text => String(text || '').toLocaleLowerCase('es').replace(/(^|[\s'-])(\p{L})/gu, (match, separator, letter) => separator + letter.toLocaleUpperCase('es'));
    const student = properName((data.estudiante?.nombres || '') + ' ' + (data.estudiante?.apellidos || ''));
    const revision = data.revision_vigente;
    const details = data.detalles || [];
    const credits = data.creditos_totales ?? details.reduce((sum, item) => sum + Number(item.curso?.creditos || 0), 0);
    const summary = [
        ['Estado', statusChip(data.estado === 'ENVIADA' ? 'done' : data.estado === 'BORRADOR' ? 'pending' : 'inactive', label(data.estado))],
        ['Enviada', data.fecha_envio ? esc(dateTime(data.fecha_envio)) : '<span class="muted">Aún no se envía</span>'],
        ['Créditos', esc(credits) + (Number(credits) === 1 ? ' crédito' : ' créditos')],
    ];
    const courses = details.length
        ? '<div class="table-scroll history-table" role="region" aria-label="Cursos seleccionados" tabindex="0"><table><thead><tr><th scope="col">Código</th><th scope="col">Curso</th><th scope="col">Ciclo</th><th scope="col" class="num">Créditos</th></tr></thead><tbody>' +
            details.map(item => '<tr><td data-label="Código"><span class="student-code">' + esc(item.curso?.codigo ?? '—') + '</span></td>' +
                '<td data-label="Curso">' + esc(item.curso?.nombre ?? '—') + '</td>' +
                '<td data-label="Ciclo">' + (item.curso?.ciclo ? esc(cycleName(item.curso.ciclo)) : '—') + '</td>' +
                '<td data-label="Créditos" class="num">' + esc(item.curso?.creditos ?? '—') + '</td></tr>').join('') +
            '</tbody><tfoot><tr class="detail-total"><th scope="row" colspan="3">Total de créditos</th><td class="num"><strong>' + esc(credits) + '</strong></td></tr></tfoot></table></div>'
        : '<div class="empty"><strong>Sin cursos</strong><p>Esta prematrícula no tiene cursos seleccionados.</p></div>';
    const dialog = document.createElement('dialog');
    dialog.className = 'modal form-modal form-modal-import';
    dialog.setAttribute('aria-labelledby', 'enrollment-dialog-title');
    dialog.innerHTML = '<div class="form-modal-header"><span class="form-modal-icon" aria-hidden="true">' + icon('file') + '</span>' +
        '<div class="form-modal-heading"><h2 id="enrollment-dialog-title">Prematrícula N.º ' + esc(data.id) + '</h2>' +
        '<p>' + esc(student) + ' · ' + esc(data.estudiante?.codigo_universitario ?? '') + ' · Período ' + esc(data.periodo?.codigo ?? '') + '</p></div>' +
        '<button type="button" class="form-modal-close" data-close aria-label="Cerrar">×</button></div>' +
        '<div class="form-modal-body">' +
        (data.correccion_en_progreso ? '<div class="form-modal-note form-modal-note-warning detail-note" role="note">' + icon('file') + '<p><strong>Corrección en progreso:</strong> el estudiante está modificando su prematrícula. Lo registrado abajo sigue vigente hasta que la reenvíe.</p></div>' : '') +
        '<dl class="detail-summary">' + summary.map(([title, value]) => '<div><dt>' + title + '</dt><dd>' + value + '</dd></div>').join('') + '</dl>' +
        '<h3 class="detail-section">Cursos seleccionados</h3>' + courses +
        '</div>' +
        '<div class="toolbar form-modal-actions">' +
        (revision?.receipt_available
            ? '<button type="button" class="btn btn-raised" data-action="receipt">' + icon('file') + '<span>Descargar constancia PDF</span></button>'
            : '<span class="muted detail-no-receipt">Constancia aún no disponible</span>') +
        '</div>';
    document.body.append(dialog);
    dialog.querySelector('[data-close]').onclick = () => dialog.close();
    dialog.addEventListener('close', () => dialog.remove(), { once: true });
    dialog.addEventListener('click', async click => {
        const action = click.target.closest('[data-action="receipt"]');
        if (!action) return;
        try { await busy(action, () => ctx.request('/coordinator/pre-enrollments/' + id + '/receipt', { download: true, filename: 'constancia-' + id + '.pdf' })); }
        catch (error) { showError(error); }
    });
    dialog.showModal();
}


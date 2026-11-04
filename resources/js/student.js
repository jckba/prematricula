import { api } from './api';
import { escape, label, date, badge, busy, heading, empty, table, pagination, action, detail, icon, quickLink } from './ui';
import { enrollment, enrollmentDetail } from './student-enrollment';
export async function render(section, root, user) {
    if (section === 'pre-enrollment') return enrollment(root);
    if (section === 'enrollments') return history(root);
    if (section === 'academic-history') return academic(root);
    if (section === 'catalog') return catalog(root);
    const [requests, availability] = await Promise.all([
        api('/student/pre-enrollments'),
        api('/student/pre-enrollment/available-courses').then(response => ({ data: response.data }), error => ({ error })),
    ]);
    if (!root.isConnected) return;
    const available = availability.data;
    const latest = requests.data?.[0];
    let current = available ? requests.data.find(row => row.periodo.id === available.periodo.id) : latest;
    for (let page = 2; available && !current && page <= (requests.meta?.last_page || 1); page++) {
        const response = await api('/student/pre-enrollments', { query: { page } });
        if (!root.isConnected) return;
        current = response.data.find(row => row.periodo.id === available.periodo.id);
    }
    const firstName = (current?.estudiante || latest?.estudiante)?.nombres?.trim().split(/\s+/)[0];
    const periodCode = available?.periodo.codigo || current?.periodo.codigo;
    const maximum = available?.creditos_maximos;
    const credits = current?.creditos_totales ?? (available ? 0 : null);
    const progress = maximum > 0 && credits != null ? '<progress aria-label="Créditos seleccionados respecto a tu máximo personal" value="' + escape(credits) + '" max="' + escape(maximum) + '">' + escape(credits) + ' / ' + escape(maximum) + '</progress>' : '';
    const metric = (title, symbol, content) => '<article class="dashboard-metric"><span class="metric-icon">' + icon(symbol) + '</span><div><h2>' + escape(title) + '</h2>' + content + '</div></article>';
    const submitted = current?.estado === 'ENVIADA';
    const bannerTitle = available ? (submitted ? 'Tu prematrícula está registrada' : 'Continúa con tu prematrícula') : (availability.error?.status === 422 ? 'Prematrícula no disponible' : 'No pudimos comprobar la apertura');
    const bannerText = available ? ('Período ' + available.periodo.codigo + '. ' + (submitted ? 'Consulta tu solicitud y descarga tu constancia.' : 'Selecciona tus asignaturas y revisa tu carga antes de enviar.')) : availability.error?.message || 'Ingresa a tu prematrícula para consultar su disponibilidad.';
    root.innerHTML = heading(firstName ? 'Hola, ' + firstName : 'Hola, te damos la bienvenida', 'Consulta tu prematrícula y elige tu siguiente paso.') +
        '<div class="dashboard-metrics">' +
        metric(available ? 'Período habilitado' : 'Último período registrado', 'calendar', '<strong>' + escape(periodCode || 'Sin registro') + '</strong><p>' + (available ? 'Disponible para prematrícula' : 'Consulta la disponibilidad abajo') + '</p>') +
        metric(current ? 'Estado de la solicitud' : 'Mi prematrícula', 'file', current ? badge(current.estado) + '<p>' + (current.correccion_en_progreso ? 'Corrección en progreso' : submitted ? 'Envío registrado' : current.estado === 'BORRADOR' ? 'Aún no has enviado tu selección' : 'Registro histórico') + '</p>' : '<strong>Sin iniciar</strong><p>Tu selección aún no está registrada</p>') +
        metric('Créditos seleccionados', 'credits', '<strong>' + escape(credits ?? 'Sin dato') + (maximum != null ? '<span class="metric-limit"> / ' + escape(maximum) + '</span>' : '') + '</strong>' + (progress || '<p>' + (current ? 'De la solicitud mostrada' : 'Se mostrarán al iniciar') + '</p>')) +
        '</div><section class="dashboard-callout" aria-labelledby="next-step-heading"><span class="callout-icon">' + icon(available ? 'calendar' : 'file') + '</span><div><h2 id="next-step-heading">' + escape(bannerTitle) + '</h2><p>' + escape(bannerText) + '</p></div><a class="btn" href="' + (submitted && available ? '#enrollments' : '#pre-enrollment') + '">' + (submitted && available ? 'Ver solicitud' : available ? 'Ir a prematrícula' : 'Revisar disponibilidad') + icon('arrow') + '</a></section>' +
        (current?.validacion_academica ? '<div class="academic-status"><span>Validación académica</span>' + badge(current.validacion_academica.estado) + '<a href="#enrollments">Ver observaciones</a></div>' : '') +
        '<section class="quick-section" aria-labelledby="quick-heading"><h2 id="quick-heading">Servicios académicos</h2><div class="quick-grid">' +
        quickLink('#pre-enrollment', 'Mi prematrícula', 'Seleccionar y revisar cursos', 'book') +
        quickLink('#academic-history', 'Historial académico', 'Consultar resultados oficiales', 'history') +
        quickLink('#enrollments', 'Solicitudes y constancias', 'Ver envíos y descargar PDF', 'file') +
        quickLink('#catalog', 'Catálogo de asignaturas', 'Consultar cursos y prerrequisitos', 'layers') +
        '</div></section><details class="calendar-disclosure"><summary><span>' + icon('calendar') + 'Calendario académico</span><span class="disclosure-hint">Ver fechas</span></summary><div data-calendar></div></details>';
    const calendarDetails = root.querySelector('.calendar-disclosure');
    const calendar = root.querySelector('[data-calendar]');
    let calendarLoaded = false;
    const loadCalendar = async (page = 1) => {
        calendar.setAttribute('aria-busy', 'true');
        try {
            const response = await api('/academic-periods', { query: { page } });
            if (!root.isConnected) return;
            calendar.innerHTML = '<p class="help-text">Fechas de clases y ventanas de prematrícula. Hora de Lima.</p>' +
                (response.data.length ? '<div class="calendar-grid">' + response.data.map(period => '<article><div class="calendar-title"><h3>' + escape(period.codigo) + '</h3>' + badge(period.estado) + '</div><p>Clases: ' + escape(date(period.fecha_inicio)) + ' al ' + escape(date(period.fecha_fin)) + '</p>' +
                    ((period.ventanas || []).map(window => '<p><strong>' + escape(label(window.tipo)) + '</strong><br>' + escape(date(window.fecha_inicio)) + ' — ' + escape(date(window.fecha_fin)) + '</p>').join('') || '<p>Sin ventanas registradas.</p>') + '</article>').join('') + '</div>' : empty('Aún no hay períodos académicos publicados.')) + pagination(response.meta);
            calendar.querySelectorAll('[data-page]').forEach(button => button.onclick = () => busy(button, () => loadCalendar(button.dataset.page)));
        } catch (error) {
            if (!root.isConnected) return;
            calendarLoaded = false;
            calendar.innerHTML = '<p class="notice notice-warning">' + escape(error.message) + '</p><button type="button" class="btn btn-secondary" data-calendar-retry>Volver a intentar</button>';
            calendar.querySelector('[data-calendar-retry]').onclick = event => busy(event.currentTarget, () => loadCalendar(page));
        } finally { calendar.removeAttribute('aria-busy'); }
    };
    calendarDetails.addEventListener('toggle', () => {
        if (!calendarDetails.open || calendarLoaded) return;
        calendarLoaded = true;
        loadCalendar();
    });
}
async function history(root, page = 1) {
    const response = await api('/student/pre-enrollments', { query: { page } });
    if (!root.isConnected) return;
    root.innerHTML = heading('Solicitudes y constancias', 'Consulta tus envíos, revisiones y constancias originales.') + '<section class="card">' +
        table([['Período', 'periodo.codigo'], ['Estado', '', row => badge(row.estado)], ['Validación', '', row => badge(row.validacion_academica?.estado)], ['Créditos', 'creditos_totales'], ['Envío', '', row => escape(date(row.fecha_envio))]], response.data, row => action('Ver solicitud', 'detail', row.id)) +
        pagination(response.meta) + '</section><section data-student-detail></section>';
    root.querySelectorAll('[data-page]').forEach(button => button.onclick = () => busy(button, () => history(root, button.dataset.page)));
    root.querySelectorAll('[data-action="detail"]').forEach(button => button.onclick = () => busy(button, () => enrollmentDetail(root.querySelector('[data-student-detail]'), button.dataset.id)));
}
async function academic(root) {
    const periods = await api('/academic-periods');
    for (let page = 2; page <= (periods.meta?.last_page || 1); page++) {
        if (!root.isConnected) break;
        const next = await api('/academic-periods', { query: { page } });
        if (!root.isConnected) return;
        periods.data.push(...next.data);
    }
    if (!root.isConnected) return;
    root.innerHTML = heading('Historial académico', 'Resultados oficiales. La nota no sustituye al estado académico oficial.') +
        '<section class="card"><form class="toolbar"><label class="field">Período<select name="academic_period_id"><option value="">Todos los períodos</option>' +
        periods.data.map(period => '<option value="' + escape(period.id) + '">' + escape(period.codigo) + '</option>').join('') +
        '</select></label><button class="btn">Consultar</button></form><div data-results></div></section>';
    const results = root.querySelector('[data-results]');
    let sequence = 0;
    const load = async (page = 1) => {
        const current = ++sequence;
        results.setAttribute('aria-busy', 'true');
        try {
        const response = await api('/student/academic-history', { query: { page, academic_period_id: root.querySelector('select').value } });
        if (!root.isConnected || current !== sequence) return;
        results.innerHTML = table([['Período', 'periodo.codigo'], ['Código', 'curso.codigo'], ['Asignatura', 'curso.nombre'], ['Nota final', 'nota_final'], ['Resultado oficial', '', row => badge(row.estado)]], response.data) + pagination(response.meta);
        results.querySelectorAll('[data-page]').forEach(button => button.onclick = () => busy(button, () => load(button.dataset.page)));
        } finally { if (current === sequence) results.removeAttribute('aria-busy'); }
    };
    root.querySelector('form').onsubmit = event => { event.preventDefault(); busy(event.submitter, () => load()); };
    await load();
}
async function catalog(root) {
    root.innerHTML = heading('Catálogo de asignaturas', 'El catálogo no representa tu elegibilidad para seleccionar cursos.') +
        '<section class="card"><form class="toolbar"><label class="field">Código o nombre<input name="q" maxlength="100" type="search"></label><label class="field">Ciclo<input name="ciclo" type="number" min="1" max="32767"></label><button class="btn">Buscar</button></form><div data-results></div></section>';
    const form = root.querySelector('form');
    const results = root.querySelector('[data-results]');
    let sequence = 0;
    const load = async (page = 1) => {
        const current = ++sequence;
        results.setAttribute('aria-busy', 'true');
        try {
        const response = await api('/courses', { query: { ...Object.fromEntries(new FormData(form)), page } });
        if (!root.isConnected || current !== sequence) return;
        results.innerHTML = table([['Código', 'codigo'], ['Asignatura', 'nombre'], ['Ciclo', 'ciclo'], ['Créditos', 'creditos'], ['Estado', '', row => badge(row.estado)]], response.data, row => action('Prerrequisitos', 'course', row.id)) + pagination(response.meta);
        results.querySelectorAll('[data-page]').forEach(button => button.onclick = () => busy(button, () => load(button.dataset.page)));
        results.querySelectorAll('[data-action="course"]').forEach(button => button.onclick = () => busy(button, async () => { const response = await api('/courses/' + button.dataset.id); if (!root.isConnected) return; detail('Asignatura y prerrequisitos', response.data); }));
        } finally { if (current === sequence) results.removeAttribute('aria-busy'); }
    };
    form.onsubmit = event => { event.preventDefault(); busy(event.submitter, () => load()); };
    await load();
}

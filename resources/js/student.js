import { api } from './api';
import { escape, date, badge, busy, heading, pagination, icon, quickLink, openPdf } from './ui';
import { enrollment } from './student-enrollment';
export async function render(section, root, user) {
    if (section === 'pre-enrollment') return enrollment(root);
    if (section === 'enrollments') return history(root);
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
    const profile = current?.estudiante || latest?.estudiante;
    const fullName = [profile?.nombres, profile?.apellidos].map(part => part?.trim()).filter(Boolean).join(' ');
    const periodCode = available?.periodo.codigo || current?.periodo.codigo;
    const maximum = available?.creditos_maximos;
    const credits = current?.creditos_totales ?? (available ? 0 : null);
    const progress = maximum > 0 && credits != null ? '<progress aria-label="Créditos seleccionados respecto a tu máximo personal" value="' + escape(credits) + '" max="' + escape(maximum) + '">' + escape(credits) + ' / ' + escape(maximum) + '</progress>' : '';
    const metric = (title, symbol, content) => '<article class="dashboard-metric"><span class="metric-icon">' + icon(symbol) + '</span><div><h2>' + escape(title) + '</h2>' + content + '</div></article>';
    const submitted = current?.estado === 'ENVIADA';
    // Estado del aviso: new (aún no inicia, azul) · draft (borrador sin registrar, ámbar) · done (registrada, verde) · closed (sin ventana).
    const stage = !available ? 'closed' : submitted ? 'done' : current ? 'draft' : 'new';
    const banner = {
        new: { title: 'Inicia tu prematrícula', text: 'El período ' + available?.periodo.codigo + ' está abierto. Elige tus cursos y regístralos.', action: 'Empezar prematrícula', symbol: 'calendar' },
        draft: { title: 'Continúa con tu prematrícula', text: 'Período ' + available?.periodo.codigo + '. Ya tienes cursos elegidos; revísalos y registra tu prematrícula.', action: 'Continuar prematrícula', symbol: 'file' },
        done: { title: 'Tu prematrícula está registrada', text: 'Período ' + available?.periodo.codigo + '. Revisa tus cursos registrados y genera tu constancia PDF.', action: 'Ver mi prematrícula', symbol: 'check-circle' },
        closed: { title: availability.error?.status === 422 ? 'Prematrícula no disponible' : 'No pudimos comprobar la apertura', text: availability.error?.message || 'Ingresa a tu prematrícula para consultar su disponibilidad.', action: 'Revisar disponibilidad', symbol: 'file' },
    }[stage];
    root.innerHTML = heading(fullName ? 'Hola, ' + fullName : 'Hola, te damos la bienvenida', 'Consulta tu prematrícula y elige tu siguiente paso.') +
        '<div class="dashboard-metrics">' +
        metric(available ? 'Período habilitado' : 'Último período registrado', 'calendar', '<strong>' + escape(periodCode || 'Sin registro') + '</strong><p>' + (available ? 'Disponible para prematrícula' : 'Consulta la disponibilidad abajo') + '</p>') +
        metric(current ? 'Estado de la prematrícula' : 'Mi prematrícula', 'file', current ? badge(current.estado) + '<p>' + (current.correccion_en_progreso ? 'Corrección en progreso' : submitted ? 'Envío registrado' : current.estado === 'BORRADOR' ? 'Aún no has enviado tu selección' : 'Registro histórico') + '</p>' : '<strong>Sin iniciar</strong><p>Tu selección aún no está registrada</p>') +
        metric('Créditos seleccionados', 'credits', '<strong>' + escape(credits ?? 'Sin dato') + (maximum != null ? '<span class="metric-limit"> / ' + escape(maximum) + '</span>' : '') + '</strong>' + (progress || '<p>' + (current ? 'De la solicitud mostrada' : 'Se mostrarán al iniciar') + '</p>')) +
        '</div><section class="dashboard-callout dashboard-callout-' + stage + '" aria-labelledby="next-step-heading"><span class="callout-icon">' + icon(banner.symbol) + '</span><div><h2 id="next-step-heading">' + escape(banner.title) + '</h2><p>' + escape(banner.text) + '</p></div><a class="btn btn-raised" href="#pre-enrollment">' + escape(banner.action) + '</a></section>' +
        // Oculto temporalmente: bloque "Validación académica" del inicio.
        // (current?.validacion_academica ? '<div class="academic-status"><span>Validación académica</span>' + badge(current.validacion_academica.estado) + '<a href="#enrollments">Ver observaciones</a></div>' : '') +
        '<section class="quick-section" aria-labelledby="quick-heading"><h2 id="quick-heading">Servicios académicos</h2><div class="quick-grid">' +
        quickLink('#pre-enrollment', 'Mi prematrícula', 'Seleccionar y revisar cursos', 'book') +
        quickLink('#enrollments', 'Historial de prematrículas', 'Períodos anteriores y constancias PDF', 'history') +
        '</div></section>';
}
// Historial de prematrículas: tabla acumulativa (10 por página, paginación del API) con la constancia PDF de cada período.
async function history(root, page = 1) {
    const response = await api('/student/pre-enrollments', { query: { page } });
    if (!root.isConnected) return;
    const rows = response.data || [];
    const registered = row => row.estado === 'ENVIADA';
    const status = row => registered(row)
        ? '<span class="history-status history-status-done">Registrada</span>'
        : '<span class="history-status history-status-pending">Sin registrar</span>';
    const receipt = row => registered(row) && row.revision_vigente?.receipt_available
        ? '<button type="button" class="btn btn-small btn-raised history-pdf" data-history-pdf="' + escape(row.id) + '" aria-label="Constancia PDF del período ' + escape(row.periodo.codigo) + ' (se abre en una pestaña nueva)" title="Abre la constancia en una pestaña nueva">' + icon('file') + '<span>Constancia PDF</span>' + icon('external').replace('ui-icon', 'ui-icon history-pdf-external') + '</button>'
        : '<span class="muted">No disponible</span>';
    const list = rows.length
        ? '<div class="table-scroll history-table"><table><thead><tr><th>Período</th><th>Fecha de registro</th><th>N° cursos</th><th>N° créditos</th><th>Estado</th><th>Constancia</th></tr></thead><tbody>' +
            rows.map(row => '<tr>' +
                '<td data-label="Período"><strong>' + escape(row.periodo.codigo) + '</strong></td>' +
                '<td data-label="Fecha de registro">' + (registered(row) ? escape(date(row.fecha_envio)) : '<span class="muted">—</span>') + '</td>' +
                '<td data-label="N° cursos">' + escape(row.detalles?.length ?? 0) + '</td>' +
                '<td data-label="N° créditos">' + escape(row.creditos_totales ?? 0) + '</td>' +
                '<td data-label="Estado">' + status(row) + '</td>' +
                '<td data-label="Constancia">' + receipt(row) + '</td></tr>').join('') +
            '</tbody></table></div>' + pagination(response.meta)
        : '<div class="empty"><strong>Aún no tienes prematrículas</strong><p>Cuando registres tu primera prematrícula aparecerá aquí con su constancia.</p><p><a class="btn" href="#pre-enrollment">Ir a mi prematrícula</a></p></div>';
    root.innerHTML = heading('Historial de prematrículas', 'Revisa las prematrículas que registraste en cada período y abre su constancia en PDF.') +
        '<section class="card history-card">' + list + '</section>';
    root.querySelectorAll('[data-page]').forEach(button => button.onclick = () => busy(button, () => history(root, button.dataset.page)));
    root.querySelectorAll('[data-history-pdf]').forEach(button => button.onclick = () => busy(button, () => openPdf('/student/pre-enrollments/' + button.dataset.historyPdf + '/receipt')));
}

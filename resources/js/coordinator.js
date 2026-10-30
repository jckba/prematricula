import { badge, date, icon, quickLink } from './ui';
import { context, shell, esc } from './coordinator-support';
import { accounts, students, periods, curriculum, results, enrollments } from './coordinator-records';
import { teachers, assignments, demand, loadReport } from './coordinator-teaching';

async function dashboard(ctx, user) {
    shell(ctx, 'Coordinación académica', 'Organiza el proceso académico desde un solo lugar.');
    const [periodsResponse, requestsResponse] = await Promise.all([
        ctx.request('/academic-periods'), ctx.request('/coordinator/pre-enrollments'),
    ]);
    if (!ctx.current()) return;
    const content = ctx.root.querySelector('[data-content]');
    content.innerHTML = '<div class="dashboard-metrics coordinator-metrics">' +
        '<article class="dashboard-metric"><span class="metric-icon">' + icon('calendar') + '</span><div><h2>Períodos registrados</h2><strong>' + esc(periodsResponse.meta?.total ?? periodsResponse.data.length) + '</strong><p>Calendario académico</p></div></article>' +
        '<article class="dashboard-metric"><span class="metric-icon">' + icon('file') + '</span><div><h2>Prematrículas registradas</h2><strong>' + esc(requestsResponse.meta?.total ?? requestsResponse.data.length) + '</strong><p>Todos los estados y períodos</p></div></article></div>' +
        '<section class="dashboard-callout"><span class="callout-icon">' + icon('calendar') + '</span><div><h2>Períodos y ventanas</h2><p>Revisa las fechas que habilitan el registro de prematrícula.</p></div><a class="btn" href="#periods">Gestionar períodos' + icon('arrow') + '</a></section>' +
        '<section class="quick-section" aria-labelledby="quick-heading"><h2 id="quick-heading">Gestión académica</h2><div class="quick-grid">' +
        quickLink('#students', 'Estudiantes', 'Consultar información académica', 'users') +
        quickLink('#enrollments', 'Prematrículas', 'Consultar registros y constancias', 'file') +
        quickLink('#demand', 'Demanda académica', 'Revisar la estimación por curso', 'chart') +
        quickLink('#load', 'Carga docente', 'Consultar carga y cobertura', 'layers') +
        '</div></section><details class="calendar-disclosure"><summary><span>' + icon('calendar') + 'Períodos académicos</span><span class="disclosure-hint">Ver calendario</span></summary><div data-dashboard-periods></div></details>';
    const holder = content.querySelector('[data-dashboard-periods]');
    const { table, pagination } = await import('./ui');
    const paint = response => {
        holder.innerHTML = table([['Período', 'codigo'], ['Estado', 'estado', row => badge(row.estado)], ['Inicio de clases', 'fecha_inicio', row => esc(date(row.fecha_inicio))], ['Fin de clases', 'fecha_fin', row => esc(date(row.fecha_fin))]], response.data) + pagination(response.meta);
    };
    paint(periodsResponse);
    ctx.on('click', async event => {
        const page = event.target.closest('[data-dashboard-periods] [data-page]');
        if (!page) return;
        const response = await ctx.request('/academic-periods', { query: { page: page.dataset.page } });
        if (ctx.current()) paint(response);
    });
}

const screens = { accounts, students, periods, curriculum, results, enrollments, teachers, assignments, demand, load: loadReport };
export async function render(section, root, user) {
    const ctx = context(root);
    const screen = screens[section];
    if (screen) await screen(ctx);
    else await dashboard(ctx, user);
}


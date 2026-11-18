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
    content.innerHTML = '<section class="dashboard-analytics-heading"><div><h2>Demanda académica</h2><p>Monitorea la matrícula y demanda por ciclo y curso</p></div><label>Filtrar por período <select data-demand-period>' + periodsResponse.data.map(period => '<option value="' + esc(period.id) + '">' + esc(period.codigo) + '</option>').join('') + '</select></label></section><div data-demand-dashboard></div>' + content.innerHTML;
    const demandTarget = content.querySelector('[data-demand-dashboard]');
    let demandTicket = 0;
    let curriculumCourses = null;
    const renderDemand = async periodId => {
        const ticket = ++demandTicket;
        if (!periodId) { demandTarget.innerHTML = '<p class="empty">No hay períodos disponibles.</p>'; return; }
        if (!curriculumCourses) {
            const first = await ctx.request('/courses', { query: { page: 1, per_page: 100 } });
            curriculumCourses = [...(first.data || [])];
            for (let page = 2; page <= (first.meta?.last_page || 1); page++) {
                const next = await ctx.request('/courses', { query: { page, per_page: 100 } });
                curriculumCourses.push(...(next.data || []));
            }
        }
        const demandPath = '/coordinator/academic-periods/' + periodId + '/demand';
        const firstDemand = await ctx.request(demandPath, { query: { per_page: 100, page: 1 } });
        const demandCourses = [...(firstDemand.data.cursos || [])];
        const lastDemandPage = firstDemand.data.paginacion?.last_page || 1;
        for (let page = 2; page <= lastDemandPage; page++) {
            const next = await ctx.request(demandPath, { query: { per_page: 100, page } });
            demandCourses.push(...(next.data.cursos || []));
        }
        const counts = new Map(demandCourses.map(course => [String(course.course_id), Number(course.registrada || 0)]));
        const byCycle = new Map();
        curriculumCourses.forEach(course => {
            const courses = byCycle.get(course.ciclo) || [];
            courses.push({ ...course, registrada: counts.get(String(course.id)) || 0 });
            byCycle.set(course.ciclo, courses);
        });
        const groups = [...byCycle.entries()].sort(([a], [b]) => a - b).map(([cycle, courses]) => ({ cycle, courses }));
        const cycles = groups.map(group => ({ ciclo: group.cycle, registrada: group.courses.reduce((sum, course) => sum + course.registrada, 0) }));
        if (!ctx.current() || ticket !== demandTicket) return;
        const total = cycles.reduce((sum, cycle) => sum + Number(cycle.registrada || 0), 0);
        const courses = groups.flatMap(group => group.courses);
        const average = courses.length ? Math.round(total / (courses.length * 100) * 100) : 0;
        const max = Math.max(0, ...cycles.map(cycle => Number(cycle.registrada || 0)));
        const chart = '<div class="demand-chart" role="img" aria-label="Alumnos matriculados por ciclo">' + cycles.map(cycle => {
            const value = Number(cycle.registrada || 0);
            return '<div class="demand-bar-column"><strong>' + value.toLocaleString('es-PE') + '</strong><div class="demand-bar-track"><span class="demand-bar" style="height:' + (max ? Math.max(4, value / max * 100) : 0) + '%"></span></div><small>Ciclo ' + esc(cycle.ciclo) + '</small></div>';
        }).join('') + '</div>';
        const accordion = groups.map(({ cycle, courses: rows }) => '<details class="demand-cycle"><summary>Ciclo ' + esc(cycle.ciclo) + '<span>' + Number(cycle.registrada || 0).toLocaleString('es-PE') + ' estudiantes</span></summary><div class="demand-course-list">' + rows.map(course => {
            const count = Number(course.registrada || 0);
            const state = count === 0 ? ['Sin registros / Pendiente', 'gray'] : count <= 20 ? ['Adecuado', 'green'] : count <= 41 ? ['Regular', 'yellow'] : count <= 61 ? ['Cerca al límite', 'orange'] : ['Exceso', 'red'];
            return '<div class="demand-course"><span class="demand-dot ' + state[1] + '"></span><span class="demand-course-name">' + esc(course.nombre) + '</span><strong>' + count + '</strong><span class="demand-status ' + state[1] + '">' + state[0] + '</span></div>';
        }).join('') + '</div></details>').join('');
        demandTarget.innerHTML = '<div class="stat-grid"><article class="stat-card"><p>Total matriculados</p><strong>' + total.toLocaleString('es-PE') + '</strong></article><article class="stat-card"><p>Capacidad promedio</p><strong>' + average + '%</strong></article></div><div class="demand-layout"><section class="card demand-chart-card"><h2>Alumnos matriculados por ciclo</h2>' + chart + '</section><section class="card demand-detail"><h2>Detalle por ciclo y curso</h2>' + (accordion || '<p class="muted">Aún no hay demanda registrada en este período.</p>') + '</section></div>';
    };
    const periodFilter = content.querySelector('[data-demand-period]');
    if (periodFilter) {
        periodFilter.addEventListener('change', () => renderDemand(periodFilter.value).catch(error => { if (ctx.current()) demandTarget.innerHTML = '<p class="notice">' + esc(error.message) + '</p>'; }));
        renderDemand(periodFilter.value).catch(error => { if (ctx.current()) demandTarget.innerHTML = '<p class="notice">' + esc(error.message) + '</p>'; });
    }
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


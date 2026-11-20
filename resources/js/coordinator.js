import { icon } from './ui';
import { downloadXlsx } from './xlsx';
import { context, shell, esc } from './coordinator-support';
import { accounts, students, periods, curriculum, enrollments } from './coordinator-records';

// ============================================================================
// CONFIGURACIÓN MANUAL DE TAMAÑO DEL GRÁFICO (Edita estos valores a tu gusto)
// ============================================================================
const CHART_CONFIG = {
    trackHeightPx: 420,   // Altura total de la pista vertical del gráfico (ej. 420, 500, 600)
    barMaxHeightPx: 380,  // Altura máxima de la barra más alta (~40px menor a trackHeightPx)
    barMaxWidthPx: 68,    // Ancho máximo de cada barra por ciclo
};

const THRESHOLDS_STORAGE_KEY = 'unt_coordinator_demand_thresholds_v1';
const DEFAULT_THRESHOLDS = { lowMax: 25, mediumMax: 49 };

function loadThresholds() {
    try {
        const raw = localStorage.getItem(THRESHOLDS_STORAGE_KEY);
        if (!raw) return { ...DEFAULT_THRESHOLDS };
        const parsed = JSON.parse(raw);
        const lowMax = Math.max(1, Number(parsed.lowMax) || DEFAULT_THRESHOLDS.lowMax);
        const mediumMax = Math.max(lowMax + 1, Number(parsed.mediumMax) || DEFAULT_THRESHOLDS.mediumMax);
        return { lowMax, mediumMax };
    } catch {
        return { ...DEFAULT_THRESHOLDS };
    }
}

function saveThresholds(thresholds) {
    try {
        localStorage.setItem(THRESHOLDS_STORAGE_KEY, JSON.stringify(thresholds));
    } catch {
        // Ignorar si el navegador bloquea storage
    }
}

function getCycleBarStyle(value, thresholds) {
    const { lowMax, mediumMax } = thresholds;
    if (value <= 0) return { color: '#dc2626', label: 'Ninguno (0)' };
    if (value <= lowMax) return { color: '#f97316', label: `Cantidad baja (1–${lowMax})` };
    if (value <= mediumMax) return { color: '#eab308', label: `Cantidad media baja (${lowMax + 1}–${mediumMax})` };
    return { color: '#16a34a', label: `Cantidad esperada (${mediumMax + 1}+)` };
}

async function dashboard(ctx, user) {
    shell(ctx, 'Inicio', 'Coordinación académica · Organiza el proceso académico desde un solo lugar.');
    const [periodsResponse] = await Promise.all([
        ctx.request('/academic-periods'),
    ]);
    if (!ctx.current()) return;

    const periodsList = periodsResponse.data || [];
    const defaultPeriod = periodsList.find(p => ['PREMATRICULA', 'ACTIVO', 'EN_CURSO'].includes(String(p.estado || '').toUpperCase())) || periodsList[0];

    const content = ctx.root.querySelector('[data-content]');
    const lowerSections =
        '<section class="dashboard-callout"><span class="callout-icon">' + icon('calendar') + '</span><div><h2>Períodos y ventanas</h2><p>Revisa las fechas que habilitan el registro de prematrícula.</p></div><a class="btn btn-raised" href="#periods">Gestionar períodos</a></section>';

    const modalHtml =
        '<div data-threshold-modal hidden style="position:fixed;inset:0;z-index:1000;background:rgba(15,23,42,0.55);display:none;align-items:center;justify-content:center;padding:1rem;">' +
        '<div role="dialog" aria-modal="true" aria-labelledby="threshold-modal-title" style="background:#fff;border-radius:12px;max-width:460px;width:100%;padding:1.5rem;box-shadow:0 20px 25px -5px rgba(0,0,0,0.18);">' +
        '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;">' +
        '<h3 id="threshold-modal-title" style="margin:0;font-size:1.15rem;">Configurar rangos de matrícula</h3>' +
        '<button type="button" data-modal-close aria-label="Cerrar" style="background:none;border:none;font-size:1.35rem;cursor:pointer;line-height:1;padding:0.25rem;">&times;</button>' +
        '</div>' +
        '<p style="margin:0 0 1.1rem;font-size:0.875rem;color:#475569;">Define el límite superior de cada nivel. Los valores iniciales se ajustan automáticamente de forma consecutiva.</p>' +
        '<form data-threshold-form style="display:flex;flex-direction:column;gap:0.9rem;">' +
        '<div style="display:flex;align-items:center;justify-content:space-between;padding:0.6rem 0.75rem;border-radius:8px;background:#fef2f2;border:1px solid #fecaca;">' +
        '<span style="display:inline-flex;align-items:center;gap:0.5rem;font-weight:600;font-size:0.875rem;"><i style="width:12px;height:12px;border-radius:3px;background:#dc2626;display:inline-block;"></i>Ninguno</span>' +
        '<span style="font-size:0.875rem;font-weight:600;color:#991b1b;">0 alumnos (fijo)</span>' +
        '</div>' +
        '<div style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;padding:0.6rem 0.75rem;border-radius:8px;background:#fff7ed;border:1px solid #fed7aa;">' +
        '<span style="display:inline-flex;align-items:center;gap:0.5rem;font-weight:600;font-size:0.875rem;"><i style="width:12px;height:12px;border-radius:3px;background:#f97316;display:inline-block;"></i>Cantidad baja</span>' +
        '<label style="display:inline-flex;align-items:center;gap:0.4rem;margin:0;font-size:0.875rem;">' +
        '<span>De <strong>1</strong> a</span>' +
        '<input type="number" name="lowMax" min="1" max="9999" required style="width:82px;padding:0.35rem 0.5rem;border:1px solid #cbd5e1;border-radius:6px;text-align:center;">' +
        '</label>' +
        '</div>' +
        '<div style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;padding:0.6rem 0.75rem;border-radius:8px;background:#fefce8;border:1px solid #fef08a;">' +
        '<span style="display:inline-flex;align-items:center;gap:0.5rem;font-weight:600;font-size:0.875rem;"><i style="width:12px;height:12px;border-radius:3px;background:#eab308;display:inline-block;"></i>Media baja</span>' +
        '<label style="display:inline-flex;align-items:center;gap:0.4rem;margin:0;font-size:0.875rem;">' +
        '<span>De <strong data-medium-min>26</strong> a</span>' +
        '<input type="number" name="mediumMax" min="2" max="9999" required style="width:82px;padding:0.35rem 0.5rem;border:1px solid #cbd5e1;border-radius:6px;text-align:center;">' +
        '</label>' +
        '</div>' +
        '<div style="display:flex;align-items:center;justify-content:space-between;padding:0.6rem 0.75rem;border-radius:8px;background:#f0fdf4;border:1px solid #bbf7d0;">' +
        '<span style="display:inline-flex;align-items:center;gap:0.5rem;font-weight:600;font-size:0.875rem;"><i style="width:12px;height:12px;border-radius:3px;background:#16a34a;display:inline-block;"></i>Esperada</span>' +
        '<span style="font-size:0.875rem;font-weight:600;color:#166534;">De <strong data-expected-min>50</strong> a más</span>' +
        '</div>' +
        '<p data-threshold-error style="margin:0;color:#dc2626;font-size:0.82rem;display:none;"></p>' +
        '<div style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;margin-top:0.5rem;">' +
        '<button type="button" data-threshold-reset style="background:none;border:1px solid #cbd5e1;border-radius:6px;padding:0.45rem 0.75rem;font-size:0.82rem;cursor:pointer;">Restablecer</button>' +
        '<div style="display:flex;gap:0.5rem;">' +
        '<button type="button" data-modal-close style="background:#f1f5f9;border:1px solid #cbd5e1;border-radius:6px;padding:0.45rem 0.9rem;font-size:0.875rem;cursor:pointer;">Cancelar</button>' +
        '<button type="submit" class="btn" style="padding:0.45rem 1rem;font-size:0.875rem;">Guardar cambios</button>' +
        '</div>' +
        '</div>' +
        '</form>' +
        '</div>' +
        '</div>';

    content.innerHTML =
        '<section class="dashboard-analytics-heading" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;margin-bottom:1.25rem;">' +
        '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:0.75rem;margin-left:auto;">' +
        '<label style="display:inline-flex;align-items:center;gap:0.6rem;margin:0;white-space:nowrap;">' +
        '<span>Filtrar por período</span>' +
        '<select data-demand-period style="width:auto;min-width:140px;">' +
        periodsList.map(period => '<option value="' + esc(period.id) + '"' + (defaultPeriod && String(period.id) === String(defaultPeriod.id) ? ' selected' : '') + '>' + esc(period.codigo) + '</option>').join('') +
        '</select>' +
        '</label>' +
        '<div data-total-matriculados></div>' +
        '</div>' +
        '</section>' +
        '<div data-demand-dashboard></div>' +
        modalHtml +
        lowerSections;

    const totalTarget = content.querySelector('[data-total-matriculados]');
    const demandTarget = content.querySelector('[data-demand-dashboard]');
    const modal = content.querySelector('[data-threshold-modal]');
    const form = content.querySelector('[data-threshold-form]');
    const lowInput = form.querySelector('input[name="lowMax"]');
    const mediumInput = form.querySelector('input[name="mediumMax"]');
    const mediumMinLabel = form.querySelector('[data-medium-min]');
    const expectedMinLabel = form.querySelector('[data-expected-min]');
    const errorLabel = form.querySelector('[data-threshold-error]');

    let thresholds = loadThresholds();
    let demandTicket = 0;
    let curriculumCourses = null;
    let lastCyclesData = null;

    const syncModalRanges = () => {
        const lowVal = Math.max(1, parseInt(lowInput.value, 10) || 1);
        mediumMinLabel.textContent = String(lowVal + 1);
        mediumInput.min = String(lowVal + 1);
        const medVal = Math.max(lowVal + 1, parseInt(mediumInput.value, 10) || (lowVal + 1));
        expectedMinLabel.textContent = String(medVal + 1);
    };

    const openModal = () => {
        lowInput.value = String(thresholds.lowMax);
        mediumInput.value = String(thresholds.mediumMax);
        errorLabel.style.display = 'none';
        syncModalRanges();
        modal.hidden = false;
        modal.style.display = 'flex';
        lowInput.focus();
    };

    const closeModal = () => {
        modal.hidden = true;
        modal.style.display = 'none';
    };

    lowInput.addEventListener('input', () => {
        const lowVal = Math.max(1, parseInt(lowInput.value, 10) || 1);
        if ((parseInt(mediumInput.value, 10) || 0) <= lowVal) {
            mediumInput.value = String(lowVal + 1);
        }
        syncModalRanges();
    });

    mediumInput.addEventListener('input', syncModalRanges);

    modal.addEventListener('click', event => {
        if (event.target === modal || event.target.closest('[data-modal-close]')) {
            closeModal();
        }
    });

    form.querySelector('[data-threshold-reset]').addEventListener('click', () => {
        lowInput.value = String(DEFAULT_THRESHOLDS.lowMax);
        mediumInput.value = String(DEFAULT_THRESHOLDS.mediumMax);
        errorLabel.style.display = 'none';
        syncModalRanges();
    });

    const paintChart = data => {
        const { lowMax, mediumMax } = thresholds;
        const { totalStudents, cycles } = data;
        const max = Math.max(mediumMax + 1, ...cycles.map(cycle => Number(cycle.registrada || 0)));

        totalTarget.innerHTML =
            '<article class="stat-card" style="display:flex;align-items:center;justify-content:space-between;gap:1.25rem;padding:0.65rem 1.1rem;min-width:220px;">' +
            '<p style="margin:0;font-size:0.875rem;">Total de estudiantes prematriculados</p>' +
            '<strong style="font-size:1.5rem;line-height:1;">' + totalStudents.toLocaleString('es-PE') + '</strong>' +
            '</article>';

        const legend =
            '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem;margin-bottom:1.25rem;">' +
            '<div style="display:flex;flex-wrap:wrap;gap:1.25rem;font-size:0.875rem;">' +
            '<span style="display:inline-flex;align-items:center;gap:0.4rem;"><i style="width:12px;height:12px;border-radius:3px;background:#dc2626;display:inline-block;"></i>Ninguno (0)</span>' +
            '<span style="display:inline-flex;align-items:center;gap:0.4rem;"><i style="width:12px;height:12px;border-radius:3px;background:#f97316;display:inline-block;"></i>Baja (1–' + lowMax + ')</span>' +
            '<span style="display:inline-flex;align-items:center;gap:0.4rem;"><i style="width:12px;height:12px;border-radius:3px;background:#eab308;display:inline-block;"></i>Media baja (' + (lowMax + 1) + '–' + mediumMax + ')</span>' +
            '<span style="display:inline-flex;align-items:center;gap:0.4rem;"><i style="width:12px;height:12px;border-radius:3px;background:#16a34a;display:inline-block;"></i>Esperada (' + (mediumMax + 1) + '+)</span>' +
            '</div>' +
            '<button type="button" data-open-thresholds style="display:inline-flex;align-items:center;gap:0.4rem;background:#f8fafc;border:1px solid #cbd5e1;border-radius:6px;padding:0.35rem 0.75rem;font-size:0.82rem;font-weight:600;cursor:pointer;">Editar rangos</button>' +
            '</div>';

        const chart =
            '<div class="cycle-chart" role="img" aria-label="Alumnos prematriculados por ciclo" style="display:flex;align-items:flex-end;gap:0.85rem;padding-top:1rem;">' +
            cycles.map(cycle => {
                const value = Number(cycle.registrada || 0);
                const barStyle = getCycleBarStyle(value, thresholds);
                const barHeightPx = value === 0 ? 12 : Math.max(24, Math.round((value / max) * CHART_CONFIG.barMaxHeightPx));
                return '<div class="cycle-col" style="flex:1;min-width:0;display:flex;flex-direction:column;align-items:center;" title="Ciclo ' + esc(cycle.ciclo) + ': ' + value + ' — ' + barStyle.label + '">' +
                    '<div class="cycle-track" style="height:' + CHART_CONFIG.trackHeightPx + 'px;width:100%;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;border-bottom:2px solid #cbd5e1;">' +
                    '<strong style="margin-bottom:0.35rem;font-size:0.9rem;line-height:1;">' + value.toLocaleString('es-PE') + '</strong>' +
                    '<span class="cycle-bar" style="display:block;width:100%;max-width:' + CHART_CONFIG.barMaxWidthPx + 'px;height:' + barHeightPx + 'px;background:' + barStyle.color + ';border-radius:6px 6px 0 0;transition:height .25s ease,background-color .25s ease;"></span>' +
                    '</div>' +
                    '<small style="margin-top:0.6rem;display:block;text-align:center;font-weight:500;">Ciclo ' + esc(cycle.ciclo) + '</small>' +
                    '</div>';
            }).join('') +
            '</div>';

        demandTarget.innerHTML =
            '<div style="width:100%;margin-bottom:1.75rem;">' +
            '<section class="card" style="width:100%;padding:1.5rem;">' +
            '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;margin-bottom:0.75rem;">' +
            '<h2 style="margin:0;">Alumnos matriculados por ciclo</h2>' +
            '</div>' +
            legend +
            chart +
            '</section>' +
            '</div>';

        const openBtn = demandTarget.querySelector('[data-open-thresholds]');
        if (openBtn) openBtn.addEventListener('click', openModal);
    };

    form.addEventListener('submit', event => {
        event.preventDefault();
        const lowMax = parseInt(lowInput.value, 10);
        const mediumMax = parseInt(mediumInput.value, 10);

        if (!Number.isInteger(lowMax) || lowMax < 1) {
            errorLabel.textContent = 'El límite de cantidad baja debe ser al menos 1.';
            errorLabel.style.display = 'block';
            return;
        }
        if (!Number.isInteger(mediumMax) || mediumMax <= lowMax) {
            errorLabel.textContent = `El límite de media baja debe ser mayor que ${lowMax}.`;
            errorLabel.style.display = 'block';
            return;
        }

        thresholds = { lowMax, mediumMax };
        saveThresholds(thresholds);
        closeModal();
        if (lastCyclesData) paintChart(lastCyclesData);
    });


    const renderDemand = async periodId => {
        const ticket = ++demandTicket;

        if (!periodId) {
            totalTarget.innerHTML = '';
            demandTarget.innerHTML = '<p class="empty">No hay períodos disponibles.</p>';
            return;
        }

        const summaryPath = '/coordinator/academic-periods/' + periodId + '/summary';

        const summaryResponse = await ctx.request(summaryPath);

        if (!ctx.current() || ticket !== demandTicket) return;

        const summary = summaryResponse.data || {};

        // El total global cuenta estudiantes únicos en todo el período.
        const totalStudents = Number(summary.total_estudiantes || 0);

        // Los datos del gráfico ya vienen agrupados por ciclo
        // y cuentan estudiantes únicos dentro de cada ciclo.
        const cycles = (summary.ciclos || []).map(cycle => ({
            ciclo: cycle.ciclo,
            registrada: Number(cycle.estudiantes_prematriculados || 0),
        }));

        lastCyclesData = {
            totalStudents,
            cycles,
        };

        paintChart(lastCyclesData);
    };


    const periodFilter = content.querySelector('[data-demand-period]');
    if (periodFilter) {
        periodFilter.addEventListener('change', () => renderDemand(periodFilter.value).catch(error => {
            if (ctx.current()) demandTarget.innerHTML = '<p class="notice">' + esc(error.message) + '</p>';
        }));
        renderDemand(periodFilter.value).catch(error => {
            if (ctx.current()) demandTarget.innerHTML = '<p class="notice">' + esc(error.message) + '</p>';
        });
    }
}

async function demand(ctx) {
    shell(ctx, 'Demanda académica', 'Visualiza la demanda de estudiantes prematriculados por curso.');
    const periodsResponse = await ctx.request('/academic-periods');
    if (!ctx.current()) return;

    const periodsList = periodsResponse.data || [];
    const defaultPeriod = periodsList.find(p => ['PREMATRICULA', 'ACTIVO', 'EN_CURSO'].includes(String(p.estado || '').toUpperCase())) || periodsList[0];

    const content = ctx.root.querySelector('[data-content]');
    content.innerHTML =
        '<div style="display:flex;justify-content:flex-end;align-items:center;margin-bottom:1.25rem;">' +
        '<label style="display:inline-flex;align-items:center;gap:0.6rem;margin:0;white-space:nowrap;">' +
        '<span>Período académico</span>' +
        '<select data-demand-period style="width:auto;min-width:145px;">' +
        periodsList.map(period => '<option value="' + esc(period.id) + '"' + (defaultPeriod && String(period.id) === String(defaultPeriod.id) ? ' selected' : '') + '>' + esc(period.codigo) + '</option>').join('') +
        '</select>' +
        '</label>' +
        '</div>' +
        '<div data-demand-view></div>';

    const viewTarget = content.querySelector('[data-demand-view]');
    const periodSelect = content.querySelector('[data-demand-period]');
    let ticket = 0;
    let cachedCourses = null;
    let selectedCycle = 'ALL';

    const renderCoursesChart = coursesRows => {
        const thresholds = loadThresholds();
        const availableCycles = [...new Set(coursesRows.map(c => c.ciclo))].sort((a, b) => a - b);

        if (selectedCycle !== 'ALL' && !availableCycles.includes(Number(selectedCycle))) {
            selectedCycle = 'ALL';
        }

        const filteredCourses = selectedCycle === 'ALL'
            ? coursesRows
            : coursesRows.filter(c => c.ciclo === Number(selectedCycle));

        const maxCourseVal = Math.max(thresholds.mediumMax + 1, ...filteredCourses.map(c => c.registrada));

        const legendHtml =
            '<div style="display:flex;flex-wrap:wrap;gap:1.25rem;margin-bottom:1.25rem;font-size:0.85rem;">' +
            '<span style="display:inline-flex;align-items:center;gap:0.4rem;"><i style="width:12px;height:12px;border-radius:3px;background:#dc2626;display:inline-block;"></i>Ninguno (0)</span>' +
            '<span style="display:inline-flex;align-items:center;gap:0.4rem;"><i style="width:12px;height:12px;border-radius:3px;background:#f97316;display:inline-block;"></i>Baja (1–' + thresholds.lowMax + ')</span>' +
            '<span style="display:inline-flex;align-items:center;gap:0.4rem;"><i style="width:12px;height:12px;border-radius:3px;background:#eab308;display:inline-block;"></i>Media baja (' + (thresholds.lowMax + 1) + '–' + thresholds.mediumMax + ')</span>' +
            '<span style="display:inline-flex;align-items:center;gap:0.4rem;"><i style="width:12px;height:12px;border-radius:3px;background:#16a34a;display:inline-block;"></i>Esperada (' + (thresholds.mediumMax + 1) + '+)</span>' +
            '</div>';

        const courseRowsHtml = filteredCourses.length
            ? filteredCourses.map(course => {
                const st = getCycleBarStyle(course.registrada, thresholds);
                const widthPct = course.registrada === 0 ? 2 : Math.max(4, Math.round((course.registrada / maxCourseVal) * 100));
                return '<div style="display:grid;grid-template-columns:minmax(220px,34%) 1fr 56px;align-items:center;gap:0.85rem;padding:0.45rem 0;border-bottom:1px solid #f1f5f9;" title="' + esc(course.nombre) + ': ' + course.registrada + ' (' + st.label + ')">' +
                    '<div style="min-width:0;">' +
                    '<span style="display:block;font-size:0.75rem;color:#64748b;">Ciclo ' + esc(course.ciclo) + (course.codigo ? ' · ' + esc(course.codigo) : '') + '</span>' +
                    '<strong style="display:block;font-size:0.88rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + esc(course.nombre) + '</strong>' +
                    '</div>' +
                    '<div style="width:100%;height:18px;background:#f1f5f9;border-radius:6px;overflow:hidden;display:flex;align-items:center;">' +
                    '<span style="display:block;height:100%;width:' + widthPct + '%;background:' + st.color + ';border-radius:6px;transition:width .25s ease;"></span>' +
                    '</div>' +
                    '<strong style="text-align:right;font-size:0.92rem;">' + course.registrada.toLocaleString('es-PE') + '</strong>' +
                    '</div>';
            }).join('')
            : '<p class="empty">No hay cursos registrados para el ciclo seleccionado.</p>';

        viewTarget.innerHTML =
            '<section class="card" style="width:100%;padding:1.5rem;margin-bottom:1.5rem;">' +
            '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem;margin-bottom:0.85rem;">' +
            '<h2 style="margin:0;">Demanda por curso</h2>' +
            '<label style="display:inline-flex;align-items:center;gap:0.5rem;margin:0;white-space:nowrap;">' +
            '<span style="font-size:0.875rem;">Ciclo</span>' +
            '<select data-demand-cycle style="width:auto;min-width:155px;">' +
            '<option value="ALL"' + (selectedCycle === 'ALL' ? ' selected' : '') + '>Todos los ciclos</option>' +
            availableCycles.map(ciclo => '<option value="' + esc(ciclo) + '"' + (String(selectedCycle) === String(ciclo) ? ' selected' : '') + '>Ciclo ' + esc(ciclo) + '</option>').join('') +
            '</select>' +
            '</label>' +
            '</div>' +
            legendHtml +
            '<div style="display:flex;flex-direction:column;gap:0.4rem;max-height:560px;overflow-y:auto;padding-right:0.35rem;">' +
            courseRowsHtml +
            '</div>' +
            '</section>' +
            '<div style="display:flex;justify-content:flex-end;margin-top:0.5rem;margin-bottom:1rem;">' +
            '<button type="button" class="btn btn-raised" data-export-demand-csv>' + icon('file') + '<span>Exportar a Excel</span></button>' +
            '</div>';

        const cycleSelect = viewTarget.querySelector('[data-demand-cycle]');
        if (cycleSelect) {
            cycleSelect.addEventListener('change', () => {
                selectedCycle = cycleSelect.value;
                renderCoursesChart(coursesRows);
            });
        }

        const exportBtn = viewTarget.querySelector('[data-export-demand-csv]');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => {
                const selectedPeriodText = periodSelect.options[periodSelect.selectedIndex]?.text || periodSelect.value;
                const suffix = selectedCycle === 'ALL' ? 'todos-los-ciclos' : `ciclo-${selectedCycle}`;
                const roman = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII', 'XIII', 'XIV'];
                const cycleText = cycle => 'Ciclo ' + (roman[Number(cycle) - 1] || cycle);
                const generated = new Intl.DateTimeFormat('es-PE', { timeZone: 'America/Lima', dateStyle: 'short', timeStyle: 'short' }).format(new Date());
                // Excel real (.xlsx) en lugar de CSV: se abre con columnas separadas en cualquier configuraci\u00F3n regional
                // y con formato (t\u00EDtulo, encabezados en azul institucional, bordes, filtro y encabezado fijo).
                downloadXlsx(`demanda-por-curso-${selectedPeriodText}-${suffix}.xlsx`, {
                    sheetName: 'Demanda ' + selectedPeriodText,
                    title: 'Demanda acad\u00E9mica por curso',
                    subtitle: 'Per\u00EDodo ' + selectedPeriodText + ' \u00B7 ' + (selectedCycle === 'ALL' ? 'Todos los ciclos' : cycleText(selectedCycle)) + ' \u00B7 Generado el ' + generated,
                    columns: [
                        { header: 'Ciclo', width: 12 },
                        { header: 'C\u00F3digo', width: 14 },
                        { header: 'Curso', width: 44 },
                        { header: 'Estudiantes prematriculados', width: 18, numeric: true },
                    ],
                    rows: filteredCourses.map(r => [cycleText(r.ciclo), String(r.codigo), String(r.nombre), Number(r.registrada) || 0]),
                });
            });
        }
    };

    const loadPeriodDemand = async periodId => {
        const currentTicket = ++ticket;
        if (!periodId) {
            viewTarget.innerHTML = '<p class="empty">No hay períodos académicos disponibles.</p>';
            return;
        }

        if (!cachedCourses) {
            const first = await ctx.request('/courses', { query: { page: 1, per_page: 100 } });
            cachedCourses = [...(first.data || [])];
            for (let page = 2; page <= (first.meta?.last_page || 1); page++) {
                const next = await ctx.request('/courses', { query: { page, per_page: 100 } });
                cachedCourses.push(...(next.data || []));
            }
        }

        const demandPath = '/coordinator/academic-periods/' + periodId + '/demand';
        const firstDemand = await ctx.request(demandPath, { query: { per_page: 100, page: 1 } });
        const demandCourses = [...(firstDemand.data?.cursos || firstDemand.data || [])];
        const lastDemandPage = firstDemand.data?.paginacion?.last_page || firstDemand.meta?.last_page || 1;
        for (let page = 2; page <= lastDemandPage; page++) {
            const next = await ctx.request(demandPath, { query: { per_page: 100, page } });
            demandCourses.push(...(next.data?.cursos || next.data || []));
        }

        if (!ctx.current() || currentTicket !== ticket) return;

        const countsMap = new Map(demandCourses.map(c => [
            String(c.course_id ?? c.id),
            Number(c.registrada ?? c.demanda_registrada ?? c.total ?? 0),
        ]));

        const coursesRows = cachedCourses
            .map(course => ({
                id: course.id,
                codigo: course.codigo || '',
                nombre: course.nombre || '',
                ciclo: Number(course.ciclo || 0),
                registrada: countsMap.get(String(course.id)) || 0,
            }))
            .sort((a, b) => (a.ciclo - b.ciclo) || (b.registrada - a.registrada) || a.nombre.localeCompare(b.nombre));

        renderCoursesChart(coursesRows);
    };

    if (periodSelect) {
        periodSelect.addEventListener('change', () => loadPeriodDemand(periodSelect.value).catch(err => {
            if (ctx.current()) viewTarget.innerHTML = '<p class="notice">' + esc(err.message) + '</p>';
        }));
        loadPeriodDemand(periodSelect.value).catch(err => {
            if (ctx.current()) viewTarget.innerHTML = '<p class="notice">' + esc(err.message) + '</p>';
        });
    }
}

const screens = { accounts, students, periods, curriculum, enrollments, demand };
export async function render(section, root, user) {
    const ctx = context(root);
    const screen = screens[section];
    if (screen) await screen(ctx);
    else await dashboard(ctx, user);
}

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { readFileSync, existsSync, mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { join, resolve, extname } from 'node:path';
import { tmpdir } from 'node:os';
import { spawn, execFileSync } from 'node:child_process';
import { once } from 'node:events';

const project = process.cwd();
const browserPath = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const user = role => ({ id: role === 'ESTUDIANTE' ? 1 : 2, correo_institucional: 'prueba@example.invalid', rol: role, cargo: role === 'COORDINADOR' ? 'SECRETARIA' : null, estado: 'ACTIVO' });
const course = { id: 1, codigo: '00101', nombre: 'Asignatura de prueba', creditos: 3, ciclo: 1, horas_teoria: '2.00', horas_practica: '1.00', horas_laboratorio: '0.00', estado: 'ACTIVO', prerrequisitos: [] };
const period = { id: 1, codigo: 'PRUEBA', estado: 'PREMATRICULA', fecha_inicio: '2027-04-01', fecha_fin: '2027-08-01', timezone: 'America/Lima', ventanas: [] };
const student = { id: 1, codigo_universitario: '00123', nombres: 'Estudiante', apellidos: 'de prueba', estado: 'ACTIVO', creditos_maximos: 22 };
const page = data => ({ data, meta: { current_page: 1, last_page: 1, total: data.length, per_page: 20 }, links: {} });
const enrollment = { id: 1, estudiante: student, periodo: period, detalles: [], estado: 'BORRADOR', creditos_totales: 0, fecha_envio: null, correccion_en_progreso: false, current_revision_id: null, revision_vigente: null, validacion_academica: null };
const requests = [];
let activeRole = 'ESTUDIANTE';
let draft = structuredClone(enrollment);
let historyResponses = null;
let availabilityFailure = null;
function responseFor(path, method, body, query) {
    if (path === '/api/v1/coordinator/curriculum/validate' || path === '/api/v1/coordinator/curriculum/import') return { data: { valid: true, sha256: 'a'.repeat(64), catalog_fingerprint: 'b'.repeat(64), summary: { created: 1, updated: 0, unchanged: 0 }, changes: [{ row: 2, codigo: '00101', action: 'create', fields: ['nombre'], protected: false }], errors: [] } };
    if (path === '/api/v1/me') return { data: user(activeRole) };
    if (path === '/api/v1/login') return { data: { user: user(activeRole), token: 'browser-fixture', expires_at: '2099-01-01T00:00:00Z' } };
    if (path === '/api/v1/logout') return { message: 'Sesión cerrada' };
    if (path === '/api/v1/academic-periods') return page([period]);
    if (path === '/api/v1/academic-periods/1') return { data: period };
    if (path.endsWith('/windows')) return { data: [] };
    if (path === '/api/v1/courses') return page([course]);
    if (path === '/api/v1/courses/1') return { data: course };
    if (path === '/api/v1/student/pre-enrollment/available-courses') return { data: { periodo: period, creditos_maximos: 22, cursos: [course] } };
    if (path === '/api/v1/student/pre-enrollment/courses' && method === 'POST') {
        draft.detalles = [{ id: 9, curso: course, preferencia_turno: body.preferencia_turno || 'SIN_PREFERENCIA' }]; draft.creditos_totales = 3;
        return { data: { detail_id: 9 }, message: 'Curso agregado' };
    }
    if (path === '/api/v1/student/pre-enrollment/courses/9' && method === 'DELETE') { draft.detalles = []; draft.creditos_totales = 0; return { message: 'Curso retirado' }; }
    if (path.endsWith('/courses/9/preference')) { draft.detalles[0].preferencia_turno = body.preferencia_turno; return { data: { id: 9, preferencia_turno: body.preferencia_turno } }; }
    if (path === '/api/v1/student/pre-enrollment/submit') {
        draft.estado = 'ENVIADA'; draft.current_revision_id = 1; draft.fecha_envio = '2026-10-27T15:00:00Z';
        draft.revision_vigente = { id: 1, revision_number: 1, receipt_available: true, receipt_code: 'PRUEBA' };
        return { data: draft };
    }
    if (path === '/api/v1/student/pre-enrollment') return { data: draft };
    if (path === '/api/v1/student/pre-enrollments/1') return { data: draft };
    if (path === '/api/v1/student/pre-enrollments') return historyResponses ? historyResponses[Number(query.get('page') || 1) - 1] : page([draft]);
    if (path.endsWith('/revisions')) return page(draft.revision_vigente ? [draft.revision_vigente] : []);
    if (path.includes('/academic-history/imports')) return path.endsWith('/imports') ? page([]) : { data: {} };
    if (path.endsWith('/academic-history')) return page([]);
    if (path === '/api/v1/coordinator/accounts') return page([user('ESTUDIANTE')]);
    if (path === '/api/v1/coordinator/accounts/1') return { data: user('ESTUDIANTE') };
    if (path === '/api/v1/coordinator/students') return page([student]);
    if (path.endsWith('/students/00123/academic')) return { data: { estudiante: student, periodo: period, historial_academico: { data: [], paginacion: page([]).meta }, prematricula: null } };
    if (path === '/api/v1/coordinator/pre-enrollments') return page([draft]);
    if (path === '/api/v1/coordinator/pre-enrollments/1') return { data: draft };
    if (path === '/api/v1/coordinator/teachers') return page([{ id: 1, codigo_institucional: 'D001', nombres: 'Docente', apellidos: 'de prueba', estado: 'ACTIVO', max_horas_semanales: '20.00' }]);
    if (path === '/api/v1/coordinator/teaching-assignments') return page([]);
    if (path.endsWith('/demand')) return { data: { academic_period_id: 1, advertencia: 'Estimación de prueba', cursos: [], paginacion: page([]).meta } };
    if (path.endsWith('/summary')) return { data: { academic_period_id: 1, advertencia: 'Estimación', alumnos_enviaron: 0, alumnos_requieren_ajuste: 0, alumnos_sin_validacion: 0, poblacion_academica: null, alumnos_no_enviaron: null, fuente_poblacion: 'Sin padrón por período', ciclos: [] } };
    if (path.endsWith('/teaching-load')) return { data: { academic_period_id: 1, advertencia: 'Sin horarios', advertencia_demanda: 'Estimación', totales: { total_semanal: '0.00', docentes_con_carga: 0, cursos_sin_asignacion: 1, docentes_exceden_limite: 0 } } };
    if (path.includes('/teaching-load/')) return { ...page([]), contexto: { advertencia: 'Sin horarios', advertencia_demanda: 'Estimación' } };
    throw new Error('Endpoint no contemplado en las pruebas: ' + method + ' ' + path);
}
test('portal renders Blade and navigates both roles in a browser using isolated API fixtures', { timeout: 120000, skip: !existsSync(browserPath) }, async t => {
    const serverErrors = [];
    const htmlCache = new Map();
    let origin;
    const server = createServer(async (req, res) => {
        const url = new URL(req.url, origin);
        try {
            if (url.pathname.startsWith('/api/v1/')) {
                const chunks = []; for await (const chunk of req) chunks.push(chunk);
                const raw = Buffer.concat(chunks).toString();
                const body = req.headers['content-type']?.includes('application/json') && raw ? JSON.parse(raw) : {};
                requests.push({ path: url.pathname, method: req.method, body, raw });
                if (url.pathname.endsWith('/receipt')) { res.writeHead(200, { 'Content-Type': 'application/pdf', 'Content-Disposition': 'attachment; filename="prueba.pdf"' }); res.end('%PDF-1.4 prueba'); return; }
                if (url.pathname === '/api/v1/student/pre-enrollment/available-courses' && availabilityFailure) {
                    res.writeHead(availabilityFailure.status, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({ message: availabilityFailure.message })); return;
                }
                const payload = responseFor(url.pathname, req.method, body, url.searchParams);
                res.writeHead(200, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(payload)); return;
            }
            if (['/login','/student','/coordinator'].includes(url.pathname)) {
                const view = { '/login': 'auth.login', '/student': 'student.dashboard', '/coordinator': 'coordinator.dashboard' }[url.pathname];
                if (!htmlCache.has(view)) {
                    const php = 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); app("url")->forceRootUrl("' + origin + '"); app("url")->forceScheme("http"); app(Illuminate\\Foundation\\Vite::class)->useHotFile(storage_path("framework/frontend-test-hot")); echo view("' + view + '")->render();';
                    const html = execFileSync('php', ['-r', php], { cwd: project, encoding: 'utf8', env: { ...process.env, SESSION_DRIVER: 'array', CACHE_STORE: 'array', LOG_CHANNEL: 'null' } });
                    htmlCache.set(view, html.replace(/https?:\/\/[^/"']+(?=\/(?:build|images)\/)/g, origin));
                }
                res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' }); res.end(htmlCache.get(view)); return;
            }
            const path = resolve(project, 'public', '.' + decodeURIComponent(url.pathname));
            if (!path.startsWith(resolve(project, 'public') + '\\') || !existsSync(path)) { res.writeHead(404); res.end(); return; }
            const mime = { '.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png', '.webp': 'image/webp', '.woff2': 'font/woff2' };
            res.writeHead(200, { 'Content-Type': mime[extname(path)] || 'application/octet-stream' }); res.end(readFileSync(path));
        } catch (error) { serverErrors.push(error.message); res.writeHead(500, { 'Content-Type': 'application/json' }); res.end(JSON.stringify({ message: 'Fixture error' })); }
    });
    server.listen(0, '127.0.0.1'); await once(server, 'listening'); origin = 'http://127.0.0.1:' + server.address().port;
    const profile = mkdtempSync(join(tmpdir(), 'prematricula-browser-'));
    const browser = spawn(browserPath, ['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-background-networking','--remote-debugging-port=0','--user-data-dir=' + profile, 'about:blank'], { windowsHide: true, stdio: ['ignore','ignore','pipe'] });
    let stderr = ''; const endpoint = await new Promise((resolve, reject) => {
        // Edge 157+ ya no siempre imprime "DevTools listening on" en stderr; también se lee el archivo DevToolsActivePort del perfil.
        const done = value => { clearTimeout(timer); clearInterval(poll); resolve(value); };
        const timer = setTimeout(() => { clearInterval(poll); reject(new Error('No inició el navegador')); }, 15000);
        const poll = setInterval(() => {
            try {
                const [port, path] = readFileSync(join(profile, 'DevToolsActivePort'), 'utf8').split(/\r?\n/);
                if (port && path) done('ws://127.0.0.1:' + port.trim() + path.trim());
            } catch { /* el archivo aún no existe */ }
        }, 200);
        browser.stderr.on('data', chunk => { stderr += chunk; const match = stderr.match(/DevTools listening on (ws:\/\/[^\s]+)/); if (match) done(match[1]); });
        browser.once('error', error => { clearTimeout(timer); clearInterval(poll); reject(error); });
    });
    const socket = new WebSocket(endpoint); await once(socket, 'open');
    let sequence = 0; const waiting = new Map(); const runtimeErrors = []; let loadResolver;
    socket.addEventListener('message', event => {
        const message = JSON.parse(event.data);
        if (message.id) { const task = waiting.get(message.id); waiting.delete(message.id); if (message.error) task?.reject(new Error(message.error.message)); else task?.resolve(message.result); }
        if (message.method === 'Page.loadEventFired' && loadResolver) { loadResolver(); loadResolver = null; }
        if (message.method === 'Runtime.exceptionThrown') runtimeErrors.push(message.params.exceptionDetails.exception?.description || message.params.exceptionDetails.text);
    });
    const send = (method, params = {}, sessionId) => new Promise((resolve, reject) => {
        const id = ++sequence; waiting.set(id, { resolve, reject }); socket.send(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) }));
    });
    const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
    const command = (method, params) => send(method, params, sessionId);
    await command('Runtime.enable'); await command('Page.enable');
    const evaluate = async expression => {
        const result = await command('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
        return result.result.value;
    };
    const waitFor = expression => evaluate('new Promise((resolve,reject)=>{const deadline=Date.now()+10000;const poll=()=>{if(' + expression + ')resolve(true);else if(Date.now()>deadline)reject(new Error("La interfaz no quedó lista: "+' + JSON.stringify(expression) + '));else setTimeout(poll,50)};poll()})');
    const navigate = async path => {
        const loaded = new Promise((resolve, reject) => {
            const timer = setTimeout(() => reject(new Error('La pagina no termino de cargar')), 20000);
            loadResolver = () => { clearTimeout(timer); resolve(); };
        });
        await command('Page.navigate', { url: origin + path }); await loaded;
        await waitFor('document.readyState === "complete"');
    };
    const screenshotDir = join(project, 'storage/app/private/frontend-review'); mkdirSync(screenshotDir, { recursive: true });
    const screenshot = async name => { await evaluate('document.fonts.ready'); await evaluate('document.body.offsetHeight; Promise.all(document.getAnimations().map(animation => animation.finished.catch(() => {})))'); const result = await command('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false }); writeFileSync(join(screenshotDir, name + '.png'), Buffer.from(result.data, 'base64')); };
    try {
        await t.test('login has both original logos, accessible fields and mobile layout', async () => {
            await command('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
            await navigate('/login'); await waitFor('document.querySelectorAll(".brand-logo").length === 2');
            assert.equal(await evaluate('[...document.images].every(image=>image.complete && image.naturalWidth>0)'), true);
            assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true);
            assert.equal(await evaluate('document.querySelector("#identifier").getAttribute("type")'), 'text');
            assert.equal(await evaluate('parseFloat(getComputedStyle(document.querySelector("#identifier")).paddingLeft) >= 48'), true);
            await evaluate('document.fonts.ready');
            await screenshot('login-mobile');

            await evaluate('document.querySelector("#toggle-password").click()');
            assert.equal(await evaluate('document.querySelector("#password").type'), 'text');
            assert.equal(await evaluate('document.querySelector("#toggle-password").getAttribute("aria-label")'), 'Ocultar contraseña');
            assert.equal(await evaluate('document.querySelector("#toggle-password").getAttribute("aria-pressed")'), 'true');
            assert.equal(await evaluate('document.querySelector("#toggle-password svg") !== null'), true);
            await evaluate('document.querySelector("#toggle-password").click()');
            assert.equal(await evaluate('document.querySelector("#password").type'), 'password');
            assert.equal(await evaluate('document.querySelector("#toggle-password").getAttribute("aria-pressed")'), 'false');

            await evaluate('document.querySelector(".login-help summary").click()');
            assert.equal(await evaluate('document.querySelector(".login-help").open'), true);
            await evaluate('document.querySelector(".login-help summary").click()');
            await command('Emulation.setDeviceMetricsOverride', { width: 1536, height: 1024, deviceScaleFactor: 1, mobile: false });
            assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true);
            await screenshot('login-desktop');
        });
        await evaluate('localStorage.setItem("pm_token","browser-fixture");localStorage.setItem("pm_user",' + JSON.stringify(JSON.stringify(user('ESTUDIANTE'))) + ')');
        await t.test('student dashboard uses the enabled period without mixing historical credits or creating drafts', async () => {
            const previous = { ...structuredClone(enrollment), id: 9, periodo: { id: 9, codigo: 'ANTERIOR' }, estado: 'ENVIADA', creditos_totales: 19 };
            historyResponses = [page([previous])];
            try {
                requests.length = 0;
                await navigate('/student');
                await waitFor('document.querySelector(".dashboard-metrics") && !document.querySelector("#screen").hasAttribute("aria-busy")');

                const cards = await evaluate('[...document.querySelectorAll(".dashboard-metric")].map(el => el.textContent)');
                assert.ok(cards[0].includes('PRUEBA'));
                assert.ok(cards[1].includes('Sin iniciar'));
                assert.ok(cards[2].includes('0 / 22'));
                assert.equal(requests.some(item => item.method !== 'GET' || item.path === '/api/v1/student/pre-enrollment'), false);
                assert.equal(requests.some(item => item.path === '/api/v1/academic-periods'), false);
                assert.equal(await evaluate('document.querySelectorAll("#sidebar [data-section] svg").length'), 3);
                assert.equal(await evaluate('document.querySelector("#user-role").textContent'), 'Estudiante');
                assert.equal(await evaluate('document.querySelector("#user-avatar").textContent'), 'P');
                assert.equal(await evaluate('document.querySelector("#sidebar #logout-btn")'), null);
                assert.equal(await evaluate('document.querySelector("#user-menu-panel").hidden'), true);
                await evaluate('document.querySelector("#user-menu-toggle").click()');
                assert.equal(await evaluate('document.querySelector("#user-menu-toggle").getAttribute("aria-expanded")'), 'true');
                assert.equal(await evaluate('document.activeElement.id'), 'logout-btn');
                assert.equal(await evaluate('document.querySelector("#logout-btn").textContent.trim()'), 'Cerrar sesión');
                await screenshot('user-menu-open');
                await evaluate('document.querySelector("#logout-btn").dispatchEvent(new KeyboardEvent("keydown", { key: "Escape", bubbles: true }))');
                assert.equal(await evaluate('document.querySelector("#user-menu-panel").hidden'), true);
                assert.equal(await evaluate('document.activeElement.id'), 'user-menu-toggle');
                assert.equal(await evaluate('document.querySelector("#sidebar-collapse").getAttribute("aria-expanded")'), 'true');
                await evaluate('document.querySelector("#sidebar-collapse").click()');
                assert.equal(await evaluate('document.querySelector("#app").classList.contains("sidebar-collapsed")'), true);
                assert.equal(await evaluate('document.querySelector("#sidebar-collapse").getAttribute("aria-label")'), 'Expandir menú');
                await waitFor('document.querySelector("#sidebar").getBoundingClientRect().width === 72');
                assert.equal(await evaluate('document.querySelector("[data-section=dashboard]").dataset.tooltip'), 'Inicio');
                await screenshot('sidebar-collapsed');
                await navigate('/student');
                await waitFor('document.querySelector("#app") && !document.querySelector("#app").hidden');
                assert.equal(await evaluate('document.querySelector("#app").classList.contains("sidebar-collapsed")'), true);
                await evaluate('document.querySelector("#sidebar-collapse").click()');
                assert.equal(await evaluate('localStorage.getItem("pm_sidebar_collapsed")'), '0');
                await waitFor('document.querySelector("#sidebar").getBoundingClientRect().width === 224');
                await screenshot('student-dashboard-desktop');

                await command('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
                assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true);
                await screenshot('student-dashboard-mobile');
                await command('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false });
            } finally { historyResponses = null; }
        });
        await t.test('student dashboard finds the enabled period on later history pages and keeps academic validation separate', async () => {
            const sent = { ...structuredClone(enrollment), estado: 'ENVIADA', creditos_totales: 6, validacion_academica: { estado: 'REQUIERE_AJUSTE' } };
            historyResponses = [
                { ...page([{ ...structuredClone(enrollment), periodo: { id: 9, codigo: 'ANTERIOR' } }]), meta: { current_page: 1, last_page: 2, total: 2 } },
                page([sent]),
            ];
            try {
                await navigate('/student');
                await waitFor('document.querySelector(".dashboard-metrics") && !document.querySelector("#screen").hasAttribute("aria-busy")');

                assert.ok((await evaluate('document.querySelectorAll(".dashboard-metric")[1].textContent')).includes('Enviada'));
                assert.ok((await evaluate('document.querySelectorAll(".dashboard-metric")[2].textContent')).includes('6 / 22'));
                // Bloque "Validación académica" comentado en student.js.
                // assert.ok((await evaluate('document.querySelector(".academic-status").textContent')).includes('Requiere ajuste'));
                assert.equal(await evaluate('document.querySelector(".academic-status")'), null);
                assert.equal(await evaluate('document.querySelector(".dashboard-callout a").getAttribute("href")'), '#pre-enrollment');
                assert.equal(await evaluate('document.querySelector(".dashboard-callout").classList.contains("dashboard-callout-done")'), true);
                assert.equal(await evaluate('document.querySelector(".dashboard-callout .callout-icon circle") !== null'), true);
                await screenshot('student-dashboard-registered');
            } finally { historyResponses = null; }
        });
        for (const scenario of [
            { status: 422, message: 'No hay una ventana activa.', title: 'Prematrícula no disponible' },
            { status: 503, message: 'Servicio temporalmente no disponible.', title: 'No pudimos comprobar la apertura' },
        ]) {
            await t.test('student dashboard explains availability response ' + scenario.status + ' without inventing a credit limit', async () => {
                availabilityFailure = scenario;
                try {
                    await navigate('/student');
                    await waitFor('document.querySelector(".dashboard-callout") && !document.querySelector("#screen").hasAttribute("aria-busy")');

                    assert.equal(await evaluate('document.querySelector(".dashboard-callout h2").textContent'), scenario.title);
                    assert.ok((await evaluate('document.querySelector(".dashboard-callout p").textContent')).includes(scenario.message));
                    assert.equal(await evaluate('document.querySelector(".dashboard-metric progress")'), null);
                    assert.equal(await evaluate('document.querySelector(".dashboard-callout a").getAttribute("href")'), '#pre-enrollment');
                } finally { availabilityFailure = null; }
            });
        }
        await t.test('student dashboard escapes student names and explains an empty history', async () => {
            historyResponses = [page([{ ...structuredClone(enrollment), estudiante: { ...student, nombres: '<script>evil()</script>' } }])];
            try {
                await navigate('/student');
                await waitFor('document.querySelector(".dashboard-metrics") && !document.querySelector("#screen").hasAttribute("aria-busy")');
                assert.equal(await evaluate('document.querySelector("#screen h1 script")'), null);
                assert.equal(await evaluate('document.querySelector("#screen h1").textContent'), 'Hola, <script>evil()</script> de prueba');
                assert.equal(await evaluate('document.querySelector(".dashboard-callout").classList.contains("dashboard-callout-draft")'), true);
                assert.equal(await evaluate('document.querySelector(".dashboard-callout h2").textContent'), 'Continúa con tu prematrícula');

                historyResponses = [page([])];
                await navigate('/student');
                await waitFor('document.querySelector(".dashboard-metrics") && !document.querySelector("#screen").hasAttribute("aria-busy")');
                assert.ok((await evaluate('document.querySelectorAll(".dashboard-metric")[1].textContent')).includes('Sin iniciar'));
                assert.ok((await evaluate('document.querySelector("#screen h1").textContent')).includes('bienvenida'));
                assert.equal(await evaluate('document.querySelector(".dashboard-callout").classList.contains("dashboard-callout-new")'), true);
                assert.equal(await evaluate('document.querySelector(".dashboard-callout h2").textContent'), 'Inicia tu prematrícula');
                assert.ok((await evaluate('document.querySelector(".dashboard-callout a").textContent')).includes('Empezar prematrícula'));
                await screenshot('student-dashboard-new');
            } finally { historyResponses = null; }
        });
        await t.test('student navigation does not create draft on dashboard', async () => {
            requests.length = 0; await navigate('/student');
            await waitFor('document.querySelector("#app") && !document.querySelector("#app").hidden && document.querySelector("#screen h1")');
            assert.equal(requests.some(request => request.path === '/api/v1/student/pre-enrollment' && request.method === 'GET'), false);
            assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true);
            assert.equal(await evaluate('document.querySelector("[data-section=catalog], [data-section=academic-history], .calendar-disclosure")'), null);
            for (const section of ['enrollments','pre-enrollment']) {
                await evaluate('location.hash=' + JSON.stringify(section));
                await waitFor('document.querySelector("[data-section=' + section + ']").getAttribute("aria-current") === "page" && document.querySelector("#screen h1") && !document.querySelector("#screen").hasAttribute("aria-busy")');
                assert.equal(await evaluate('document.querySelector("#flash").hidden || !document.querySelector("#flash").textContent.includes("no contemplado")'), true);
            }
            historyResponses = [{ ...page([
                { ...structuredClone(enrollment), id: 7, estado: 'ENVIADA', periodo: { id: 2, codigo: '2027-I' }, fecha_envio: '2026-11-18T20:48:00Z', creditos_totales: 12, detalles: [{}, {}, {}, {}], revision_vigente: { id: 3, receipt_available: true } },
                structuredClone(enrollment),
            ]), meta: { current_page: 1, last_page: 2, total: 12 } }];
            try {
                await evaluate('location.hash="enrollments"');
                await waitFor('document.querySelector(".history-card") && !document.querySelector("#screen").hasAttribute("aria-busy")');
                assert.equal(await evaluate('document.querySelector("#screen h1").textContent'), 'Historial de prematrículas');
                assert.equal(await evaluate('document.querySelector("[data-section=enrollments]").textContent.trim()'), 'Historial de prematrículas');
                assert.equal(await evaluate('[...document.querySelectorAll(".history-table th")].map(th => th.textContent).join("|")'), 'Período|Fecha de registro|N° cursos|N° créditos|Estado|Constancia');
                assert.equal(await evaluate('[...document.querySelectorAll(".history-status")].map(el => el.textContent).join("|")'), 'Registrada|Sin registrar');
                assert.equal(await evaluate('document.querySelectorAll("[data-history-pdf]").length'), 1);
                assert.equal(await evaluate('document.querySelector("[data-history-pdf] svg") !== null'), true);
                assert.equal(await evaluate('document.querySelector("[data-history-toggle], .history-courses")'), null);
                assert.ok((await evaluate('document.querySelector(".history-card .pagination").textContent')).includes('Página 1 de 2'));
                await screenshot('student-history-desktop');
            } finally { historyResponses = null; }
            await evaluate('location.hash="pre-enrollment"');
            await waitFor('document.querySelector("[data-cycle-select]") && !document.querySelector("#screen").hasAttribute("aria-busy")');
            await command('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false });
            await screenshot('student-desktop');
        });
        
        await t.test('student can add, remove and submit with explicit confirmation', async () => {
            assert.equal(await evaluate('document.querySelectorAll("[data-cycle-select] option").length'), 11);
            assert.equal(await evaluate('document.querySelector("[data-cycle-select] option").textContent'), 'Seleccionar ciclo');
            assert.equal(await evaluate('document.querySelector("#courses-title").textContent'), 'Cursos disponibles');
            assert.equal(await evaluate('[...document.querySelectorAll("[data-cycle-select] option")].slice(1).map(el => el.textContent.split(" · ")[0]).join(",")'), 'Ciclo I,Ciclo II,Ciclo III,Ciclo IV,Ciclo V,Ciclo VI,Ciclo VII,Ciclo VIII,Ciclo IX,Ciclo X');
            assert.ok((await evaluate('document.querySelector(".course-table-empty").textContent')).includes('Selecciona un ciclo'));
            assert.equal(await evaluate('document.querySelector("[data-course-row]")'), null);
            assert.equal(await evaluate('document.querySelector("#course-search, [data-summary]")'), null);
            assert.equal(draft.detalles.length, 0);
            requests.length = 0;
            await evaluate('(() => { const select = document.querySelector("[data-cycle-select]"); select.value = "2"; select.dispatchEvent(new Event("change", { bubbles: true })); })()');
            assert.ok((await evaluate('document.querySelector("[data-cycle-courses]").textContent')).includes('No tienes cursos disponibles en el ciclo II'));
            await evaluate('(() => { const select = document.querySelector("[data-cycle-select]"); select.value = "1"; select.dispatchEvent(new Event("change", { bubbles: true })); })()');
            assert.equal(await evaluate('document.querySelector("[data-cycle-select]").value'), '1');
            assert.equal(await evaluate('document.querySelectorAll("[data-course-row]").length'), 1);
            assert.equal(await evaluate('document.querySelector("[data-action=add]").classList.contains("btn-add")'), true);
            await screenshot('student-cycle-courses');
            assert.equal(requests.length, 0);
            assert.equal(await evaluate('document.querySelector("[data-course-preference], [data-preference]")'), null);
            await evaluate('window.__list=document.querySelector("[data-cycle-courses]")');
            await evaluate('document.querySelector("[data-action=add]").click()');
            await waitFor('document.querySelector("[data-action=remove]") && document.querySelector("[data-course-status]")');
            assert.equal(draft.creditos_totales, 3);
            assert.equal(draft.detalles[0].preferencia_turno, 'SIN_PREFERENCIA');
            assert.deepEqual(requests.map(item => item.method + ' ' + item.path), ['POST /api/v1/student/pre-enrollment/courses', 'GET /api/v1/student/pre-enrollments/1']);
            assert.equal('preferencia_turno' in requests[0].body, false);
            assert.equal(await evaluate('window.__list.isConnected'), true);
            assert.equal(await evaluate('document.activeElement === document.querySelector("[data-course-status]")'), true);
            assert.ok((await evaluate('document.querySelector("[data-selection-panel]").textContent')).includes('3 / 22'));
            assert.ok((await evaluate('document.querySelector("[data-cycle-select] option[value=\\"1\\"]").textContent')).includes('1 elegido'));
            assert.equal(await evaluate('document.querySelector("[data-cycle-select]").value'), '1');
            await evaluate('(() => { const select = document.querySelector("[data-cycle-select]"); select.value = "2"; select.dispatchEvent(new Event("change", { bubbles: true })); })()');
            await evaluate('(() => { const select = document.querySelector("[data-cycle-select]"); select.value = "1"; select.dispatchEvent(new Event("change", { bubbles: true })); })()');
            assert.equal(await evaluate('document.querySelector("[data-course-row] [data-course-status]").textContent'), 'En tu selección');
            await evaluate('window.__list=document.querySelector("[data-cycle-courses]")');
            await screenshot('student-selection-desktop');
            await command('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
            assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true);
            await screenshot('student-selection-mobile');
            await command('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false });
            await evaluate('document.querySelector("[data-action=remove]").click()');
            await waitFor('document.querySelector("dialog[open]")');
            await evaluate('document.querySelector("dialog[open] button[value=confirm]").click()');
            await waitFor('!document.querySelector("[data-action=remove]") && document.querySelector("[data-action=add]")');
            assert.equal(draft.creditos_totales, 0);
            assert.equal(await evaluate('window.__list.isConnected'), true);
            await evaluate('document.querySelector("[data-action=add]").click()');
            await waitFor('document.querySelector("[data-action=remove]") && document.querySelector("[data-course-status]")');
            assert.equal(await evaluate('document.querySelector("[data-action=review]")'), null);
            assert.equal(await evaluate('document.querySelector("[data-selection-panel] [data-action=submit]").textContent'), 'Registrar prematrícula');
            await evaluate('document.querySelector("[data-selection-panel] [data-action=submit]").click()');
            await waitFor('document.querySelector("dialog[open] button[value=confirm]")');
            assert.equal(draft.estado, 'BORRADOR');
            assert.ok((await evaluate('document.querySelector("dialog[open]").textContent')).includes('1 curso (3 créditos)'));
            await evaluate('document.querySelector("dialog[open] button[value=confirm]").click()');
            await waitFor('document.querySelector("dialog.success-modal[open]")');
            assert.equal(draft.estado, 'ENVIADA');
            assert.ok((await evaluate('document.querySelector("dialog.success-modal[open]").textContent')).includes('Tu prematrícula se registró con éxito.'));
            await screenshot('student-success-modal');
            await evaluate('document.querySelector("dialog.success-modal[open] button[value=ok]").click()');
            await waitFor('!document.querySelector("dialog[open]") && document.querySelector(".receipt-card [data-action=receipt]") && !document.querySelector("#screen").hasAttribute("aria-busy")');
            assert.ok((await evaluate('document.querySelector(".receipt-card [data-action=receipt]").textContent')).includes('Generar constancia PDF'));
            assert.equal(await evaluate('document.activeElement === document.querySelector(".receipt-card [data-action=receipt]")'), true);
            await screenshot('student-registered-desktop');
            assert.equal(await evaluate('document.querySelector(".steps")'), null);
            await evaluate('window.__opened=null;window.__realOpen=window.open;window.open=()=>({closed:false,opener:{},document:document.implementation.createHTMLDocument(""),location:{replace:url=>{window.__opened=url;}},close(){}})');
            await evaluate('document.querySelector("[data-action=receipt]").click()');
            await waitFor('window.__opened');
            assert.ok((await evaluate('window.__opened')).startsWith('blob:'));
            await evaluate('window.open=()=>null');
            await evaluate('document.querySelector("[data-action=receipt]").click()');
            await waitFor('document.querySelector("dialog[open] [data-open-pdf]")');
            assert.ok((await evaluate('document.querySelector("dialog[open] [data-open-pdf]").getAttribute("href")')).startsWith('blob:'));
            await evaluate('document.querySelector("dialog[open] [data-close]").click();window.open=window.__realOpen');
            assert.equal(await evaluate('document.querySelector("#screen [data-action=revisions], #screen [data-action=correction]")'), null);
        });

        await t.test('secretary can navigate every coordinator module without JavaScript failures', async () => {
            activeRole = 'COORDINADOR';
            await evaluate('localStorage.setItem("pm_user",' + JSON.stringify(JSON.stringify(user('COORDINADOR'))) + ')');
            await navigate('/coordinator'); await waitFor('document.querySelector("#screen h1")');
            await waitFor('document.querySelector(".dashboard-metrics") && !document.querySelector("#screen").hasAttribute("aria-busy")');
            await screenshot('coordinator-dashboard-desktop');
            for (const section of ['accounts','students','curriculum','periods','results','enrollments','demand','teachers','assignments','load']) {
                await evaluate('location.hash=' + JSON.stringify(section));
                await waitFor('document.querySelector("[data-section=' + section + ']").getAttribute("aria-current") === "page" && document.querySelector("#screen h1") && !document.querySelector("#screen").hasAttribute("aria-busy")');
                const message = await evaluate('document.querySelector("#flash").hidden ? "" : document.querySelector("#flash").textContent');
                assert.equal(message, '', section + ': ' + message);
                assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true, section);
                await screenshot('coordinator-' + section);
            }
            await screenshot('coordinator-desktop');
            await command('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
            assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true);
            await waitFor('document.querySelector("#sidebar").inert === true');
            await evaluate('document.querySelector("#menu-toggle").click()');
            assert.equal(await evaluate('document.querySelector("#menu-toggle").getAttribute("aria-expanded")'), 'true');
            await screenshot('coordinator-mobile');
        });

        await t.test('account form sends the documented nested student payload', async () => {
            await evaluate('location.hash="accounts"');
            await waitFor('document.querySelector("[data-section=accounts]").getAttribute("aria-current")==="page" && !document.querySelector("#screen").hasAttribute("aria-busy")');
            await evaluate('document.querySelector("[data-action=create]").click()');
            await waitFor('document.querySelector("dialog[open] form")');
            await evaluate(`(() => {
                const form=document.querySelector("dialog[open] form");
                const values={"correo_institucional":"fixture@example.invalid","password":"fixture-pass","estudiante.codigo_universitario":"00099","estudiante.nombres":"Registro","estudiante.apellidos":"de prueba","estudiante.creditos_maximos":"22"};
                for(const [name,value] of Object.entries(values))form.elements.namedItem(name).value=value;
                form.requestSubmit();
            })()`);
            await waitFor('!document.querySelector("dialog[open]")');
            const request=requests.findLast(item=>item.path==="/api/v1/coordinator/accounts" && item.method==="POST");
            assert.deepEqual(request.body, { correo_institucional: 'fixture@example.invalid', password: 'fixture-pass', estudiante: { codigo_universitario: '00099', nombres: 'Registro', apellidos: 'de prueba', creditos_maximos: 22 } });
        });
        await t.test('curriculum validation and confirmation preserve file and server fingerprints', async () => {
            await evaluate('location.hash="curriculum"');
            await waitFor('document.querySelector("[data-section=curriculum]").getAttribute("aria-current")==="page" && !document.querySelector("#screen").hasAttribute("aria-busy")');
            await evaluate(`(() => {
                const form=document.querySelector("[data-import-form]");
                const transfer=new DataTransfer();transfer.items.add(new File(["ISOLATED_BROWSER_FIXTURE"],"malla.xlsx",{type:"application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"}));
                form.elements.file.files=transfer.files;form.elements.file.dispatchEvent(new Event("change",{bubbles:true}));form.requestSubmit();
            })()`);
            await waitFor('!document.querySelector("[data-import=confirm]").disabled');
            await evaluate('document.querySelector("[data-import=confirm]").click()');
            await waitFor('document.querySelector("dialog[open]")');
            assert.equal(requests.some(item=>item.path==="/api/v1/coordinator/curriculum/import"),false);
            await evaluate('document.querySelector("dialog[open] button[value=confirm]").click()');
            await waitFor('document.querySelector("[data-import-form] input[type=file]").files.length===0 && !document.querySelector("dialog[open]")');
            const request=requests.findLast(item=>item.path==="/api/v1/coordinator/curriculum/import");
            assert.ok(request.raw.includes('ISOLATED_BROWSER_FIXTURE'));
            assert.ok(request.raw.includes('a'.repeat(64)));
            assert.ok(request.raw.includes('b'.repeat(64)));
            assert.ok(request.raw.includes('name="catalog_fingerprint"'));
        });

        assert.deepEqual(serverErrors, []);
        assert.deepEqual(runtimeErrors, []);
    } finally {
        await send('Browser.close').catch(() => {}); socket.close(); browser.kill(); server.close();
        await new Promise(resolve => setTimeout(resolve, 300));
        try { if (resolve(profile).startsWith(resolve(tmpdir()) + '\\') && profile.includes('prematricula-browser-')) rmSync(profile, { recursive: true, force: true }); } catch {}
    }
});

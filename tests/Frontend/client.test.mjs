import { test, beforeEach } from 'node:test';
import assert from 'node:assert/strict';

const memory = new Map();
const redirects = [];
globalThis.localStorage = { getItem: key => memory.get(key) ?? null, setItem: (key, value) => memory.set(key, value), removeItem: key => memory.delete(key) };
globalThis.window = { location: { origin: 'http://frontend.test', assign: value => redirects.push(value), replace: value => redirects.push(value) } };
const { api, auth, login, logout, requireRole } = await import('../../resources/js/api.js');
const { escape, date } = await import('../../resources/js/ui.js');
const json = (body, status = 200, headers = {}) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json', ...headers } });
beforeEach(() => { memory.clear(); redirects.length = 0; globalThis.fetch = async () => { throw new Error('Unexpected request'); }; });

test('login preserves university code as text and stores server expiry', async () => {
    globalThis.fetch = async (url, options) => {
        assert.equal(url.pathname, '/api/v1/login');
        assert.deepEqual(JSON.parse(options.body), { identifier: '00123', password: 'example-only' });
        return json({ data: { token: 'test-token', expires_at: '2027-01-01T00:00:00Z', user: { rol: 'ESTUDIANTE' } } });
    };
    await login('00123', 'example-only');
    assert.equal(auth.token(), 'test-token');
    assert.equal(auth.expiresAt(), '2027-01-01T00:00:00Z');
});
test('JSON requests add Bearer, encode filters, omit empty filters and never use cookies', async () => {
    auth.save('test-token', { rol: 'COORDINADOR' });
    globalThis.fetch = async (url, options) => {
        assert.equal(url.searchParams.get('q'), 'A&B');
        assert.equal(url.searchParams.has('estado'), false);
        assert.equal(options.headers.Authorization, 'Bearer test-token');
        assert.equal(options.headers['Content-Type'], 'application/json');
        assert.equal(options.credentials, 'omit');
        assert.deepEqual(JSON.parse(options.body), { estado: 'ACTIVO' });
        return json({ data: { id: 7 } });
    };
    assert.deepEqual(await api('/coordinator/teachers/7', { method: 'PATCH', query: { q: 'A&B', estado: '' }, body: { estado: 'ACTIVO' } }), { data: { id: 7 } });
});
test('multipart preserves browser boundary and original File', async () => {
    const file = new File(['example'], 'malla.xlsx');
    const body = new FormData(); body.append('file', file);
    globalThis.fetch = async (_url, options) => {
        assert.equal(options.body, body);
        assert.equal(options.body.get('file'), file);
        assert.equal(options.headers['Content-Type'], undefined);
        return json({ data: { valid: true } });
    };
    await api('/coordinator/curriculum/validate', { method: 'POST', body });
});
test('422 retains field errors and row reports', async () => {
    const payload = { message: 'Revise el archivo', errors: { file: ['Archivo inválido'] }, data: { valid: false, errors: [{ row: 2, column: 'codigo', message: 'Duplicado' }] } };
    globalThis.fetch = async () => json(payload, 422);
    await assert.rejects(api('/coordinator/curriculum/validate', { method: 'POST', body: new FormData() }), error => {
        assert.equal(error.status, 422); assert.deepEqual(error.report, payload.data); assert.deepEqual(error.errors, payload.errors); return true;
    });
});
test('429 retains Retry-After without automatic retries', async () => {
    let count = 0;
    globalThis.fetch = async () => { count++; return json({ message: 'Espere' }, 429, { 'Retry-After': '30' }); };
    await assert.rejects(api('/courses'), error => error.status === 429 && error.retryAfter === '30');
    assert.equal(count, 1);
});
test('401 clears token and redirects authenticated user', async () => {
    auth.save('test-token', { rol: 'ESTUDIANTE' });
    globalThis.fetch = async () => json({ message: 'Venció' }, 401);
    await assert.rejects(api('/me'), error => error.status === 401);
    assert.equal(auth.token(), null); assert.deepEqual(redirects, ['/login']);
});
test('403 does not discard credentials or retry a write', async () => {
    auth.save('test-token', { rol: 'ESTUDIANTE' }); let calls = 0;
    globalThis.fetch = async () => { calls++; return json({ message: 'Sin permiso' }, 403); };
    await assert.rejects(api('/coordinator/accounts', { method: 'POST', body: {} }), error => error.status === 403);
    assert.equal(auth.token(), 'test-token'); assert.equal(calls, 1); assert.deepEqual(redirects, []);
});
test('concurrent identical writes are blocked and lock releases after completion', async () => {
    let finish;
    globalThis.fetch = () => new Promise(resolve => { finish = () => resolve(json({ data: {} })); });
    const first = api('/student/pre-enrollment/submit', { method: 'POST' });
    await assert.rejects(api('/student/pre-enrollment/submit', { method: 'POST' }), error => error.status === 409);
    finish(); await first;
    globalThis.fetch = async () => json({ data: {} });
    await api('/student/pre-enrollment/submit', { method: 'POST' });
});
test('download distinguishes JSON conflicts from file responses', async () => {
    globalThis.fetch = async () => json({ message: 'Sin evidencia' }, 409);
    await assert.rejects(api('/student/pre-enrollments/7/receipt', { download: true }), error => error.status === 409 && error.message === 'Sin evidencia');
    globalThis.fetch = async () => json({ data: {} });
    await assert.rejects(api('/student/pre-enrollments/7/receipt', { download: true }), /archivo esperado/);
});
test('network errors preserve session and provide recovery instructions', async () => {
    auth.save('test-token', { rol: 'ESTUDIANTE' });
    globalThis.fetch = async () => { throw new TypeError('offline'); };
    await assert.rejects(api('/courses'), error => error.status === 0 && error.message.includes('Verifica tu conexión'));
    assert.equal(auth.token(), 'test-token');
});
test('aborted requests preserve AbortError for navigation cancellation', async () => {
    globalThis.fetch = async () => { throw new DOMException('Aborted', 'AbortError'); };
    await assert.rejects(api('/courses'), error => error.name === 'AbortError');
});
test('role guard validates server role and redirects to correct portal', async () => {
    auth.save('test-token', { rol: 'ESTUDIANTE' });
    globalThis.fetch = async () => json({ data: { rol: 'COORDINADOR', cargo: 'SECRETARIA' } });
    assert.equal(await requireRole('ESTUDIANTE'), null);
    assert.deepEqual(redirects, ['/coordinator']);
    assert.equal(auth.user().cargo, 'SECRETARIA');
});
test('logout revokes current token and clears local session', async () => {
    auth.save('test-token', { rol: 'ESTUDIANTE' });
    globalThis.fetch = async (url, options) => {
        assert.equal(url.pathname, '/api/v1/logout'); assert.equal(options.method, 'POST');
        return json({ message: 'Sesión cerrada' });
    };
    await logout(); assert.equal(auth.token(), null); assert.deepEqual(redirects, ['/login']);
});
test('untrusted names escape HTML and class date remains in local calendar', () => {
    const escaped = escape('<img src=x onerror="alert(1)"> & name');
    assert.equal(escaped.includes('<img'), false); assert.equal(escaped.includes('&lt;img'), true);
    assert.equal(date('2026-04-01'), '01/04/2026');
});

test('successful PDF download uses authenticated fetch and server filename', async () => {
    const oldDocument = globalThis.document;
    const anchor = { click() { this.clicked = true; } };
    globalThis.document = { createElement: name => { assert.equal(name, 'a'); return anchor; } };
    auth.save('test-token', { rol: 'ESTUDIANTE' });
    globalThis.fetch = async (_url, options) => {
        assert.equal(options.headers.Authorization, 'Bearer test-token');
        return new Response('%PDF-1.4 fixture', { headers: { 'Content-Type': 'application/pdf', 'Content-Disposition': "attachment; filename*=UTF-8''constancia%20prueba.pdf" } });
    };
    try {
        await api('/student/pre-enrollments/1/receipt', { download: true });
        assert.equal(anchor.download, 'constancia prueba.pdf');
        assert.equal(anchor.clicked, true);
        assert.equal(anchor.href.startsWith('blob:'), true);
    } finally { globalThis.document = oldDocument; }
});
test('malformed JSON is a recoverable response error', async () => {
    globalThis.fetch = async () => new Response('{incomplete', { headers: { 'Content-Type': 'application/json' } });
    await assert.rejects(api('/courses'), error => error.message.includes('respuesta incompleta'));
});
test('empty 204 responses are accepted', async () => {
    globalThis.fetch = async () => new Response(null, { status: 204 });
    assert.equal(await api('/coordinator/teaching-assignments/1', { method: 'DELETE' }), null);
});

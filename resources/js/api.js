const TOKEN_KEY = 'pm_token';
const USER_KEY = 'pm_user';
const EXPIRY_KEY = 'pm_expires_at';
const store = {
    get(key) { try { return localStorage.getItem(key); } catch { return null; } },
    set(key, value) { try { localStorage.setItem(key, value); } catch { throw new Error('Habilita el almacenamiento del navegador para iniciar sesión.'); } },
    del(key) { try { localStorage.removeItem(key); } catch {} },
};
export const auth = {
    token: () => store.get(TOKEN_KEY),
    user() { try { return JSON.parse(store.get(USER_KEY) || 'null'); } catch { return null; } },
    expiresAt: () => store.get(EXPIRY_KEY),
    save(token, user, expiresAt) {
        store.set(TOKEN_KEY, token); store.set(USER_KEY, JSON.stringify(user));
        if (expiresAt) store.set(EXPIRY_KEY, expiresAt);
    },
    clear() { [TOKEN_KEY, USER_KEY, EXPIRY_KEY].forEach(key => store.del(key)); },
};
export function homeFor(role) { return role === 'COORDINADOR' ? '/coordinator' : '/student'; }
export class ApiError extends Error {
    constructor(message, status = 0, payload = {}, headers = new Headers()) {
        super(message); this.name = 'ApiError'; this.status = status; this.payload = payload;
        this.errors = payload.errors || {}; this.report = payload.data; this.retryAfter = headers.get('Retry-After');
    }
}
const pendingWrites = new Set();
export async function api(path, { method = 'GET', body, query, signal, download = false, filename } = {}) {
    method = method.toUpperCase();
    const url = new URL('/api/v1' + path, window.location.origin);
    Object.entries(query || {}).forEach(([key, value]) => {
        if (value !== '' && value !== undefined && value !== null) url.searchParams.set(key, value);
    });
    const key = method + ' ' + url.pathname + url.search;
    const isWrite = method !== 'GET';
    if (isWrite && pendingWrites.has(key)) throw new ApiError('Esta operación ya está en curso.', 409);
    if (isWrite) pendingWrites.add(key);
    try {
        const headers = { Accept: download ? 'application/pdf, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, text/csv, application/json' : 'application/json' };
        const token = auth.token();
        if (token) headers.Authorization = 'Bearer ' + token;
        const multipart = body instanceof FormData;
        if (body !== undefined && !multipart) headers['Content-Type'] = 'application/json';
        let response;
        try {
            response = await fetch(url, { method, headers, body: body === undefined ? undefined : multipart ? body : JSON.stringify(body), signal, credentials: 'omit', cache: 'no-store' });
        } catch (error) {
            if (error.name === 'AbortError') throw error;
            throw new ApiError('No se pudo conectar. Verifica tu conexión. Si estabas guardando, consulta el resultado antes de repetir la operación.');
        }
        const contentType = response.headers.get('Content-Type') || '';
        const isJson = contentType.includes('json');
        let payload = null;
        if (isJson) {
            try { payload = await response.json(); } catch { throw new ApiError('El servidor devolvió una respuesta incompleta.', response.status); }
        }
        if (!response.ok) {
            if (response.status === 401 && token) { auth.clear(); window.location.assign('/login'); }
            const fallback = { 401: 'Credenciales incorrectas o sesión vencida.', 403: 'No tienes permiso para realizar esta operación.', 404: 'El recurso no está disponible.', 409: 'La información cambió. Actualiza y vuelve a revisar.', 413: 'El archivo supera el tamaño permitido por el servidor.', 422: 'Revisa los datos y las reglas indicadas.', 429: 'Demasiadas solicitudes. Espera antes de volver a intentar.' };
            throw new ApiError(payload?.message || fallback[response.status] || 'No se pudo completar la operación.', response.status, payload || {}, response.headers);
        }
        if (download) {
            if (isJson || !/application\/(pdf|vnd\.openxmlformats-officedocument\.spreadsheetml\.sheet)|text\/csv|application\/octet-stream/.test(contentType)) throw new ApiError('El servidor no devolvió el archivo esperado.', response.status, payload || {});
            const disposition = response.headers.get('Content-Disposition') || '';
            const match = disposition.match(/filename\*?=(?:UTF-8'')?"?([^";]+)/i);
            let name = filename || 'descarga';
            if (match) { try { name = decodeURIComponent(match[1]); } catch { name = match[1]; } }
            name = name.replace(/[\\/\x00-\x1f]/g, '_');
            const objectUrl = URL.createObjectURL(await response.blob());
            const anchor = document.createElement('a'); anchor.href = objectUrl; anchor.download = name; anchor.click();
            setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
            return null;
        }
        if (response.status === 204) return null;
        if (!isJson) throw new ApiError('Se esperaba una respuesta JSON del servidor.', response.status);
        return payload;
    } finally { if (isWrite) pendingWrites.delete(key); }
}
export async function login(identifier, password) {
    const response = await api('/login', { method: 'POST', body: { identifier, password } });
    const { token, user, expires_at: expiresAt } = response.data;
    auth.save(token, user, expiresAt); return user;
}
export async function logout() {
    try { await api('/logout', { method: 'POST' }); }
    finally { auth.clear(); window.location.assign('/login'); }
}
export async function requireRole(role) {
    if (!auth.token()) { window.location.replace('/login'); return null; }
    const { data: user } = await api('/me');
    if (!['ESTUDIANTE', 'COORDINADOR'].includes(user.rol)) throw new ApiError('Rol no admitido.', 403);
    auth.save(auth.token(), user);
    if (user.rol !== role) { window.location.replace(homeFor(user.rol)); return null; }
    return user;
}
window.PM = { api, auth, login, logout, requireRole, homeFor, ApiError };

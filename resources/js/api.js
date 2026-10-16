// Cliente de la API (Sanctum, token Bearer) + guardas de sesión/rol.
const TOKEN_KEY = 'pm_token';
const USER_KEY = 'pm_user';

const store = {
    get(k) { try { return localStorage.getItem(k); } catch { return null; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch {} },
    del(k) { try { localStorage.removeItem(k); } catch {} },
};

export const auth = {
    token: () => store.get(TOKEN_KEY),
    user: () => {
        try { return JSON.parse(store.get(USER_KEY) || 'null'); } catch { return null; }
    },
    save(token, user) {
        store.set(TOKEN_KEY, token);
        store.set(USER_KEY, JSON.stringify(user));
    },
    clear() {
        store.del(TOKEN_KEY);
        store.del(USER_KEY);
    },
};

// Ruta de inicio según rol (ESTUDIANTE / COORDINADOR).
export function homeFor(rol) {
    return String(rol || '').toUpperCase().startsWith('COORD') ? '/coordinator' : '/student';
}

export class ApiError extends Error {
    constructor(message, status, errors = {}) {
        super(message);
        this.status = status;
        this.errors = errors;
    }
}

export async function api(path, { method = 'GET', body } = {}) {
    const headers = { Accept: 'application/json' };
    const token = auth.token();
    if (token) headers.Authorization = `Bearer ${token}`;
    if (body !== undefined) headers['Content-Type'] = 'application/json';

    const res = await fetch(`/api/v1${path}`, {
        method,
        headers,
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });

    let data = null;
    try { data = await res.json(); } catch {}

    if (res.status === 401 && token) {
        auth.clear();
        window.location.href = '/login';
        throw new ApiError('Sesión expirada.', 401);
    }
    if (!res.ok) {
        throw new ApiError(data?.message || 'Ocurrió un error.', res.status, data?.errors || {});
    }
    return data;
}

export async function login(correo_institucional, password) {
    const res = await api('/login', { method: 'POST', body: { correo_institucional, password } });
    auth.save(res.data.token, res.data.user);
    return res.data.user;
}

export async function logout() {
    try { await api('/logout', { method: 'POST' }); } catch {}
    auth.clear();
    window.location.href = '/login';
}

// Protege una página: exige token válido y el rol indicado.
export async function requireRole(rol) {
    if (!auth.token()) {
        window.location.href = '/login';
        return null;
    }
    try {
        const { data } = await api('/me');
        auth.save(auth.token(), data);
        if (String(data.rol).toUpperCase().slice(0, 5) !== String(rol).toUpperCase().slice(0, 5)) {
            window.location.href = homeFor(data.rol);
            return null;
        }
        return data;
    } catch {
        auth.clear();
        window.location.href = '/login';
        return null;
    }
}

window.PM = { api, auth, login, logout, requireRole, homeFor, ApiError };

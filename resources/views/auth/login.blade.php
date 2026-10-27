<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · Prematrícula UNT</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-unt.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#0b1220] text-slate-200 antialiased">
<main class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-4xl overflow-hidden rounded-2xl border border-white/5 bg-[#131b2c] shadow-2xl md:grid md:grid-cols-2">

        {{-- Panel institucional --}}
        <section class="flex flex-col items-center justify-center gap-5 bg-gradient-to-br from-[#16213a] to-[#0f1729] px-8 py-10 text-center md:border-r md:border-white/5">
            <img src="{{ asset('images/logo-unt.png') }}" alt="Escudo de la Universidad Nacional de Trujillo" class="h-32 md:h-44 drop-shadow-lg">
            <div>
                <p class="text-xs font-medium uppercase tracking-[0.2em] text-blue-400">Sistema Académico</p>
                <h1 class="mt-2 text-2xl font-semibold text-white">Universidad Nacional de Trujillo</h1>
                <p class="mt-2 text-sm text-slate-400">Prematrícula de estudiantes y carga académica docente</p>
            </div>
        </section>

        {{-- Formulario --}}
        <section class="px-8 py-10 md:px-10">
            <h2 class="text-xl font-semibold text-white">Iniciar sesión</h2>
            <p class="mt-1 text-sm text-slate-400">Ingresa con tu correo institucional o código universitario.</p>

            <div id="error" role="alert" class="hidden mt-6 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2.5 text-sm text-red-300"></div>

            <form id="loginForm" class="mt-6 space-y-5" novalidate>
                <div>
                    <label for="identifier" class="mb-1.5 block text-sm font-medium text-slate-300">Correo o código</label>
                    <input id="identifier" name="identifier" type="text" required autofocus autocomplete="username"
                           placeholder="usuario@unitru.edu.pe"
                           class="w-full rounded-lg border border-white/10 bg-[#0b1220] px-4 py-2.5 text-white placeholder:text-slate-500 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-slate-300">Contraseña</label>
                    <div class="relative">
                        <input id="password" name="password" type="password" required autocomplete="current-password"
                               class="w-full rounded-lg border border-white/10 bg-[#0b1220] px-4 py-2.5 pr-20 text-white focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <button type="button" id="togglePass"
                                class="absolute inset-y-0 right-0 px-4 text-xs font-medium text-slate-400 hover:text-white">Mostrar</button>
                    </div>
                </div>
                <button id="submitBtn" type="submit"
                        class="w-full rounded-lg bg-blue-600 py-2.5 font-medium text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-60">
                    Ingresar
                </button>
            </form>

            <p class="mt-8 text-center text-xs text-slate-500">&copy; {{ date('Y') }} Universidad Nacional de Trujillo</p>
        </section>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const { auth, login, homeFor } = window.PM;

    // Si ya hay sesión, ir directo al panel (el panel valida el token con /me).
    if (auth.token() && auth.user()) {
        window.location.replace(homeFor(auth.user().rol));
        return;
    }

    const form = document.getElementById('loginForm');
    const errorBox = document.getElementById('error');
    const btn = document.getElementById('submitBtn');
    const pass = document.getElementById('password');

    document.getElementById('togglePass').addEventListener('click', (e) => {
        const show = pass.type === 'password';
        pass.type = show ? 'text' : 'password';
        e.currentTarget.textContent = show ? 'Ocultar' : 'Mostrar';
    });

    const showError = (msg) => {
        errorBox.textContent = msg;
        errorBox.classList.remove('hidden');
    };

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        errorBox.classList.add('hidden');

        const identifier = document.getElementById('identifier').value.trim();
        if (!identifier || !pass.value) {
            showError('Completa tu correo o código y tu contraseña.');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Ingresando...';
        try {
            const user = await login(identifier, pass.value);
            window.location.replace(homeFor(user.rol));
        } catch (err) {
            if (err.status === 401) showError('Credenciales incorrectas.');
            else if (err.status === 422) showError(Object.values(err.errors)[0]?.[0] ?? err.message);
            else if (err.status === 429) showError('Demasiados intentos. Espera un minuto e inténtalo de nuevo.');
            else if (err.status) showError('Error del servidor. Intenta nuevamente.');
            else showError('No se pudo conectar con el servidor.');
            btn.disabled = false;
            btn.textContent = 'Ingresar';
        }
    });
});
</script>
</body>
</html>

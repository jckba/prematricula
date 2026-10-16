<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Prematrícula')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">

{{-- Contenido oculto hasta validar sesión y rol --}}
<div id="app" class="hidden min-h-screen md:flex">

    {{-- Sidebar --}}
    <aside class="hidden md:flex w-64 shrink-0 flex-col bg-slate-900 text-slate-200">
        <div class="px-5 py-5 border-b border-slate-700">
            <p class="text-xs uppercase tracking-wider text-slate-400">Sistema de</p>
            <h1 class="text-lg font-bold text-white">Prematrícula</h1>
        </div>
        <nav class="flex-1 p-3 space-y-1 text-sm">
            @yield('nav')
        </nav>
        <div class="p-4 border-t border-slate-700 text-xs text-slate-400">
            <p id="user-email" class="truncate"></p>
            <p id="user-role" class="font-semibold text-slate-300"></p>
        </div>
    </aside>

    <div class="flex-1 flex flex-col min-w-0">
        {{-- Barra superior --}}
        <header class="flex items-center justify-between bg-white px-6 py-3 shadow-sm">
            <h2 class="text-base font-semibold">@yield('heading')</h2>
            <button id="logout-btn" class="text-sm font-medium text-red-600 hover:underline">Cerrar sesión</button>
        </header>

        <main class="p-6">
            <div id="flash" class="hidden mb-4 rounded-lg border px-3 py-2 text-sm"></div>
            @yield('content')
        </main>
    </div>
</div>

<div id="splash" class="min-h-screen flex items-center justify-center text-sm text-slate-500">
    Cargando...
</div>

<script>
// Helpers globales de UI
window.flash = (msg, type = 'success') => {
    const el = document.getElementById('flash');
    el.className = 'mb-4 rounded-lg border px-3 py-2 text-sm ' + (type === 'success'
        ? 'border-green-300 bg-green-50 text-green-800'
        : 'border-red-300 bg-red-50 text-red-700');
    el.textContent = msg;
    setTimeout(() => el.classList.add('hidden'), 5000);
};

document.addEventListener('DOMContentLoaded', async () => {
    const user = await window.PM.requireRole(@json($role));
    if (!user) return;

    document.getElementById('user-email').textContent = user.correo_institucional;
    document.getElementById('user-role').textContent = user.rol;
    document.getElementById('splash').remove();
    document.getElementById('app').classList.remove('hidden');
    document.getElementById('app').classList.add('flex');

    document.getElementById('logout-btn').addEventListener('click', () => window.PM.logout());

    document.dispatchEvent(new CustomEvent('pm:ready', { detail: { user } }));
});
</script>
@stack('scripts')
</body>
</html>

@extends('layouts.guest')

@section('title', 'Iniciar sesión')

@section('content')
<div class="bg-white shadow-lg rounded-xl p-8">
    <h1 class="text-2xl font-bold text-slate-900">Sistema de Prematrícula</h1>
    <p class="text-sm text-slate-500 mt-1 mb-6">Ingresa con tu correo institucional</p>

    <div id="alert" class="hidden mb-4 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700"></div>

    <form id="login-form" novalidate>
        <div class="mb-4">
            <label for="correo" class="block text-sm font-medium mb-1">Correo institucional</label>
            <input id="correo" type="email" autocomplete="username" required autofocus
                   placeholder="usuario@unitru.edu.pe"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <p data-error="correo_institucional" class="mt-1 text-xs text-red-600"></p>
        </div>

        <div class="mb-6">
            <label for="password" class="block text-sm font-medium mb-1">Contraseña</label>
            <input id="password" type="password" autocomplete="current-password" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <p data-error="password" class="mt-1 text-xs text-red-600"></p>
        </div>

        <button id="submit" type="submit"
                class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
            Iniciar sesión
        </button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const { auth, login, homeFor } = window.PM;

    // Si ya hay sesión, ir directo a su panel.
    if (auth.token() && auth.user()) {
        window.location.href = homeFor(auth.user().rol);
        return;
    }

    const form = document.getElementById('login-form');
    const btn = document.getElementById('submit');
    const alertBox = document.getElementById('alert');

    const clearErrors = () => {
        alertBox.classList.add('hidden');
        document.querySelectorAll('[data-error]').forEach(el => el.textContent = '');
    };

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearErrors();
        btn.disabled = true;
        btn.textContent = 'Ingresando...';

        try {
            const user = await login(
                document.getElementById('correo').value.trim(),
                document.getElementById('password').value
            );
            window.location.href = homeFor(user.rol);
        } catch (err) {
            if (err.status === 422) {
                Object.entries(err.errors).forEach(([field, msgs]) => {
                    const el = document.querySelector(`[data-error="${field}"]`);
                    if (el) el.textContent = msgs[0];
                });
            } else {
                alertBox.textContent = err.message;
                alertBox.classList.remove('hidden');
            }
            btn.disabled = false;
            btn.textContent = 'Iniciar sesión';
        }
    });
});
</script>
@endsection

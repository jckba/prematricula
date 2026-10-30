@extends('layouts.guest')
@section('title', 'Iniciar sesión')
@section('content')
<section class="login-intro" aria-labelledby="institution-name">
    <div class="login-photo" aria-hidden="true">
        <img src="{{ asset('images/reseña_frontis_escuela_informatica.webp') }}" alt="" width="900" height="500">
    </div>
    <div class="login-identity relative z-10 flex flex-col items-center text-center">
        <img src="{{ asset('images/logo_info.png') }}" alt="Escudo de la Escuela Profesional de Informática de la UNT" class="brand-logo login-hero-logo" width="1402" height="1122">
        <h1 id="institution-name">Prematrícula</h1>
        <p>Escuela Profesional de Informática<br>UNT</p>
        <span class="login-gold-line" aria-hidden="true"></span>
    </div>
</section>
<section class="login-panel" aria-labelledby="login-heading">
    <div class="login-card relative z-10 w-full">
        <div class="flex flex-col items-center text-center">
            <img src="{{ asset('images/logo_info.png') }}" alt="Escuela Profesional de Informática de la UNT" class="brand-logo login-card-logo" width="1402" height="1122">
            <h2 id="login-heading">Iniciar sesión</h2>
        </div>
        <div id="flash" class="notice notice-danger" role="alert" hidden></div>
        <form id="login-form">
            <div class="field">
                <label class="sr-only" for="identifier">Correo institucional o código universitario</label>
                <div class="login-input-shell">
                    <svg class="login-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="7" r="4"/><path d="M4 21v-3a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v3Z"/></svg>
                    <input id="identifier" name="identifier" type="text" placeholder="Correo institucional o código" autocomplete="username" required maxlength="255" aria-describedby="identifier-help identifier-error" spellcheck="false" autocapitalize="none">
                </div>
                <small id="identifier-help" class="sr-only">Escribe tu código completo, incluidos los ceros iniciales.</small>
                <p id="identifier-error" data-error="identifier" class="field-error"></p>
            </div>
            <div class="field">
                <label class="sr-only" for="password">Contraseña</label>
                <div class="login-input-shell">
                    <svg class="login-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="10" width="16" height="12" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4"/><path d="M12 15v2"/></svg>
                    <input id="password" name="password" type="password" placeholder="Contraseña" autocomplete="current-password" required maxlength="1024" aria-describedby="password-error">
                    <button id="toggle-password" type="button" aria-label="Mostrar contraseña" aria-controls="password" aria-pressed="false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="login-eye-slash" d="m3 3 18 18"/></svg>
                        <span class="sr-only" data-password-label>Mostrar</span>
                    </button>
                </div>
                <p id="password-error" data-error="password" class="field-error"></p>
            </div>
            <button class="btn login-submit" type="submit">Ingresar</button>
        </form>
        <details class="login-help">
            <summary>
                <span class="login-help-line" aria-hidden="true"></span>
                <span class="inline-flex items-center gap-2">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4"/><path d="M12 17h.01"/></svg>
                    Ayuda
                </span>
                <span class="login-help-line" aria-hidden="true"></span>
            </summary>
            <p>Ingresa tu correo institucional o tu código universitario completo. Si no puedes acceder, comunícate con la secretaría de tu escuela para verificar tu cuenta.</p>
        </details>
        <noscript><p class="notice notice-danger">Activa JavaScript para utilizar el portal y autenticarte.</p></noscript>
    </div>
</section>
@endsection

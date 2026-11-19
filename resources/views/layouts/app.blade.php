<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="same-origin">
    <title>@yield('title', 'Prematrícula') · UNT Informática</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=2" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=2">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=2">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<a class="skip-link" href="#main">Saltar al contenido</a>
<div id="splash" class="splash" role="status"><div><p class="eyebrow">UNT · INFORMÁTICA</p><p>Verificando tu acceso…</p></div></div>
<div id="app" data-role="{{ $role }}" class="app-shell" hidden>
    <button id="menu-backdrop" class="menu-backdrop" type="button" aria-label="Cerrar menú de navegación" hidden></button>
    <aside id="sidebar" class="sidebar" aria-label="Navegación principal">
        <button id="menu-close" class="sidebar-close" type="button" aria-label="Cerrar menú de navegación">Cerrar <span aria-hidden="true">×</span></button>
        <a class="sidebar-brand" href="#dashboard" aria-label="UNT Informática · Inicio">
            <img src="{{ asset('images/logo_info.png') }}" alt="" class="sidebar-emblem" width="1402" height="1122">
            <strong>UNT</strong>
            <span class="sidebar-school">Escuela Profesional<br>de Informática</span>
        </a>
        <nav>@yield('nav')</nav>
        <div class="sidebar-bottom">
            <button id="logout-btn" class="user-menu-item" type="button">
                <span data-shell-icon="logout" aria-hidden="true"></span>Cerrar sesión
            </button>
            <span class="sidebar-caption">Universidad Nacional de Trujillo</span>
        </div>
    </aside>
    <div class="app-main">
        <header class="topbar">
            <div class="topbar-start">
                <button id="sidebar-collapse" class="sidebar-collapse" type="button" aria-controls="sidebar" aria-expanded="true" aria-label="Contraer menú" title="Contraer menú"><span data-shell-icon="panel-close" aria-hidden="true"></span></button>
                <button id="menu-toggle" class="icon-button" type="button" aria-label="Abrir menú" aria-controls="sidebar" aria-expanded="false"><span data-shell-icon="menu" aria-hidden="true"></span></button>
                <div class="topbar-title">
                    <span class="institution-name">Universidad Nacional de Trujillo</span>
                    <div class="institution-service"><strong>Sistema de Prematrícula</strong><nav aria-label="Sección actual"><span id="breadcrumb">Inicio</span></nav></div>
                </div>
            </div>
            <div class="user-menu">
                <button id="user-menu-toggle" class="user-menu-trigger" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="user-menu-panel" aria-label="Menú de usuario">
                    <span id="user-avatar" class="user-avatar" aria-hidden="true"></span>
                    <span class="user-identity"><span id="user-email"></span><small id="user-role"></small></span>
                    <span class="user-menu-chevron" data-shell-icon="chevron" aria-hidden="true"></span>
                </button>
                <div id="user-menu-panel" class="user-menu-panel" role="menu" aria-label="Opciones de usuario" hidden>
                    <div class="user-menu-header"><strong id="user-menu-email"></strong><small id="user-menu-role"></small></div>
                    <button id="logout-btn" class="user-menu-item" type="button" role="menuitem"><span data-shell-icon="logout" aria-hidden="true"></span>Cerrar sesión</button>
                </div>
            </div>
        </header>
        <main id="main" class="main-content">
            <div id="flash" role="status" data-toast hidden></div>
            <section id="screen" aria-live="polite"></section>
        </main>
        <footer class="app-footer"><span>UNT · Informática</span><span>La prematrícula no constituye matrícula definitiva.</span></footer>
    </div>
</div>
<noscript><p class="notice notice-danger">Activa JavaScript para utilizar las funciones del portal.</p></noscript>
</body>
</html>

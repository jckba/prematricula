<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="same-origin">
    <title>@yield('title', 'Prematrícula') · UNT Informática</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="guest-body">
    <a class="skip-link" href="#main">Saltar al contenido</a>
    <main id="main" class="login-layout">@yield('content')</main>
</body>
</html>

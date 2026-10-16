<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Prematrícula')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
    <main class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md">@yield('content')</div>
    </main>
</body>
</html>

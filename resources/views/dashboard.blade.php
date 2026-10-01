<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Dashboard</title>
</head>

<body>

<h1>Dashboard de prueba</h1>

<p>
    Usuario autenticado:
    <strong>
        {{ auth()->user()->correo_institucional }}
    </strong>
</p>

<p>
    Rol:
    <strong>
        {{ auth()->user()->rol->value }}
    </strong>
</p>

<form
    method="POST"
    action="{{ route('logout') }}"
>
    @csrf

    <button type="submit">
        Cerrar sesión
    </button>
</form>

</body>
</html>

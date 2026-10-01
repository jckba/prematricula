<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Prematrícula - Iniciar sesión</title>
</head>

<body>

<h1>Sistema de Prematrícula</h1>

<form
    method="POST"
    action="{{ route('login.store') }}"
>
    @csrf

    <div>
        <label for="correo_institucional">
            Correo institucional
        </label>

        <input
            id="correo_institucional"
            name="correo_institucional"
            type="email"
            value="{{ old('correo_institucional') }}"
            required
        >

        @error('correo_institucional')
        <div>
            {{ $message }}
        </div>
        @enderror
    </div>

    <div>
        <label for="password">
            Contraseña
        </label>

        <input
            id="password"
            name="password"
            type="password"
            required
        >

        @error('password')
        <div>
            {{ $message }}
        </div>
        @enderror
    </div>

    <button type="submit">
        Iniciar sesión
    </button>
</form>

</body>
</html>

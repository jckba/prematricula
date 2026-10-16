# Módulo de Autenticación (`/api/v1`)

La autenticación utiliza **Laravel Sanctum** mediante tokens Bearer (`Authorization: Bearer <token>`).

Contrato actualizado y gestión de cuentas: [OpenAPI/API.md](../OpenAPI/API.md) y [OpenAPI 3.1](../OpenAPI/openapi.yaml).

---

## 1. Iniciar Sesión (`POST /login`)

Inicia sesión con `identifier` (correo institucional o código universitario como string) y contraseña. Se mantiene temporalmente `correo_institucional` como alternativa exclusiva a `identifier`: enviar ambos devuelve 422. Retorna el token Sanctum, su vencimiento y la identidad pública.

### Endpoint
`POST /api/v1/login`

### Autenticación
No requerida (Público).

### Headers
```http
Accept: application/json
Content-Type: application/json
```

### Request Body
```json
{
  "identifier": "student@test.edu.pe",
  "password": "password"
}
```

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `identifier` | `string` | Sí, salvo alias antiguo | Correo institucional o código universitario; conserva ceros iniciales (máx. 255 caracteres). |
| `correo_institucional` | `string` (email) | Solo como alternativa | Alias temporal; no puede enviarse junto a `identifier`. |
| `password` | `string` | Sí | Contraseña del usuario (máx. 1024 caracteres). |

### Respuestas

#### `200 OK` (Login exitoso)
```json
{
  "message": "Autenticación correcta.",
  "data": {
    "token": "<token de acceso>",
    "expires_at": "2026-10-16T18:00:00.000000Z",
    "user": {
      "id": 1,
      "correo_institucional": "student@test.edu.pe",
      "rol": "ESTUDIANTE",
      "estado": "ACTIVO",
      "cargo": null
    }
  }
}
```

#### `401 Unauthorized` (Credenciales erróneas)
```json
{
  "message": "Credenciales incorrectas."
}
```

El mismo 401 se devuelve para identificadores inexistentes o ambiguos, usuarios inactivos y estudiantes sin perfil activo, sin emitir tokens.

#### `429 Too Many Requests`

Cinco intentos por identificador/IP y treinta por IP cada minuto. Incluye `Retry-After`. El alias y el login web comparten los límites.

#### `422 Unprocessable Content` (Errores de validación)
```json
{
  "message": "Los datos enviados no son válidos.",
  "errors": {
    "identifier": [
      "The identifier field is required when correo institucional is not present."
    ],
    "password": [
      "The password field is required."
    ]
  }
}
```

---

## 2. Consultar Usuario Autenticado (`GET /me`)

Obtiene los datos del usuario asociado al token actual.

### Endpoint
`GET /api/v1/me`

### Autenticación
Requerida (`auth:sanctum`).

### Headers
```http
Accept: application/json
Authorization: Bearer <TOKEN>
```

### Respuestas

#### `200 OK`
```json
{
  "data": {
    "id": 1,
    "correo_institucional": "student@test.edu.pe",
    "rol": "ESTUDIANTE",
    "estado": "ACTIVO",
    "cargo": null
  }
}
```

#### `401 Unauthorized`
```json
{
  "message": "Unauthenticated."
}
```

---

## 3. Cerrar Sesión (`POST /logout`)

Invalida y elimina el token de acceso actual en el backend.

Los tokens de otros dispositivos permanecen. Reutilizar el token revocado devuelve 401. Los tokens duran ocho horas, incluidos los antiguos sin vencimiento explícito; la limpieza diaria requiere el scheduler de Laravel. Cambios de contraseña, correo o estado desde gestión de cuentas revocan todos los tokens del destinatario.

Todas las rutas API protegidas verifican actividad. Detectar una cuenta desactivada o un perfil estudiantil ausente/inactivo devuelve 403 y revoca sus tokens. Director y secretaria conservan el rol `COORDINADOR`, con cargos `DIRECTOR_ESCUELA` / `SECRETARIA` y los mismos permisos.

### Endpoint
`POST /api/v1/logout`

### Autenticación
Requerida (`auth:sanctum`).

### Headers
```http
Accept: application/json
Authorization: Bearer <TOKEN>
```

### Respuestas

#### `200 OK`
```json
{
  "message": "Sesión cerrada correctamente."
}
```

# Módulo de Autenticación (`/api/v1`)

La autenticación utiliza **Laravel Sanctum** mediante tokens Bearer (`Authorization: Bearer <token>`).

---

## 1. Iniciar Sesión (`POST /login`)

Inicia sesión con correo institucional y contraseña. Retorna el token de acceso Sanctum y la información básica del usuario autenticado.

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
  "correo_institucional": "student@test.edu.pe",
  "password": "password"
}
```

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `correo_institucional` | `string` (email) | Sí | Correo institucional del usuario (máx. 255 caracteres). |
| `password` | `string` | Sí | Contraseña del usuario. |

### Respuestas

#### `200 OK` (Login exitoso)
```json
{
  "message": "Autenticación correcta.",
  "data": {
    "token": "1|AbCdEf1234567890...",
    "user": {
      "id": 1,
      "correo_institucional": "student@test.edu.pe",
      "rol": "ESTUDIANTE"
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

#### `403 Forbidden` (Usuario inactivo)
```json
{
  "message": "El usuario se encuentra inactivo."
}
```

#### `422 Unprocessable Content` (Errores de validación)
```json
{
  "message": "The correo institucional field is required.",
  "errors": {
    "correo_institucional": [
      "The correo institucional field is required."
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
    "estado": "ACTIVO"
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

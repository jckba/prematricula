# Documentación de API Backend para Frontend

La referencia vigente para la entrega final es [FRONTEND_HANDOFF.md](FRONTEND_HANDOFF.md), junto con [API.md](API.md), [ARCHITECTURE.md](ARCHITECTURE.md), [DATABASE.md](DATABASE.md), [BUSINESS_RULES.md](BUSINESS_RULES.md), [IMPORT_FORMATS.md](IMPORT_FORMATS.md) y [DEVELOPMENT.md](DEVELOPMENT.md). Los contratos verificables están en [OpenAPI](../OpenAPI/openapi.yaml) y [Postman](../OpenAPI/Prematricula.postman_collection.json).

Esta documentación está diseñada específicamente para que los desarrolladores frontend puedan integrar de manera rápida, confiable y eficiente todas las funcionalidades del **Sistema de Prematrícula Académica**.

---

## 📌 Índice de Documentación

Contrato actualizado de autenticación, cuentas y catálogo: [API.md](../OpenAPI/API.md), [OpenAPI 3.1](../OpenAPI/openapi.yaml) y [colección Postman](../OpenAPI/Prematricula.postman_collection.json).

1. [Guía de Autenticación (`docs/auth.md`)](./auth.md)
   - Login con credenciales institucionales.
   - Consulta de perfil y rol (`/me`).
   - Cierre de sesión (`/logout`).
   - Manejo de tokens con Laravel Sanctum (`Bearer Token`).

2. [Módulo de Estudiante (`docs/student.md`)](./student.md)
   - Consulta de cursos disponibles y prerrequisitos del periodo activo.
   - Gestión del borrador de prematrícula (creación automática, visualización).
   - Agregar curso con preferencia de turno (`MANANA`, `TARDE`, `SIN_PREFERENCIA`).
   - Actualizar preferencia de turno de un curso seleccionado.
   - Eliminar curso de la prematrícula.
   - Envío final de la prematrícula para revisión.
   - Consulta del historial de solicitudes del estudiante.

3. [Módulo de Coordinador (`docs/coordinator.md`)](./coordinator.md)
   - Listado paginado de prematrículas (con filtro por estado: `BORRADOR`, `ENVIADA`, `APROBADA`, `RECHAZADA`).
   - Detalle completo de una solicitud de prematrícula.
   - Consulta de validación académica, revisiones y PDFs vigentes/históricos.
   - No hay aprobación/rechazo posterior al registro ENVIADA.

4. [Enums, Estados y Tipos TypeScript (`docs/enums-and-types.md`)](./enums-and-types.md)
   - Diccionario de valores válidos para roles, estados de solicitud, turnos y periodos.
   - Definiciones de interfaces TypeScript listas para copiar y pegar en el proyecto frontend.

5. [Malla curricular y catálogo académico (`docs/curriculum.md`)](./curriculum.md)
   - Catálogo, horas académicas semanales y prerrequisitos por código.
   - Plantilla, validación previa, importación transaccional y exportación `.xlsx`.
   - Política de actualización segura y protección del historial.

6. [Períodos, ventanas e historial académico (`docs/academic-process.md`)](./academic-process.md)
   - Calendario independiente de clases y ventanas ordinarias/extraordinarias globales.
   - Resultados oficiales XLSX, auditoría y revalidación de prematrículas.
   - Demanda válida y corrección extraordinaria conservando constancias.

---

## 🌐 Configuración Base y Headers

- **Base URL API:** `http://localhost:8000/api/v1` (o la URL base configurada en el entorno)
- **Prefijo de API:** `/api/v1`

### Headers HTTP requeridos

Para todas las peticiones con cuerpo JSON:
```http
Accept: application/json
Content-Type: application/json
```

Para todas las peticiones autenticadas (rutas protegidas):
```http
Authorization: Bearer <TU_TOKEN_SANCTUM>
```

---

## 🛡️ Formato de Respuestas y Manejo de Errores

El backend estandariza las respuestas HTTP JSON para facilitar el consumo en el frontend:

### 1. Respuesta Exitosa
```json
{
  "message": "Operación realizada con éxito.",
  "data": "{ ... }"
}
```
*(El campo `message` es opcional según el endpoint; recursos directos devuelven la propiedad `data`).*

### 2. Error de Validación (`422 Unprocessable Content`)
Ocurre cuando faltan campos o no cumplen con el formato esperado:
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

### 3. Error de Regla de Negocio / Dominio (`422 Unprocessable Content` o `400 Bad Request`)
Ocurre cuando la petición es válida a nivel de esquema, pero viola una regla de negocio (ej. superar límite de créditos, prerrequisitos no aprobados, periodo fuera de fecha):
```json
{
  "message": "La selección supera el límite máximo de créditos permitido."
}
```

### 4. No Autenticado (`401 Unauthorized`)
Ocurre cuando no se envió el token `Authorization: Bearer <token>` o el token expiró/es inválido:
```json
{
  "message": "Unauthenticated."
}
```

### 5. No Autorizado por Rol / Prohibido (`403 Forbidden`)
Ocurre cuando el usuario autenticado no posee el rol adecuado para la ruta (ej. un estudiante intentando acceder a rutas de coordinador, o un usuario inactivo):
```json
{
  "message": "Acceso no autorizado para este rol."
}
```

### 6. Recurso No Encontrado (`404 Not Found`)
```json
{
  "message": "No query results for model [App\\Models\\...]."
}
```

---

## 🔄 Flujo Completo del Proceso de Prematrícula

```
[Estudiante]
   │
   ├── 1. POST /api/v1/login ─────────────► Obtiene Token Sanctum y Rol "ESTUDIANTE"
   ├── 2. GET  /api/v1/student/pre-enrollment/available-courses ──► Cursos habilitados según historial
   ├── 3. GET  /api/v1/student/pre-enrollment ──────────────────► Obtiene / Crea borrador actual
   ├── 4. POST /api/v1/student/pre-enrollment/courses ──────────► Agrega cursos (valida prerreq. y créditos)
   ├── 5. PATCH/DELETE cursos según preferencia del estudiante
   └── 6. POST /api/v1/student/pre-enrollment/submit ───────────► Registra ENVIADA, revisión y PDF

[Coordinador]
   │
   ├── 1. POST /api/v1/login ─────────────► Obtiene Token Sanctum y Rol "COORDINADOR"
   ├── 2. GET  /api/v1/coordinator/pre-enrollments?estado=ENVIADA ──► Consulta registros enviados
   ├── 3. GET  /api/v1/coordinator/pre-enrollments/{id} ────────► Revisa detalle de cursos y turnos
   └── 4. GET /api/v1/coordinator/pre-enrollments/{id}/receipt ─► Descarga constancia vigente
```

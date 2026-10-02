# Módulo de Estudiante (`/api/v1/student`)

Todas las rutas bajo `/api/v1/student` requieren autenticación y middleware de rol `ESTUDIANTE` con perfil de estudiante activo (`student.profile`).

**Header requerido en todas las peticiones:**
```http
Authorization: Bearer <TOKEN_ESTUDIANTE>
Accept: application/json
```

---

## 1. Obtener Cursos Disponibles para Prematrícula

Consulta el periodo académico abierto para prematrícula, el límite de créditos del estudiante y la lista de cursos a los que puede postular según su historial académico y prerrequisitos.

### Endpoint
`GET /api/v1/student/pre-enrollment/available-courses`

### Respuesta `200 OK`
```json
{
  "data": {
    "periodo": {
      "id": 1,
      "codigo": "2027-I"
    },
    "creditos_maximos": 24,
    "cursos": [
      {
        "id": 10,
        "codigo": "INF301",
        "nombre": "Base de Datos",
        "creditos": 4,
        "ciclo": 3,
        "prerrequisitos": [
          {
            "id": 5,
            "codigo": "INF201",
            "nombre": "Algoritmos y Estructuras de Datos"
          }
        ]
      },
      {
        "id": 11,
        "codigo": "INF302",
        "nombre": "Ingeniería de Software I",
        "creditos": 4,
        "ciclo": 3,
        "prerrequisitos": []
      }
    ]
  }
}
```

---

## 2. Obtener / Crear Borrador de Prematrícula Activa

Obtiene la solicitud de prematrícula del periodo activo. Si el estudiante aún no tiene una solicitud creada para el periodo actual, el backend crea automáticamente una en estado `BORRADOR`.

### Endpoint
`GET /api/v1/student/pre-enrollment`

### Respuesta `200 OK`
```json
{
  "data": {
    "id": 15,
    "estado": "BORRADOR",
    "fecha_envio": null,
    "fecha_revision": null,
    "comentarios_coordinador": null,
    "periodo": {
      "id": 1,
      "codigo": "2027-I"
    },
    "estudiante": {
      "id": 8,
      "codigo_universitario": "1452700722",
      "nombres": "Juan",
      "apellidos": "Pérez Morales"
    },
    "detalles": [
      {
        "id": 42,
        "curso": {
          "id": 10,
          "codigo": "INF301",
          "nombre": "Base de Datos",
          "creditos": 4,
          "ciclo": 3
        },
        "preferencia_turno": "MANANA"
      }
    ],
    "creditos_totales": 4,
    "revisado_por": null
  }
}
```

---

## 3. Agregar Curso a la Prematrícula

Agrega un curso disponible a la prematrícula en borrador.

### Endpoint
`POST /api/v1/student/pre-enrollment/courses`

### Headers
```http
Content-Type: application/json
```

### Request Body
```json
{
  "course_id": 10,
  "preferencia_turno": "MANANA"
}
```

| Campo | Tipo | Requerido | Descripción / Opciones |
|---|---|---|---|
| `course_id` | `integer` | Sí | ID del curso a agregar. |
| `preferencia_turno` | `string` | No (opcional) | Opciones: `MANANA`, `TARDE`, `NOCHE`, `SIN_PREFERENCIA`. Por defecto: `SIN_PREFERENCIA`. |

### Respuesta `201 Created`
```json
{
  "message": "Curso agregado correctamente.",
  "data": {
    "detail_id": 42
  }
}
```

### Posibles Errores de Negocio (`422 Unprocessable Content`):
- `"Solo se pueden modificar solicitudes en estado BORRADOR."`
- `"El curso ya fue agregado a la prematrícula."`
- `"El estudiante no cumple los requisitos para seleccionar este curso."`
- `"La selección supera el límite máximo de créditos permitido."`
- `"El periodo académico no se encuentra en fase de prematrícula."`

---

## 4. Actualizar Preferencia de Turno de un Curso

Permite cambiar la preferencia de turno de un detalle de curso en la prematrícula.

### Endpoint
`PATCH /api/v1/student/pre-enrollment/courses/{detail}/preference`
*(Donde `{detail}` es el `id` del detalle en `detalles[i].id`)*

### Request Body
```json
{
  "preferencia_turno": "NOCHE"
}
```

| Campo | Tipo | Requerido | Opciones permitidas |
|---|---|---|---|
| `preferencia_turno` | `string` | Sí | `MANANA`, `TARDE`, `NOCHE`, `SIN_PREFERENCIA` |

### Respuesta `200 OK`
```json
{
  "message": "Preferencia actualizada correctamente.",
  "data": {
    "id": 42,
    "preferencia_turno": "NOCHE"
  }
}
```

---

## 5. Eliminar Curso de la Prematrícula

Elimina un curso previamente agregado a la prematrícula en borrador.

### Endpoint
`DELETE /api/v1/student/pre-enrollment/courses/{detail}`
*(Donde `{detail}` es el `id` del detalle en `detalles[i].id`)*

### Respuesta `200 OK`
```json
{
  "message": "Curso eliminado correctamente."
}
```

---

## 6. Enviar Prematrícula para Revisión

Valida las reglas finales (créditos totales, requisitos y que contenga al menos 1 curso) y cambia el estado de `BORRADOR` a `ENVIADA`.

### Endpoint
`POST /api/v1/student/pre-enrollment/submit`

### Request Body
No requiere cuerpo (vacío).

### Respuesta `200 OK`
```json
{
  "data": {
    "id": 15,
    "estado": "ENVIADA",
    "fecha_envio": "2026-10-01T22:15:00.000000Z",
    "fecha_revision": null,
    "comentarios_coordinador": null,
    "periodo": {
      "id": 1,
      "codigo": "2027-I"
    },
    "estudiante": {
      "id": 8,
      "codigo_universitario": "1452700722",
      "nombres": "Juan",
      "apellidos": "Pérez Morales"
    },
    "detalles": [
      {
        "id": 42,
        "curso": {
          "id": 10,
          "codigo": "INF301",
          "nombre": "Base de Datos",
          "creditos": 4,
          "ciclo": 3
        },
        "preferencia_turno": "NOCHE"
      }
    ],
    "creditos_totales": 4,
    "revisado_por": null
  }
}
```

### Posibles Errores de Negocio (`422 Unprocessable Content` / `404 Not Found`):
- `"No existe una prematrícula para enviar."` (404)
- `"Solo una solicitud en borrador puede ser enviada."` (422)
- `"Debe seleccionar al menos un curso."` (422)
- `"La solicitud supera el límite máximo de créditos."` (422)

---

## 7. Consultar Historial de Prematrículas

Lista paginada de todas las solicitudes de prematrícula enviadas, aprobadas, rechazadas o en borrador pertenecientes al estudiante.

### Endpoint
`GET /api/v1/student/pre-enrollments?page=1`

### Parámetros Query
| Parámetro | Tipo | Requerido | Descripción |
|---|---|---|---|
| `page` | `integer` | No | Número de página (10 registros por página). |

### Respuesta `200 OK`
```json
{
  "data": [
    {
      "id": 15,
      "estado": "ENVIADA",
      "fecha_envio": "2026-10-01T22:15:00.000000Z",
      "fecha_revision": null,
      "comentarios_coordinador": null,
      "periodo": {
        "id": 1,
        "codigo": "2027-I"
      },
      "estudiante": {
        "id": 8,
        "codigo_universitario": "1452700722",
        "nombres": "Juan",
        "apellidos": "Pérez Morales"
      },
      "detalles": "[ ... ]",
      "creditos_totales": 20,
      "revisado_por": null
    }
  ],
  "links": {
    "first": "http://localhost:8000/api/v1/student/pre-enrollments?page=1",
    "last": "http://localhost:8000/api/v1/student/pre-enrollments?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "per_page": 10,
    "to": 1,
    "total": 1
  }
}
```

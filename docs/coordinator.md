# Módulo de Coordinador (`/api/v1/coordinator`)

Todas las rutas bajo `/api/v1/coordinator` requieren autenticación y middleware de rol `COORDINADOR`.

**Header requerido en todas las peticiones:**
```http
Authorization: Bearer <TOKEN_COORDINADOR>
Accept: application/json
```

---

## 1. Listar Prematrículas

Obtiene una lista paginada de todas las solicitudes de prematrícula registradas en el sistema.

### Endpoint
`GET /api/v1/coordinator/pre-enrollments`

### Parámetros Query
| Parámetro | Tipo | Requerido | Descripción |
|---|---|---|---|
| `estado` | `string` | No (opcional) | Filtra por estado: `BORRADOR`, `ENVIADA`, `APROBADA`, `RECHAZADA`. Por defecto: `ENVIADA`. |
| `page` | `integer` | No (opcional) | Número de página (20 registros por página ordenados descendentemente por fecha de envío). |

### Ejemplo de Petición
`GET /api/v1/coordinator/pre-enrollments?estado=ENVIADA&page=1`

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
  ],
  "links": {
    "first": "http://localhost:8000/api/v1/coordinator/pre-enrollments?page=1",
    "last": "http://localhost:8000/api/v1/coordinator/pre-enrollments?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "per_page": 20,
    "to": 1,
    "total": 1
  }
}
```

---

## 2. Ver Detalle de una Prematrícula

Consulta toda la información de una solicitud de prematrícula específica por su ID.

### Endpoint
`GET /api/v1/coordinator/pre-enrollments/{preEnrollment}`
*(Donde `{preEnrollment}` es el ID numérico de la solicitud de prematrícula)*

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

---

## 3. Aprobar Prematrícula

Aprueba una solicitud de prematrícula en estado `ENVIADA`. Registra al coordinador revisor y la fecha de revisión.

### Endpoint
`POST /api/v1/coordinator/pre-enrollments/{preEnrollment}/approve`

### Request Body
No requiere cuerpo (vacío).

### Respuesta `200 OK`
```json
{
  "data": {
    "id": 15,
    "estado": "APROBADA",
    "fecha_envio": "2026-10-01T22:15:00.000000Z",
    "fecha_revision": "2026-10-01T22:30:00.000000Z",
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
    "creditos_totales": 4,
    "revisado_por": {
      "id": 2,
      "correo_institucional": "coordinator@test.edu.pe"
    }
  }
}
```

### Posibles Errores de Negocio (`422 Unprocessable Content`):
- `"Solo se pueden revisar solicitudes en estado ENVIADA."`

---

## 4. Rechazar Prematrícula

Rechaza una solicitud de prematrícula en estado `ENVIADA`. Requiere obligatoriamente un comentario de justificación para informar al estudiante del motivo.

### Endpoint
`POST /api/v1/coordinator/pre-enrollments/{preEnrollment}/reject`

### Headers
```http
Content-Type: application/json
```

### Request Body
```json
{
  "comentario": "Se rechaza la solicitud debido a cruce de horarios y cupos limitados en el turno noche."
}
```

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `comentario` | `string` | Sí | Motivo detallado del rechazo (máx. 1000 caracteres). |

### Respuesta `200 OK`
```json
{
  "data": {
    "id": 15,
    "estado": "RECHAZADA",
    "fecha_envio": "2026-10-01T22:15:00.000000Z",
    "fecha_revision": "2026-10-01T22:30:00.000000Z",
    "comentarios_coordinador": "Se rechaza la solicitud debido a cruce de horarios y cupos limitados en el turno noche.",
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
    "creditos_totales": 4,
    "revisado_por": {
      "id": 2,
      "correo_institucional": "coordinator@test.edu.pe"
    }
  }
}
```

### Posibles Errores:
- **`422 Unprocessable Content` (Validación):** Si falta el campo `comentario` o supera los 1000 caracteres.
- **`422 Unprocessable Content` (Dominio):** Si la solicitud no está en estado `ENVIADA`.

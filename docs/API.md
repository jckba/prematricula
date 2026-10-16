# API REST v1

Contrato completo: [OpenAPI 3.1](../OpenAPI/openapi.yaml). Colección ejecutable: [Postman](../OpenAPI/Prematricula.postman_collection.json). La prueba ApiContractTest exige coincidencia entre este OpenAPI y todas las rutas reales; no existen endpoints de aprobación/rechazo.

## Convenciones

- Prefijo /api/v1. Bearer Sanctum en Authorization; login público.
- Accept: application/json. Content-Type: application/json para cuerpos JSON.
- Multipart/form-data para XLSX; no establecer manualmente boundary.
- PDF, XLSX y CSV son las únicas respuestas de descarga no JSON.
- Identificadores universitarios/institucionales son strings, incluidos ceros iniciales.
- Horas y notas decimales se devuelven como texto con escala 2.
- Fechas de clases Y-m-d; timestamps ISO 8601. Ventanas de entrada usan hora local institucional.
- Los filtros y orden permitidos están enumerados por operación en OpenAPI; no se interpolan columnas libres.

## JSON y paginación

Un Resource individual usa data; mensajes de acciones pueden incluir message. Los listados paginados de Resources incluyen data, links y meta. Hay contratos heredados explícitos: demanda usa data.cursos/data.paginacion; interesados usa paginador plano; detalles académicos y de carga incluyen paginadores propios. No unificar estas formas en el cliente sin adaptar cada endpoint.

Ejemplo de validación de campos:

```json
{
  "message": "Los datos enviados no son válidos.",
  "errors": {
    "teacher_id": ["La asignación supera el máximo semanal del docente en este período."]
  }
}
```

Los informes de importación usan data.valid, data.errors (fila, columna, message), data.changes, data.summary y huellas. Un XLSX estructuralmente inválido devuelve errors.file.

| HTTP | Significado |
| --- | --- |
| 200 | Consulta, modificación, envío registrado o baja lógica correcta. |
| 201 | Recurso creado; también creación del borrador mediante su alias GET. |
| 401 | Falta token válido, expiró o credenciales incorrectas. |
| 403 | Rol, estado de cuenta o perfil sin permiso. |
| 404 | Recurso inexistente/ajeno; rutas de revisión antiguas no operativas. |
| 405 | Método no disponible. |
| 409 | Conflicto de archivo/huella, evidencia PDF no disponible o período cerrado. |
| 413 | Solicitud mayor que el límite del servidor PHP/proxy. |
| 422 | Validación de campos o reglas de negocio/importación. |
| 429 | Rate limiting; observar Retry-After. |
| 500 | Error interno genérico, sin SQL ni traza. |

Login limita 5 intentos por identificador/IP y 30 por IP cada minuto; el identificador de la clave de rate limiting se almacena como hash. En producción el proxy puede añadir límites generales. CORS permite orígenes configurados y expone Content-Disposition y Retry-After; el cliente Bearer no requiere cookies cross-origin.

## Catálogo de operaciones

El siguiente catálogo se deriva de la especificación cotejada contra Laravel. Los bodies, parámetros, enums, respuestas de archivo y esquemas completos están en OpenAPI.

| Metodo | Ruta | Acceso | Operacion |
| --- | --- | --- | --- |
| POST | `/api/v1/login` | Publico | Login con correo institucional o código universitario |
| GET | `/api/v1/me` | Autenticado activo | Consultar identidad autenticada |
| POST | `/api/v1/logout` | Autenticado activo | Revocar únicamente el token actual |
| GET | `/api/v1/courses` | Autenticado activo | Catálogo paginado; estudiantes solo ven cursos activos |
| GET | `/api/v1/courses/{course}` | Autenticado activo | Curso por ID y sus prerrequisitos |
| PATCH | `/api/v1/coordinator/courses/{course}` | COORDINADOR | Modificar datos permitidos; exclusivo para coordinadores |
| GET | `/api/v1/coordinator/curriculum/template` | COORDINADOR | Descargar plantilla XLSX; exclusivo para coordinadores |
| POST | `/api/v1/coordinator/curriculum/validate` | COORDINADOR | Validar XLSX sin persistir; exclusivo para coordinadores |
| POST | `/api/v1/coordinator/curriculum/import` | COORDINADOR | Confirmar importación transaccional; exclusivo para coordinadores |
| GET | `/api/v1/coordinator/curriculum/export` | COORDINADOR | Exportar malla XLSX; exclusivo para coordinadores |
| GET | `/api/v1/academic-periods` | Autenticado activo | Consultar períodos con ventanas; usuario activo |
| GET | `/api/v1/academic-periods/{period}` | Autenticado activo | Consultar un período por ID |
| GET | `/api/v1/academic-periods/{period}/windows` | Autenticado activo | Ventanas históricas del período; usuario activo |
| POST | `/api/v1/coordinator/academic-periods` | COORDINADOR | Crear período; coordinador |
| PATCH | `/api/v1/coordinator/academic-periods/{period}` | COORDINADOR | Administrar período; coordinador |
| DELETE | `/api/v1/coordinator/academic-periods/{period}` | COORDINADOR | Eliminar solo un período sin referencias; coordinador |
| POST | `/api/v1/coordinator/academic-periods/{period}/windows` | COORDINADOR | Crear ordinaria o reapertura extraordinaria GLOBAL; coordinador |
| GET | `/api/v1/coordinator/students` | COORDINADOR | Buscar estudiantes con identidad académica mínima |
| GET | `/api/v1/coordinator/students/{student}/academic` | COORDINADOR | Consultar situación académica por código universitario exacto |
| GET | `/api/v1/coordinator/academic-periods/{period}/demand` | COORDINADOR | Demanda registrada y validada por curso |
| GET | `/api/v1/coordinator/academic-periods/{period}/summary` | COORDINADOR | Resumen de alumnos por período y demanda por ciclo |
| GET | `/api/v1/coordinator/academic-periods/{period}/demand/export` | COORDINADOR | Exportar demanda a CSV |
| GET | `/api/v1/coordinator/academic-periods/{period}/demand/courses/{course}/students` | COORDINADOR | Alumnos interesados de la revisión vigente por curso y período |
| GET | `/api/v1/student/academic-history` | ESTUDIANTE | Historial propio, sin posibilidad de escritura |
| GET | `/api/v1/coordinator/academic-history` | COORDINADOR | Consultar resultados oficiales; coordinador |
| GET | `/api/v1/coordinator/academic-history/template` | COORDINADOR | Plantilla XLSX de resultados; coordinador |
| POST | `/api/v1/coordinator/academic-history/validate` | COORDINADOR | Validación previa XLSX sin persistencia; coordinador |
| POST | `/api/v1/coordinator/academic-history/import` | COORDINADOR | Confirmación transaccional y auditada; coordinador |
| GET | `/api/v1/coordinator/academic-history/imports` | COORDINADOR | Lotes de auditoría; coordinador |
| GET | `/api/v1/coordinator/academic-history/imports/{import}` | COORDINADOR | Auditoría con cambios de nota/estado antes y después; coordinador |
| POST | `/api/v1/student/pre-enrollments/{preEnrollment}/corrections` | ESTUDIANTE | Corregir solicitud propia durante una extraordinaria global |
| GET | `/api/v1/student/pre-enrollment/available-courses` | ESTUDIANTE | Elegibilidad académica sin límite por número de ciclo |
| GET | `/api/v1/student/pre-enrollment` | ESTUDIANTE | Consultar solicitud actual o por período propio |
| POST | `/api/v1/student/pre-enrollment` | ESTUDIANTE | Crear/obtener una única solicitud lógica del período abierto |
| GET | `/api/v1/student/pre-enrollments` | ESTUDIANTE | Historial propio de solicitudes y revisiones vigentes |
| GET | `/api/v1/student/pre-enrollments/{preEnrollment}` | ESTUDIANTE | Consultar solicitud propia, incluso con ventana cerrada |
| POST | `/api/v1/student/pre-enrollment/courses` | ESTUDIANTE | Agregar curso elegible sin duplicados ni exceso de créditos |
| DELETE | `/api/v1/student/pre-enrollment/courses/{detail}` | ESTUDIANTE | Quitar curso del borrador propio durante ventana activa |
| PATCH | `/api/v1/student/pre-enrollment/courses/{detail}/preference` | ESTUDIANTE | Modificar preferencia del borrador propio durante ventana |
| POST | `/api/v1/student/pre-enrollment/submit` | ESTUDIANTE | Registrar envío/reenvío, revisión inmutable y PDF; sin aprobación |
| GET | `/api/v1/student/pre-enrollments/{preEnrollment}/revisions` | ESTUDIANTE | Historial inmutable de envíos; propietario o coordinador autorizado |
| GET | `/api/v1/student/pre-enrollments/{preEnrollment}/receipt` | ESTUDIANTE | PDF A4 de la revisión vigente, sin reconstruir relaciones actuales |
| GET | `/api/v1/student/pre-enrollments/{preEnrollment}/revisions/{revision}/receipt` | ESTUDIANTE | PDF histórico original; revisión debe pertenecer a la solicitud |
| GET | `/api/v1/coordinator/pre-enrollments/{preEnrollment}/revisions` | COORDINADOR | Historial inmutable de envíos; propietario o coordinador autorizado |
| GET | `/api/v1/coordinator/pre-enrollments/{preEnrollment}/receipt` | COORDINADOR | PDF A4 de la revisión vigente, sin reconstruir relaciones actuales |
| GET | `/api/v1/coordinator/pre-enrollments/{preEnrollment}/revisions/{revision}/receipt` | COORDINADOR | PDF histórico original; revisión debe pertenecer a la solicitud |
| GET | `/api/v1/coordinator/pre-enrollments` | COORDINADOR | Consultar registros, no una bandeja de aprobación |
| GET | `/api/v1/coordinator/pre-enrollments/{preEnrollment}` | COORDINADOR | Consultar registros, no una bandeja de aprobación |
| GET | `/api/v1/coordinator/teachers` | COORDINADOR | Consultar docentes, búsqueda literal y paginación |
| POST | `/api/v1/coordinator/teachers` | COORDINADOR | Registrar docente sin crear cuenta de acceso |
| GET | `/api/v1/coordinator/teachers/{teacher}` | COORDINADOR | Consultar docente |
| PATCH | `/api/v1/coordinator/teachers/{teacher}` | COORDINADOR | Actualizar perfil o desactivar docente |
| PUT | `/api/v1/coordinator/teachers/{teacher}` | COORDINADOR | Actualizar perfil o desactivar docente |
| DELETE | `/api/v1/coordinator/teachers/{teacher}` | COORDINADOR | Desactivar docente conservando su ficha y asignaciones |
| GET | `/api/v1/coordinator/teaching-assignments` | COORDINADOR | Consultar asignaciones vigentes |
| POST | `/api/v1/coordinator/teaching-assignments` | COORDINADOR | Distribuir horas semanales entre docentes |
| GET | `/api/v1/coordinator/teaching-assignments/{assignment}` | COORDINADOR | Consultar asignación vigente |
| PATCH | `/api/v1/coordinator/teaching-assignments/{assignment}` | COORDINADOR | Modificar horas asignadas |
| PUT | `/api/v1/coordinator/teaching-assignments/{assignment}` | COORDINADOR | Modificar horas asignadas |
| DELETE | `/api/v1/coordinator/teaching-assignments/{assignment}` | COORDINADOR | Dar de baja lógica una asignación de período abierto |
| GET | `/api/v1/coordinator/teaching-assignments/{assignment}/history` | COORDINADOR | Auditoría paginada, incluidas asignaciones dadas de baja |
| GET | `/api/v1/coordinator/academic-periods/{period}/teaching-load` | COORDINADOR | Resumen completo de horas, cobertura y excesos del período |
| GET | `/api/v1/coordinator/academic-periods/{period}/teaching-load/teachers` | COORDINADOR | Carga semanal por docente |
| GET | `/api/v1/coordinator/academic-periods/{period}/teaching-load/courses` | COORDINADOR | Cobertura docente, pendientes y demanda estimada por curso |
| GET | `/api/v1/coordinator/academic-periods/{period}/teaching-load/teachers/{teacher}` | COORDINADOR | Carga y cursos asignados a un docente por período |
| GET | `/api/v1/coordinator/academic-periods/{period}/teaching-load/courses/{course}` | COORDINADOR | Docentes asignados y horas pendientes de un curso por período |
| GET | `/api/v1/coordinator/accounts` | COORDINADOR | Listar cuentas estudiantiles |
| POST | `/api/v1/coordinator/accounts` | COORDINADOR | Crear cuenta y perfil estudiantil |
| GET | `/api/v1/coordinator/accounts/{account}` | COORDINADOR | Consultar identidad publica de la cuenta |
| PATCH | `/api/v1/coordinator/accounts/{account}` | COORDINADOR | Actualizar credenciales o estado |
| PUT | `/api/v1/coordinator/accounts/{account}` | COORDINADOR | Actualizar credenciales o estado |

## Descargas

Para una constancia, solicitar la ruta de receipt y leer Content-Type antes de convertir a blob. En 200 se recibe application/pdf y Content-Disposition con nombre; Cache-Control es private, no-store. Las revisiones sin binario histórico verificable responden 409 JSON.

Las plantillas y exportación de malla usan XLSX genuino. La demanda exporta CSV UTF-8 con BOM y advertencia de estimación, sin identidades de alumnos; neutraliza textos que podrían ejecutar fórmulas. La exportación respeta filtros y orden, exportando todas las filas sin limitarse a la página actual.

## Operaciones especiales

El período estudiantil se determina en backend; no enviar student_id ni academic_period_id para elegir propietario/destino de una prematrícula. POST de curso devuelve data.detail_id. Las asignaciones docentes conservan vínculos: PATCH/PUT solo actualizan horas; para otro docente/curso/período se da de baja y se crea otra en planificación.

Los estados APROBADA/RECHAZADA solo permiten lectura histórica. La validación académica de una revisión se interpreta separadamente del estado operativo ENVIADA y nunca como aprobación de coordinación. Ver [BUSINESS_RULES.md](BUSINESS_RULES.md).

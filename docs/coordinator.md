# Consultas de prematrícula para coordinadores

La prematrícula **no necesita aprobación individual**. ENVIADA significa registro satisfactorio. Coordinadores activos, tanto DIRECTOR_ESCUELA como SECRETARIA, consultan solicitudes, validación académica, revisiones y constancias; no aprueban ni rechazan nuevos envíos.

| Método | Ruta (prefijo /api/v1) | Uso |
| --- | --- | --- |
| GET | /coordinator/pre-enrollments | Listado paginado |
| GET | /coordinator/pre-enrollments/{preEnrollment} | Detalle actual y revisión vigente |
| GET | /coordinator/pre-enrollments/{preEnrollment}/revisions | Historial de envíos |
| GET | /coordinator/pre-enrollments/{preEnrollment}/receipt | Constancia PDF vigente |
| GET | /coordinator/pre-enrollments/{preEnrollment}/revisions/{revision}/receipt | PDF histórico |
| GET | /coordinator/academic-periods/{period}/demand | Demanda académicamente válida |

El listado admite `estado` y pagina de 20 en 20. Por defecto incluye ENVIADA y borradores de corrección con una revisión vigente. No es una bandeja de aprobaciones pendientes. Los estados APROBADA/RECHAZADA siguen siendo filtros válidos únicamente para consultar historia.

Durante corrección, el recurso tiene estado BORRADOR y una `revision_vigente` enviada que no cambia hasta el reenvío exitoso. `validacion_academica` refleja la selección enviada vigente; los detalles principales pueden mostrar cambios del borrador. Los cursos REQUIERE_AJUSTE o PENDIENTE_RESULTADOS no se cuentan como demanda válida. Iniciar o fallar una corrección no reemplaza el último registro.

Las antiguas rutas POST `.../approve` y `.../reject` se retiraron y devuelven 404. No se migran ni eliminan decisiones históricas, revisores o comentarios anteriores.

Ver [flujo estudiantil, revisiones y PDF](student.md), [ventanas e historial](academic-process.md) y [cuentas autorizadas](../OpenAPI/API.md).

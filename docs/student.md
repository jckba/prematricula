# Prematrícula del Estudiante — API v1

La prematrícula es un registro, no una solicitud de aprobación individual. Para nuevas solicitudes: **BORRADOR → ENVIADA**. ENVIADA significa que el backend registró satisfactoriamente el envío y su constancia. No hay aprobación/rechazo posterior.

Todas las operaciones requieren Sanctum, cuenta y perfil estudiantil activos. Solo se accede a recursos propios; un ID ajeno devuelve 404. Ningún cuerpo puede elegir otro estudiante o modificar fechas de ventanas.

## Endpoints

| Método | Ruta (prefijo /api/v1) | Resultado |
| --- | --- | --- |
| GET | /student/pre-enrollment/available-courses | Cursos elegibles para el período objetivo; créditos máximos |
| GET | /student/pre-enrollment | Consulta actual; compatibilidad: crea borrador si no existe y hay ventana |
| POST | /student/pre-enrollment | Crear/obtener la única solicitud lógica del período abierto |
| GET | /student/pre-enrollments | Solicitudes propias, paginadas |
| GET | /student/pre-enrollments/{preEnrollment} | Consulta propia por ID, también fuera de ventana |
| POST | /student/pre-enrollment/courses | Agregar curso al borrador |
| DELETE | /student/pre-enrollment/courses/{detail} | Quitar del borrador |
| PATCH | /student/pre-enrollment/courses/{detail}/preference | Cambiar preferencia |
| POST | /student/pre-enrollment/submit | Enviar o reenviar borrador |
| POST | /student/pre-enrollments/{preEnrollment}/corrections | Iniciar/retomar corrección extraordinaria explícita |
| GET | /student/pre-enrollments/{preEnrollment}/revisions | Revisiones enviadas, paginadas |
| GET | /student/pre-enrollments/{preEnrollment}/receipt | PDF de la revisión vigente |
| GET | /student/pre-enrollments/{preEnrollment}/revisions/{revision}/receipt | PDF histórico |

La consulta singular acepta `academic_period_id` como filtro de lectura propio. Sin filtro se consulta el período con ventana activa; si no lo hay, se devuelve la última solicitud propia existente. Nunca crea borradores fuera de ventana. POST devuelve 201 al crear y 200 al reutilizar. El GET de compatibilidad también puede devolver 201 si crea; se recomienda POST explícito para nuevos clientes.

## Cursos, créditos y preferencia

Se reutilizan `GetAvailableCourses`, `AcademicEligibility` y los casos de uso de selección/envío. Se admiten cursos de cualquier ciclo, incluidos adelantados: el número de ciclo nunca es una restricción por sí mismo.

Para cada prerrequisito, APROBADO lo satisface; EN_CURSO lo satisface provisionalmente y registra PENDIENTE_RESULTADOS. DESAPROBADO, RETIRADO o ausencia de resultado no lo satisfacen. Se consideran intentos de períodos anteriores al destino: un APROBADO prevalece y, si no hay uno, se utiliza el intento anterior más reciente. No se ofrecen nuevamente cursos ya aprobados o en curso. Ver [reglas oficiales y revalidación](academic-process.md).

Agregar:

```json
{"course_id": 10, "preferencia_turno": "MANANA"}
```

Preferencias válidas: `MANANA`, `TARDE`, `SIN_PREFERENCIA` (predeterminada). NOCHE se rechaza con 422. PATCH requiere `preferencia_turno`. No se generan horarios ni se asignan secciones.

Se impiden duplicados mediante validación e índice único, y se comprueba el límite individual de créditos. Todas las escrituras validan la ventana real dentro de una transacción: crear borrador, agregar, quitar, preferencia, corregir y enviar. El envío exige al menos un curso y vuelve a comprobar todos los prerrequisitos, actividad y créditos.

## Solicitud actual, revisión vigente y borrador de corrección

- **Solicitud lógica:** una fila por estudiante/período, con índice único. Su ID no cambia con las correcciones.
- **Revisión vigente:** último envío exitoso. Tiene número consecutivo, fecha propia, ventana, código único de constancia, snapshot completo y PDF almacenado. Las revisiones anteriores son inmutables, también mediante trigger PostgreSQL.
- **Borrador:** selección editable de `pre_enrollment_details`. Inicialmente no tiene revisión vigente. Una corrección coloca exclusivamente esa solicitud en BORRADOR, conservando intacta su revisión vigente.
- **Corrección en progreso:** BORRADOR con `correction_window_id` y revisión vigente anterior. La marca es contexto de edición, nunca un permiso individual de reapertura.

Campos agregados a los recursos:

```json
{
  "estado": "BORRADOR",
  "correccion_en_progreso": true,
  "current_revision_id": 15,
  "revision_vigente": {
    "id": 15,
    "revision_number": 1,
    "submitted_at": "2026-10-16T15:00:00.000000Z",
    "window_id": 2,
    "receipt_code": "PRE-<uuid>",
    "receipt_available": true,
    "schema_version": 2,
    "snapshot": {"estado":"ENVIADA","detalles":[]}
  },
  "validacion_academica": {
    "estado":"VALIDA",
    "revision_id":15,
    "cursos":[],
    "evaluated_at":"2026-10-16T15:00:00.000000Z"
  }
}
```

El ejemplo abrevia el snapshot. `detalles` y `creditos_totales` del recurso principal describen el borrador/selección actual. Durante una corrección pueden diferir de `revision_vigente.snapshot`; esta última es el registro oficialmente enviado. `fecha_envio` identifica el último envío exitoso, no el inicio de la corrección. Los campos antiguos de revisor/comentarios solo conservan historia, no indican un paso pendiente.

## Corrección y reenvío

Cualquier titular con una solicitud ENVIADA puede iniciar corrección durante una EXTRAORDINARIA activa, aunque su validación sea VALIDA. No hace falta autorización individual ni un resultado REQUIERE_AJUSTE. Una reapertura no cambia masivamente solicitudes a BORRADOR; también permite enviar borradores nunca enviados y crear solicitudes faltantes.

1. POST `.../{preEnrollment}/corrections`, sin cuerpo.
2. Editar el borrador usando las rutas existentes.
3. POST `/student/pre-enrollment/submit` para reenviar.

Repetir el inicio de corrección es idempotente: no duplica revisiones. Si vence la ventana, el borrador queda guardado pero no puede modificarse/enviarse; se puede retomar explícitamente durante otra extraordinaria. Una ventana ordinaria no permite editar un borrador de corrección pendiente.

Un reenvío exitoso crea revisión N+1, otra fecha y otra constancia; actualiza el puntero vigente y deja ENVIADA. Si falla por reglas académicas, créditos, PDF o base de datos, la revisión vigente y su constancia siguen intactas, no se consume número y el borrador sigue disponible. No se alteran las fechas de ventanas.

La selección vigente continúa siendo la última enviada hasta que el reenvío se complete. La validación académica y la demanda se calculan sobre esa revisión, no sobre cambios todavía no enviados del borrador. Solo selecciones VALIDA cuentan como demanda válida; PENDIENTE_RESULTADOS y REQUIERE_AJUSTE se informan por separado.

## Constancias PDF

Generación local y gratuita con Dompdf 3.1.6; no se usan servicios externos, imágenes remotas, HTML del usuario, firmas ni sellos inventados. Se usa un lienzo PDF de una página A4, fuente Unicode incluida y ajuste de tipografía/filas según el contenido, sin truncar nombres ni omitir cursos y sin imponer un máximo de cursos distinto al límite académico.

Configurar antes de nuevos envíos:

```dotenv
INSTITUTION_UNIVERSITY="Nombre oficial de la universidad"
INSTITUTION_SCHOOL="Nombre oficial de la escuela"
ACADEMIC_TIMEZONE=America/Lima
```

Los valores predeterminados son los rótulos genéricos Universidad/Escuela. El snapshot captura institución, estudiante, código universitario, período, fecha/zona de envío, número de revisión, ventana y todos los cursos con código, nombre, créditos y preferencia, más el total. El PDF indica registro satisfactorio y que **no constituye matrícula definitiva**.

La transacción persiste snapshot, PDF en base64 privado y SHA-256 antes de publicar la revisión vigente. La API JSON no expone el base64. Las descargas verifican el digest y devuelven exactamente los bytes originales, con `application/pdf`, disposición attachment y caché privada no-store. Cambiar nombres, códigos, créditos o configuración después no regenera ni modifica PDFs históricos.

Coordinadores activos tienen rutas de consulta equivalentes bajo `/coordinator/pre-enrollments/{preEnrollment}/revisions`, `/receipt` y `/revisions/{revision}/receipt`. Ambos cargos comparten permisos. Un ID de revisión que no pertenece a la solicitud devuelve 404.

## Compatibilidad histórica y errores

La migración es aditiva. Conserva APROBADA/RECHAZADA, revisores, comentarios y snapshots anteriores como evidencia histórica; no genera nuevas solicitudes en esos estados. Las URI anteriores `.../approve` y `.../reject` se retiraron tras revisar consumidores y tests; responden 404 y no modifican datos.

Los snapshots antiguos incompletos se conservan con `schema_version: 1`; no se inventan nombres/fechas originales ni PDFs. Se enumeran los antiguos envíos y se enlaza el último cuando hay evidencia. Para una solicitud antigua sin snapshot, iniciar corrección captura el estado conocido entonces como LEGACY_CAPTURE, explícitamente sin certificación histórica. Descargar una revisión sin PDF original devuelve 409. Los nuevos envíos siempre tienen snapshot completo y PDF.

Estados HTTP: 401 sin token; 403 por rol o cuenta deshabilitada; 404 recurso ajeno/inexistente o ruta retirada; 409 constancia histórica no disponible/verificable; 422 reglas académicas, estado, créditos o ventana inválidos.

## Instalación y pruebas

Tras incorporar la dependencia PDF autorizada: `composer install`, `php artisan migrate --no-interaction` y, para solicitudes previas, `php artisan academic:revalidate --no-interaction`. No usar migrate:fresh en bases operativas. Las pruebas concurrentes usan exclusivamente `prematricula_test`, con workers PHP independientes; las pruebas PDF requieren herramientas gratuitas `pdfinfo` y `pdftotext` (Poppler) en el entorno de tests, no en producción.

```bash
php artisan test --compact tests/Feature/StudentPreEnrollmentWorkflowTest.php tests/Feature/PreEnrollmentReceiptTest.php tests/Feature/PreEnrollmentConcurrencyTest.php
```

[Postman](../OpenAPI/Prematricula.postman_collection.json) y [OpenAPI](../OpenAPI/openapi.yaml) incluyen el flujo definitivo.

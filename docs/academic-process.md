# Períodos, ventanas e historial académico

Backend Laravel 13, Sanctum y `/api/v1`. No añade permisos individuales de reapertura ni aprobación de prematrículas. Las rutas operativas se conservan salvo aprobar/rechazar, retiradas en el [flujo definitivo](student.md). Ambos cargos de coordinador tienen los mismos permisos.

## Períodos académicos

| Método | Ruta | Acceso |
| --- | --- | --- |
| GET | `/api/v1/academic-periods` | Usuario habilitado; paginación de 20 |
| GET | `/api/v1/academic-periods/{period}` | Usuario habilitado |
| POST | `/api/v1/coordinator/academic-periods` | Coordinador |
| PATCH | `/api/v1/coordinator/academic-periods/{period}` | Coordinador |
| DELETE | `/api/v1/coordinator/academic-periods/{period}` | Coordinador; solo sin referencias |
| GET | `/api/v1/academic-periods/{period}/windows` | Usuario habilitado |
| POST | `/api/v1/coordinator/academic-periods/{period}/windows` | Coordinador |
| GET | `/api/v1/coordinator/academic-periods/{period}/demand` | Coordinador |

Creación de período (201), edición parcial (200):

```json
{"codigo":"2027-I","fecha_inicio":"2027-04-01","fecha_fin":"2027-08-01","estado":"PREMATRICULA"}
```

`codigo` es texto único hasta 20 caracteres, con letras ASCII, números, punto, guion o guion bajo; inicia con letra o número. Las fechas de clases usan `YYYY-MM-DD`, con fin igual o posterior al inicio. Estados existentes: `PLANIFICACION`, `PREMATRICULA`, `EN_CURSO`, `CERRADO`. No se aceptan `created_by`, `updated_by` ni las fechas heredadas de prematrícula en estos cuerpos. Creador y último modificador se obtienen del coordinador autenticado, con timestamps. Los recursos devuelven estos campos y `ventanas`.

Las fechas de clases son independientes de las ventanas: una reapertura no desplaza clases y no exige que ambas franjas coincidan. Un período con historial o solicitudes conserva código y fechas de clases, para no reinterpretar resultados históricos. Puede cambiar de estado o recibir ventanas cuando esté en planificación/prematrícula. El DELETE devuelve 409 si hay ventanas, historial o solicitudes; sus claves foráneas también impiden borrar registros referenciados.

## Ventanas globales e inmutables

Ejemplo de creación (201):

```json
{
  "tipo":"ORDINARIA",
  "fecha_inicio":"2026-10-16 09:00:00",
  "fecha_fin":"2026-10-18 18:00:00",
  "motivo":"Apertura del proceso ordinario"
}
```

Tipos `ORDINARIA` y `EXTRAORDINARIA`. Las fechas se envían exactamente como `YYYY-MM-DD HH:mm:ss` en la zona institucional `config('academic.timezone')`, configurada con `ACADEMIC_TIMEZONE` (predeterminado `America/Lima`). Se convierten a instantes UTC y se almacenan como `timestamp with time zone`. La respuesta usa ISO 8601 UTC y expone la zona utilizada en el registro. El reloj de la aplicación y los timestamps anteriores permanecen en UTC. No cambiar la zona para reinterpretar ventanas ya guardadas.

El intervalo activo es **[inicio, fin)**: incluye el inicio, excluye el fin. Se requiere fin posterior al inicio. Cada período admite una sola ordinaria; las extraordinarias necesitan una ordinaria previa y deben comenzar al terminar o después de todas las ventanas registradas. No se permiten solapamientos ni insertar reaperturas fuera de secuencia. Un bloqueo transaccional PostgreSQL serializa creación de ventanas y cambios del proceso. Las fechas locales inexistentes por cambios horarios se rechazan.

Para reabrir se crea un nuevo registro con tipo `EXTRAORDINARIA`, motivo obligatorio, coordinador autor y timestamps. No hay PATCH ni DELETE de ventanas. No se actualizan las fechas anteriores ni se crean listas de estudiantes autorizados. La extraordinaria habilita a **todos** los estudiantes con cuenta y perfil activos, sujetos a las reglas académicas normales.

`ActivePreEnrollmentWindow` es la fuente central de apertura; `CurrentPreEnrollmentPeriod` y `PreEnrollmentPeriodValidator` la utilizan. Una ventana solo habilita el proceso si el período está en `PREMATRICULA`. El destino estudiantil es el siguiente período conocido de planificación/prematrícula, por fecha de inicio de clases posterior al último período `EN_CURSO`. Debe tener una ventana activa: no se salta a otro período posterior si el siguiente está cerrado para solicitudes. Si no existe período en curso, se usa el único destino abierto. Cero destinos o varios destinos simultáneos producen 422, nunca una elección silenciosa por ID/código. Los cuerpos estudiantiles no eligen el período ni sus fechas. Un ciclo curricular alto no cambia la elegibilidad.

### Migración de fechas heredadas

La migración incremental conserva las columnas `academic_periods.fecha_inicio_prematricula` y `fecha_fin_prematricula` sin modificarlas. Para cada par completo y válido crea una ordinaria con `origen: MIGRACION`, motivo explícito y autor nulo porque ese dato no existía. Pares nulos, incompletos o invertidos no abren ventanas; deben revisarse y configurarse por coordinador.

`ACADEMIC_LEGACY_TIMEZONE` indica cómo interpretar los timestamps antiguos sin zona; predeterminado `UTC`, coherente con la configuración anterior del proyecto. Configurarlo **antes de migrar** si la instalación guardó horas locales. La nueva API no escribe esos campos. Se mantiene un puente temporal para seeders/consumidores Eloquent internos: al crear un período con ambos campos heredados válidos se genera su ordinaria; editar los campos heredados después no modifica ni reabre ventanas. Las ventanas, no las columnas antiguas, son la fuente de verdad.

## Importación del historial académico

| Método | Ruta | Acceso |
| --- | --- | --- |
| GET | `/api/v1/student/academic-history` | Estudiante; únicamente su propio historial |
| GET | `/api/v1/coordinator/academic-history` | Coordinador; filtros `student_id`, `academic_period_id` |
| GET | `/api/v1/coordinator/academic-history/template` | Coordinador; plantilla XLSX |
| POST | `/api/v1/coordinator/academic-history/validate` | Coordinador; validación sin persistencia |
| POST | `/api/v1/coordinator/academic-history/import` | Coordinador; confirmación transaccional |
| GET | `/api/v1/coordinator/academic-history/imports` | Coordinador; auditoría paginada |
| GET | `/api/v1/coordinator/academic-history/imports/{import}` | Coordinador; cambios antes/después |

El estudiante no tiene operaciones de escritura de historial. En su consulta, un `student_id` ajeno no cambia el propietario autenticado. Listados de resultados paginan de 20 en 20 y retornan `nota_final` como string decimal o null.

Columnas obligatorias en la primera fila, una vez cada una y en cualquier orden:

| Columna | Reglas |
| --- | --- |
| `codigo_universitario` | Texto, hasta 30 caracteres; alumno existente. Conservar ceros iniciales. |
| `codigo_curso` | Texto, hasta 30 caracteres; curso existente. |
| `codigo_periodo` | Texto, hasta 20 caracteres; período existente. |
| `nota_final` | Número con hasta dos decimales o celda vacía según estado. |
| `estado` | `APROBADO`, `DESAPROBADO`, `RETIRADO`, `EN_CURSO`. |

`APROBADO` y `DESAPROBADO` requieren nota. `EN_CURSO` permite nota nula o una nota numérica provisional. `RETIRADO` exige nota nula, no cero. No se infiere el estado desde la nota: una nota baja con estado oficial APROBADO sigue siendo APROBADO y viceversa. No hay nota mínima aprobatoria implementada. El rango técnico predeterminado es 0–999.99, límite no negativo del `decimal(5,2)` existente, **no una escala institucional de calificación**. `academic.grade_min/grade_max` pueden restringirlo cuando se confirme la escala oficial; no determinan aprobación. No se redondean notas con exceso de decimales.

Se reutiliza el lector seguro de XLSX del catálogo y sus límites: archivo 5 MiB, 5000 filas físicas de datos, una hoja visible, 256 entradas y 20 MiB descomprimidos. Se rechazan fórmulas incluso con resultado almacenado, errores de celda, macros, vínculos externos, entidades, contenedores corruptos, archivos vacíos y encabezados sin resultados. Formatear los tres códigos como texto en Excel.

Flujo:

1. POST `validate`, multipart con campo `file`. Respuesta 200 válida o 422 con errores. No guarda resultados, auditorías ni archivos persistentes.
2. Revisar `data.valid`, `summary`, `changes` y `errors`. Conservar `sha256` y `history_fingerprint`.
3. POST `import`, multipart con el **mismo** `file`, `sha256`, `history_fingerprint` y `motivo` obligatorio (hasta 2000 caracteres). Se vuelven a validar todos los datos bajo bloqueo.

Ejemplo de informe válido:

```json
{
  "data": {
    "valid":true,
    "sha256":"<64 caracteres hexadecimales>",
    "history_fingerprint":"<64 caracteres hexadecimales>",
    "summary":{"created":0,"updated":1,"unchanged":0},
    "changes":[{
      "row":2,"codigo_universitario":"000123","codigo_curso":"INF101","codigo_periodo":"2026-II","action":"update",
      "before":{"nota_final":null,"estado":"EN_CURSO"},
      "after":{"nota_final":"15.50","estado":"APROBADO"}
    }],
    "errors":[]
  }
}
```

Los errores de datos se devuelven en `data.errors`, cada uno con `row` físico de Excel, `column` y `message`. Ejemplo: `{"row":3,"column":"codigo_curso","message":"No existe el registro indicado por codigo_curso."}`. Errores estructurales o de carga sin celda fiable usan `{message, errors: {file: [...]}}`. El SHA-256 se devuelve en validación aunque haya errores de contenido. La confirmación exitosa añade `import_id`; su huella sigue identificando el estado base de la operación.

Un alumno/curso/período identifica un único resultado, reforzado por el índice único existente. Duplicar esa combinación dentro del Excel se rechaza; importar una combinación existente actualiza nota/estado y audita antes/después. No se eliminan resultados omitidos. Repetir datos idénticos no duplica resultados ni cambios de notas, pero registra cada confirmación como lote de auditoría, incluso sin cambios.

Archivo cambiado o historial modificado con cambios pendientes: 409 y nueva validación. Un reenvío ya aplicado es un no-op aunque use la huella anterior. No hay aplicación parcial: resultados, lotes, cambios y revalidación pertenecen a una sola transacción, con rollback conjunto ante fallos. Las auditorías se conservan en `academic_history_imports` y `academic_history_changes`, con autor, motivo, SHA-256, timestamps y valores anteriores/nuevos. No se registran notas ni contenido de archivos en logs.

## Revalidación y demanda

Cada solicitud mantiene su estado operativo anterior (por ejemplo ENVIADA). Su validación académica vive por separado en `pre_enrollment_validations` y se expone como `validacion_academica` en los recursos estudiantiles y de coordinador:

```json
{
  "estado":"ENVIADA",
  "correccion_en_progreso":false,
  "validacion_academica":{
    "estado":"REQUIERE_AJUSTE",
    "evaluated_at":"2026-10-16T15:00:00.000000Z",
    "cursos":[{
      "course_id":2,"codigo":"INF201","estado":"REQUIERE_AJUSTE","motivo":null,
      "prerrequisitos":[{"id":1,"codigo":"INF101","estado_historial":"DESAPROBADO","estado_validacion":"REQUIERE_AJUSTE"}]
    }]
  }
}
```

Reglas deterministas:

- Solo se usan resultados de períodos cuya fecha de inicio es anterior a la del destino. No se toman resultados futuros ni se decide por ID o nombre del período.
- Un APROBADO oficial en cualquier intento anterior satisface el prerrequisito. Si no existe, manda el intento anterior más reciente (fecha de clases e ID como desempate).
- Prerrequisito APROBADO: VALIDA. EN_CURSO: PENDIENTE_RESULTADOS. DESAPROBADO, RETIRADO o ausencia de resultado: REQUIERE_AJUSTE.
- El curso seleccionado en sí no debe estar ya aprobado/en curso ni inactivo; si lo está requiere ajuste. El ciclo no lo excluye por sí solo.
- Para un curso y luego para la solicitud, REQUIERE_AJUSTE prevalece sobre PENDIENTE_RESULTADOS, que prevalece sobre VALIDA. Una selección sin prerrequisitos puede ser válida inmediatamente.
- Importar cambios (también correcciones de notas/estados) revalida todas las solicitudes BORRADOR/ENVIADA de los alumnos afectados en períodos no cerrados. Las solicitudes de períodos CERRADO no se reescriben. El comando de inicialización usa el mismo criterio.

No se borran solicitudes ni detalles al importar resultados, ni se cambia el estado operativo, fecha de envío, revisión o ventanas. Se guarda únicamente la validación separada. Cuando hay revisión vigente, se revalida su selección inmutable, incluso durante la edición de un borrador de corrección, y se informa `revision_id`. La demanda validada usa la última revisión registrada y excluye toda solicitud global REQUIERE_AJUSTE o con corrección abierta, aunque algún curso individual sea válido. Los cambios del borrador no reemplazan ese registro. Se conservan por separado registrada, valida, pendiente_resultados, requiere_ajuste y sin_validacion; ver [reglas definitivas](BUSINESS_RULES.md). Para períodos cerrados se usa la última validación guardada, sin recalcular su historia.

## Corrección durante una extraordinaria

`POST /api/v1/student/pre-enrollments/{preEnrollment}/corrections`, sin cuerpo, inicia corrección de cualquier solicitud propia ENVIADA durante una ventana EXTRAORDINARIA activa. No requiere un resultado REQUIERE_AJUSTE. Reintentos no duplican revisiones. Un estudiante ajeno obtiene 404. No es un permiso individual ni permite alterar fechas: registra que el titular está editando dentro de una ventana global.

La solicitud actual pasa a BORRADOR, pero la revisión enviada anterior permanece íntegra y vigente. Se reutilizan las rutas de agregar/quitar cursos, preferencias y enviar; cada escritura exige ventana activa y, para correcciones, extraordinaria. El reenvío vuelve a comprobar cursos y créditos. Si tiene éxito registra revisión N+1 y nueva fecha, emite otra constancia y vuelve a ENVIADA. Si falla conserva el envío y PDF anteriores. La validación/demanda vigente no se sustituyen por la selección del borrador antes de registrar el reenvío.

`pre_enrollment_revisions` conserva cada envío con snapshot completo y PDF inmutables, número único por solicitud y ventana habilitante. No se crea una revisión enviada por iniciar una corrección. Las instantáneas antiguas se preservan sin fabricar evidencia faltante. Si vence la ventana, se impiden cambios/envío; puede retomarse durante otra extraordinaria. No se concede reapertura fuera de fechas a una persona. Ver [contrato de revisiones y constancias](student.md).

## Instalación y pruebas

Configurar las zonas antes de migrar, luego:

```bash
php artisan migrate --no-interaction
php artisan academic:revalidate --no-interaction
```

El segundo comando inicializa las validaciones de solicitudes vigentes anteriores a esta fase sin modificar sus datos enviados ni ventanas. Antes de ejecutarlo, las solicitudes antiguas pueden mostrar `validacion_academica: null`; nunca se cuentan como válidas por defecto. No usar `migrate:fresh` sobre la base habitual. Un rollback que elimine estas tablas perdería auditorías/ventanas/revisiones: en producción preferir una migración correctiva hacia adelante.

Pruebas sobre PostgreSQL separado (`prematricula_test`), nunca datos habituales:

```bash
php artisan test --compact tests/Feature/AcademicPeriodsApiTest.php tests/Feature/AcademicHistoryImportApiTest.php tests/Feature/AcademicRevalidationTest.php
```

Incluyen ventanas abiertas/cerradas, extraordinarias globales, solapamientos, período sin ventana, fechas y migración heredada, importación/auditoría/idempotencia/rollback, estados y notas, prerrequisitos, revalidación, corrección histórica, demanda y autorización. La colección Postman incluye los nuevos endpoints sin credenciales ni tokens incrustados.

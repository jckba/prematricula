# API v1: autenticación, cuentas y catálogo

Contrato actualizado: [OpenAPI 3.1](openapi.yaml). Se conservan Laravel Sanctum, `/api/v1` y los campos de respuesta existentes. Las únicas rutas retiradas son aprobar/rechazar, según el flujo definitivo descrito debajo. No hay registro público ni cambios de frontend.

Períodos, ventanas ordinarias/extraordinarias e historial: [guía y contrato de esta fase](../docs/academic-process.md). Incluye formatos de notas, auditorías, revalidación separada de ENVIADA, conteos de demanda y corrección extraordinaria. Configurar zonas antes de migrar y ejecutar `academic:revalidate` para solicitudes existentes. No se añaden reaperturas individuales ni aprobación.

Prematrícula definitiva: [contrato del estudiante](../docs/student.md). Nuevos estados BORRADOR → ENVIADA, sin aprobación posterior. Correcciones explícitas generan un borrador conservando la revisión vigente; cada envío exitoso crea una revisión y PDF inmutables. Un fallo no reemplaza el último registro. Se retiraron las URI de aprobar/rechazar (404), preservando toda historia. Ver [consultas de coordinadores](../docs/coordinator.md).

Enviar `Accept: application/json` y `Content-Type: application/json` para cuerpos JSON. Las cargas Excel usan `multipart/form-data` con su boundary generado por el cliente. Las operaciones protegidas requieren `Authorization: Bearer <token>`.

## Malla curricular y catálogo académico

Formato Excel, ejemplos completos, límites y política de actualización: [guía del módulo](../docs/curriculum.md). Colección ejecutable: [Postman](Prematricula.postman_collection.json). No se renombraron ni eliminaron las rutas existentes.

| Método | Ruta | Acceso / respuesta |
| --- | --- | --- |
| GET | `/api/v1/courses` | Usuario activo; colección paginada. Estudiantes: solo activos. |
| GET | `/api/v1/courses/{course}` | Usuario activo; recurso por ID. Inactivo para estudiante: 404. |
| PATCH | `/api/v1/coordinator/courses/{course}` | Coordinador; cambios académicos autorizados o estado. |
| GET | `/api/v1/coordinator/curriculum/template` | Coordinador; descarga `.xlsx` con encabezados. |
| POST | `/api/v1/coordinator/curriculum/validate` | Coordinador; `file` multipart; 200 válido o 422 con errores. Sin persistencia. |
| POST | `/api/v1/coordinator/curriculum/import` | Coordinador; `file`, `sha256`, `catalog_fingerprint` multipart; 200 confirmado, 409 si cambió archivo/catálogo. |
| GET | `/api/v1/coordinator/curriculum/export` | Coordinador; descarga catálogo completo `.xlsx`. |

Los dos cargos de coordinador tienen los mismos permisos mediante `CoursePolicy`. Toda ruta exige Sanctum y cuenta activa. Sin token: 401; estudiante en operaciones de coordinador: 403. Los recursos conservan los campos anteriores y añaden horas semanales decimales como strings y `estado`.

Se reutilizan `courses` y `course_prerequisites`; no hay tablas ni versiones de planes. Los cursos utilizados en historial o prematrículas (también borradores) no admiten cambios académicos ni de prerrequisitos, únicamente de estado. Los cursos no usados se actualizan por código sin duplicados; los omitidos se conservan. El ciclo no limita por sí solo la elegibilidad, que mantiene su autorización estudiantil existente.

Pruebas del módulo:

```bash
php artisan test --compact tests/Feature/Api/CourseCatalogTest.php tests/Feature/Api/Coordinator/CurriculumImportTest.php tests/Feature/Api/Coordinator/UpdateCourseTest.php tests/Feature/Policies/CoursePolicyTest.php
```

## POST /api/v1/login

Público. Recibe `identifier` y `password` como strings:

```json
{"identifier": "estudiante@unitru.edu.pe", "password": "password"}
```

También permite el código universitario, conservando ceros iniciales:

```json
{"identifier": "0014527007", "password": "password"}
```

Compatibilidad temporal para clientes existentes:

```json
{"correo_institucional": "estudiante@unitru.edu.pe", "password": "password"}
```

Enviar exactamente uno de `identifier` o `correo_institucional`. Si llegan ambos, se responde 422 incluso cuando pertenecen a la misma cuenta. El alias antiguo solo admite correo válido. `identifier` admite hasta 255 caracteres; el código se almacena como texto de hasta 30 caracteres. Se resuelve mediante `Student::user`, sin conversiones numéricas. Si un identificador coincide con el correo de una cuenta y el código de otra, se rechaza con 401.

Respuesta 200:

```json
{
  "message": "Autenticación correcta.",
  "data": {
    "token": "<token entregado una sola vez>",
    "expires_at": "2026-10-16T18:00:00.000000Z",
    "user": {
      "id": 1,
      "correo_institucional": "estudiante@unitru.edu.pe",
      "rol": "ESTUDIANTE",
      "estado": "ACTIVO",
      "cargo": null
    }
  }
}
```

| Estado | Comportamiento |
| --- | --- |
| 200 | Cuenta activa y contraseña correcta; estudiantes requieren perfil activo. |
| 401 | `{"message":"Credenciales incorrectas."}` para contraseña incorrecta, identificador inexistente o ambiguo, cuenta inactiva o perfil estudiantil ausente/inactivo. No se emite token. |
| 422 | Datos inválidos; `message` y `errors` por campo. Incluye códigos enviados como números, objetos o arrays. |
| 429 | Límite de intentos; incluye `Retry-After`. |

Límite: cinco peticiones por identificador/IP y treinta por IP, por minuto. También se cuentan los logins correctos. El alias antiguo y `/login` web comparten los límites para impedir eludirlos cambiando de campo o ruta. Se aplica un tiempo mínimo de comprobación mediante `Timebox`; la contraseña se verifica con `Hash::check`.

## GET /api/v1/me

Requiere autenticación Sanctum y cuenta habilitada. Respuesta 200:

```json
{
  "data": {
    "id": 2,
    "correo_institucional": "coordinador@unitru.edu.pe",
    "rol": "COORDINADOR",
    "estado": "ACTIVO",
    "cargo": "DIRECTOR_ESCUELA"
  }
}
```

`rol` conserva exclusivamente `ESTUDIANTE` y `COORDINADOR`. `cargo` es `DIRECTOR_ESCUELA`, `SECRETARIA` o `null`. Los estudiantes tienen cargo nulo. Los coordinadores anteriores pueden mantenerlo nulo hasta confirmar su cargo: la migración no lo deduce desde el correo. Ambos cargos tienen los mismos permisos.

Sin token o con token inválido, revocado o vencido: 401. Si una cuenta desactivada o un estudiante sin perfil activo usa un token todavía almacenado: 403, `{"message":"Acceso no autorizado."}`, y revocación de todos sus tokens. Se aplica a todas las rutas API protegidas, incluido `/me`.

## POST /api/v1/logout

Requiere autenticación. No necesita cuerpo. Respuesta 200:

```json
{"message":"Sesión cerrada correctamente."}
```

Elimina únicamente el token actual; los tokens de otros dispositivos permanecen. Reutilizarlo o repetir logout con él devuelve 401. Se aplican las mismas comprobaciones de actividad que a `/me`.

## Tokens y confidencialidad

- Duración de 480 minutos (ocho horas), sin renovación deslizante ni endpoint público de refresh. Después del vencimiento se requiere login.
- `config/sanctum.php` aplica también ese máximo a los tokens antiguos sin `expires_at`.
- Cambiar contraseña, correo o estado mediante gestión de cuentas revoca todos los tokens del destinatario en la misma transacción. La emisión de tokens bloquea la cuenta para coordinarse con esas actualizaciones.
- La tarea diaria `sanctum:prune-expired --hours=24` limpia tokens caducados hace al menos 24 horas. Requiere el scheduler de Laravel en ejecución; el vencimiento se comprueba aunque la limpieza no corra.
- Contraseñas y hashes no aparecen en recursos API. El token en claro solo se devuelve en login, nunca en logs. Los errores SQL se registran con conexión y SQLSTATE, sin consulta, parámetros ni excepción original; sus respuestas API son genéricas incluso con debug habilitado.

## Gestión de cuentas por coordinadores

La auditoría no establece jerarquía para administrar otras cuentas coordinadoras. El alcance inicial permite a ambos cargos consultar y administrar cuentas de estudiantes, mediante `UserPolicy`. El aprovisionamiento y la asignación de cargos de coordinadores siguen siendo internos. No se permite elevar roles, cambiar cargos, editar otros coordinadores ni eliminar cuentas por API.

| Método | Ruta | Resultado |
| --- | --- | --- |
| GET | `/api/v1/coordinator/accounts` | Estudiantes, paginados de 20 en 20. |
| GET | `/api/v1/coordinator/accounts/{account}` | Identidad de una cuenta estudiante. |
| POST | `/api/v1/coordinator/accounts` | Crea cuenta y perfil estudiante juntos; 201. |
| PATCH / PUT | `/api/v1/coordinator/accounts/{account}` | Actualiza correo, contraseña o estado; 200. |

Cuerpo de creación:

```json
{
  "correo_institucional": "nuevo@unitru.edu.pe",
  "password": "contraseña-de-ejemplo",
  "estudiante": {
    "codigo_universitario": "0014527007",
    "nombres": "Alumno",
    "apellidos": "Prueba",
    "creditos_maximos": 22
  }
}
```

`creditos_maximos` es opcional (predeterminado 22, rango 1–32767). La contraseña de creación/actualización requiere entre 8 y 1024 caracteres; con bcrypt (configuración actual), también tiene un máximo de 72 bytes y no admite bytes nulos, para evitar truncamientos o fallos al guardar. Correo, código y nombres son obligatorios; se valida unicidad del correo y código, incluidas colisiones entre ambos tipos de identificador. La cuenta nace con rol `ESTUDIANTE`, estado `ACTIVO` y cargo nulo. No acepta `rol`, `cargo` ni `estado` en creación.

Ejemplo de desactivación: `{"estado":"INACTIVO"}`. La actualización no admite cambios de rol, cargo o perfil académico. Respuestas: 401 sin autenticación; 403 por rol/política o cuenta inactiva; 404 si no existe el recurso; 422 por validación. Las respuestas solo contienen identidad pública, nunca contraseña ni tokens.

Los estudiantes consultan únicamente sus propias prematrículas. Inyectar `student_id` en cuerpos o filtros no cambia al propietario autenticado. Borrar cursos o modificar preferencias ajenos devuelve 404 y no altera el recurso. Las rutas antiguas de revisión y preferencias se conservan en esta fase.

## Migración y demostración

La migración aditiva `add_cargo_to_users_table` se aplica con `php artisan migrate --no-interaction`. Quedó aplicada al entorno local junto con las demás migraciones pendientes al integrar la carga docente.

`UserSeeder` y `StudentSeeder` crean estas cuentas únicamente en `local` o `testing`:

| Cuenta | Identificador | Cargo |
| --- | --- | --- |
| Estudiante | `estudiante@unitru.edu.pe` o `1452700722` | Nulo |
| Director | `coordinador@unitru.edu.pe` | `DIRECTOR_ESCUELA` |
| Secretaria | `secretaria.demo@unitru.edu.pe` | `SECRETARIA` |

Contraseña de demostración: `password`. Los seeders de cuentas pueden repetirse sin reiniciar credenciales, estados o cargos existentes. Si una cuenta ya existe, se conserva tal cual. `DatabaseSeeder` tampoco carga datos de demostración fuera de esos entornos. Para crear solo cuentas/perfil: `php artisan db:seed --class=UserSeeder --no-interaction` y luego `php artisan db:seed --class=StudentSeeder --no-interaction`.

## Pruebas de esta fase

```bash
php artisan test --compact tests/Feature/Api/AuthenticationApiTest.php tests/Feature/Api/Coordinator/AccountApiTest.php tests/Feature/Api/Student/ResourceAuthorizationTest.php tests/Feature/Auth/DemoAccountsSeederTest.php tests/Feature/Auth/SensitiveDatabaseErrorsTest.php tests/Feature/Policies/UserPolicyTest.php
```

PHPUnit usa PostgreSQL y la base separada `prematricula_test`, que debe existir. Se pueden sobrescribir `DB_HOST`, `DB_PORT` y `DB_USERNAME` para usar una instancia de pruebas aislada. No usar la base habitual: las pruebas migran y escriben datos de prueba.

## Consulta académica y estadísticas para coordinadores

Todas estas rutas requieren token Sanctum, cuenta activa y rol `COORDINADOR` (ambos cargos). Devuelven 401 sin autenticación, 403 sin autorización, 404 para recursos inexistentes y 422 para filtros inválidos. No se modificó el frontend.

**Las cifras son estimaciones de demanda de prematrícula; no constituyen matrícula definitiva.** La advertencia aparece en los reportes JSON y en la primera fila del CSV.

| Método | Ruta bajo `/api/v1/coordinator` | Resultado |
| --- | --- | --- |
| GET | `/students?codigo_universitario=0014527007` | Búsqueda exacta, con ceros iniciales. |
| GET | `/students?q=ana&estado=ACTIVO` | Código parcial o nombres y apellidos, paginados. |
| GET | `/students/{codigo}/academic?academic_period_id=1` | Identidad, historial relevante y prematrícula del período. |
| GET | `/academic-periods/{period}/demand` | Demanda por curso, paginada. |
| GET | `/academic-periods/{period}/summary` | Totales de alumnos por período y agregados por ciclo. |
| GET | `/academic-periods/{period}/demand/export` | CSV completo con los mismos filtros y orden que el reporte. |
| GET | `/academic-periods/{period}/demand/courses/{course}/students` | Alumnos interesados del envío vigente, paginados. |

### Identidad e historial

La búsqueda devuelve `data`, `links` y `meta`; cada estudiante contiene exclusivamente `codigo_universitario`, `nombres`, `apellidos`, `estado` y `creditos_maximos`. `estado` es `ACTIVO` o `INACTIVO`, según el perfil académico existente; no expresa aprobación, promedio ni condición de egreso. Se mantienen separados el historial de resultados y la validación de la selección. No se exponen correo, cuenta, contraseña, tokens ni PDF.

Filtros de búsqueda: `codigo_universitario` exacto (texto, máximo 30), `q` parcial literal de 2–150 caracteres, `estado`, `sort=codigo_universitario|nombres|apellidos`, `direction=asc|desc`, `page` y `per_page` (1–100, predeterminado 20). Los filtros se combinan con AND; `q` no distingue mayúsculas y trata `%` y `_` como caracteres literales.

La consulta exacta por código exige `academic_period_id`. Devuelve `data.estudiante`, `data.periodo`, `data.historial_academico` (`data` y `paginacion`) y `data.prematricula`. El historial incluye los intentos anteriores a la fecha inicial del período consultado, ordenados por fecha del período descendente; `page` y `per_page` paginan ese historial. Los resultados futuros no se usan como antecedentes. Si no hay prematrícula, se devuelve `null`.

La prematrícula contiene estado, revisión vigente, fecha de envío, cursos, preferencias, créditos y validación académica asociada a esa revisión. `origen_cursos=REVISION_REGISTRADA` identifica el último envío. Durante una corrección se muestra esa selección registrada; las ediciones aún no enviadas no la reemplazan. Un borrador inicial se identifica con `origen_cursos=BORRADOR` y `revision_id=null`. Una validación de una revisión reemplazada se devuelve como `null`.

Los detalles incompletos de una revisión se descartan en la consulta y los cursos repetidos aparecen una sola vez, también al sumar créditos. La validación expone únicamente curso, código, estado, motivo y prerrequisitos académicos; no propaga campos adicionales del registro interno.

### Fuente y reglas de conteo

La fuente es `current_revision_id` de cada solicitud del período y la selección inmutable de esa revisión, enlazada con `pre_enrollment_revision_courses`. Debe ser una revisión `ENVIO` con fecha de envío, número de revisión y al menos un curso registrado. No se cuentan solicitudes sin envío, selecciones vacías, capturas `LEGACY_CAPTURE`, revisiones históricas ni detalles de borradores. La clave única estudiante/período y la deduplicación SQL de curso por revisión impiden contar dos veces al mismo alumno en el mismo curso/período.

Una corrección abierta conserva el último envío como demanda **registrada** y lo clasifica con ajuste mientras se prepara el reenvío; no se cuentan los cursos editados en el borrador. Al enviar la nueva revisión, la anterior deja de aportar demanda. Las importaciones oficiales de notas actualizan la validación mediante el proceso existente, sin alterar la revisión ni la constancia. Las lecturas de reportes no revalidan ni escriben datos. En períodos cerrados se conserva la validación registrada conforme a las reglas existentes.

Cada fila incluye `course_id`, `codigo`, `nombre`, `ciclo`, `creditos` y estos conteos:

| Campo | Significado |
| --- | --- |
| `registrada` | Alumnos distintos con selección en la revisión registrada vigente. |
| `valida` | Demanda validada: selección `VALIDA` y solicitud sin ajuste. |
| `pendiente_resultados` | Prerrequisitos provisionales, separados de la demanda validada. |
| `requiere_ajuste` | Selección con ajuste, solicitud global con ajuste o corrección abierta. |
| `sin_validacion` | Sin validación vigente para ese curso y revisión; nunca se asume válida. |
| `manana`, `tarde`, `sin_preferencia` | Preferencias `MANANA`, `TARDE`, `SIN_PREFERENCIA` de la demanda registrada. |

`registrada = valida + pendiente_resultados + requiere_ajuste + sin_validacion`. Las preferencias suman `registrada`. Si la solicitud completa es `REQUIERE_AJUSTE`, todos sus cursos quedan fuera de `valida`, aunque alguno sea válido individualmente. Los cursos sin demanda se omiten.

Filtros de demanda, CSV y agregados por ciclo: `q` (código/nombre parcial literal, 2–150 caracteres), `course_id`, `ciclo`, `estado_validacion=VALIDA|PENDIENTE_RESULTADOS|REQUIERE_AJUSTE|SIN_VALIDACION` y `preferencia_turno=MANANA|TARDE|SIN_PREFERENCIA`. Orden del reporte y CSV: `sort=course_id|codigo|nombre|ciclo|creditos|registrada|valida|pendiente_resultados|requiere_ajuste`, `direction=asc|desc`; predeterminado `course_id asc` y desempate por ID. El reporte devuelve `data.cursos` y `data.paginacion`; `per_page` es 20 por defecto y admite 1–100.

La consulta de interesados admite `estado_validacion`, `preferencia_turno`, `page` y `per_page`, y ordena por código universitario ascendente. Devuelve el paginador (`data`, `total`, `current_page`, etc.) con código, nombres, apellidos, revisión, preferencia y clasificación de la selección. Usa exactamente la misma fuente del reporte; un curso existente sin interesados devuelve una lista vacía.

Un detalle de validación que no contiene una lista de cursos se trata como `SIN_VALIDACION` en la demanda, conservando el envío en `registrada` sin asumir validez ni interrumpir el reporte o la exportación.

### Resumen y población

`alumnos_enviaron` y `alumnos_requieren_ajuste` cuentan alumnos distintos del período completo; los filtros del resumen solo afectan a `ciclos`. `alumnos_sin_validacion` informa envíos sin validación de la revisión vigente. Un borrador inicial no se considera enviado.

En `ciclos`, los campos de demanda y preferencias cuentan selecciones alumno/curso; el campo adicional `alumnos_enviaron` cuenta alumnos distintos en ese ciclo. Un alumno puede interesarse en varios cursos y ciclos, por lo que no se deben sumar alumnos entre ciclos para obtener el total del período.

Actualmente no existe padrón académico por período. Por ello `poblacion_academica` y `alumnos_no_enviaron` se devuelven como `null`, con explicación en `fuente_poblacion`. No se toma la lista actual de estudiantes activos como población histórica ni se inventa un cero. Se pueden consultar los enviados y quienes requieren ajuste sin disponer de ese padrón.

### CSV, índices y verificación

El CSV usa UTF-8 con BOM y coma como separador. Primera fila: advertencia; segunda fila: encabezados. Las siguientes filas incluyen período, identidad del curso y todos los conteos. Exporta todas las filas filtradas, independientemente de `page` o `per_page`, mediante cursor; no exporta identidades estudiantiles. Los textos que podrían activar fórmulas se prefijan con apóstrofo. No se agregaron dependencias.

Las agregaciones usan PostgreSQL (JSONB, LATERAL y agregados condicionales), con un número de consultas independiente del número de solicitudes. El historial carga cursos y períodos anticipadamente. La migración aditiva `2026_10_16_193522_add_academic_reporting_indexes.php` incorpora índices para período/revisión vigente y orden por apellidos/ID; el código universitario ya tiene índice único. Se aplica con `php artisan migrate --no-interaction` en el entorno correspondiente y quedó aplicada al entorno local al integrar la carga docente.

Pruebas de este módulo:

```bash
php artisan test --compact tests/Feature/Api/Coordinator/AcademicConsultationTest.php tests/Feature/Api/Coordinator/AcademicDemandTest.php
```

Cubren revisión vigente e histórica, duplicados de curso, borradores y solicitudes incompletas, validación ausente o de otra revisión, ajustes globales, importaciones oficiales de notas, reapertura y reemplazo, agregados, filtros y orden, paginación, CSV e inyección de fórmulas, minimización de datos, autorización y consultas sin N+1. Se ejecutan contra `prematricula_test`, no contra la base habitual.

## Gestión de carga académica docente

Todas las rutas siguientes están bajo `/api/v1/coordinator` y requieren autenticación, cuenta activa y rol `COORDINADOR`. Director y secretaría tienen los mismos permisos. Los docentes son fichas académicas independientes: no se crean usuarios, contraseñas ni tokens para ellos.

**Limitación del modelo:** una unidad docente por curso y período. Distribuye horas semanales entre docentes; no representa grupos paralelos, turnos, horarios, ambientes, aulas, conflictos de tiempo ni matrícula definitiva. La demanda estudiantil es una estimación independiente: no multiplica horas ni genera grupos o secciones.

| Método | Ruta | Operación |
| --- | --- | --- |
| GET, POST | `/teachers` | Buscar o registrar docentes. |
| GET | `/teachers/{teacher}` | Consultar ficha docente. |
| PATCH, PUT | `/teachers/{teacher}` | Actualizar campos del perfil, estado o límite. |
| DELETE | `/teachers/{teacher}` | Desactivar; conserva físicamente la ficha y sus asignaciones. |
| GET, POST | `/teaching-assignments` | Listar o crear asignaciones. |
| GET | `/teaching-assignments/{assignment}` | Consultar asignación vigente. |
| PATCH, PUT | `/teaching-assignments/{assignment}` | Actualizar horas. |
| DELETE | `/teaching-assignments/{assignment}` | Baja lógica durante planificación; libera horas. |
| GET | `/teaching-assignments/{assignment}/history` | Auditoría, incluso después de la baja lógica. |
| GET | `/academic-periods/{period}/teaching-load` | Totales del período. |
| GET | `/academic-periods/{period}/teaching-load/teachers` | Carga por docente. |
| GET | `/academic-periods/{period}/teaching-load/teachers/{teacher}` | Carga y cursos asignados a un docente. |
| GET | `/academic-periods/{period}/teaching-load/courses` | Cobertura, pendientes y demanda por curso. |
| GET | `/academic-periods/{period}/teaching-load/courses/{course}` | Docentes, horas y demanda de un curso. |

### Datos de docentes

POST exige `codigo_institucional` único (texto, máximo 30), `nombres` (100) y `apellidos` (150). `correo_institucional` es opcional y admite `null`; no actúa como credencial. `estado` admite `ACTIVO` (predeterminado) o `INACTIVO`. `max_horas_semanales` admite `null` (sin límite configurado) o un número entre 0 y 99999.99, con hasta dos decimales. Cero impide toda carga positiva. No se aceptan `user_id`, `password` ni `rol`.

PATCH y PUT actualizan únicamente los campos enviados. Reducir el máximo se rechaza si queda por debajo de la carga vigente del docente en cualquiera de sus períodos; las cargas de períodos distintos no se suman entre sí. Desactivar no elimina ni libera las asignaciones existentes: se siguen mostrando en los reportes y deben revisarse expresamente. No se pueden crear o modificar asignaciones de un docente inactivo.

GET `/teachers` admite `q` (código o nombre completo parcial literal, 2–150 caracteres, sin distinguir mayúsculas), `estado`, `page` y `per_page` (1–100, predeterminado 20). Orden fijo por código e ID. Los listados devuelven `data`, `links` y `meta`.

### Asignaciones, límites y auditoría

Ejemplo de creación:

```json
{
  "academic_period_id": 1,
  "course_id": 1,
  "teacher_id": 1,
  "horas_teoria_asignadas": "1.50",
  "horas_practica_asignadas": "0.50",
  "horas_laboratorio_asignadas": "0.00"
}
```

Los tres tipos son independientes, no negativos y admiten hasta dos decimales (0–999.99 por tipo). Los tipos omitidos al crear valen cero; al menos uno debe ser positivo. Se permiten asignaciones parciales. La suma de cada tipo entre todos los docentes del mismo curso/período no puede superar `horas_teoria`, `horas_practica` o `horas_laboratorio` del curso. La suma de los tres tipos, entre todos los cursos del docente en ese período, no puede superar su máximo configurado. Los cálculos internos de validación usan centésimas enteras; los reportes usan agregados SQL NUMERIC. Las horas se devuelven como cadenas decimales con dos posiciones.

Solo puede existir una asignación vigente por docente/curso/período. Los vínculos son inmutables: PATCH/PUT solo aceptan horas. Para cambiar un vínculo en un período abierto, se da de baja la asignación y se crea otra. Cada creación, actualización o baja registra autor, acción, instante y snapshots `before`/`after`; `created_by` conserva al creador y `updated_by` identifica al último actor. Estos campos no se toman del cliente. La auditoría no tiene endpoints de escritura o borrado.

Las operaciones se ejecutan dentro de `AcademicProcessLock`, una transacción con bloqueo asesor PostgreSQL compartido por la gestión de períodos. Se releen y bloquean la asignación, el período, el curso y el docente antes de comprobar capacidad y límites. El bloqueo de curso también coordina con la edición/importación de la malla. Una restricción única parcial impide duplicados vigentes; CHECK protege horas no negativas y selección no vacía, y las claves foráneas evitan borrar referencias históricas.

**Política histórica:** un período `CERRADO` no admite crear, actualizar ni dar de baja asignaciones (409); su consulta permanece disponible. La API no ofrece eliminación física, purga ni restauración de asignaciones. Las bajas de períodos abiertos conservan la fila y su auditoría, se excluyen de las cargas vigentes y permiten crear una nueva asignación para la misma combinación. Un período referenciado por asignaciones, incluidas las dadas de baja, no puede eliminarse ni cambiar su código o fechas de clases. Las definiciones académicas de cursos referenciados se protegen con la misma política existente de la malla, también tras una baja lógica.

El listado de asignaciones admite `academic_period_id`, `teacher_id`, `course_id`, `page` y `per_page`; se ordena por ID y carga docentes/cursos anticipadamente. La auditoría se ordena por ID y admite paginación. Respuestas de creación: 201; consulta/actualización/baja: 200; sin autenticación: 401; sin permisos: 403; recurso inexistente o asignación dada de baja consultada fuera de su historial: 404; período cerrado: 409; validación, duplicación o sobreasignación: 422.

### Reportes y comparación con demanda

Todos los reportes incluyen la advertencia del modelo y la de demanda estimada. Los listados por docente y curso devuelven paginación y `contexto`; los detalles y resumen devuelven `data` con contexto. Los listados recorren las fichas docentes y el catálogo de cursos vigente, incluso sin asignaciones. La presencia de un curso no declara una oferta formal para ese período.

Por docente se incluyen `cantidad_cursos`, las tres horas asignadas, `total_semanal`, `horas_disponibles` (`null` sin máximo) y `excede_limite`. Se comparan con el límite actualmente configurado, también al consultar períodos históricos. El filtro `excede_limite=1` detecta excesos preexistentes o incorporados fuera de esta API; las operaciones normales impiden generarlos. También se admite `teacher_id`, `q`, `estado`, `page` y `per_page`. Orden por código docente e ID. El detalle contiene sus asignaciones/cursos paginados.

Por curso se incluyen su identidad y ciclo, horas semanales del catálogo, `cantidad_docentes`, horas asignadas y `horas_teoria_pendientes`, `horas_practica_pendientes`, `horas_laboratorio_pendientes`. También se muestran `demanda_registrada`, `demanda_valida`, `demanda_pendiente_resultados`, `demanda_requiere_ajuste` y `demanda_sin_validacion`, reutilizando las reglas de revisión vigente del módulo de demanda. No se infieren grupos a partir de esos conteos. Admite `course_id`, `ciclo`, `q`, `estado`, `page` y `per_page`; orden por código del curso e ID. El detalle contiene sus docentes/asignaciones paginados.

El resumen considera todo el período: asignaciones, docentes con carga, cursos con y sin asignación, horas asignadas por tipo, total semanal, pendientes por tipo y docentes que exceden su máximo. Los filtros de los listados no alteran esos totales. Las consultas agregan en SQL y su número no aumenta por cada docente o curso mostrado.

### Base de datos y pruebas

La migración aditiva `2026_10_16_201941_create_teaching_workload_tables.php` crea `teachers`, `teaching_assignments` y `teaching_assignment_changes`, con índices para consultas por período/docente y curso/período. Se ejecutó `php artisan migrate --no-interaction` y las seis migraciones pendientes quedaron aplicadas en el entorno local, sin cargar datos de demostración. En otros entornos se deben aplicar las migraciones antes de usar el módulo. Las pruebas usan exclusivamente `prematricula_test`.

```bash
php artisan test --compact tests/Feature/TeacherApiTest.php tests/Feature/TeachingAssignmentApiTest.php tests/Feature/TeachingLoadReportTest.php tests/Feature/TeachingAssignmentConcurrencyTest.php
```

Cubren ambos cargos coordinadores, altas sin cuentas, edición y desactivación, horas parciales y decimales, múltiples docentes/cursos, límites por tipo y docente, duplicados, transacciones y fallo de auditoría, protección histórica, reportes, demanda separada, filtros, paginación, autorización y consultas sin N+1. Las pruebas concurrentes ejecutan dos procesos reales contra PostgreSQL y comprueban capacidad de curso, límite docente y duplicación. No deben ejecutarse simultáneamente con otra suite que use la misma base de pruebas.

Hay factories y seeders optativos de demostración: `TeacherSeeder` y `TeachingAssignmentSeeder`, exclusivos de `local`/`testing`; no se ejecutan automáticamente. El segundo necesita un coordinador activo, un período abierto y un curso disponible, y crea una sola hora de teoría con el mismo caso de uso y auditoría de la API. Ninguno crea cuentas docentes.

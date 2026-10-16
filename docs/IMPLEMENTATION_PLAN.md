# Plan de implementación del backend académico

Fecha de inspección: 2026-10-16. Base del análisis: commit `3a79b38`, rama `master`, árbol de trabajo inicialmente limpio.

## 1. Alcance y estado de esta fase

Esta fase entrega una auditoría y un plan; no implementa módulos, migraciones, cambios de endpoints ni modificaciones de tests. El único archivo fuente agregado es este documento. No se modifican Blade, JavaScript, CSS, frameworks de interfaz ni Vite. Los archivos internos de renderizado PDF solo podrán crearse en la fase del PDF.

El objetivo definitivo es registrar prematrículas mediante `BORRADOR -> ENVIADA`, sin revisión posterior. `ENVIADA` significa registro exitoso, no solicitud pendiente. Se conservan los registros históricos `APROBADA` y `RECHAZADA`, sus comentarios, fechas y revisores. No se convierten masivamente a `ENVIADA`.

Se incluyen director de escuela y secretaria como coordinadores, ventanas globales de fechas, malla única e historial/notas importables desde Excel, adelanto por prerrequisitos y créditos, constancia PDF A4 de una página, demanda, búsqueda de estudiantes y asignación de cursos/horas semanales a docentes. Se excluyen secciones, horarios, aulas, cuentas docentes y matrícula definitiva.

## 2. Evidencia y stack real

Se inspeccionaron las 11 migraciones, 7 modelos, 6 enums, 6 seeders, la única factory, los 8 casos de uso, 3 servicios, controllers de autenticación y prematrícula, Form Requests, Resources, middleware, bootstrap, rutas, los 8 archivos de tests, README y los cinco documentos de API existentes.

| Componente | Versión o estado comprobado |
|---|---|
| PHP CLI | 8.4.25 |
| Laravel instalado | 13.34.0; `composer.json` declara `^13.17` |
| Sanctum | 4.3.3 |
| PHPUnit | 12.5.36 |
| Pint / Boost | 1.32.1 / 2.10.0 |
| PostgreSQL local | 18.6, conexión predeterminada `pgsql` |
| Excel / PDF | Sin dependencia directa instalada para esas funcionalidades |
| Reglas locales | `AGENTS.md`; no existe `.ai/rules` |

Fuentes de comprobación: `composer show --direct`, `php -v`, Laravel Boost `application-info`, `database-connections`, `database-schema`, consultas de solo lectura y `php artisan route:list`. Se aplicaron las skills locales `.claude/skills/laravel-best-practices/SKILL.md` y `.claude/skills/testing-best-practices/SKILL.md`: conservación de convenciones, revisión de límites de autorización, cambios de esquema por etapas y uso de PostgreSQL real en pruebas.

Se consultó `search-docs` de Boost para Laravel 13 y Sanctum sobre aislamiento, transacciones y bloqueos. Referencias: [pruebas de base de datos](https://github.com/laravel/docs/blob/13.x/database-testing.md), [bloqueos pesimistas](https://github.com/laravel/docs/blob/13.x/queries.md#pessimistic-locking) y [Sanctum](https://github.com/laravel/docs/blob/13.x/sanctum.md). No se cambian versiones ni dependencias en esta fase.

### Base local observada, no inventario de producción

La conexión normal apunta a `prematricula`, usuario `prematricula_user`, en `127.0.0.1:5432`. Solo se hicieron consultas de lectura sobre ella. Las 11 migraciones figuran aplicadas: diez en batch 1 y tokens en batch 2.

| Tabla de negocio | Filas observadas |
|---|---:|
| `users` | 2 |
| `students` | 1 |
| `academic_periods` | 2 |
| `courses` | 5 |
| `course_prerequisites` | 3 |
| `academic_histories` | 2 |
| `pre_enrollment_requests` | 0 |
| `pre_enrollment_details` | 0 |

No se observaron prematrículas históricas en esta base local. Eso no autoriza eliminar columnas o estados: otros entornos pueden contenerlos. Los seeders declaran tres periodos, pero esta base contiene dos; no debe asumirse que el estado desplegado reproduce los seeders.

## 3. Arquitectura actual

### Persistencia y relaciones

| Migración / tabla | Estructura y relaciones relevantes |
|---|---|
| `0001_01_01_000000_create_users_table.php` | `users`: correo institucional único, contraseña, rol, estado, remember token. También `password_reset_tokens` y `sessions`; `sessions.user_id` tiene índice sin FK. |
| `0001_01_01_000001_create_cache_table.php` | `cache`, `cache_locks`; infraestructura Laravel. |
| `0001_01_01_000002_create_jobs_table.php` | `jobs`, `job_batches`, `failed_jobs`; infraestructura Laravel. |
| `2026_09_29_035342_create_students_table.php` | Usuario único por estudiante, código universitario único, nombres, apellidos, máximo de créditos (22 por defecto), estado. FK a usuario con eliminación restringida. |
| `2026_09_29_035402_create_academic_periods_table.php` | Código único, fechas académicas, un único par de timestamps opcionales de prematrícula, estado. No registra sucesivas ventanas. |
| `2026_09_29_035423_create_courses_table.php` | Código único, nombre, créditos, ciclo y estado. Catálogo global, sin tabla de mallas múltiples. |
| `2026_09_29_035450_create_course_prerequisites_table.php` | Relación autorreferenciada de cursos, PK compuesta `(course_id, prerequisite_id)`, ambas FK con cascada de eliminación. No evita autorreferencias ni ciclos. |
| `2026_09_29_035536_create_academic_histories_table.php` | Estudiante, curso y periodo con FK restrictivas; nota decimal `(5,2)` opcional; estado. Único por estudiante/curso/periodo, permite intentos en periodos distintos. |
| `2026_09_29_035620_create_pre_enrollment_request_table.php` | Crea realmente `pre_enrollment_requests`, plural. Único por estudiante/periodo; estado, envío, revisión, revisor y comentarios. FK restrictivas salvo revisor con `SET NULL`. |
| `2026_09_29_035637_create_pre_enrollment_details_table.php` | Solicitud/curso únicos, preferencia de turno. Eliminar cabecera elimina detalles; eliminar curso está restringido. |
| `2026_10_01_153600_create_personal_access_tokens_table.php` | Tokens Sanctum, relación polimórfica, hash único, abilities y expiración opcional. |

Los modelos usan Eloquent, atributos `#[Fillable]`, `casts()` y `HasFactory`. `User` oculta contraseña/remember token, aplica hash y usa `HasApiTokens`; tiene un estudiante. `Student` pertenece a usuario y tiene historiales y solicitudes. `AcademicPeriod` tiene historiales y solicitudes. `Course` tiene prerrequisitos, relación inversa, historiales y detalles. `AcademicHistory` pertenece a estudiante, curso y periodo. `PreEnrollmentRequest` pertenece a estudiante/periodo/revisor y tiene detalles. `PreEnrollmentDetail` pertenece a solicitud y curso. Las relaciones carecen de tipos explícitos de retorno: deuda de mantenimiento, no causa demostrada de fallo.

Los estados se almacenan como cadenas y se convierten a enums PHP. El esquema real no tiene restricciones `CHECK` de dominio; `unsignedSmallInteger` termina siendo `smallint` en PostgreSQL, sin garantía de no negatividad.

| Enum real | Valores |
|---|---|
| `UserRole` | `ESTUDIANTE`, `COORDINADOR` |
| `PreEnrollmentStatus` | `BORRADOR`, `ENVIADA`, `APROBADA`, `RECHAZADA` |
| `AcademicPeriodStatus` | `EN_CURSO`, `PLANIFICACION`, `PREMATRICULA`, `CERRADO` |
| `AcademicHistoryStatus` | `EN_CURSO`, `APROBADO`, `DESAPROBADO`, `RETIRADO` |
| `SchedulePreference` | `SIN_PREFERENCIA`, `MANANA`, `TARDE` |
| `RecordStatus` | `ACTIVO`, `INACTIVO` |

### Casos de uso y servicios

`GetOrCreateDraft` valida periodo y busca por estudiante/periodo; si no existe, crea `BORRADOR`. Devuelve también una solicitud ya enviada o histórica si existe para ese periodo. La creación ocurre actualmente desde un GET.

`GetAvailableCourses` excluye cursos aprobados o en curso del estudiante, exige curso activo y todos sus prerrequisitos. Actualmente un prerrequisito `APROBADO` **o `EN_CURSO`** satisface el requisito; existe un test explícito para esto. No restringe por ciclo, por lo que ya permite adelantar cursos. No recibe periodo objetivo y no limita historiales por fecha/periodo. Lista elegibilidad académica, pero no descuenta créditos seleccionados.

`AddCourse` valida ventana, borrador, actividad del curso, duplicados, elegibilidad y suma máxima de créditos. `RemoveCourse` valida ventana, borrador y pertenencia del detalle. `UpdateSchedulePreference` valida ventana y borrador. Estos casos usan relaciones cargadas y no bloquean una cabecera común.

`SubmitPreEnrollment` revalida periodo, borrador, selección no vacía, créditos y elegibilidad dentro de una transacción; actualiza `ENVIADA` y `fecha_envio`. No vuelve a obtener la cabecera con bloqueo. Una transacción por sí sola no serializa adiciones, eliminaciones y envíos concurrentes.

`ApprovePreEnrollment` y `RejectPreEnrollment` bloquean la cabecera dentro de transacciones y escriben `APROBADA`/`RECHAZADA` con auditoría. `PreEnrollmentReviewValidator` exige revisor activo, rol coordinador y solicitud enviada. Ese es el flujo que debe dejar de ser invocable en la fase siguiente.

`CurrentPreEnrollmentPeriod` exige exactamente un periodo `PREMATRICULA` cuya fecha inicial/final contenga el instante actual. `PreEnrollmentPeriodValidator` repite la validación para una instancia de periodo. No hay servicio ni endpoint de gestión de ventanas.

Los controllers coordinan HTTP y casos de uso. Los Form Requests validan login, curso/preferencia, listado por estado y comentario de rechazo; sus `authorize()` retornan `true`, por lo que la autorización depende de middleware y comprobaciones del controller. Los Resources serializan cursos, detalles y solicitudes, incluidos datos históricos de revisión. Los totales de créditos se calculan usando los valores actuales de `courses`, sin snapshot.

### Autenticación y autorización

- Login web mínimo: `GET/POST /login`, sesión Laravel, usuario activo, regeneración de sesión; dashboard protegido por `auth`. Logout invalida sesión y regenera token CSRF.
- API: login institucional crea token Sanctum `frontend`; `/me` consulta identidad y `/logout` elimina el token actual. Los tokens no tienen vencimiento global configurado y no se asignan abilities específicas.
- `RoleMiddleware` acepta un único rol exacto. No verifica actividad del usuario en cada solicitud.
- `RequireStudentProfile` verifica existencia del perfil; **no verifica `students.estado`**, pese a lo indicado por la documentación. Tampoco existe una comprobación general de actividad para tokens emitidos antes de una desactivación.
- Quitar curso/cambiar preferencia comprueban el propietario del detalle. Los listados estudiantiles se filtran por estudiante autenticado.
- No hay rate limiting explícito para login en las rutas ni configuración de limiter en `AppServiceProvider`. `route:list -vv` confirma que el login API no tiene throttle.
- Existen rutas web `/student` y `/coordinator` sin middleware de autenticación del servidor; el comentario de rutas delega el control de esas vistas al cliente. No se modifican esas vistas ni su JavaScript. Los datos API sí están protegidos por Sanctum y rol.
- `bootstrap/app.php` convierte `DomainException` en 422, errores de validación en JSON 422 y recursos/rutas ausentes en 404. No personaliza aquí todos los mensajes 401/403.

### Seeders y factories

`DatabaseSeeder` ejecuta periodos, cursos, usuarios, estudiante e historial en ese orden. Crea ejemplos mediante `create`, sin idempotencia: volver a ejecutarlos puede violar restricciones únicas. `AcademicPeriodSeeder` combina periodos fijos con una ventana relativa a `now()`. `CourseSeeder` crea cinco cursos y tres relaciones de prerrequisitos. `UserSeeder` crea estudiante/coordinador con contraseña de demostración; `StudentSeeder` e `AcademicHistorySeeder` dependen de correos/códigos fijos.

Solo existe `UserFactory`; conserva campos del esqueleto (`name`, `email`, `email_verified_at`) ausentes en `users`, y no define correo institucional/rol/estado adecuados. No sirve como base confiable para nuevos tests. Los tests actuales construyen registros mediante `create()` y helpers propios; no se reescribieron en esta fase.

## 4. Inventario real de rutas y nombres

Hay **14 rutas API v1, todas sin nombre**. No existe un prefijo de nombres `api.v1.*`. Se conserva inicialmente el contrato de URI; añadir nombres es una mejora interna propuesta para la siguiente fase.

En la tabla, `S` significa `auth:sanctum`; `E`, `S + role:ESTUDIANTE + student.profile`; `C`, `S + role:COORDINADOR`. Todas las URI se expresan respecto de `/api/v1`.

| Método | URI actual | Acceso | Destino propuesto |
|---|---|---|---|
| POST | `/login` | Público | Conservar; reforzar limitación de intentos y pruebas |
| GET | `/me` | S | Conservar; exponer cargo coordinador cuando exista |
| POST | `/logout` | S | Conservar; verificar revocación real de token |
| GET | `/student/pre-enrollment/available-courses` | E | Adaptar elegibilidad por periodo/ventana y política académica |
| GET | `/student/pre-enrollment` | E | Conservar compatibilidad; separar consulta y creación posteriormente |
| GET | `/student/pre-enrollments` | E | Conservar historial, incluidos estados heredados |
| POST | `/student/pre-enrollment/courses` | E | Adaptar para selección sin turnos y control concurrente |
| DELETE | `/student/pre-enrollment/courses/{detail}` | E | Conservar con bloqueo y propiedad |
| PATCH | `/student/pre-enrollment/courses/{detail}/preference` | E | Retirar del flujo objetivo sin horarios |
| POST | `/student/pre-enrollment/submit` | E | Conservar; `ENVIADA` equivale a registrada |
| GET | `/coordinator/pre-enrollments` | C | Adaptar de bandeja de revisión a consulta de registros |
| GET | `/coordinator/pre-enrollments/{preEnrollment}` | C | Conservar consulta y auditoría histórica |
| POST | `/coordinator/pre-enrollments/{preEnrollment}/approve` | C | Retirar operación en la siguiente fase |
| POST | `/coordinator/pre-enrollments/{preEnrollment}/reject` | C | Retirar operación en la siguiente fase |

Nombres web existentes: `login`, `login.store`, `dashboard`, `logout`, `student.dashboard`, `coordinator.dashboard`. La redirección `/` no tiene nombre. Se verificaron con `route:list`; los tests HTTP actuales usan URI literales válidas del router actual. No se encontraron referencias de tests a rutas antiguas inexistentes; las operaciones obsoletas siguen existiendo en código y documentación, y los tests de revisión llaman directamente a casos de uso.

## 5. Hallazgos priorizados

Los siguientes hallazgos de código provienen de inspección; los tests funcionales no llegaron a ejecutarlos por el bloqueo de infraestructura descrito en la sección 10.

| Prioridad | Hallazgo y evidencia | Acción posterior |
|---|---|---|
| P0 | Falta la base `prematricula_test`; 29 errores al preparar Feature tests | Provisionar base separada y repetir suite antes de cambios funcionales |
| P0 | `phpunit.xml`: `db_port` no equivale a `DB_PORT`; variables sin `force` y sin guardia en `Tests/TestCase.php` | Corregir aislamiento, impedir uso de base normal y URLs/configuración heredadas; cuenta de pruebas dedicada |
| P1 | `Api/Coordinator/PreEnrollmentController.php::index` referencia `ListPreEnrollmentsRequest` sin importar `App\Http\Requests\Api\ListPreEnrollmentsRequest` | Corregir resolución de clase; añadir test de listado exitoso con coordinador. El test existente solo prueba denegación a estudiante |
| P1 | `2026_09_29_035620_create_pre_enrollment_request_table.php::down()` elimina `pre_enrollment_request`, singular, aunque `up()` crea plural | Corregir el método inverso en una tarea explícita y probar rollback aislado; puede quedar la tabla y fallar la eliminación de tablas referenciadas |
| P1 | Aprobación/rechazo siguen accesibles y escriben nuevos estados históricos | Desactivar rutas y retirar casos de uso invocables junto con sus tests de comportamiento antiguo |
| P1 | Token de usuario luego inactivado y perfil estudiantil inactivo no se rechazan de forma general | Añadir control de actividad y tests con tokens reales y perfiles inactivos |
| P1 | `AddCourse` y envío no comparten bloqueo; creación de borrador hace buscar/crear | Serializar mutaciones por cabecera; tratar carrera de unicidad al crear borrador; probar instancias desactualizadas y solicitudes concurrentes |
| P1 | Cambiar catálogo puede alterar créditos y datos mostrados en registros ya enviados | Capturar snapshot de inscripción/constancia al enviar; no reconstruir valores históricos desconocidos como si fueran originales |
| P2 | Una sola ventana mutable por periodo y sin restricciones de solapamiento | Añadir ventanas persistidas y regla global de apertura |
| P2 | Historial `EN_CURSO` cuenta como prerrequisito sin relación temporal con periodo objetivo | Definir política institucional; preservar por ahora conducta existente y hacer explícita la elegibilidad temporal |
| P2 | Factory desactualizada; seeders no idempotentes y dependientes de fechas/códigos | Adecuar factory; separar demostración de carga operativa sin ejecutar seeders en base normal |
| P2 | Sin checks de rango, estados, fechas ni autorrequisitos en esquema real | Auditar datos y añadir restricciones gradualmente, sin eliminar filas inválidas |
| P2 | GET de prematrícula crea borrador y exige periodo abierto incluso para consulta | Mantener compatibilidad inicialmente; proponer creación explícita y consulta de registros fuera de ventana |
| P2 | Elegibilidad carga todo el catálogo/historial; `isAvailable` lo repite por curso al enviar | Reutilizar evaluación dentro de la operación y medir consultas; no introducir caché prematura |

### Desajustes de documentación

`README.md` afirma Laravel 12; está instalado Laravel 13. El README prescribe copiar `.env.example`, pero el archivo no existe en este checkout. Tampoco existe `.env.testing`. `composer.json` contiene instrucciones de setup y creación de proyecto que no deben ejecutarse ciegamente: incluyen migraciones, build frontend y creación de SQLite.

`docs/README.md`, `docs/student.md` y `docs/coordinator.md` describen revisión posterior y turnos, incluyendo `NOCHE`, que el enum real no acepta. El ejemplo de rechazo por cruce de horarios/cupos está fuera del alcance definitivo. `docs/enums-and-types.md` inventa roles `DOCENTE`/`ADMINISTRADOR`, periodos `MATRICULA`/`FINALIZADO`, omite `CERRADO` y `RETIRADO`, y presenta `ENVIADA` como pendiente. Los ejemplos de validación/404/403 no siempre coinciden con el renderizado actual de excepciones; `revisado_por` es condicional a la carga de relación y no está presente uniformemente en todas las respuestas.

En la fase de adaptación se actualizarán esos documentos junto con los endpoints y ejemplos de respuesta. No se deben presentar contratos futuros como ya implementados. Esta fase concentra la auditoría en este plan y conserva la línea base del código.

## 6. Arquitectura objetivo y decisiones

Se mantienen `Models + Enums + UseCases + Services + Form Requests + API Resources`, Eloquent y `/api/v1`. Los controllers manejan HTTP; los casos de uso delimitan transacciones; los servicios comparten reglas académicas y de ventanas. Se reutilizan los modelos actuales, sin repositorios genéricos ni otra capa de persistencia.

| Decisión | Consecuencia y compatibilidad |
|---|---|
| D01. Registrar con `ENVIADA` | No agregar estado `REGISTRADA`; finalizar exitosamente en `ENVIADA` y conservar `fecha_envio` |
| D02. Historial inmutable de revisión | Mantener enums, columnas y relación de revisor para lectura; no escribir nuevas aprobaciones/rechazos ni remapear estados históricos |
| D03. Roles compatibles | Conservar `COORDINADOR` como rol técnico; proponer cargo `DIRECTOR_ESCUELA` o `SECRETARIA` para identificar ambas funciones. No crear rol/cuenta docente ni administrador |
| D04. Reapertura por fechas | Crear ventanas globales del periodo; no permisos individuales ni aprobación para reabrir |
| D05. Una malla | Usar `courses` y `course_prerequisites` como malla única; no crear planes paralelos ni asignar mallas distintas por alumno |
| D06. Adelanto académico | No bloquear por ciclo; exigir prerrequisitos y límite de créditos. `ciclo` ordena y describe el curso |
| D07. Sin planificación horaria | Retirar preferencias de turno del flujo objetivo, conservando valores históricos y default para compatibilidad temporal; no crear secciones/horarios/aulas |
| D08. Atomicidad | Todas las mutaciones de una solicitud usan la misma cabecera bloqueada y datos actualizados; conservar unicidad estudiante/periodo |
| D09. Importaciones verificables | Validación completa previa, informe por fila, aplicación transaccional e idempotencia; no borrar catálogo ni historial para reemplazarlos |
| D10. Constancia estable | PDF de registro, no de matrícula definitiva; snapshot al enviar, autorización de propietario/coordinador, A4 y exactamente una página |
| D11. Demanda explícita | Nuevas estadísticas cuentan estudiantes por curso/periodo con `ENVIADA`, excluyen borradores y rechazos; `APROBADA` se consulta como histórico separado por defecto |
| D12. Carga docente acotada | Docente como dato académico sin `user_id`; asignación docente/curso/periodo con horas semanales, sin matrícula ni horarios |
| D13. Persistencia segura | Migraciones aditivas, backfill controlado y validación antes de restricciones; no eliminación automática de datos ni downgrade destructivo |

### Ventanas y reapertura

Proponer `PreEnrollmentWindow` asociada a `AcademicPeriod`, con apertura/cierre, creador y timestamps. Crear nuevas ventanas preserva fechas previas y habilita globalmente a estudiantes elegibles. Deben evitarse rangos invertidos y ambigüedad entre periodos abiertos. Al mantener la regla actual de un único periodo habilitado, una reapertura no puede habilitar simultáneamente otro periodo objetivo.

Abrir otra ventana no convierte automáticamente solicitudes enviadas en borrador y no cambia estados históricos. Para conservar estrictamente `BORRADOR -> ENVIADA`, el comportamiento base permite completar borradores o crear solicitudes faltantes. Si se requiere corregir registros ya enviados durante la nueva ventana, hace falta acordar un contrato de revisiones/snapshots que conserve el registro anterior; no se añadirá silenciosamente `ENVIADA -> BORRADOR`. Esta decisión académica queda pendiente antes de implementar esa edición.

### Importación Excel

Reutilizar claves naturales: `courses.codigo`, `students.codigo_universitario`, `academic_periods.codigo` y unicidad estudiante/curso/periodo. Conservar códigos como texto para no perder ceros iniciales. Proponer un proceso de validación y aplicación, con huella del archivo, usuario, tipo de importación, resumen y errores por hoja/fila. La trazabilidad de importación no es un proceso de aprobación académica.

Malla: código, nombre, créditos, ciclo, estado y códigos de prerrequisitos; validar referencias, duplicados, autorrequisitos y ciclos del grafo antes de aplicar. No eliminar cursos ausentes en un Excel ni regenerar IDs. Los cambios de relaciones se aplican solo para cursos incluidos explícitamente y con trazabilidad.

Historial/notas: código de alumno, código de curso, periodo, nota y estado; validar referencias y coherencia nota/estado. Reimportar el mismo contenido no duplica intentos; una corrección de nota registra valor anterior/nuevo y autor. No crear alumnos, usuarios o periodos implícitamente cuando una fila tiene referencias desconocidas. La escala, redondeo y umbral de aprobación requieren definición institucional, no se deducen de una nota de ejemplo.

No hay lector Excel instalado: evaluar la biblioteca en esa fase contra PHP/Laravel reales y solicitar autorización antes de cambiar dependencias. Limitar tamaño/filas, guardar archivos en almacenamiento privado y no ejecutar fórmulas/macros ni cargar enlaces externos. Empezar con aplicación transaccional completa; si el volumen exige procesamiento por lotes, mantener idempotencia y publicación consistente del resultado.

### PDF, demanda, estudiantes y docentes

El snapshot debe incluir estudiante, periodo, fecha, cursos/códigos/créditos y total usados al enviar. La biblioteca PDF queda por evaluar y aprobar como dependencia. La constancia debe soportar acentos, nombres largos y la mayor selección permitida por las reglas académicas en una página A4, comprobando tamaño y número de páginas. No truncar cursos ni imponer un máximo arbitrario para hacer caber el documento. Si el tamaño admisible no cabe legiblemente, resolver el formato antes de cerrar esa fase.

Estadísticas: agregación PostgreSQL/Eloquent por periodo y curso, sin conteo duplicado por joins; alumnos únicos, total de registros y créditos con definición de estados visible. Búsqueda paginada por código, nombres y apellidos para coordinadores, validando filtros y orden. No publicar información personal en rutas públicas. Los índices se eligen con datos representativos y planes de consulta, sin añadir extensiones ni motores de búsqueda por defecto.

Carga docente: proponer `Teacher` y `TeachingAssignment`, con nombre/identificador institucional y estado en docente; curso, docente, periodo y horas semanales en asignación. Validar horas positivas y unicidad de la misma asignación. No inferir horas a partir de créditos, no introducir cuentas de acceso y no limitar a un solo docente por curso sin regla académica expresa.

## 7. Migraciones incrementales propuestas

Estos son diseños y nombres orientativos, **no archivos creados**. Confirmar esquema real antes de redactarlos y generarlos con Artisan en sus fases. No editar `up()` de migraciones ya aplicadas para introducir el nuevo modelo.

| Etapa | Cambio propuesto | Backfill, validación y reversión |
|---|---|---|
| M0 | Sin DDL de negocio: provisionar PostgreSQL de tests separado, corregir puerto/guardia/factory y diagnosticar fallos reales | Usuario restringido a esa base; repetir línea base antes de adaptar conducta |
| M1 | Agregar cargo coordinador nullable a `users` | Mapear cuentas existentes por identificación confirmada, sin inferir director/secretaria desde correo. Nuevos coordinadores exigen cargo válido; activar restricción para existentes después del mapeo |
| M2 | Crear `pre_enrollment_windows` con FK restrictiva a periodo/creador, timestamps de inicio/fin e índices | Copiar un par de fechas válido por periodo como ventana inicial, sin inventar ventanas anteriores. Reportar periodos incompletos/invertidos/solapados. Conservar columnas originales mientras se migra lectura |
| M3 | Agregar referencia nullable a ventana de envío y almacenamiento aditivo de snapshot/revisión de registro | Capturar para nuevos envíos. Registros anteriores sin evidencia quedan marcados como snapshot no disponible; no inventar valores originales ni borrar revisión heredada |
| M4 | Crear tablas de trazabilidad de importaciones y de correcciones de historial | Sin reemplazo de `courses`, pivot o `academic_histories`; referencias restrictivas, claves de idempotencia y metadatos sin secretos |
| M5 | Añadir checks de estados/rangos/fechas y prohibición de autorrequisito | Auditar filas existentes; PostgreSQL permite validar checks por etapas (`NOT VALID` y posterior validación). Corregir datos con trazabilidad antes de validar; ciclos se validan además como grafo |
| M6 | Crear `teachers` y `teaching_assignments` | FK restrictivas a curso/periodo/docente, horas positivas y unicidad docente/curso/periodo; no FK de docente a usuario |
| M7 | Índices para demanda/listados/búsqueda tras medición | Candidatos: solicitudes por periodo/estado, detalles por curso y asignaciones por periodo/docente; revisar índices compuestos existentes antes de duplicarlos |
| M8 | Retiro físico eventual de campos legacy | Fuera del despliegue inicial. Solo después de definir archivo histórico, restauración, consumidores y retención; no se propone eliminar ahora revisión, preferencias o fechas antiguas |

La retirada de endpoints de revisión no necesita eliminar columnas ni una migración de estados. Los enums deben seguir leyendo los valores heredados. Los checks futuros de solicitud deben aceptar los cuatro estados para no invalidar historia; impedir nuevas revisiones corresponde al flujo de escritura.

Antes de cada despliegue: backup comprobable, ensayo de restauración en instancia aislada, comparación de IDs/conteos/estados y claves, estimación de bloqueos y prueba de instalación desde cero y actualización desde esquema anterior con datos. No ejecutar `migrate:fresh`, seeders o rollbacks destructivos sobre una base operativa. La eliminación en cascada de detalles y el `SET NULL` del revisor hacen preferible inactivar registros y conservar usuarios revisores.

Los rollbacks de tablas nuevas con datos también son destructivos: favorecer corrección hacia adelante y reversión de aplicación compatible con columnas aditivas. El error del `down()` histórico requiere revisión separada del método inverso; una nueva migración no arregla por sí misma ese rollback antiguo. Nunca validar ese rollback contra la base normal.

## 8. Código que se conserva, adapta o retira

| Área / archivos reales | Tratamiento |
|---|---|
| `app/Models/{User,Student,Course,AcademicPeriod,AcademicHistory,PreEnrollmentRequest,PreEnrollmentDetail}.php` | Conservar relaciones y claves; adaptar casts/cargos/ventanas/snapshots sin perder historial |
| `app/Enums/PreEnrollmentStatus.php` | Conservar los cuatro valores; solo dos participan en nuevas transiciones |
| `app/Enums/{RecordStatus,AcademicHistoryStatus,AcademicPeriodStatus}.php` | Conservar; no añadir matrícula definitiva |
| `app/Enums/UserRole.php` | Conservar rol técnico; identificar cargos coordinadores por separado |
| `GetAvailableCourses`, `AddCourse`, `RemoveCourse`, `GetOrCreateDraft`, `SubmitPreEnrollment` | Conservar casos de uso; adaptar ventanas, concurrencia y elegibilidad |
| `CurrentPreEnrollmentPeriod`, `PreEnrollmentPeriodValidator` | Adaptar para resolver y validar ventanas persistidas desde una regla compartida |
| `ApprovePreEnrollment`, `RejectPreEnrollment`, `PreEnrollmentReviewValidator` | Retirar del código invocable en la fase de adaptación; conservar evidencia en datos y control de versiones |
| `UpdateSchedulePreference`, `SchedulePreference`, Requests de preferencia | Retirar operaciones del flujo nuevo; mantener temporalmente enum/cast y dato histórico mientras el modelo los lea |
| `RejectPreEnrollmentRequest` y métodos `approve`/`reject` del controller coordinador | Retirar junto con rutas antiguas |
| Controllers API estudiante/coordinador, `ListPreEnrollmentsRequest` | Adaptar al registro/consulta; corregir import; filtros por periodo/estudiante, sin bandeja pendiente |
| Resources actuales | Conservar estructura v1 inicialmente; campos de revisión quedan de compatibilidad histórica y no significan paso obligatorio |
| Login web/API, Sanctum y middleware | Conservar mecanismos; reforzar actividad, autorización y pruebas. No modificar renderizado web |
| Seeders y factories | Conservar finalidad de demostración; reparar factory y preparar datos deterministas de prueba |
| `routes/api.php` | Conservar prefijo/URI útiles; añadir nombres, retirar revisión y preferencia sin crear aliases que ejecuten operaciones obsoletas |
| README y documentos de API | Actualizar en fase funcional para reflejar contratos comprobados |

Al retirar revisión y preferencias, las antiguas URI dejarán de ejecutar mutaciones y responderán 404, salvo que se acuerde explícitamente una respuesta de deprecación 410. Añadir tests que confirmen ausencia de cambios de estado. No crear redirecciones de aprobación a envío ni endpoints de rechazo que simulen éxito.

## 9. Secuencia de trabajo y criterios de aceptación

1. **Infraestructura de pruebas y defectos base.** Provisionar base separada; fijar puerto y protección de entorno; reparar factory/import; verificar rollback aislado y obtener resultados funcionales reales. No confundir los errores actuales de infraestructura con defectos de comportamiento.
2. **Registro sin aprobación.** Conservar `submit`, desactivar revisión y turnos, endurecer concurrencia/actividad y actualizar documentación y tests en el mismo cambio. Aceptación: envío válido persiste `ENVIADA` y fecha sin revisor; histórico se consulta intacto; no se generan nuevos `APROBADA`/`RECHAZADA`.
3. **Coordinadores y ventanas.** Cargos de director/secretaria, gestión de ventanas globales, límites temporales y migración de fechas existentes. Resolver antes la edición de solicitudes ya enviadas. Aceptación: apertura/cierre y reapertura global sin permisos individuales, sin periodos ambiguos.
4. **Malla e historial Excel.** Acordar formatos y política académica; aprobar dependencias; validar y aplicar con trazabilidad. Aceptación: errores por fila sin cambios parciales, reimportación sin duplicados, intentos y notas anteriores conservados, grafo válido.
5. **Elegibilidad y constancia.** Consolidar reglas por periodo y captura de snapshot; generar PDF. Aceptación: adelanto por requisitos/créditos y PDF A4 de una página estable tras modificar el catálogo, accesible solo a quien corresponda.
6. **Consulta y demanda.** Búsqueda estudiantil y agregaciones por periodo/curso. Aceptación: estados y contadores correctos, sin borradores ni doble conteo, paginación y permisos comprobados.
7. **Carga docente.** Asignación curso/docente/periodo y horas semanales. Aceptación: validación de horas/unicidad, sin cuentas docentes, secciones, horarios, aulas ni matrícula definitiva.

### Adaptación futura de tests

No se modificaron tests para maquillar el resultado inicial. En la siguiente fase, conservar pruebas útiles de login, elegibilidad, créditos y envío. Sustituir los cinco tests de `ReviewPreEnrollmentTest` que habilitan el flujo anterior por cobertura de endpoints retirados, imposibilidad de nuevas revisiones y lectura intacta de fixtures `APROBADA`/`RECHAZADA`. Los fixtures históricos se insertan como datos, sin ejecutar aprobaciones nuevas. Adaptar las pruebas de preferencia a selección sin turnos y verificar preservación histórica.

Añadir cobertura de login/me/logout con tokens reales, usuario desactivado después de autenticarse, perfil ausente/inactivo, ambos cargos coordinadores, listado coordinador exitoso y filtros inválidos, propiedad de detalles y constancias, registros fuera de ventana, límites exactos de fechas, múltiples ventanas/periodos, duplicados, exceso de créditos al enviar y concurrencia. Mantener `GetAvailableCoursesTest` sobre prerrequisito `EN_CURSO` mientras no se apruebe otra política académica.

Para migraciones, ensayar contra copia sintética PostgreSQL con todos los estados heredados, notas, revisores y preferencias; comprobar conservación y restricciones, además de instalación limpia. Congelar tiempo en tests temporales y usar factories reparadas para datos nuevos. No usar SQLite como sustituto ni ejecutar la suite en la base de desarrollo. Ejecutar el conjunto mínimo relevante tras cada cambio; los PHP modificados requieren `vendor/bin/pint --dirty --format agent`.

## 10. Resultados reales de tests iniciales

### Aislamiento comprobado

Antes de ejecutar se revisaron `phpunit.xml`, `tests/TestCase.php`, configuración y los siete Feature test files: todos usan `RefreshDatabase`; no se encontraron conexiones alternativas ni consultas deliberadas a otra base en tests. No existe `bootstrap/cache/config.php` en el checkout inspeccionado.

Se cargaron las variables declaradas por PHPUnit en el entorno del proceso hijo, se fijó `DB_PORT=5432` explícitamente y se arrancó Laravel para verificar `APP_ENV=testing`, conexión predeterminada `pgsql`, base configurada `prematricula_test`, URL de conexión vacía y ausencia de caché de configuración. Esta fijación fue solo del proceso, sin editar `.env` o `phpunit.xml`.

La conexión de lectura al destino de pruebas devolvió `FATAL: database "prematricula_test" does not exist`. Mediante Boost se verificó que el servidor solo lista `postgres` y `prematricula` como bases no plantilla; `prematricula_user` tiene `rolcreatedb=false` y `rolsuper=false`. No se elevan permisos ni se usa la base normal como alternativa. La suite se lanzó de todas formas con el destino de pruebas fijado para registrar sus errores de preparación reales.

### Ejecución y salida

Runner: `vendor/bin/phpunit` con `phpunit.xml`, invocado desde un proceso PHP que fija y verifica el entorno indicado. Resultado real del runner, código de salida **2**:

```json
{
  "tool": "phpunit",
  "result": "failed",
  "tests": 30,
  "passed": 1,
  "assertions": 1,
  "duration_ms": 771,
  "errors": 29
}
```

| Archivo | Tests | Resultado |
|---|---:|---|
| `tests/Unit/ExampleTest.php` | 1 | Pasa; solo verifica `true`, sin valor funcional académico |
| `tests/Feature/Auth/AuthenticationTest.php` | 7 | Errores al preparar PostgreSQL |
| `tests/Feature/Api/Student/PreEnrollmentApiTest.php` | 4 | Errores al preparar PostgreSQL |
| `tests/Feature/Api/Coordinator/PreEnrollmentApiTest.php` | 1 | Error al preparar PostgreSQL |
| `tests/Feature/UseCases/PreEnrollment/AddCourseTest.php` | 5 | Errores al preparar PostgreSQL |
| `tests/Feature/UseCases/PreEnrollment/GetAvailableCoursesTest.php` | 3 | Errores al preparar PostgreSQL |
| `tests/Feature/UseCases/PreEnrollment/ReviewPreEnrollmentTest.php` | 5 | Errores al preparar PostgreSQL |
| `tests/Feature/UseCases/PreEnrollment/SubmitPreEnrollmentTest.php` | 4 | Errores al preparar PostgreSQL |

Error común: `SQLSTATE[08006] [7] ... FATAL: database "prematricula_test" does not exist`, al comprobar la tabla `migrations`. **No son 29 aserciones de negocio fallidas: los tests Feature no alcanzaron su comportamiento objetivo.** No se puede afirmar que el backend funciona ni que los defectos estáticos son los únicos pendientes.

Dos intentos previos con Artisan no ejecutaron tests: `php artisan test --compact --no-interaction` trasladó la opción a PHPUnit y produjo `Unknown option "--no-interaction"`; colocando la opción antes de `test` produjo `Test file "test" not found`. Se usó el runner PHPUnit directo permitido por el proyecto, sin modificar código para sortear esos problemas.

### Reproducción segura de la ejecución realizada

Desde la raíz, este comando carga las credenciales ya configuradas sin imprimirlas y fija el destino exclusivamente de pruebas. La base debe seguir siendo desechable; `RefreshDatabase` puede reconstruir sus tablas. El comando no crea archivos ni bases:

```bash
php -r '
require "vendor/autoload.php";
$xml = simplexml_load_file("phpunit.xml");
foreach ($xml->php->env as $item) {
    $name = (string) $item["name"];
    $value = (string) $item["value"];
    putenv($name."=".$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}
putenv("DB_PORT=5432");
$_ENV["DB_PORT"] = $_SERVER["DB_PORT"] = "5432";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if ($app->configurationIsCached()
    || $app->environment() !== "testing"
    || config("database.default") !== "pgsql"
    || config("database.connections.pgsql.database") !== "prematricula_test"
    || config("database.connections.pgsql.url")) {
    throw new RuntimeException("Aislamiento de pruebas no verificado");
}
passthru("vendor/bin/phpunit", $status);
exit($status);
'
```

Pendiente operativo para obtener una línea base funcional: un administrador PostgreSQL debe crear `prematricula_test` con propietario autorizado para migrar en esa base. No es necesario conceder `CREATEDB` o superusuario al usuario de aplicación. Como mejora posterior, separar también el usuario de pruebas y sus permisos del usuario de la base normal.

## 11. Definiciones pendientes antes de sus fases

- Identificación de cuáles cuentas coordinadoras son director y secretaria; no hay datos suficientes para mapearlas automáticamente.
- Alcance de reapertura sobre registros ya enviados, conservando auditoría y sin inventar una transición de aprobación.
- Prerrequisitos `EN_CURSO`: mantener criterio actual o exigir aprobación; periodo válido para considerar el historial. No confundir aprobación de cursos con aprobación de prematrículas.
- Formatos reales de Excel, escala/redondeo/umbral de notas, política de correcciones y volumen esperado.
- Identificador docente y precisión permitida de horas semanales; reparto entre docentes sin secciones.
- Plantilla/datos institucionales de constancia, tratamiento de registros históricos sin snapshot y política de retención.

Estas definiciones no impiden entregar la auditoría. Sí deben resolverse antes de implementar la parte que dependa de ellas; no justifican ampliar el alcance a módulos excluidos.

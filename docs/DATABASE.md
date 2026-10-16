# Base de datos

PostgreSQL es obligatorio. El entorno de pruebas usa exclusivamente `prematricula_test`, separado del desarrollo y producción. La base debe existir antes de migrar.

## Entidades

| Tabla | Responsabilidad y restricciones principales |
| --- | --- |
| users | Correo único, contraseña hash, rol, estado y cargo; Sanctum relaciona tokens con usuarios. |
| students | Un perfil por usuario, código universitario único y textual, nombres, créditos máximos y estado. |
| courses | Malla única; código único, ciclo, créditos y horas semanales independientes por tipo. |
| course_prerequisites | Relación dirigida única; los casos de uso rechazan ciclos y autorreferencias. |
| academic_periods | Código único, fechas de clases, estado y autores. |
| pre_enrollment_windows | Ventanas globales; una ordinaria por período, rango válido, tipo válido y autor. |
| academic_histories | Un resultado por estudiante/curso/período, nota y estado oficial. |
| academic_history_imports / changes | Archivo hash, autor, motivo, resumen y cambios before/after. |
| pre_enrollment_requests | Una solicitud por estudiante/período, estado, revisión vigente y contexto de corrección. |
| pre_enrollment_details | Borrador mutable; curso único dentro de la solicitud y preferencia. |
| pre_enrollment_revisions | Snapshot JSONB, versión, fecha, recibo y PDF; número único por solicitud e inmutabilidad. |
| pre_enrollment_revision_courses | Índice relacional único de cursos contenidos en cada revisión. |
| pre_enrollment_validations | Validación actual de la solicitud y revisión a la que corresponde. |
| teachers | Código institucional único; estado, contacto opcional y máximo semanal opcional. Sin user_id. |
| teaching_assignments | Período/curso/docente, horas, autores y baja lógica. Única combinación vigente. |
| teaching_assignment_changes | Actor, acción, before/after e instante de cada cambio; consulta incluso tras la baja. |
| personal_access_tokens | Hash de token Sanctum y expiración; nunca almacena el token de uso en texto claro. |

También existen tablas estándar de caché, sesiones y colas según la configuración de Laravel.

## Integridad y precisión

Las claves foráneas restrictivas conservan referencias a cursos, docentes, períodos, autores y revisiones. La FK compuesta de `current_revision_id` asegura que la revisión pertenezca a su solicitud. Los snapshots nuevos enviados tienen restricciones de completitud; las capturas heredadas no inventan evidencia histórica.

Las horas usan NUMERIC con escala 2. Las asignaciones tienen CHECK para valores no negativos y suma positiva; un índice único parcial excluye bajas lógicas. Las validaciones de agregados por curso/docente se hacen dentro de transacciones con bloqueo, no con verificaciones previas vulnerables a carreras.

Los índices cubren identidad, relaciones únicas, período/revisión vigente, período/docente, curso/período y auditoría por asignación/ID. Los reportes agregan en SQL y paginan sus filas. No existen tablas de secciones, aulas, grupos, horarios o matrícula definitiva.

## Historial y migraciones

Aplicar cambios de esquema con:

```bash
php artisan migrate --no-interaction
php artisan migrate:status --no-interaction
```

No editar migraciones ya aplicadas a un entorno compartido. No usar reset/fresh/rollback en desarrollo o producción: los comandos destructivos se bloquean fuera del test aislado. Diseñar correcciones hacia delante y realizar backups antes de desplegar.

Las migraciones antiguas convierten ventanas heredadas usando `ACADEMIC_LEGACY_TIMEZONE`. Antes de una primera actualización de una instalación heredada, comprobar la zona real en que fueron almacenadas sus fechas sin timezone. Los registros no documentados no se convierten en PDFs históricos inventados.

Los períodos referenciados conservan código y fechas; cursos con historial, borradores, revisiones o asignaciones (incluidas bajas) conservan definición académica. Un período cerrado mantiene asignaciones sin altas, cambios o bajas. Desactivar un docente conserva todas sus asignaciones; una baja de asignación abierta libera horas pero conserva fila y auditoría.

## Preparación de bases

Ejemplo administrativo para desarrollo local; sustituir la clave local:

```sql
CREATE USER prematricula WITH PASSWORD 'replace_local_password';
CREATE DATABASE prematricula OWNER prematricula;
CREATE USER prematricula_test WITH PASSWORD 'testing_only';
CREATE DATABASE prematricula_test OWNER prematricula_test;
```

La cuenta de test debe poder crear/eliminar tablas de su base aislada. Nunca apuntar `.env.testing` a una base con datos útiles. El guard de PHPUnit comprueba la conexión efectiva, incluso si existe DB_URL.

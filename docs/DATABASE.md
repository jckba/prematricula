# Base de datos

El motor soportado es MariaDB 10.6+ (producción, Hostinger) o MySQL 8.0+, con `utf8mb4` y collation `utf8mb4_unicode_ci`. El entorno de pruebas usa exclusivamente `prematricula_test`, separado del desarrollo y producción. La base debe existir antes de migrar.

## Entidades

| Tabla | Responsabilidad y restricciones principales |
| --- | --- |
| users | Correo único, contraseña hash, rol, estado y cargo; Sanctum relaciona tokens con usuarios. |
| students | Un perfil por usuario, código universitario único y textual, nombres, créditos máximos y estado. |
| courses | Malla única; código único, ciclo, créditos y horas semanales independientes por tipo. |
| course_prerequisites | Relación dirigida única; los casos de uso rechazan ciclos y autorreferencias. |
| academic_periods | Código único, fechas de clases, estado y autores. |
| pre_enrollment_windows | Ventanas globales; una ordinaria por período (columna generada `ordinary_period_id` + índice único), rango válido, tipo válido y autor. |
| academic_histories | Un resultado por estudiante/curso/período, nota y estado oficial. |
| academic_history_imports / changes | Archivo hash, autor, motivo, resumen y cambios before/after. |
| pre_enrollment_requests | Una solicitud por estudiante/período, estado, revisión vigente y contexto de corrección. |
| pre_enrollment_details | Borrador mutable; curso único dentro de la solicitud y preferencia. |
| pre_enrollment_revisions | Snapshot JSON, versión, fecha, recibo y PDF (`LONGTEXT`); número único por solicitud e inmutabilidad (triggers). |
| pre_enrollment_revision_courses | Índice relacional único de cursos contenidos en cada revisión. |
| pre_enrollment_validations | Validación actual de la solicitud y revisión a la que corresponde. |
| teachers | Código institucional único; estado, contacto opcional y máximo semanal opcional. Sin user_id. |
| teaching_assignments | Período/curso/docente, horas, autores y baja lógica. Única combinación vigente. |
| teaching_assignment_changes | Actor, acción, before/after e instante de cada cambio; consulta incluso tras la baja. |
| process_locks | Una fila por proceso serializado (`curriculum_catalog`, `academic_process`); se bloquea con `SELECT ... FOR UPDATE`. |
| personal_access_tokens | Hash de token Sanctum y expiración; nunca almacena el token de uso en texto claro. |

También existen tablas estándar de caché, sesiones y colas según la configuración de Laravel.

## Integridad y precisión

Las claves foráneas restrictivas conservan referencias a cursos, docentes, períodos, autores y revisiones. La FK compuesta de `current_revision_id` asegura que la revisión pertenezca a su solicitud. Los snapshots nuevos enviados tienen restricciones de completitud; las capturas heredadas no inventan evidencia histórica.

Las horas usan DECIMAL con escala 2. Las asignaciones tienen CHECK para valores no negativos y suma positiva; un índice único excluye bajas lógicas (las migraciones de docentes y asignaciones no están en esta rama; al reincorporarlas, el índice parcial debe expresarse con una columna generada, como en `pre_enrollment_windows`). Las validaciones de agregados por curso/docente se hacen dentro de transacciones con bloqueo, no con verificaciones previas vulnerables a carreras.

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

## Decisiones de portabilidad (antes PostgreSQL)

| PostgreSQL | MariaDB/MySQL | Por qué |
| --- | --- | --- |
| `pg_advisory_xact_lock(n)` | Fila de `process_locks` bloqueada con `SELECT ... FOR UPDATE` (`App\Services\TransactionLock`) | Ambos serializan transacciones y se liberan solos en COMMIT/ROLLBACK. |
| Aislamiento READ COMMITTED (por defecto) | `isolation_level = READ COMMITTED` en la conexión | InnoDB usa REPEATABLE READ por defecto; se mantiene el comportamiento con el que se escribió el código. |
| Índice único parcial `WHERE tipo = ORDINARIA` | Columna generada `ordinary_period_id` + índice único | No existen índices parciales; `UNIQUE` ignora los `NULL` de las ventanas extraordinarias. |
| Función/trigger plpgsql | Dos triggers `BEFORE UPDATE`/`BEFORE DELETE` con `SIGNAL SQLSTATE 45000` | Un trigger MySQL atiende un solo evento. |
| `snapshot ?& array[...]` en un CHECK | `JSON_CONTAINS_PATH(snapshot, all, ...)` | Operador exclusivo de jsonb. |
| `ILIKE` | `LIKE` vía `whereLike()` | `utf8mb4_unicode_ci` ya ignora mayúsculas y tildes. |
| `jsonb`, `LATERAL`, `jsonb_array_elements`, `DISTINCT ON`, `FILTER` | `json`, `JSON_EXTRACT`/`JSON_LENGTH` con una tabla derivada de posiciones, `COUNT(CASE WHEN ...)` | MariaDB < 10.6 no tiene `LATERAL` ni `JSON_TABLE`. |
| `timestamptz` | `DATETIME` en UTC | `TIMESTAMP` acaba en 2038, depende de la zona de sesión y, en MariaDB < 10.10, recibe `ON UPDATE CURRENT_TIMESTAMP` implícito. |
| `text` | `LONGTEXT` para `pdf_base64` | `TEXT` de MySQL admite solo 64 KB. |
| Nombres de índice de hasta 63 caracteres (truncados) | Nombres explícitos de hasta 64 | MySQL rechaza identificadores más largos. |

Consecuencias a tener presentes:

- **Mayúsculas en índices únicos.** Con `utf8mb4_unicode_ci`, `MAT01` y `mat01` (o `ana@unitru.edu.pe` y `ANA@unitru.edu.pe`) cuentan como el mismo valor. En PostgreSQL eran distintos. Para correos y códigos es lo correcto, y la regla `unique:` de Laravel consulta con la misma collation, por lo que valida igual que el índice.
- **El DDL no es transaccional.** `CREATE`/`ALTER`/`DROP` hacen COMMIT implícito. Las pruebas no deben alterar el esquema dentro de una transacción de `RefreshDatabase`; para simular un fallo de base de datos se usa `failDatabaseWrite()` de `tests/TestCase.php`.
- **Triggers en hosting compartido.** Crear triggers requiere el privilegio `TRIGGER`. El `.sql` de despliegue los deja al final y sin cláusula `DEFINER`. La aplicación también rechaza modificar revisiones desde el modelo, por lo que el trigger es una segunda barrera.

## Preparación de bases

Ejemplo administrativo para desarrollo local; sustituir la clave local:

```sql
CREATE DATABASE prematricula CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'prematricula'@'localhost' IDENTIFIED BY 'replace_local_password';
GRANT ALL PRIVILEGES ON prematricula.* TO 'prematricula'@'localhost';

CREATE DATABASE prematricula_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'prematricula_test'@'localhost' IDENTIFIED BY 'testing_only';
GRANT ALL PRIVILEGES ON prematricula_test.* TO 'prematricula_test'@'localhost';
```

La cuenta de test debe poder crear/eliminar tablas y triggers de su base aislada. Nunca apuntar `.env.testing` a una base con datos útiles. El guard de PHPUnit comprueba la conexión efectiva, incluso si existe DB_URL.

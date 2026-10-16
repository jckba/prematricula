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

Aplicar la migración aditiva `add_cargo_to_users_table` con `php artisan migrate --no-interaction`. No se ejecutó sobre la base habitual durante esta implementación.

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

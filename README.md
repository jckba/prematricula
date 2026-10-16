# Sistema de Prematrícula Académica (Backend API)

API RESTful desarrollada en **Laravel 13 (PHP 8.4)** y **PostgreSQL** para la gestión integral del proceso de prematrícula universitaria.

Permite a los estudiantes autenticarse, consultar cursos elegibles sin restricción por ciclo, editar un borrador con preferencias de turno y registrar su prematrícula. Cada envío genera una revisión inmutable y su constancia PDF A4. No requiere aprobación individual. Las correcciones explícitas se realizan durante ventanas extraordinarias globales, conservando las revisiones anteriores.

---

## 📚 Documentación para Desarrolladores Frontend

Toda la documentación detallada para la integración del cliente frontend se encuentra en la carpeta [`docs/`](./docs/README.md):

- **[Índice y Guía General](./docs/README.md)**: Convenciones HTTP, cabeceras, formato estándar de respuestas y manejo de errores.
- **[Módulo de Autenticación](./docs/auth.md)**: Login (`/api/v1/login`), consulta de usuario autenticado (`/api/v1/me`) y logout (`/api/v1/logout`).
- **[Módulo de Estudiante](./docs/student.md)**: Elegibilidad, borrador, envío, correcciones, revisiones y PDF histórico.
- **[Módulo de Coordinador](./docs/coordinator.md)**: Consulta de registros, validación académica, revisiones y constancias; sin aprobar/rechazar.
- **[Malla curricular y catálogo](./docs/curriculum.md)**: Consulta de cursos, horas semanales, prerrequisitos e importación/exportación Excel.
- **[Períodos, ventanas y resultados](./docs/academic-process.md)**: Gestión del calendario, reaperturas globales, historial XLSX y revalidación académica.
- **[Contrato OpenAPI](./OpenAPI/openapi.yaml)** y **[colección Postman](./OpenAPI/Prematricula.postman_collection.json)**: Autenticación y operaciones del catálogo.
- **[Enums y Tipos TypeScript](./docs/enums-and-types.md)**: Enumeraciones del backend e interfaces TypeScript listas para usar.

---

## 🛠️ Stack Tecnológico

- **Lenguaje:** PHP 8.4
- **Framework:** Laravel 13
- **Base de Datos:** PostgreSQL
- **Autenticación:** Laravel Sanctum (Bearer Token)
- **Testing:** PHPUnit
- **Formateo de Código:** Laravel Pint

---

## 📋 Requisitos Previos

- PHP >= 8.3 (desarrollo y pruebas con 8.4; extensiones `pdo_pgsql`, `mbstring`, `openssl`, `bcmath`, `curl`, `simplexml`, `libxml`, `zlib`)
- Composer >= 2.x
- PostgreSQL >= 14
- Git

---

## 🚀 Instalación y Puesta en Marcha

1. **Clonar el repositorio:**
   ```bash
   git clone <URL_REPOSITORIO>
   cd prematricula
   ```

2. **Instalar dependencias de PHP:**
   ```bash
   composer install
   ```

3. **Configurar el entorno:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configurar la base de datos en `.env`:**
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=prematricula
   DB_USERNAME=tu_usuario_postgres
   DB_PASSWORD=tu_password
   ```

5. **Ejecutar migraciones y seeders iniciales:**
   ```bash
   php artisan migrate --seed
   ```

6. **Iniciar el servidor de desarrollo:**
   ```bash
   php artisan serve
   ```
   La API estará disponible en `http://localhost:8000/api/v1`.

---

## 🧪 Pruebas Automatizadas

Crear una base PostgreSQL separada `prematricula_test` y configurar sus credenciales en el entorno de pruebas. Nunca apuntar estas pruebas a la base habitual: ejecutan migraciones y escrituras. `DB_HOST`, `DB_PORT`, `DB_USERNAME` y `DB_DATABASE` admiten sobrescritura por entorno.

Ejecutar la suite completa de pruebas:
```bash
php artisan test
```

Ejecutar pruebas específicas de la API:
```bash
php artisan test tests/Feature/Api/Student/PreEnrollmentApiTest.php
php artisan test tests/Feature/Api/Coordinator/PreEnrollmentApiTest.php
```

Pruebas de malla curricular y catálogo:
```bash
php artisan test --compact tests/Feature/Api/CourseCatalogTest.php tests/Feature/Api/Coordinator/CurriculumImportTest.php tests/Feature/Api/Coordinator/UpdateCourseTest.php tests/Feature/Policies/CoursePolicyTest.php
```

Formateo y análisis de estilo con Pint:
```bash
vendor/bin/pint --format agent
```

---

## 🗺️ Resumen de Rutas de la API (`/api/v1`)

Períodos e historial: consultar [rutas, formatos y reglas](./docs/academic-process.md). La migración aditiva convierte fechas heredadas en ventanas ordinarias sin borrarlas; configurar `ACADEMIC_LEGACY_TIMEZONE` (antes UTC) y `ACADEMIC_TIMEZONE` (predeterminado America/Lima) antes de aplicarla. Ejecutar después `php artisan academic:revalidate --no-interaction` para inicializar solicitudes existentes. No se añaden permisos individuales de reapertura ni aprobación en esta fase.

### Malla curricular y catálogo

Existe una sola malla vigente, sin versiones de planes. Las horas son **académicas semanales**, admiten dos decimales (`decimal(5,2)`) y la API las devuelve como texto decimal. El ciclo no restringe por sí solo la elegibilidad para prematrícula.

| Método | Ruta | Acceso |
|---|---|---|
| GET | `/api/v1/courses` | Usuario activo; estudiante ve cursos activos |
| GET | `/api/v1/courses/{course}` | Usuario activo; estudiante ve cursos activos |
| PATCH | `/api/v1/coordinator/courses/{course}` | Coordinador |
| GET | `/api/v1/coordinator/curriculum/template` | Coordinador |
| POST | `/api/v1/coordinator/curriculum/validate` | Coordinador |
| POST | `/api/v1/coordinator/curriculum/import` | Coordinador |
| GET | `/api/v1/coordinator/curriculum/export` | Coordinador |

Los archivos `.xlsx` tienen ocho columnas obligatorias, con prerrequisitos separados por `;`. La confirmación exige el mismo archivo, su SHA-256 y la huella de catálogo devueltos por la validación previa. Los cambios se revalidan y persisten en una transacción. Repetir una importación no duplica cursos ni relaciones.

Un curso usado en historial académico o cualquier prematrícula (incluso borrador) conserva sus datos académicos y prerrequisitos; solo puede cambiar su estado. Los cursos omitidos en un archivo no se eliminan ni modifican. La migración incremental añade horas con valor inicial `0.00` (sin carga previa), sin reconstruir ni eliminar registros históricos.

En instalaciones existentes: `composer install` y `php artisan migrate --no-interaction`. Se usan `shuchkin/simplexlsx` 1.1 y `shuchkin/simplexlsxgen` 1.5, compatibles con el PHP instalado sin exigir `ext-zip` ni `gd`. Consultar [formato, límites, errores y política de actualización](./docs/curriculum.md).

### Autenticación
| Método | Ruta | Rol / Middleware | Descripción |
|---|---|---|---|
| `POST` | `/api/v1/login` | Público | Autenticación con credenciales institucionales |
| `GET` | `/api/v1/me` | `auth:sanctum` | Obtiene datos del usuario en sesión |
| `POST` | `/api/v1/logout` | `auth:sanctum` | Invalida el token actual |

### Estudiante (`/api/v1/student`)
| Método | Ruta | Middleware | Descripción |
|---|---|---|---|
| `GET` | `/api/v1/student/pre-enrollment/available-courses` | `auth:sanctum`, `role:ESTUDIANTE` | Cursos disponibles y límite de créditos |
| `GET` | `/api/v1/student/pre-enrollment` | `auth:sanctum`, `role:ESTUDIANTE` | Obtener / crear borrador de prematrícula |
| `GET` | `/api/v1/student/pre-enrollments` | `auth:sanctum`, `role:ESTUDIANTE` | Historial de solicitudes del estudiante |
| `POST` | `/api/v1/student/pre-enrollment/courses` | `auth:sanctum`, `role:ESTUDIANTE` | Agregar curso al borrador con turno |
| `PATCH` | `/api/v1/student/pre-enrollment/courses/{detail}/preference` | `auth:sanctum`, `role:ESTUDIANTE` | Cambiar preferencia de turno |
| `DELETE` | `/api/v1/student/pre-enrollment/courses/{detail}` | `auth:sanctum`, `role:ESTUDIANTE` | Quitar curso del borrador |
| `POST` | `/api/v1/student/pre-enrollment/submit` | `auth:sanctum`, `role:ESTUDIANTE` | Registrar envío/reenvío y constancia |

### Coordinador (`/api/v1/coordinator`)
| Método | Ruta | Middleware | Descripción |
|---|---|---|---|
| `GET` | `/api/v1/coordinator/pre-enrollments` | `auth:sanctum`, `role:COORDINADOR` | Listado paginado con filtro por estado |
| `GET` | `/api/v1/coordinator/pre-enrollments/{id}` | `auth:sanctum`, `role:COORDINADOR` | Detalle completo de una solicitud |

Las rutas de aprobar/rechazar fueron retiradas (404); las decisiones históricas siguen consultables. Para creación explícita de borrador, corrección, revisiones y descarga de PDF, ver [contrato definitivo del estudiante](./docs/student.md). Cada reenvío exitoso actualiza la revisión vigente y fecha de envío; un borrador de corrección nunca reemplaza el último registro hasta completarse.

Las constancias usan generación local Dompdf 3.1.6, sin servicios externos. Configurar `INSTITUTION_UNIVERSITY`, `INSTITUTION_SCHOOL` y `ACADEMIC_TIMEZONE` antes de nuevos envíos; esos valores quedan en el snapshot. Las pruebas de formato, conteo real de páginas y contenido necesitan `pdfinfo`/`pdftotext` (Poppler), únicamente en tests. No se reconstruyen PDFs históricos a partir de datos actuales.

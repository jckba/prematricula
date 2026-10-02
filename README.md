# Sistema de Prematrícula Académica (Backend API)

API RESTful desarrollada en **Laravel 12 (PHP 8.4)** y **PostgreSQL** para la gestión integral del proceso de prematrícula universitaria.

Permite a los estudiantes autenticarse, consultar los cursos habilitados según su historial académico y prerrequisitos, armar y editar su borrador de prematrícula con preferencias de turno, y enviar la solicitud. Posteriormente, los coordinadores académicos pueden revisar, aprobar o rechazar las solicitudes recibidas.

---

## 📚 Documentación para Desarrolladores Frontend

Toda la documentación detallada para la integración del cliente frontend se encuentra en la carpeta [`docs/`](./docs/README.md):

- **[Índice y Guía General](./docs/README.md)**: Convenciones HTTP, cabeceras, formato estándar de respuestas y manejo de errores.
- **[Módulo de Autenticación](./docs/auth.md)**: Login (`/api/v1/login`), consulta de usuario autenticado (`/api/v1/me`) y logout (`/api/v1/logout`).
- **[Módulo de Estudiante](./docs/student.md)**: Cursos disponibles, borrador de prematrícula, selección de cursos, preferencias de turno, envío e historial.
- **[Módulo de Coordinador](./docs/coordinator.md)**: Listado con filtros, detalle de solicitudes, aprobación y rechazo con comentarios.
- **[Enums y Tipos TypeScript](./docs/enums-and-types.md)**: Enumeraciones del backend e interfaces TypeScript listas para usar.

---

## 🛠️ Stack Tecnológico

- **Lenguaje:** PHP 8.4
- **Framework:** Laravel 12
- **Base de Datos:** PostgreSQL
- **Autenticación:** Laravel Sanctum (Bearer Token)
- **Testing:** PHPUnit
- **Formateo de Código:** Laravel Pint

---

## 📋 Requisitos Previos

- PHP >= 8.3 / 8.4 (con extensiones `pdo_pgsql`, `mbstring`, `openssl`, `bcmath`, `curl`)
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

Ejecutar la suite completa de pruebas:
```bash
php artisan test
```

Ejecutar pruebas específicas de la API:
```bash
php artisan test tests/Feature/Api/Student/PreEnrollmentApiTest.php
php artisan test tests/Feature/Api/Coordinator/PreEnrollmentApiTest.php
```

Formateo y análisis de estilo con Pint:
```bash
vendor/bin/pint --format agent
```

---

## 🗺️ Resumen de Rutas de la API (`/api/v1`)

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
| `POST` | `/api/v1/student/pre-enrollment/submit` | `auth:sanctum`, `role:ESTUDIANTE` | Enviar solicitud para revisión |

### Coordinador (`/api/v1/coordinator`)
| Método | Ruta | Middleware | Descripción |
|---|---|---|---|
| `GET` | `/api/v1/coordinator/pre-enrollments` | `auth:sanctum`, `role:COORDINADOR` | Listado paginado con filtro por estado |
| `GET` | `/api/v1/coordinator/pre-enrollments/{id}` | `auth:sanctum`, `role:COORDINADOR` | Detalle completo de una solicitud |
| `POST` | `/api/v1/coordinator/pre-enrollments/{id}/approve` | `auth:sanctum`, `role:COORDINADOR` | Aprobar prematrícula enviada |
| `POST` | `/api/v1/coordinator/pre-enrollments/{id}/reject` | `auth:sanctum`, `role:COORDINADOR` | Rechazar prematrícula con comentario |

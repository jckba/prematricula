# Prematrícula y gestión de carga académica — backend

API REST versionada en `/api/v1`, desarrollada con Laravel 13, PostgreSQL y PHP 8.4.1 o posterior. La plataforma de Composer se fija en 8.4.1; la comprobación local también utiliza PHP 8.5.

El alcance incluye autenticación por correo o código, coordinadores director/secretaría, una malla curricular, importaciones XLSX, resultados oficiales, ventanas globales, prematrículas y correcciones versionadas, PDF de una página, consulta académica, demanda estimada y carga docente semanal. Los docentes no tienen cuentas. No existen secciones, horarios, aulas, matrícula definitiva ni aprobación/rechazo del flujo nuevo. El frontend existente no forma parte de esta entrega.

## Inicio

Requisitos: PHP con las extensiones declaradas en Composer, Composer 2, PostgreSQL 16 recomendado y Poppler (`pdfinfo`, `pdftotext`, `pdftoppm`) para pruebas de PDF. Python 3.14 se usa únicamente para validar documentación; no es una dependencia de ejecución del backend.

```bash
composer install
cp .env.example .env
php artisan key:generate --no-interaction
# Crear la base PostgreSQL y configurar DB_* antes de continuar.
php artisan migrate --no-interaction
php artisan db:seed --no-interaction
php artisan serve --host=127.0.0.1
```

En PowerShell, `Copy-Item .env.example .env` reemplaza a `cp`. Los seeders de demostración funcionan solo en `local` o `testing` y no se ejecutan automáticamente al instalar dependencias. No se necesita Node, npm ni compilar frontend para usar o probar la API.

Las cuentas locales de demostración son `estudiante@unitru.edu.pe` (también código `1452700722`), `coordinador@unitru.edu.pe` y `secretaria.demo@unitru.edu.pe`, con contraseña de demostración `password`. Nunca se crean en producción ni se reinician credenciales existentes al repetir los seeders.

## Calidad

Crear una base **independiente**, llamada exactamente `prematricula_test`, y copiar/configurar `.env.testing.example` como `.env.testing`.

```bash
composer validate --strict --no-check-publish
composer audit --locked
composer lint
composer analyse
composer test
python -m pip install -r requirements-quality.txt
python -m openapi_spec_validator OpenAPI/openapi.yaml
```

Las pruebas rechazan cualquier conexión que no sea PostgreSQL `prematricula_test`, antes de ejecutar migraciones. No ejecutar pruebas concurrentemente contra esa misma base. Los comandos destructivos de Artisan están prohibidos fuera del entorno de pruebas aislado. Para desarrollo/producción se utilizan migraciones incrementales, nunca `migrate:fresh`.

El workflow [.github/workflows/backend.yml](.github/workflows/backend.yml) instala dependencias bloqueadas, levanta PostgreSQL aislado y ejecuta Composer audit, Pint de verificación, Larastan nivel 5, OpenAPI y toda la suite. No incluye builds frontend.

## Documentación canónica

- [Arquitectura](docs/ARCHITECTURE.md)
- [Base de datos](docs/DATABASE.md)
- [Reglas de negocio](docs/BUSINESS_RULES.md)
- [API y catálogo de endpoints](docs/API.md)
- [Formatos de importación](docs/IMPORT_FORMATS.md)
- [Desarrollo, configuración y producción](docs/DEVELOPMENT.md)
- [Entrega e instrucciones para frontend](docs/FRONTEND_HANDOFF.md)
- [OpenAPI 3.1](OpenAPI/openapi.yaml) y [colección Postman](OpenAPI/Prematricula.postman_collection.json)

Las cifras de demanda son estimaciones, no matrícula definitiva. Las asignaciones docentes representan una unidad por curso/período y no calculan grupos a partir de estudiantes interesados.

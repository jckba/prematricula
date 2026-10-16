# Desarrollo y operación reproducible

## Requisitos

PHP 8.4.1+ (extensiones exigidas por Composer), Composer 2 y PostgreSQL 16 recomendado. El lock se resuelve para PHP 8.4.1 para evitar introducir dependencias exclusivas del PHP más nuevo de un desarrollador. La validación de PDF exige Poppler en PATH. En Windows instalar una distribución de Poppler; en Debian/Ubuntu: `sudo apt-get install poppler-utils`. Python 3.14 y `requirements-quality.txt` son herramientas de documentación, no del servidor.

## Instalación desde un clon limpio

1. Clonar el repositorio y ejecutar `composer install --no-interaction --prefer-dist`.
2. Copiar `.env.example` a `.env`, completar PostgreSQL y ejecutar `php artisan key:generate --no-interaction`.
3. Crear bases y roles PostgreSQL independientes según [DATABASE.md](DATABASE.md).
4. Ejecutar `php artisan migrate --no-interaction`.
5. Solo en local: `php artisan db:seed --no-interaction`. Es repetible y conserva datos/contraseñas preexistentes.
6. Iniciar `php artisan serve --host=127.0.0.1` o `composer run dev`.
7. Autenticarse por `POST /api/v1/login`. No se requiere compilar frontend.

`composer run setup` instala dependencias, prepara .env y genera clave; no crea bases, migra ni ejecuta npm automáticamente. El frontend preexistente conserva sus archivos.

## Variables

| Variable | Uso |
| --- | --- |
| APP_ENV | local, testing o production; controla datos de demostración. |
| APP_KEY | Clave generada por entorno, no copiar una clave de test a producción. |
| APP_DEBUG | false en producción; no exponer información interna. |
| APP_URL | Origen público de la aplicación. |
| DB_CONNECTION / DB_HOST / DB_PORT / DB_DATABASE / DB_USERNAME / DB_PASSWORD | Conexión PostgreSQL. |
| DB_URL | URL alternativa que puede sobrescribir DB_*; dejar vacía si no se usa. |
| ACADEMIC_TIMEZONE | Zona para introducir ventanas y presentar fechas académicas; America/Lima por defecto. |
| ACADEMIC_LEGACY_TIMEZONE | Zona original de fechas heredadas sin timezone; definir antes de su primera migración. |
| INSTITUTION_UNIVERSITY / INSTITUTION_SCHOOL | Identidad impresa en las constancias nuevas. |
| CORS_ALLOWED_ORIGINS | Orígenes frontend permitidos separados por coma, sin comodines en producción. |
| LOG_CHANNEL / LOG_LEVEL | Canal y nivel; evitar registro de payloads, archivos, passwords o tokens. |
| CACHE_STORE / SESSION_DRIVER / QUEUE_CONNECTION | Almacenes compartidos adecuados al despliegue. |
| SESSION_ENCRYPT / SESSION_SECURE_COOKIE | Cifrado de sesión y cookie segura con HTTPS. |

Las fechas de clases son Y-m-d. Las ventanas se reciben como fecha/hora local institucional y se devuelven en ISO 8601 UTC; no sumar manualmente cinco horas en el cliente. Fechas inválidas por cambios de horario se rechazan.

## Pruebas seguras

```bash
cp .env.testing.example .env.testing
# Completar usuario/clave de la base prematricula_test.
php artisan config:clear --no-interaction
composer test
```

El bootstrap de PHPUnit comprueba APP_ENV, driver y nombre efectivo de base antes de traits de migración. Incluso DB_URL no puede dirigir la suite a una base distinta de prematricula_test. La clave constante en phpunit.xml es exclusivamente de pruebas. Las credenciales locales no están en el repositorio; .env.testing está ignorado.

Las pruebas de carreras usan dos procesos reales y reinician únicamente la base aislada, con una guardia adicional. No ejecutar suites simultáneas sobre esa base. No usar `migrate:fresh` sobre desarrollo o producción: la aplicación también prohíbe los comandos destructivos fuera del test aislado.

`BackendHandoffTest` cubre login real, importaciones, período, ventanas, registro, PDF histórico, corrección, demanda y carga docente. `PreEnrollmentReceiptTest` verifica con Poppler texto, A4 y exactamente una página, también con nombres largos. `ApiContractTest` compara todas las rutas reales contra OpenAPI, comprueba referencias/variables y errores JSON.

## Calidad y CI

```bash
composer validate --strict --no-check-publish
composer check-platform-reqs
composer audit --locked
composer lint
composer analyse
python -m pip install -r requirements-quality.txt
python -m openapi_spec_validator OpenAPI/openapi.yaml
```

Pint de CI solo verifica; para corregir ejecutar `php vendor/bin/pint --dirty --format agent`. Larastan nivel 5 analiza app, database, routes, config y bootstrap/app.php sin baseline ni errores ignorados. Las relaciones y casts forman parte de su análisis. CI usa PHP 8.4 y PostgreSQL de servicio exclusivo; no usa builds frontend.

## Producción

Usar PHP-FPM/servidor detrás de HTTPS con document root en public. Definir APP_ENV=production, APP_DEBUG=false, APP_KEY propia, CORS exacto, cookies seguras y credenciales de base restringidas. No instalar dependencias de desarrollo en el servidor: `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`.

Realizar backup, aplicar `php artisan migrate --force --no-interaction`, preparar caché con `php artisan config:cache` y `php artisan route:cache`, y comprobar salud/API. No cambiar APP_KEY de una instalación existente sin una política de rotación. No ejecutar seeders de demostración; cada uno rechaza producción incluso por invocación directa.

Configurar límites de carga en proxy/PHP coherentes con 5 MiB XLSX y espacio para el multipart. Mantener logs con permisos restringidos y `zend.exception_ignore_args=On`; las consultas fallidas se registran sin SQL/bindings y las importaciones solo registran identificadores/hashes/resúmenes. No usar Pail/debug en producción para mostrar datos a clientes.

Programar limpieza de tokens expirados con `sanctum:prune-expired` según operación institucional. Cache/rate limiting deben ser compartidos si hay varias instancias. Las constancias y snapshots viven en PostgreSQL y deben incluirse en la política de backup.

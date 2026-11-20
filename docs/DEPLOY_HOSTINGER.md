# Publicar en Hostinger (hosting compartido, sin SSH)

Recibirás tres archivos de quien construyó el paquete con `scripts/build-hostinger.sh`:

| Archivo | Para qué |
| --- | --- |
| `prematricula-hostinger.zip` | La aplicación lista para usar. |
| `prematricula.sql` | Las tablas vacías de la base de datos. |
| `app-key.txt` | Una clave secreta. No la compartas ni la publiques. |

Todo se hace desde **hPanel** en el navegador. Reserva unos 20 minutos.

## 1. PHP

hPanel → **Avanzado → Configuración de PHP**:

1. Versión: **PHP 8.4**.
2. Pestaña **Extensiones PHP**: deben estar marcadas `pdo_mysql`, `mbstring`, `gd`, `zip`, `fileinfo`, `dom`, `simplexml`, `zlib`. Guarda.

## 2. Base de datos

1. hPanel → **Bases de datos → Administración**. Crea una base y un usuario nuevos (por ejemplo `prematricula`; Hostinger les pone delante un prefijo como `u123456789_`). Apunta el nombre, el usuario y la contraseña.
2. Pulsa **Entrar a phpMyAdmin** en esa base.
3. Comprueba que la base esté **vacía**. Pestaña **Operaciones** → *Cotejamiento*: `utf8mb4_unicode_ci` (cámbialo si no lo es).
4. Pestaña **Importar** → elige `prematricula.sql` → **Importar**.
5. Debe terminar en verde. Si el error menciona **TRIGGER** o **SUPER**, las tablas ya se crearon igualmente: avisa a soporte técnico, que la aplicación funciona y solo falta esa protección adicional.

No vuelvas a importar el archivo sobre una base con datos: está hecho para detenerse antes que borrar algo.

## 3. Archivos

1. hPanel → **Archivos → Administrador de archivos**. Entra en `domains/TU-DOMINIO/` (la carpeta que contiene `public_html`).
2. Si `public_html` tiene un `default.php` de Hostinger, bórralo.
3. Sube `prematricula-hostinger.zip` a `domains/TU-DOMINIO/` (arrastrándolo o por FTP).
4. Clic derecho sobre el ZIP → **Extraer** → en la misma carpeta. Quedará así:

```
domains/TU-DOMINIO/
├── prematricula/     ← la aplicación (no es accesible desde Internet)
└── public_html/      ← lo único público: index.php, .htaccess, build/, images/
```

5. Borra el ZIP.

## 4. Configuración (.env)

1. Entra en `prematricula/`. Activa *Mostrar archivos ocultos* si no ves `.env.example`.
2. Renómbralo a **`.env`** y edítalo:
   - `APP_KEY=` → pega el contenido de `app-key.txt`.
   - `APP_URL` y `CORS_ALLOWED_ORIGINS` → tu dirección, por ejemplo `https://prematricula.tudominio.com`.
   - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` → los del paso 2. `DB_HOST` se queda en `localhost`.
3. Guarda. No cambies `APP_KEY` más adelante: cerraría todas las sesiones y dejaría ilegibles los datos cifrados.

## 5. Comprobar

1. hPanel → **Seguridad → SSL**: certificado activo (el `.env` exige cookies seguras con https).
2. Abre `https://TU-DOMINIO/up`: debe responder en verde (*Application up*).
3. Abre la página principal e inicia sesión con el coordinador que te indicaron. Si el SQL no incluía uno, pide al equipo técnico que genere el paquete con `INITIAL_COORDINATOR_EMAIL`.

## Si algo falla

| Síntoma | Qué revisar |
| --- | --- |
| Página "Composer detected issues in your platform" | Paso 1: versión de PHP o una extensión sin marcar. |
| Error 500 | `prematricula/storage/logs/laravel.log` (descárgalo y envíalo al equipo técnico). |
| "No application encryption key" | `APP_KEY` vacío en `.env`. |
| "Access denied for user" / "Unknown database" | Datos `DB_*` del `.env` (con el prefijo `u123456789_`). |
| Sin estilos | La carpeta `public_html/build` no se extrajo. |

## Actualizar a una versión nueva

1. Haz una copia: phpMyAdmin → **Exportar** la base, y descarga `prematricula/.env`.
2. Si la versión nueva trae cambios en la base de datos, el equipo técnico te entregará un `.sql` de actualización aparte: **no** importes otra vez el `prematricula.sql` inicial.
3. Extrae el ZIP nuevo encima, conservando `prematricula/.env` y `prematricula/storage/`.

## Opcional: limpieza de sesiones caducadas

hPanel → **Avanzado → Cron Jobs** → comando personalizado, una vez al día:

```
/usr/bin/php /home/u123456789/domains/TU-DOMINIO/prematricula/artisan sanctum:prune-expired --hours=24
```

(Ajusta `u123456789` y `TU-DOMINIO`; la ruta exacta aparece en el Administrador de archivos.)

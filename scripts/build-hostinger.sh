#!/usr/bin/env bash
# Builds everything a Hostinger shared-hosting account needs, without SSH:
#
#   build/hostinger/prematricula-hostinger.zip   upload by FTP and extract next to public_html
#   build/hostinger/prematricula.sql             import once with phpMyAdmin into an empty database
#   build/hostinger/app-key.txt                  APP_KEY to paste into the server .env
#
# Usage (Git Bash on Windows, or bash on Linux/macOS), from any directory:
#   EXPORT_DB_PASSWORD=... scripts/build-hostinger.sh
#
# Needs: git, PHP 8.4 with pdo_mysql and zip, Composer 2, npm, mysqldump (or
# mariadb-dump) and a MariaDB server where a throwaway database can be created.
# The package is built from the last commit (HEAD); uncommitted changes are left out.
#
# Variables (all optional):
#   EXPORT_DB_HOST/PORT/USERNAME/PASSWORD  server used to generate the SQL (127.0.0.1:3306, root)
#   EXPORT_DB_DATABASE                     throwaway database, must end in _export (prematricula_export)
#   APP_DIR_NAME                           folder created next to public_html (prematricula)
#   INITIAL_COORDINATOR_EMAIL              if set, the SQL also creates this first coordinator;
#   INITIAL_COORDINATOR_POSITION           DIRECTOR_ESCUELA (default) or SECRETARIA;
#   INITIAL_COORDINATOR_PASSWORD           its password (asked interactively when empty).
#   PHP, COMPOSER, NPM, MYSQLDUMP          commands to use
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="$ROOT/build/hostinger"
# Short temporary path: inside the project, vendor/ paths exceed Windows MAX_PATH (260).
STAGE="$(mktemp -d)"
APP_DIR_NAME="${APP_DIR_NAME:-prematricula}"
APP="$STAGE/$APP_DIR_NAME"
PUBLIC="$STAGE/public_html"
PHP="${PHP:-php}"
COMPOSER="${COMPOSER:-composer}"
NPM="${NPM:-npm}"
MYSQLDUMP="${MYSQLDUMP:-$(command -v mysqldump || command -v mariadb-dump || echo mysqldump)}"
HELPERS="$ROOT/scripts/hostinger/helpers.php"

export EXPORT_DB_HOST="${EXPORT_DB_HOST:-127.0.0.1}"
export EXPORT_DB_PORT="${EXPORT_DB_PORT:-3306}"
export EXPORT_DB_USERNAME="${EXPORT_DB_USERNAME:-root}"
export EXPORT_DB_PASSWORD="${EXPORT_DB_PASSWORD:-}"
export EXPORT_DB_DATABASE="${EXPORT_DB_DATABASE:-prematricula_export}"

step() { printf '\n==> %s\n' "$*"; }

[[ "$APP_DIR_NAME" =~ ^[A-Za-z0-9_-]+$ ]] || { echo "APP_DIR_NAME inválido: $APP_DIR_NAME" >&2; exit 1; }
"$PHP" -r 'exit(extension_loaded("pdo_mysql") && extension_loaded("zip") ? 0 : 1);' \
    || { echo "PHP necesita las extensiones pdo_mysql y zip para construir el paquete." >&2; exit 1; }
command -v "$MYSQLDUMP" >/dev/null || { echo "No se encontró mysqldump/mariadb-dump (variable MYSQLDUMP)." >&2; exit 1; }

if [[ -n "$(git -C "$ROOT" status --porcelain)" ]]; then
    echo "AVISO: hay cambios sin commit; el paquete usa solo el último commit ($(git -C "$ROOT" rev-parse --short HEAD))."
fi

step "Preparando $OUT"
rm -rf "$OUT"
mkdir -p "$OUT" "$APP" "$PUBLIC"
trap 'rm -rf "$STAGE"' EXIT
git -C "$ROOT" archive --format=tar HEAD | tar -x -C "$APP"

step "Instalando dependencias PHP de producción (composer install --no-dev)"
# The build machine needs the same extensions as the server: vendor/composer/platform_check.php
# then fails fast on Hostinger if PHP 8.4 or an extension is missing there.
(cd "$APP" && "$COMPOSER" install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress)

step "Compilando el frontend (npm run build)"
(cd "$ROOT" && { [[ -d node_modules ]] || "$NPM" install; } && "$NPM" run build)
cp -R "$ROOT/public/build" "$APP/public/build"

step "Quitando archivos que no se usan en el servidor"
(cd "$APP" && rm -rf tests .github docs OpenAPI scripts resources/js resources/css \
    phpunit.xml phpstan.neon pint.json requirements-quality.txt package.json package-lock.json vite.config.js \
    AGENTS.md CLAUDE.md boost.json README.md .ai .claude .junie .cursor .mcp.json .env.example .env.testing.example .gitattributes .editorconfig .npmrc)
cp "$ROOT/scripts/hostinger/env.production.example" "$APP/.env.example"
# Caches with absolute paths or this machine's settings must be generated on the server.
rm -f "$APP"/bootstrap/cache/config.php "$APP"/bootstrap/cache/routes-*.php "$APP"/bootstrap/cache/events.php

step "Separando public/ en public_html/"
(shopt -s dotglob && mv "$APP"/public/* "$PUBLIC"/)
rmdir "$APP/public"
"$PHP" "$HELPERS" patch-index "$PUBLIC/index.php" "$APP_DIR_NAME"

step "Generando la estructura SQL en la base desechable $EXPORT_DB_DATABASE"
"$PHP" "$HELPERS" create-export-db
trap '"$PHP" "$HELPERS" drop-export-db || true; rm -rf "$STAGE"' EXIT
(cd "$APP" && APP_ENV=production APP_KEY= DB_CONNECTION=mariadb DB_URL= \
    DB_HOST="$EXPORT_DB_HOST" DB_PORT="$EXPORT_DB_PORT" DB_DATABASE="$EXPORT_DB_DATABASE" \
    DB_USERNAME="$EXPORT_DB_USERNAME" DB_PASSWORD="$EXPORT_DB_PASSWORD" \
    "$PHP" artisan migrate --force --no-interaction)

dump() {
    MYSQL_PWD="$EXPORT_DB_PASSWORD" "$MYSQLDUMP" --host="$EXPORT_DB_HOST" --port="$EXPORT_DB_PORT" \
        --user="$EXPORT_DB_USERNAME" --skip-comments --skip-add-drop-table --single-transaction "$@"
}
SQL="$OUT/prematricula.sql"
{
    echo "-- Prematrícula: esquema inicial para phpMyAdmin (generado desde el commit $(git -C "$ROOT" rev-parse --short HEAD))."
    echo "-- Importar UNA sola vez en una base VACÍA con cotejamiento utf8mb4_unicode_ci."
    echo "-- No contiene DROP TABLE: si las tablas ya existen, la importación se detiene sin borrar nada."
    echo
    echo "-- 1. Tablas"
    dump --no-data --skip-triggers "$EXPORT_DB_DATABASE"
    echo
    echo "-- 2. Datos mínimos: migraciones aplicadas y bloqueos de proceso"
    dump --no-create-info --skip-triggers --complete-insert "$EXPORT_DB_DATABASE" migrations process_locks
    if [[ -n "${INITIAL_COORDINATOR_EMAIL:-}" ]]; then
        if [[ -z "${INITIAL_COORDINATOR_PASSWORD:-}" ]]; then
            read -r -s -p "Contraseña para $INITIAL_COORDINATOR_EMAIL (mín. 12 caracteres): " INITIAL_COORDINATOR_PASSWORD </dev/tty
            echo >&2
        fi
        echo
        echo "-- 3. Primer coordinador (contraseña guardada con bcrypt)"
        INITIAL_COORDINATOR_PASSWORD="$INITIAL_COORDINATOR_PASSWORD" \
            "$PHP" "$HELPERS" coordinator-sql "$INITIAL_COORDINATOR_EMAIL" "${INITIAL_COORDINATOR_POSITION:-DIRECTOR_ESCUELA}"
    fi
    echo
    echo "-- 4. Triggers de inmutabilidad (al final: si el hosting no permite triggers, todo lo anterior ya quedó importado)"
    # DEFINER=user@host would require SUPER on import; without it the trigger runs as the importing user.
    dump --no-create-info --no-data --triggers "$EXPORT_DB_DATABASE" pre_enrollment_revisions \
        | sed -E 's#/\*!50017 DEFINER=[^*]*\*/ ?##g; s#DEFINER=`[^`]*`@`[^`]*` ?##g'
} > "$SQL"
grep -q 'CREATE TABLE `process_locks`' "$SQL" && grep -q 'immutable_pre_enrollment_revision_update' "$SQL" \
    || { echo "El SQL generado está incompleto." >&2; exit 1; }

step "Generando APP_KEY"
"$PHP" -r 'echo "base64:".base64_encode(random_bytes(32)), PHP_EOL;' > "$OUT/app-key.txt"

step "Empaquetando"
"$PHP" "$HELPERS" zip "$STAGE" "$OUT/prematricula-hostinger.zip"
rm -rf "$STAGE"

cat <<EOF

Listo:
  $OUT/prematricula-hostinger.zip
  $OUT/prematricula.sql
  $OUT/app-key.txt   (secreto: no lo subas al repositorio)
Siguiente paso: docs/DEPLOY_HOSTINGER.md
EOF

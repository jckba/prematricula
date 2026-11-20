<?php

/*
 * Small, dependency-free helpers for scripts/build-hostinger.sh.
 * PHP is used instead of zip/mysql CLI tools because it is the one tool that is
 * guaranteed to exist on every machine that builds this Laravel application.
 *
 *   php helpers.php patch-index <public_html/index.php> <app-dir-name>
 *   php helpers.php zip <source-dir> <zip-file>
 *   php helpers.php create-export-db        (EXPORT_DB_* environment variables)
 *   php helpers.php drop-export-db          (EXPORT_DB_* environment variables)
 *   php helpers.php coordinator-sql <email> <DIRECTOR_ESCUELA|SECRETARIA>   (password in INITIAL_COORDINATOR_PASSWORD)
 */

declare(strict_types=1);

function fail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function exportConnection(bool $withDatabase): PDO
{
    $host = getenv('EXPORT_DB_HOST') ?: '127.0.0.1';
    $port = getenv('EXPORT_DB_PORT') ?: '3306';
    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4".($withDatabase ? ';dbname='.exportDatabase() : '');

    return new PDO($dsn, getenv('EXPORT_DB_USERNAME') ?: 'root', getenv('EXPORT_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

function exportDatabase(): string
{
    $name = getenv('EXPORT_DB_DATABASE') ?: 'prematricula_export';
    // The build creates and later drops this database, so insist on a name that
    // cannot be confused with a real one.
    if (! preg_match('/^[A-Za-z0-9_]+_export$/', $name)) {
        fail("EXPORT_DB_DATABASE debe terminar en _export (recibido: {$name}).");
    }

    return $name;
}

$command = $argv[1] ?? '';

switch ($command) {
    case 'patch-index':
        [, , $file, $appDir] = $argv + [null, null, null, null];
        $code = file_get_contents($file) ?: fail("No se puede leer {$file}.");
        $replaced = str_replace("__DIR__.'/../", "__DIR__.'/../{$appDir}/", $code, $paths);
        // public/ now lives in public_html/, outside the app: tell Laravel where it is
        // so public_path(), asset() and the Vite manifest keep working.
        $replaced = str_replace('$app->handleRequest(', "\$app->usePublicPath(__DIR__);\n\n\$app->handleRequest(", $replaced, $publicPath);
        if ($paths !== 3 || $publicPath !== 1) {
            fail("index.php no tiene la forma esperada ({$paths} rutas, {$publicPath} handleRequest).");
        }
        file_put_contents($file, $replaced);
        break;

    case 'zip':
        [, , $source, $target] = $argv + [null, null, null, null];
        $source = realpath($source) ?: fail('Directorio de origen inexistente.');
        $zip = new ZipArchive;
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            fail("No se puede crear {$target}.");
        }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST);
        foreach ($files as $path => $info) {
            $relative = str_replace('\\', '/', substr($path, strlen($source) + 1));
            $info->isDir() ? $zip->addEmptyDir($relative) : $zip->addFile($path, $relative);
        }
        $zip->close() || fail('No se pudo cerrar el ZIP.');
        break;

    case 'create-export-db':
        $name = exportDatabase();
        $pdo = exportConnection(false);
        $tables = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?');
        $tables->execute([$name]);
        if ((int) $tables->fetchColumn() > 0) {
            fail("La base {$name} ya tiene tablas. Bórrala a mano (DROP DATABASE {$name};) o usa otro EXPORT_DB_DATABASE.");
        }
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo $pdo->query('SELECT VERSION()')->fetchColumn(), "\n";
        break;

    case 'drop-export-db':
        exportConnection(false)->exec('DROP DATABASE IF EXISTS `'.exportDatabase().'`');
        break;

    case 'coordinator-sql':
        [, , $email, $position] = $argv + [null, null, null, null];
        $password = getenv('INITIAL_COORDINATOR_PASSWORD') ?: '';
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            fail('INITIAL_COORDINATOR_EMAIL no es un correo válido.');
        }
        if (! in_array($position, ['DIRECTOR_ESCUELA', 'SECRETARIA'], true)) {
            fail('INITIAL_COORDINATOR_POSITION debe ser DIRECTOR_ESCUELA o SECRETARIA.');
        }
        if (strlen($password) < 12) {
            fail('La contraseña inicial del coordinador debe tener al menos 12 caracteres.');
        }
        // Same algorithm Laravel's Hash facade uses by default (bcrypt, $2y$).
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $quote = fn (string $value): string => "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
        echo 'INSERT INTO `users` (`correo_institucional`, `password`, `rol`, `cargo`, `estado`, `created_at`, `updated_at`) VALUES ('
            .$quote($email).', '.$quote($hash).", 'COORDINADOR', ".$quote($position).", 'ACTIVO', UTC_TIMESTAMP(), UTC_TIMESTAMP());\n";
        break;

    default:
        fail('Comando desconocido. Ver la cabecera de scripts/hostinger/helpers.php.');
}

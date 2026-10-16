# Malla curricular y catálogo académico

Una sola malla vigente, sin planes versionados. Se conservan `courses`, `course_prerequisites`, las rutas anteriores, Sanctum y `/api/v1`. Este módulo no añade secciones, horarios ni carga docente.

## Autorización y consultas

Todas las rutas requieren `Accept: application/json`, token Bearer y cuenta habilitada. `DIRECTOR_ESCUELA` y `SECRETARIA` tienen idéntico acceso mediante `CoursePolicy`, extensible para futuras políticas. Solo coordinadores pueden importar, exportar, descargar plantilla y modificar cursos.

`GET /api/v1/courses` devuelve `{data: Course[], links, meta}`. Parámetros opcionales:

| Parámetro | Validación / comportamiento |
| --- | --- |
| `q` | Texto hasta 100 caracteres; busca código o nombre sin interpretar `%` ni `_` como comodines. |
| `ciclo` | Entero 1–32767; filtro de catálogo, no restricción de elegibilidad. |
| `estado` | `ACTIVO` o `INACTIVO`. Estudiantes no pueden pedir inactivos (403). |
| `per_page` | Entero 1–100, predeterminado 20. |
| `page` | Paginación estándar de Laravel. |

Orden: ciclo, código e ID. Coordinadores ven ambos estados por defecto; estudiantes solo activos. `GET /api/v1/courses/{course}` consulta por ID; un curso inexistente o inactivo para un estudiante devuelve 404. No es un endpoint de elegibilidad: para esta se mantiene `/api/v1/student/pre-enrollment/available-courses`, con sus comprobaciones de propietario, historial, prerrequisitos y créditos. El ciclo del curso no impone un límite adicional.

Ejemplo de recurso (envuelto en `data` para detalle y actualización):

```json
{
  "id": 1,
  "codigo": "00101",
  "nombre": "Matemática I",
  "creditos": 4,
  "ciclo": 1,
  "horas_teoria": "2.50",
  "horas_practica": "2.00",
  "horas_laboratorio": "0.00",
  "estado": "ACTIVO",
  "prerrequisitos": []
}
```

Los prerrequisitos se devuelven como objetos `{id, codigo, nombre}`. Las horas son **horas académicas semanales**, no horas del semestre ni minutos de reloj. Se almacenan como `decimal(5,2)`, rango 0–999.99, admitiendo hasta dos decimales sin redondear entradas inválidas. La API devuelve strings decimales para mantener precisión. Créditos y ciclo son enteros positivos, rango 1–32767.

La migración incremental añade las tres horas con valor inicial `0.00`; en registros anteriores significa que no había una carga de horas registrada. No deduce ni altera horas históricas. La política de protección también cubre estos cursos si ya están utilizados.

## Archivo Excel

`GET /api/v1/coordinator/curriculum/template` descarga `plantilla-malla.xlsx`, con una hoja y encabezados (sin cursos de ejemplo). Completar las siguientes ocho columnas en la fila 1; pueden reordenarse, pero no omitirse, repetirse ni añadirse otras:

| Columna | Contenido |
| --- | --- |
| `codigo` | Texto único, estable y sensible a mayúsculas; hasta 30 caracteres. Letras ASCII, números, `.`, `_`, `-`; inicia con letra o número. |
| `nombre` | Texto obligatorio, hasta 150 caracteres. |
| `creditos` | Entero 1–32767. |
| `ciclo` | Entero 1–32767. |
| `horas_teoria` | Número 0–999.99, máximo dos decimales. |
| `horas_practica` | Número 0–999.99, máximo dos decimales. |
| `horas_laboratorio` | Número 0–999.99, máximo dos decimales. |
| `prerrequisitos` | Vacío o códigos de cursos separados por `;`, por ejemplo `00101;FIS101`. No repetir códigos. |

Formatear códigos y prerrequisitos como **texto** en Excel, especialmente si tienen ceros iniciales. No convertirlos en números. Se recortan espacios exteriores; las referencias se resuelven por código, independientemente del orden de las filas, contra el archivo completo y los cursos existentes. No se admiten referencias inexistentes, autorreferencias ni ciclos, incluso si un ciclo atraviesa cursos fuera del archivo.

Límites en `config/curriculum.php`: 5 MiB por archivo, 5000 filas físicas de datos (fila máxima 5001 contando encabezado), 256 entradas ZIP y 20 MiB descomprimidos. Una sola hoja visible, XML UTF-8, extensión `.xlsx` y MIME compatible. Se rechazan archivos vacíos, encabezados sin cursos, ZIP truncados/corruptos, fórmulas (aunque tengan valor almacenado), errores de celda, macros, vínculos externos y declaraciones de entidades XML. Se ignoran filas de datos completamente vacías, conservando los números físicos para los errores.

Lectura con `shuchkin/simplexlsx` 1.1.19 y escritura con `shuchkin/simplexlsxgen` 1.5.17, dependencias fijadas en Composer, compatibles con PHP 8.4 y sin exigir `ext-zip`/`gd`. La descompresión está acotada antes de analizar XML. La exportación fuerza códigos y nombres a texto, sin ejecutar fórmulas ni interpretar etiquetas como formato.

## Validar y confirmar

1. `POST /api/v1/coordinator/curriculum/validate`, `multipart/form-data`, campo `file`: archivo `.xlsx`. No guarda cursos, relaciones ni archivos persistentes. Un archivo válido devuelve 200:

```json
{
  "data": {
    "valid": true,
    "catalog_fingerprint": "<64 caracteres hexadecimales>",
    "summary": {"created": 1, "updated": 0, "unchanged": 0},
    "changes": [{"row": 2, "codigo": "00101", "action": "create", "fields": ["nombre", "creditos", "ciclo", "horas_teoria", "horas_practica", "horas_laboratorio", "prerrequisitos"], "protected": false}],
    "errors": [],
    "sha256": "<64 caracteres hexadecimales>"
  }
}
```

`summary` describe cambios previstos; si `valid` es falso no se aplicará ninguno. `changes.action` es `create`, `update` o `unchanged`; `protected` identifica cursos ya utilizados. `catalog_fingerprint` corresponde a la instantánea base del catálogo, incluyendo relaciones, estados y uso académico, no al resultado futuro de importar.

2. Revisar el informe y confirmar con `POST /api/v1/coordinator/curriculum/import`, también multipart, enviando **el mismo archivo** en `file`, más `sha256` y `catalog_fingerprint` del paso anterior. Un archivo cambiado devuelve 409. Un catálogo modificado con cambios aún pendientes devuelve 409 y requiere nueva validación. El servidor no confía en los hashes como prueba de validez: vuelve a validar todas las filas y el grafo bajo bloqueo transaccional. No existe importación parcial.

3. Éxito 200: `{message: "Malla curricular importada correctamente.", data: <informe con sha256>}`. `catalog_fingerprint` sigue identificando la base de esa operación. Se pueden repetir los mismos datos: si ya coinciden con el catálogo actual, la operación es un no-op incluso con la huella anterior; no duplica cursos, prerrequisitos ni cambia timestamps sin necesidad.

Se usa una transacción PostgreSQL con bloqueo asesor de la malla única y bloqueo de cursos para serializar cambios y proteger la comprobación de uso. Un fallo al escribir revierte cursos y relaciones completos. Los logs de importación solo contienen ID del coordinador, SHA-256 del archivo y conteos, nunca contenido del Excel, contraseñas ni tokens.

### Errores

Errores de datos o de grafo: 422 con el mismo informe, `valid: false` y `errors` localizados:

```json
{
  "data": {
    "valid": false,
    "catalog_fingerprint": "<huella>",
    "summary": {"created": 1, "updated": 0, "unchanged": 0},
    "changes": [],
    "errors": [{"row": 4, "column": "prerrequisitos", "message": "El prerrequisito NOEXISTE no existe en el catálogo ni en el archivo."}]
  }
}
```

En validación se añade `sha256` incluso si hay errores de datos; en confirmación inválida puede omitirse. Los números de fila son los de Excel, con encabezado en 1. Para errores globales del grafo se usa fila 1; un archivo sin cursos usa fila 2. Errores de carga, encabezados o contenedor sin ubicación de celda fiable devuelven el formato Laravel `{message, errors: {file: [mensaje]}}`, también 422. No se exponen detalles internos del parser. Otros estados: 401 sin autenticación, 403 por permisos o cuenta inactiva, 404 curso no disponible, 409 conflicto de confirmación y 500 error de persistencia sin detalles sensibles.

## Política explícita de actualización segura

- El código identifica al curso: no se renombra por API ni importación. Un código nuevo crea un curso activo. No hay endpoint de eliminación.
- Cursos sin historial ni detalles de prematrícula: pueden cambiar nombre, créditos, ciclo, horas y prerrequisitos. La lista de prerrequisitos de cada fila reemplaza la lista anterior, no se acumula.
- Cursos usados en **cualquier** registro de `academic_histories` o `pre_enrollment_details`, incluso borradores: sus datos académicos y prerrequisitos son inmutables. Se rechaza el archivo completo si intenta cambiarlos. Reimportar exactamente los mismos valores sí está permitido. No se reescriben registros históricos ni se introducen planes versionados o snapshots nuevos.
- El estado puede cambiar por PATCH incluso para un curso usado. Importar conserva estados existentes, no reactiva cursos ni elimina aquellos que falten en el archivo.

`PATCH /api/v1/coordinator/courses/{course}` recibe JSON parcial. Campos opcionales: `nombre`, `creditos`, `ciclo`, las tres horas, `prerrequisitos` (array de códigos, `[]` para quitar todos) y `estado`. Ejemplo para un curso no utilizado:

```json
{"horas_teoria": "2.50", "prerrequisitos": ["00101", "FIS101"]}
```

Ejemplo válido incluso para un curso utilizado: `{"estado":"INACTIVO"}`. Las validaciones académicas son las mismas que en importación. Errores de reglas del grafo/protección usan el informe con `row: 0` (la edición JSON no tiene fila Excel); errores de esquema usan `{message, errors}` por campo.

## Exportación y Postman

`GET /api/v1/coordinator/curriculum/export` descarga `malla-curricular.xlsx`, con las ocho columnas y **todos** los cursos activos e inactivos, ordenados por código. No contiene estado porque no forma parte del formato obligatorio. Reimportar en el mismo catálogo preserva los estados; importar códigos nuevos los crea activos. Los prerrequisitos se ordenan y separan por `;`. Las descargas usan MIME XLSX y caché privada `no-store`.

Importar [colección Postman](../OpenAPI/Prematricula.postman_collection.json), establecer `base_url`, `identifier`, `password` y ejecutar Login. El token se guarda solo como variable local de la colección, sin scripts de consola. Seleccionar el archivo en Validar; una validación correcta guarda automáticamente `sha256` y `catalog_fingerprint`. Seleccionar el mismo archivo en Confirmar. Establecer `course_id` para detalle/PATCH. Las variables sensibles se entregan vacías: no exportar ni compartir la colección con credenciales o tokens guardados.

## Instalación y pruebas

En una instalación existente ejecutar `composer install` y `php artisan migrate --no-interaction`. No usar `migrate:fresh` sobre datos existentes. La migración es aditiva y no borra historial.

```bash
php artisan test --compact tests/Feature/Api/CourseCatalogTest.php tests/Feature/Api/Coordinator/CurriculumImportTest.php tests/Feature/Api/Coordinator/UpdateCourseTest.php tests/Feature/Policies/CoursePolicyTest.php
```

Usar únicamente PostgreSQL de pruebas separado (`prematricula_test` por defecto), nunca la base habitual. La cobertura incluye archivos válidos y corruptos, límites, estructura, errores por fila/columna, duplicados, dependencias, protección histórica, rollback real e idempotencia, catálogo, exportación y autorización de ambos cargos y estudiantes.

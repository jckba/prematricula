# Importaciones XLSX

Solo coordinadores activos pueden descargar plantillas, validar y confirmar. El archivo debe ser XLSX genuino; CSV, XLS y archivos renombrados se rechazan.

## Límites compartidos

- Archivo: máximo 5120 KiB.
- Filas físicas: encabezado en fila 1 y hasta 5000 filas de datos.
- ZIP: hasta 256 entradas y 20 MiB descomprimidos.
- Exactamente una hoja de cálculo, visible y sin hojas adicionales.
- Encabezados obligatorios exactamente una vez; el orden puede cambiar.
- Sin columnas extra, coordenadas repetidas, fórmulas, macros, vínculos externos, errores de celda, entidades XML o DTD.

Se valida el contenedor antes de procesar datos. Las celdas de código deben ser **texto**, no números formateados visualmente con ceros. Las plantillas/exportación conservan códigos textuales. Los límites están en `config/curriculum.php`; también configurar límites equivalentes en PHP/proxy para devolver errores antes de agotar memoria.

## Malla única

Plantilla: GET `/api/v1/coordinator/curriculum/template`.

| Columna | Formato |
| --- | --- |
| codigo | Texto único, hasta 30; letras, números, punto, guion o guion bajo; inicia alfanumérico. |
| nombre | Texto, hasta 150. |
| creditos | Entero 1–32767. |
| ciclo | Entero 1–32767. |
| horas_teoria | Número 0–999.99, hasta dos decimales. |
| horas_practica | Número 0–999.99, hasta dos decimales. |
| horas_laboratorio | Número 0–999.99, hasta dos decimales. |
| prerrequisitos | Códigos separados por punto y coma; vacío si no hay. |

Se permiten referencias a cursos de filas posteriores. Se rechazan códigos/prerrequisitos repetidos, inexistentes, autorreferencias y ciclos. Cursos con referencias históricas conservan su definición académica; un curso omitido del archivo no se elimina.

1. POST `/coordinator/curriculum/validate`, multipart con `file`.
2. Revisar `data.valid`, `summary`, `changes` y errores con fila/columna.
3. POST `/coordinator/curriculum/import`, multipart con el **mismo archivo**, `sha256` y `catalog_fingerprint` del reporte.

Archivo o catálogo cambiado: 409 y nueva validación. Confirmación inválida: 422. La operación es transaccional y repetir el mismo contenido no duplica cursos.

## Resultados oficiales

Plantilla: GET `/api/v1/coordinator/academic-history/template`.

| Columna | Formato |
| --- | --- |
| codigo_universitario | Texto, hasta 30, estudiante existente. |
| codigo_curso | Texto, hasta 30, curso existente. |
| codigo_periodo | Texto, hasta 20, período existente. |
| nota_final | Número entre 0 y 999.99, hasta dos decimales, o vacío según estado. |
| estado | EN_CURSO, APROBADO, DESAPROBADO, RETIRADO. |

APROBADO/DESAPROBADO requieren nota; RETIRADO exige nota vacía. EN_CURSO admite nota vacía o informada, manteniendo siempre su estado provisional. El estado es oficial y no se infiere de la nota. Se rechazan combinaciones estudiante/curso/período repetidas en el archivo.

1. POST `/coordinator/academic-history/validate`, multipart con `file`.
2. POST `/coordinator/academic-history/import` con el mismo `file`, `sha256`, `history_fingerprint` y `motivo` obligatorio (hasta 2000 caracteres).
3. Consultar auditoría en `/coordinator/academic-history/imports` y `/{import}`.

Cada importación confirmada registra su autor, hash, motivo, resumen y before/after de los cambios. Repetir contenido no duplica resultados; puede registrar otro evento de importación sin cambios. Notas, auditoría y revalidación abierta son atómicas. Se conservan revisiones/PDF previos; períodos cerrados no se revalidan retrospectivamente.

# Entrega para integración frontend

El cliente consume /api/v1 con Bearer Sanctum. Esta entrega no modifica frontend, no incluye secciones/horarios/aulas ni cuentas docentes, y no genera grupos a partir de la demanda.

## Contrato

La especificación verificable es [OpenAPI 3.1](../OpenAPI/openapi.yaml); el catálogo completo está en [API.md](API.md). Usar `Accept: application/json` y `Authorization: Bearer <token>`. Para cargas usar FormData y dejar que el navegador construya Content-Type/boundary.

La colección [Postman](../OpenAPI/Prematricula.postman_collection.json) incluye base_url (con /api/v1), student_token, director_token, secretary_token, academic_period_id, course_id, student_id, pre_enrollment_id y teacher_id. Los tokens/credenciales se distribuyen vacíos. Completar los identificadores por rol y password local; los tres Login guardan su token correspondiente. Las carpetas coordinadoras usan director_token; puede sustituirse por secretary_token. Hay una consulta explícita con el token de secretaría.

No tratar los IDs de usuario y estudiante como intercambiables. Login devuelve identidad de usuario; el código universitario se mantiene como string. Un docente nunca se autentica.

## Recorrido de integración

1. Login de cada actor; manejar 401 y expiración de 480 minutos mediante nuevo login, sin endpoint de refresh.
2. Coordinador importa malla: validar XLSX, mostrar errores/cambios y confirmar exactamente archivo y hashes.
3. Configurar período y fechas de clases; crear ventana ordinaria global en hora institucional.
4. Importar resultados oficiales con motivo y huella del historial; el estado no se calcula a partir de un umbral de nota.
5. Estudiante consulta /student/pre-enrollment: crea/obtiene borrador; el alias GET puede responder 201 al crearlo. POST también está disponible.
6. Obtener /student/pre-enrollment/available-courses. Los cursos adelantados ya están filtrados por elegibilidad.
7. Agregar curso con POST /student/pre-enrollment/courses. Guardar **data.detail_id**, no data.id. Eliminar/modificar preferencia usa ese ID.
8. Enviar con POST /student/pre-enrollment/submit. ENVIADA es registro automático, no espera aprobación. Mostrar validación académica por separado.
9. Descargar /student/pre-enrollments/{id}/receipt como blob PDF; comprobar Content-Type. En errores, la respuesta es JSON, no un PDF.
10. Con resultados nuevos, coordinador valida/importa y abre una EXTRAORDINARIA después de las ventanas previas.
11. Estudiante inicia /student/pre-enrollments/{id}/corrections, edita el borrador y reenvía. La revisión/PDF anterior sigue accesible.
12. Consultar revisiones con paginación y descargar /revisions/{revision}/receipt para evidencia histórica.
13. Coordinador busca estudiantes por código exacto o nombre, y consulta /students/{codigo}/academic?academic_period_id=...
14. Consultar demanda, resumen, interesados y CSV. Mostrar siempre advertencia de estimación.
15. Crear docentes y distribuir horas con /teaching-assignments. Mostrar límites del curso y del docente, pendientes y auditoría.
16. Consultar /academic-periods/{period}/teaching-load y sus detalles por docente/curso.

Los directorios y respuestas concretos de este recorrido se prueban en BackendHandoffTest. El PDF no se reconstruye desde el catálogo actual. Cambiar una preferencia de borrador no altera el registro vigente hasta reenviar.

## Respuestas y errores

No asumir una única forma de paginación: los Resources usan data/links/meta; demanda usa data.cursos y data.paginacion; interesados usan el paginador plano; los detalles académicos incluyen sus propios paginadores. Seguir los esquemas OpenAPI de cada operación.

- 401: autenticar de nuevo.
- 403: rol, cuenta o perfil sin permiso; no reintentar una escritura automáticamente.
- 404: recurso inexistente/ajeno; no revelar identidad de otro alumno.
- 409: conflicto de huella, PDF histórico no disponible o período cerrado; refrescar y explicar al usuario.
- 422: errores de campos o informe de importación en data.errors, con fila/columna.
- 429: esperar Retry-After.
- 500: mensaje genérico; no se exponen SQL ni trazas.

## Validación y límites de entrega

La auditoría corrige tipos/relaciones, firma de middleware, ocultación de errores internos, guardia de base de pruebas, seeders directos y revalidación por estado de curso. No hay rutas de aprobación/rechazo y se conserva compatibilidad de lectura para APROBADA/RECHAZADA heredados.

Limitaciones deliberadas:

- No hay padrón por período: población y alumnos no enviados son null, con explicación.
- Una unidad docente por curso/período, sin paralelos ni conflictos de tiempo.
- Los listados de cobertura recorren el catálogo vigente; no declaran oferta formal.
- PDFs con selecciones excepcionalmente extensas reducen tipografía para conservar una página; no omiten cursos.
- No se inventan PDFs para capturas heredadas sin evidencia.
- La renovación de token requiere credenciales; no hay refresh token.
- El workflow se entrega configurado; ejecutarlo remotamente exige subir el repositorio a GitHub. No se afirma una ejecución remota que no ocurrió.

## Evidencia de verificación — 16 de octubre de 2026

| Comprobación ejecutada | Resultado real |
| --- | --- |
| Suite completa en el árbol de trabajo | 398 tests, 3744 assertions, cero fallos. |
| Suite completa en clon temporal limpio | 398 tests, 3744 assertions, cero fallos. |
| Base utilizada por ambas suites | PostgreSQL aislado, prematricula_test. |
| Instalación desde composer.lock sin vendor preexistente | 125 paquetes instalados correctamente. |
| Migraciones desde base de pruebas vacía | Las 17 migraciones aplicadas correctamente. |
| Seeders de demostración repetidos | Dos ejecuciones correctas; conservación de datos y rechazo en producción cubiertos por tests. |
| Servidor del clon, peticiones HTTP por TCP | Login 200, /me autenticado 200 JSON y logout 200. Servidor detenido al finalizar. |
| Flujo completo BackendHandoffTest, incluido en ambas suites | Login, malla y resultados XLSX, período, ventanas, registro, PDF, corrección, historial, demanda y carga docente. |
| PDF | Poppler verifica una página A4; también se inspeccionó visualmente una constancia generada. |
| Pint | Corrección aplicada y verificación completa sin diferencias. |
| Larastan/PHPStan | Nivel 5, cero errores, sin baseline ni errores ignorados. |
| Composer | Manifest y lock válidos; requisitos de plataforma satisfechos; auditoría en línea sin avisos de vulnerabilidad. |
| OpenAPI y Postman | Validación formal correcta y 71 operaciones coincidentes con las rutas reales. |
| Configuración y rutas cacheadas en el clon | Generación y limpieza correctas. |
| Frontend | Sin cambios en resources, public ni manifiestos JavaScript. Sin builds frontend. |

El clon se creó desde una instantánea Git temporal de los archivos versionables de esta entrega, incluidos los cambios pendientes; no se realizaron commits en el repositorio principal. La instalación de dependencias se hizo de nuevo. Los cambios de documentación posteriores no alteran el código probado. Las credenciales locales y los directorios de herramientas no forman parte de la entrega.

La verificación local usó PHP 8.5.11. Composer resuelve el lock para PHP 8.4.1 y CI está configurado con PHP 8.4; todavía no se ha ejecutado ese workflow en GitHub. El recorrido completo se verificó mediante las pruebas Feature contra el kernel HTTP de Laravel; las comprobaciones adicionales por TCP verificaron arranque y autenticación, sin sustituir esa cobertura automatizada.

Los informes JUnit locales se generan en storage/logs/final-junit.xml y, dentro del clon temporal, storage/logs/clean-clone-junit.xml. CI publica su propio informe como artefacto. Para repetir las verificaciones seguir [DEVELOPMENT.md](DEVELOPMENT.md); no ejecutar suites simultáneas sobre la misma base.

No se detectaron fallos críticos pendientes en las verificaciones ejecutadas. Quedan la ejecución remota de CI y la preparación institucional de DNS/TLS, secretos, límites de infraestructura y backups. No reutilizar claves o contraseñas de ejemplos en producción. Las limitaciones del modelo enumeradas arriba son parte del alcance acordado.

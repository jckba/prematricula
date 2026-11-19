Título:
feature(pre-matricula): adapta el modelo de prematricula al esquema del portal

Objetivo
Adaptar el modelo del proyecto original de prematrícula (jckba/prematricula) a la base de datos del portal. Se reutilizan las tablas compartidas de core/seguridad y se agregan al esquema prematricula las tablas que el portal no tenía (revisiones, validaciones, historial importado y carga docente).

Cambios clave
- 12 migraciones nuevas en app/Modules/PreMatricula/Database/Migrations (3 alter y 9 create), todas con down() simétrico y nombres calificados.
- cronograma_prematricula admite una ventana ORDINARIA y varias EXTRAORDINARIA por período.
- detalle_solicitud agrega preferencia_turno (MANANA / TARDE / SIN_PREFERENCIA).
- Nuevas tablas: revision_solicitud (inmutable por trigger), revision_curso, validacion_solicitud, limite_creditos_estudiante, historial_academico, importacion_historial, cambio_historial, carga_docente y cambio_carga_docente.
- 10 modelos nuevos y 3 modelos actualizados; las relaciones con core/seguridad usan App\Shared\Models.
- PreMatriculaService::demandaPorCurso() para el dashboard de demanda.
- 21 pruebas nuevas en tests/Feature/PreMatricula y tests/Unit/PreMatricula.
- Documentación en docs/modulos/pre-matricula (adaptación de BD, arquitectura, diagramas de flujo y manual de usuario).

Cómo probar
1. docker compose up -d --build app
2. docker compose exec app php artisan module:migrate pre-matricula
3. docker compose exec app php artisan module:migrate:status pre-matricula  (deben aparecer las 12 migraciones 2026_11_18_* como Ran)
4. Volver a ejecutar el paso 2: debe responder "Nothing to migrate".
5. En instalación local, contra una base de pruebas: vendor/bin/phpunit --filter=PreMatricula y vendor/bin/phpunit tests/Unit/ModuleStructureTest.php

Impacto / Riesgos
- Requiere ejecutar migraciones. Solo tocan el esquema prematricula; no modifican core, seguridad, comun ni otros módulos.
- Se actualizaron dos pruebas existentes que contaban las tablas y FK del módulo: tests/Feature/PrematriculaDatabaseTest.php y tests/Feature/Database/ModularDatabaseStructureTest.php (prematricula pasa de 3 a 12 tablas).
- Alex y Nick (PDF y dashboard) dependen de revision_solicitud, revision_curso y demandaPorCurso().
- Pendiente del equipo: agregar App\Shared\Models\Core\Docente para declarar la relación de CargaDocente.

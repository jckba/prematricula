# Módulo Pre Matrícula — documentación

| Documento | Contenido |
| --- | --- |
| [01-adaptacion-base-de-datos.md](01-adaptacion-base-de-datos.md) | Cómo se adaptó el modelo del proyecto original al esquema `prematricula` del portal (casos 1 y 2), tabla de equivalencias y MER |
| [02-manual-de-arquitectura.md](02-manual-de-arquitectura.md) | Capas, carpetas, dependencias, reglas de negocio que protege la base de datos y cómo probar |
| [03-diagramas-de-flujo.md](03-diagramas-de-flujo.md) | Flujos del estudiante, de la corrección, del coordinador y de la carga docente |
| [04-manual-de-usuario.md](04-manual-de-usuario.md) | Uso del módulo por rol: estudiante (`alumno`) y coordinador (`director_escuela`, `secretaria`) |

Proyecto de origen: `jckba/prematricula` (API REST Laravel 13 + PostgreSQL).
Destino: módulo `PreMatricula` del portal (`app/Modules/PreMatricula`, esquema `prematricula`).

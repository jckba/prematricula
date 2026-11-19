# Adaptación del módulo Pre Matrícula al portal

Esta carpeta no forma parte de la aplicación de este repositorio: es el trabajo de adaptar el modelo de datos de este proyecto a la base de datos del portal del grupo (`Alex300VA24/project-portal-gestion-direccion-escuela`, rama `develop`).

| Carpeta / archivo | Contenido |
| --- | --- |
| `archivos/` | Los 34 archivos con la ruta que tendrán en el portal (`app/Modules/PreMatricula/...`, `tests/...`, `docs/modulos/pre-matricula/...`) |
| `archivos/docs/modulos/pre-matricula/` | Adaptación de la BD (casos 1 y 2), manual de arquitectura, diagramas de flujo y manual de usuario |
| `parches/` | Los 10 commits listos para aplicar en el portal |
| `evidencia/` | Esquema `prematricula` antes y después, y los archivos originales que se modificaron |
| `PR_BORRADOR.md` | Título y cuerpo del Pull Request para el portal |

## Resultado

- El esquema `prematricula` pasa de 3 a 12 tablas, con 12 migraciones que siguen la convención de `/docs` del portal.
- Se reutilizan `core` y `seguridad` (estudiante, curso, plan, prerrequisito, período, docente, usuario).
- La suite del portal pasa de 126 a 155 pruebas, todas OK en PostgreSQL.

## Integrar al portal (cuando se apruebe)

Desde la raíz del portal:

    git switch develop && git pull
    git switch -c feature/prematricula-adaptacion-modelo develop
    git am ruta/a/adaptacion-portal/parches/*.patch
    git rebase develop --exec "git commit --amend --no-edit --reset-author"
    php artisan module:migrate pre-matricula

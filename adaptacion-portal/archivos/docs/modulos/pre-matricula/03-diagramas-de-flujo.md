# Diagramas de flujo — Módulo Pre Matrícula

Los diagramas usan Mermaid: GitHub los dibuja automáticamente, y también se pueden pegar en [mermaid.live](https://mermaid.live) para exportarlos como imagen.

## 1. Estudiante: registrar la prematrícula

```mermaid
flowchart TD
    A([Inicio]) --> B[Iniciar sesión con rol alumno]
    B --> C{¿Hay período en PREMATRICULA<br/>con una ventana activa?}
    C -- No --> C1[Ver mensaje: prematrícula cerrada] --> Z([Fin])
    C -- Sí --> D[Abrir o crear su solicitud del período<br/>estado BORRADOR]
    D --> E[Ver cursos elegibles]
    E --> F[Agregar curso y elegir turno<br/>MANANA / TARDE / SIN_PREFERENCIA]
    F --> G{¿Curso válido?<br/>activo, no aprobado, no en curso,<br/>prerrequisitos y créditos}
    G -- No --> G1[Mostrar motivo] --> E
    G -- Sí --> H[Guardar en detalle_solicitud]
    H --> I{¿Agregar otro curso?}
    I -- Sí --> E
    I -- No --> J[Enviar]
    J --> K{¿Selección no vacía y<br/>ventana aún activa?}
    K -- No --> K1[Mostrar error] --> E
    K -- Sí --> L[Crear revision_solicitud ENVIO<br/>snapshot + número + constancia + PDF]
    L --> M[Copiar cursos a revision_curso]
    M --> N[Solicitud ENVIADA<br/>id_revision_vigente = nueva revisión]
    N --> O[Descargar constancia PDF]
    O --> Z
```

## 2. Estudiante: corregir en ventana extraordinaria

```mermaid
flowchart TD
    A([Inicio]) --> B{¿Hay ventana EXTRAORDINARIA activa<br/>y la solicitud está ENVIADA?}
    B -- No --> B1[No se puede corregir] --> Z([Fin])
    B -- Sí --> C[Solicitud vuelve a BORRADOR<br/>id_cronograma_correccion = ventana]
    C --> D[La revisión anterior se conserva<br/>y sigue contando en la demanda]
    D --> E[Editar cursos y turnos]
    E --> F[Reenviar]
    F --> G[Nueva revision_solicitud<br/>numero_revision + 1 y nueva constancia]
    G --> H[id_revision_vigente apunta a la nueva]
    H --> Z
```

## 3. Coordinador: preparar el período

```mermaid
flowchart TD
    A([Inicio]) --> B[Iniciar sesión<br/>director_escuela o secretaria]
    B --> C[Verificar período en core.periodo_academico]
    C --> D[Importar resultados oficiales XLSX]
    D --> E{¿Archivo válido?}
    E -- No --> E1[Ver errores por fila] --> D
    E -- Sí --> F[Guardar historial_academico<br/>+ importacion_historial + cambio_historial]
    F --> G[Revalidar solicitudes abiertas<br/>validacion_solicitud]
    G --> H[Crear ventana ORDINARIA]
    H --> I{¿Hace falta reabrir?}
    I -- Sí --> J[Crear ventana EXTRAORDINARIA<br/>posterior y sin solaparse]
    I -- No --> K[Consultar demanda por curso]
    J --> K
    K --> L[Exportar o ver dashboard]
    L --> Z([Fin])
```

## 4. Coordinador: carga docente semanal

```mermaid
flowchart TD
    A([Inicio]) --> B[Elegir período y curso]
    B --> C[Elegir docente activo de core.docente]
    C --> D[Ingresar horas de teoría, práctica y laboratorio]
    D --> E{¿Horas ≥ 0 y total > 0?}
    E -- No --> D
    E -- Sí --> F{¿No supera las horas del curso<br/>ni el máximo del docente?}
    F -- No --> F1[Mostrar exceso] --> D
    F -- Sí --> G{¿Ya existe una carga vigente<br/>período + curso + docente?}
    G -- Sí --> G1[Modificar horas<br/>acción CAMBIO]
    G -- No --> G2[Crear carga_docente<br/>acción ALTA]
    G1 --> H[Registrar cambio_carga_docente<br/>antes / después]
    G2 --> H
    H --> I{¿Dar de baja?}
    I -- Sí --> J[eliminado_en = ahora<br/>acción BAJA; se conserva la fila]
    I -- No --> Z([Fin])
    J --> Z
```

## 5. Estados de la solicitud

```mermaid
stateDiagram-v2
    [*] --> BORRADOR: crear en ventana ORDINARIA
    BORRADOR --> ENVIADA: enviar (revisión n)
    ENVIADA --> BORRADOR: corregir en ventana EXTRAORDINARIA
    BORRADOR --> ENVIADA: reenviar (revisión n+1)
    ENVIADA --> [*]: cierre del período
```

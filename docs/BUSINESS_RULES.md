# Reglas de negocio

## Identidad y permisos

Los roles son `ESTUDIANTE` y `COORDINADOR`; los cargos coordinadores son `DIRECTOR_ESCUELA` y `SECRETARIA`, con iguales permisos. Cuenta/perfil inactivos no pueden operar. Los docentes no son usuarios autenticables.

Login acepta correo o código universitario textual exacto mediante `identifier`; el alias `correo_institucional` se conserva. No enviar ambos. Se conservan ceros iniciales. Credenciales inexistentes, ambiguas o inactivas reciben el mismo 401. Tokens Bearer duran 480 minutos; logout revoca el token actual y cambiar credenciales/estado revoca tokens de la cuenta.

El estudiante ve solo sus solicitudes, revisiones, historial y PDF. El coordinador administra cuentas estudiantiles, malla, períodos, ventanas, resultados, docentes, asignaciones y reportes. Los recursos ajenos al estudiante devuelven 404. Las consultas académicas no exponen correo de la cuenta, contraseña, token ni PDF embebido.

## Estados y ventanas

| Dominio | Estados |
| --- | --- |
| Perfil, curso, docente | ACTIVO, INACTIVO |
| Período | PLANIFICACION, PREMATRICULA, EN_CURSO, CERRADO |
| Historial oficial | EN_CURSO, APROBADO, DESAPROBADO, RETIRADO |
| Prematrícula nueva | BORRADOR, ENVIADA |
| Validación académica | VALIDA, PENDIENTE_RESULTADOS, REQUIERE_AJUSTE |

APROBADA/RECHAZADA sobreviven solo para leer registros heredados. No hay endpoints ni casos de uso operativos que aprueben o rechacen prematrículas.

Las ventanas son globales para el período: una ORDINARIA y posteriores EXTRAORDINARIA sin solapamiento. El intervalo es inicio inclusivo y fin exclusivo. Los registros de ventana no se editan/eliminan. Una extraordinaria requiere una ordinaria anterior y comienza después de las ventanas previas.

Solo el próximo período conocido respecto al período EN_CURSO puede operar como objetivo. Debe estar en PREMATRICULA con una única ventana activa aplicable. Sin período vigente se exige igualmente un único período habilitado; una configuración ambigua se rechaza.

## Elegibilidad, envío y corrección

La malla es única y no tiene versiones de planes. Cursos adelantados se permiten por elegibilidad, sin imponer un límite de ciclo. No se puede seleccionar un curso inactivo, ya aprobado o actualmente en curso. Un prerrequisito aprobado habilita; EN_CURSO permite selección provisional; sin evidencia aprobada/en curso exige ajuste. Una aprobación anterior prevalece sobre intentos posteriores no aprobados; en otro caso se toma el intento anterior más reciente. Resultados futuros no habilitan períodos anteriores.

Las notas y su estado oficial se importan: no se deriva aprobación mediante un umbral numérico inventado. La importación audita correcciones y revalida solicitudes de períodos no cerrados. Desactivar/reactivar un curso también revalida las solicitudes abiertas afectadas, sin reescribir snapshots ni PDF.

El borrador respeta créditos máximos y evita cursos duplicados. Enviar exige selección no vacía, ventana activa y elegibilidad vigente; se registra automáticamente como ENVIADA, sin aprobación individual. El snapshot, su PDF y la revisión vigente se guardan atómicamente.

Corregir requiere una ventana extraordinaria activa y una solicitud enviada. Se marca BORRADOR con contexto de corrección y se conserva la revisión registrada. Guardar preferencias o cursos no reemplaza esa evidencia. El reenvío crea otra revisión/constancia y cambia el puntero vigente. Expirar la ventana bloquea ediciones/envío; ninguna ventana individual se abre para un alumno.

## Demanda estimada

Se cuentan solo revisiones ENVIO registradas y vigentes, con cursos indexados y snapshot consistente. No cuentan borradores iniciales, capturas LEGACY_CAPTURE, versiones reemplazadas o selecciones vacías. Cada estudiante/curso/período aparece una sola vez.

`registrada = valida + pendiente_resultados + requiere_ajuste + sin_validacion`. Una solicitud global con ajuste o corrección abierta no aporta demanda validada. Mientras se corrige, se conserva la última selección enviada como registrada y con ajuste; no se cuentan sus cambios de borrador.

Las preferencias MANANA, TARDE y SIN_PREFERENCIA son preferencias declaradas, no horarios ni grupos. La población y alumnos no enviados son null porque no existe padrón académico por período. No se usa la población activa actual para inventar una población histórica.

## Carga docente semanal

Cada asignación vigente combina período, curso y docente; sus vínculos son inmutables. Se permiten varios docentes por curso y varios cursos por docente. Se distribuyen teoría, práctica y laboratorio independientemente, con centésimas exactas, valores no negativos y al menos uno positivo.

La suma de cada tipo no supera la capacidad semanal del curso/período. La suma de todos los tipos/cursos del docente no supera su máximo de ese período, si está configurado. No se suman períodos diferentes. Una reducción del máximo se rechaza si invalida carga existente en cualquiera de ellos.

No se asignan docentes ni cursos inactivos. Las asignaciones parciales son válidas. La baja lógica de períodos abiertos conserva auditoría y libera capacidad; se puede crear una nueva combinación vigente. Períodos CERRADO no admiten altas, cambios ni bajas. Desactivar un docente conserva sus asignaciones para revisión expresa.

La comparación demanda/cobertura no multiplica horas por alumnos ni calcula grupos. El modelo no representa unidades paralelas, secciones, turnos, aulas, horarios ni conflictos de tiempo.

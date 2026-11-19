# Manual de usuario — Módulo Pre Matrícula

> **Estado:** este manual describe las funciones del módulo según el proyecto original y el modelo ya adaptado al portal. Donde dice **[Captura]** va una imagen de la pantalla real cuando la interfaz esté integrada.

## 1. ¿Qué es la prematrícula?

La prematrícula permite que cada estudiante **declare qué cursos piensa llevar** el próximo período y en qué turno los prefiere. Con esa información, la Dirección de Escuela calcula la **demanda por curso** y planifica la **carga docente**.

Importante:

- La prematrícula **no es la matrícula definitiva** y no se aprueba ni se rechaza: al enviarla queda **registrada**.
- El turno es solo una **preferencia**; no asigna horario, sección ni aula.

## 2. Acceso

1. Entrar a la dirección del portal (en local: `http://localhost:8000`).
2. Iniciar sesión con el correo y la contraseña entregados por el equipo.
3. En el menú, abrir **Pre Matrícula**.

| Rol en el portal | Quién es | Qué puede hacer |
| --- | --- | --- |
| `alumno` | Estudiante | Su propia solicitud, su constancia y su historial |
| `director_escuela` | Director(a) de escuela | Todo lo del coordinador |
| `secretaria` | Secretaría académica | Lo mismo que el director (mismos permisos) |

[Captura: pantalla de inicio de sesión]

---

## 3. Estudiante (`alumno`)

### 3.1 Ver si la prematrícula está abierta

Al entrar al módulo verás el período y la ventana vigente:

- **Ventana ordinaria:** el plazo normal para registrar la prematrícula.
- **Ventana extraordinaria:** una reapertura, solo para corregir una prematrícula ya enviada.
- Si no hay ventana activa, el sistema muestra que la prematrícula está cerrada. Igual puedes consultar tus envíos anteriores.

Las ventanas empiezan a la hora indicada y terminan justo en la hora de cierre: a esa hora ya no se acepta el envío.

[Captura: encabezado con período y ventana]

### 3.2 Elegir cursos

1. Abre **Cursos disponibles**. Solo aparecen los cursos que puedes llevar.
2. Un curso **no** aparece o aparece bloqueado si:
   - está inactivo;
   - ya lo aprobaste;
   - lo estás llevando en el período actual;
   - te falta un prerrequisito aprobado (si el prerrequisito está *en curso*, se permite de forma provisional).
3. Pulsa **Agregar** en cada curso.
4. Elige el turno: **Mañana**, **Tarde** o **Sin preferencia**.
5. Revisa el contador de créditos. No puedes superar tu **límite de créditos** (22 por defecto, o el que haya fijado la Dirección).

Puedes quitar cursos o cambiar el turno mientras la ventana siga activa. Todo se guarda como **borrador**.

[Captura: lista de cursos disponibles y borrador]

### 3.3 Enviar

1. Pulsa **Enviar prematrícula**.
2. El sistema comprueba que tengas al menos un curso, que la ventana siga abierta y que tus cursos sigan siendo elegibles.
3. Si todo está bien, la solicitud pasa a **ENVIADA** y se genera una **constancia PDF** con un código único.

[Captura: confirmación de envío]

### 3.4 Descargar la constancia

En **Mis prematrículas** pulsa **Descargar constancia**. El PDF guarda exactamente lo que enviaste en ese momento, aunque después cambie el catálogo de cursos. También puedes descargar las constancias de envíos anteriores.

[Captura: constancia PDF]

### 3.5 Corregir un envío

Solo es posible cuando la Dirección abre una **ventana extraordinaria**:

1. Abre tu solicitud y pulsa **Corregir**.
2. La solicitud vuelve a borrador. Tu envío anterior **sigue registrado** hasta que reenvíes.
3. Haz los cambios y pulsa **Reenviar**. Se crea una nueva versión (revisión 2, 3, …) con una constancia nueva.

Si la ventana extraordinaria se cierra antes de reenviar, queda vigente tu último envío.

### 3.6 Ver mi historial académico

En **Historial** ves tus resultados oficiales (aprobado, desaprobado, en curso o retirado). Es solo de lectura.

### 3.7 Mensajes frecuentes

| Mensaje | Qué significa | Qué hacer |
| --- | --- | --- |
| La prematrícula está cerrada | No hay ventana activa | Esperar la ventana o consultar a Secretaría |
| Excede el límite de créditos | La suma supera tu máximo | Quitar un curso |
| Falta prerrequisito | No tienes aprobado el curso requerido | Elegir otro curso o consultar a Dirección |
| Curso ya agregado | El curso ya está en tu borrador | Ninguna acción |
| Requiere ajuste | Un resultado nuevo cambió la elegibilidad de un curso enviado | Corregir en la próxima ventana extraordinaria |

---

## 4. Coordinador (`director_escuela` / `secretaria`)

### 4.1 Importar resultados oficiales

1. Abre **Historial académico → Importar**.
2. Descarga la **plantilla XLSX** y complétala (código de estudiante, curso, período, nota y estado).
3. Sube el archivo y pulsa **Validar**. Verás los errores por fila sin guardar nada.
4. Escribe el **motivo** de la carga y pulsa **Confirmar**.
5. En **Importaciones** puedes ver qué cambió en cada resultado (antes y después) y quién lo hizo.

Al importar, el sistema vuelve a validar las solicitudes abiertas.

[Captura: validación de importación]

### 4.2 Abrir ventanas de prematrícula

1. Abre **Períodos** y elige el período en estado PREMATRICULA.
2. Pulsa **Nueva ventana**:
   - **Ordinaria:** solo una por período.
   - **Extraordinaria:** solo después de la ordinaria y sin solaparse con otras ventanas.
3. Indica inicio, fin y motivo.

Las ventanas no se editan ni se borran: quedan como historial.

### 4.3 Límite de créditos

En la ficha del estudiante puedes fijar su **límite de créditos**. Si no se fija, se usan 22.

### 4.4 Consultar prematrículas

En **Prematrículas** ves las solicitudes por período, sus envíos y sus constancias. Es una consulta: no existe aprobar ni rechazar.

### 4.5 Demanda por curso

En **Demanda** eliges el período y ves, por curso:

- número de estudiantes que lo prematricularon;
- cuántos prefieren mañana, tarde o no tienen preferencia.

Solo cuenta el **último envío vigente** de cada estudiante; los borradores no cuentan. Estas cifras alimentan el **dashboard**.

[Captura: dashboard de demanda]

### 4.6 Carga docente

1. Abre **Carga docente** y elige período y curso.
2. Elige un docente activo e ingresa sus horas de teoría, práctica y laboratorio.
3. El sistema no permite superar las horas del curso ni el máximo semanal del docente.
4. Para quitar una asignación usa **Dar de baja**: se conserva en el historial y se liberan las horas.
5. En **Historial** de cada asignación ves quién la creó, cambió o dio de baja.

---

## 5. Preguntas frecuentes

**¿Mi prematrícula me asegura vacante?**
No. Es una declaración de intención para planificar; la matrícula definitiva es otro proceso.

**¿Puedo cambiar mi envío cuando quiera?**
Solo durante una ventana extraordinaria.

**¿Por qué mi constancia muestra un curso que ya no está en el catálogo?**
La constancia es una foto del momento del envío y no se modifica.

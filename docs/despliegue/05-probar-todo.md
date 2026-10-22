# 5 · Probar todo

Lista para recorrer **antes de imprimir los carteles** y cada vez que se actualice el sistema. Cada
casilla es algo que un docente o un técnico va a hacer de verdad. Si una falla, se arregla antes de
seguir: un fallo encontrado aquí no le cuesta nada a nadie; encontrado en un aula, le cuesta la
clase a un docente.

**Haz las pruebas en orden:** las de soporte usan los reportes creados en las del celular.

Al terminar, deja el contador en cero (última sección) para que las pruebas no se cuenten como datos
de la investigación.

---

## Antes de empezar

- [ ] <http://localhost:8080/salud> en la PC responde `"status":"ok"`.
- [ ] Si usas IA: en esa misma respuesta, `"llm":"ok"`.
- [ ] En el panel, **Conocimiento**: las ocho guías con procesamiento **«Listo»** y estado **«Publicado»**.

---

## Desde el celular (como docente)

Con un celular **con el WiFi apagado**, usando datos móviles, entrando por la dirección pública o
escaneando el QR.

### Entrar y ubicarse

- [ ] El QR abre el sistema **sin pedir usuario ni contraseña**.
- [ ] Aparece la pantalla «¿Dónde estás?», con candado de conexión segura.
- [ ] Al elegir un pabellón, la lista de pisos muestra **solo los de ese pabellón**.
- [ ] Al elegir un piso, la lista de aulas muestra **solo las de ese piso**.
- [ ] Escribir el código de un aula en el buscador la encuentra sin recorrer la lista.
- [ ] Al confirmar, la pantalla muestra el código del aula elegida.

### Las opciones dependen del aula

- [ ] En un aula **sin ecran** según el checklist, **no** aparece la opción del ecran.
- [ ] En un aula **con** ecran, sí aparece.
- [ ] La opción **«Otro problema»** aparece siempre, en cualquier aula.
- [ ] En un ambiente **HYFLEX**, el sistema **no** pregunta qué falla: avisa que ese ambiente lo
      atiende soporte directamente y pasa a pedir ayuda.

### Diagnóstico guiado

- [ ] Elegir **«El proyector no prende»** muestra pasos cortos, uno por pantalla.
- [ ] Ningún paso pide abrir un equipo ni tocar un tablero eléctrico.
- [ ] Cada problema ofrece **cuatro o cinco soluciones**, una por pantalla, cada una con una imagen.
- [ ] En **cualquier** paso, **«Listo, ya funciona»** cierra el reporte directamente con
      **«Problema resuelto»**, sin volver a preguntar si se solucionó.
- [ ] **«Lo hice y sigue igual»** pasa a la siguiente solución. Después de la última, ofrece
      **pedir ayuda a soporte**.
- [ ] En el ecran y en el mouse, el primer paso **pregunta cómo es el equipo** (interruptor o manual;
      con cable o inalámbrico) y las soluciones cambian según la respuesta.
- [ ] Al terminar, aparece **«¿Te resultó fácil de usar?»** del 1 al 5. Es opcional.

### Riesgo físico

- [ ] Escribir **«sale humo del proyector»** (o «huele a quemado») **no** muestra pasos: dice que no
      se toque el equipo y pasa directo a pedir ayuda, con prioridad máxima.

### La inteligencia artificial (solo si está activa)

- [ ] Escribir con palabras propias, por ejemplo **«no se ve nada en la pizarra»**, lleva a la
      categoría correcta (proyector o HDMI).
- [ ] Escribir **«el cañón no prende»** lleva a **proyector**, no a computadora. La IA puede
      tardar unos segundos en responder: no vuelvas a pulsar.
- [ ] En **Preguntar**, «¿cómo cambio la entrada del proyector a HDMI?» responde **y dice de qué guía
      sacó la respuesta**.
- [ ] Preguntar algo ajeno, como **«cuánto cuesta la matrícula»**, **no** inventa una respuesta: dice
      que no tiene información y ofrece pedir ayuda.

### Pedir ayuda a soporte

- [ ] El formulario pide si el problema impide seguir con la clase y que se marque **«Confirmo
      que necesito que un técnico venga al aula»**. Nada más es obligatorio.
- [ ] **Adjuntar una foto** con la cámara del celular funciona.
- [ ] **No** adjuntar foto también funciona: la foto es opcional.
- [ ] Al enviar, aparece un **enlace de seguimiento**.
- [ ] El enlace de seguimiento abre **sin pedir usuario ni contraseña** y muestra el estado.
- [ ] El seguimiento **no** muestra el nombre del técnico asignado.

### Protección contra abusos

- [ ] Enviar **dos veces el mismo problema** en la misma aula, seguido, ofrece **sumarse al reporte
      existente** en lugar de crear otro.

### El panel no se ve desde internet

- [ ] La dirección pública con **`/entrar`** al final responde **«No encontrado» (404)**.
- [ ] La dirección pública con **`/panel`** al final responde **«No encontrado» (404)**.
- [ ] La dirección pública con **`/phpmyadmin/`** al final responde **«No encontrado» (404)**.
- [ ] La dirección pública con **`/cgi-bin/printenv.pl`** al final responde **«No encontrado» (404)**.

---

## En la PC de soporte (como técnico)

Entrando por <http://localhost:8080/entrar>.

### Bandeja y atención

- [ ] Los reportes de las pruebas anteriores aparecen en **Incidencias**.
- [ ] Con la bandeja abierta, crear un reporte nuevo desde el celular hace aparecer un **aviso** en
      menos de medio minuto, sin recargar la página.
- [ ] El detalle muestra: aula, categoría, texto del docente, foto si la hubo, y **los pasos que el
      docente ya probó con sus respuestas**.
- [ ] El reporte de **humo** aparece con la **prioridad más alta** y una advertencia de riesgo.
- [ ] **Tomar** un reporte lo asigna al técnico que lo toma.
- [ ] **Resolver** un reporte **exige** elegir cómo se resolvió —no viene ninguna opción marcada— y
      escribir qué se hizo.
- [ ] El historial del reporte dice los estados por su nombre: «Nuevo → En atención».
- [ ] En el celular, el enlace de seguimiento muestra el cambio de estado.

### Historial y reportes

- [ ] Desde el detalle, **Historial del aula** muestra los reportes anteriores de esa aula.
- [ ] **Reportes** muestra las cifras agrupadas y avisa si hay muy pocos casos para concluir algo.

### Administración

- [ ] **Usuarios › Nuevo usuario** crea una cuenta de técnico. Esa cuenta **no** ve el menú de
      usuarios.
- [ ] **Código QR** muestra el QR con la dirección pública y **sin recuadro rojo**.
- [ ] **Ver cartel para imprimir** muestra el cartel **sin** «NO PEGAR».

---

## Lo que corre solo

Para comprobarlo hay que esperar, así que conviene revisarlo al día siguiente de instalar.

- [ ] **Programador de tareas** de Windows: **Incidencias - programador** con «Última ejecución» de
      hace menos de dos minutos y resultado `0x0`.
- [ ] En `C:\incidencias\storage\app\respaldos` (o en la carpeta de `RESPALDO_CARPETA`) hay un archivo
      `incidencias-AAAA-MM-DD_….sql` **de hoy**, de varios cientos de KB o más.
- [ ] Reiniciar la PC: sin que nadie inicie sesión, la dirección pública sigue abriendo la pantalla
      del docente al cabo de un par de minutos.

---

## Al terminar: dejar el contador en cero

Las pruebas crearon reportes, encuestas y consultas que **no** son datos de la investigación. Hay que
borrarlos antes de que empiecen los docentes reales:

```powershell
cd C:\incidencias
php artisan pruebas:reiniciar --force
```

**Qué tiene que salirte:** el recuento de lo borrado. Se conservan las aulas, los equipos, las guías,
los procedimientos, las imágenes y las cuentas de usuario.

> **Esto se hace una sola vez, antes de la primera clase real.** Después, nunca: borraría datos de
> la investigación.

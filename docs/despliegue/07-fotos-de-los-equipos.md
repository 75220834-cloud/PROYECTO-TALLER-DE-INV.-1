# 7 · Fotos de los equipos

Qué fotos tomar, cómo tomarlas y cómo subirlas para que aparezcan en los pasos que sigue el docente.

**Por qué importan.** Un paso que dice «revisa el cable HDMI» solo le sirve a quien ya sabe qué es
un HDMI. La foto es lo que hace que el docente que no lo sabe pueda seguir el paso. Sin fotos, el
sistema funciona igual, pero más docentes terminan pidiendo soporte sin haber intentado nada.

**Mientras no estén las fotos**, el sistema puede mostrar ilustraciones provisionales (sección
final). Cada una dice arriba «ILUSTRACIÓN PROVISIONAL», para que nadie la confunda con una foto real.

---

## Antes de empezar

- **Pide autorización** a quien corresponda en la universidad para fotografiar los equipos de las
  aulas. Este proyecto no sabe quién la da: confírmalo antes de salir a tomar fotos.
- Toma las fotos **con el aula vacía**. Ninguna foto puede tener personas, pantallas con datos de
  alguien ni documentos.
- Basta un celular. El sistema reduce cada foto a un tamaño liviano y le borra los datos ocultos,
  incluida la ubicación GPS.

## Cómo tomar cada foto

| Hazlo así | Por qué |
|---|---|
| **Acércate:** que la pieza ocupe casi toda la foto | El docente la ve en un celular, en pequeño |
| Con **buena luz**, sin flash sobre pantallas o plástico brillante | El flash deja un reflejo blanco que tapa justo lo que hay que ver |
| **Horizontal**, salvo los controles remotos, que salen mejor verticales | Así llenan el ancho de la pantalla del celular |
| Que se lean las **letras** que el paso menciona («IN», «OUT», «Source») | Muchos pasos dependen de leer una palabra impresa en el equipo |
| Del **mismo modelo** que hay en la mayoría de las aulas | Si en un aula el equipo es distinto, el docente igual se orienta por la forma |

---

## Las fotos

Cada foto se sube con una **clave**. La clave decide en qué pasos aparece: una foto subida con la
clave `hdmi_port` sale en **todos** los pasos que hablan de la entrada HDMI, en cualquier problema.

Si subes **varias fotos con la misma clave**, la más reciente se muestra primero y las demás
aparecen en el botón «Ver otra vista». Así, por ejemplo, el botón de encendido de una «todo en uno»,
de una torre y de un NUC se suben con la misma clave, y cada docente ve el de su equipo.

### Prioridad 1 · con estas ya funciona

| # | Foto | Clave | Dónde aparece |
|---|---|---|---|
| 1 | **Computadora «todo en uno» (AIO) completa**, de frente | `computer` | «¿Cómo está la computadora?» y reiniciar |
| 2 | **Botón de encendido de la AIO**, de cerca | `computer_power_button` | Prender, apagar a la fuerza, descargar energía |
| 3 | **Cable de corriente de la AIO**, enchufado por detrás | `computer_power_cable` | «Empuja hasta el fondo el cable de corriente» |
| 4 | **Regleta** del aula, con su interruptor | `power_strip` | Revisar la regleta, de la computadora y del proyector |
| 5 | **Proyector** en el techo, vista general, que se vea su botón | `projector` | Interruptor del proyector, su botón propio |
| 6 | **Luces del proyector**, de cerca | `projector_leds` | «¿Cómo está la luz del proyector?» y esperar a que enfríe |
| 7 | **Control del proyector**, apuntando al proyector | `projector_remote` | Prender con el control, apuntar bien |
| 8 | **Tapa de pilas del control, abierta**, que se vean el + y el − | `remote_batteries` | Sacar y volver a poner las pilas |
| 9 | **Botones del control**: «Source» o «Input», «Auto», «Blank» o «AV Mute», «Focus» | `remote_buttons` | Cambiar de entrada, ajuste automático, pantalla en negro, enfoque |
| 10 | **Entrada HDMI de la AIO**, de cerca, que se lea «IN» u «OUT» si lo dice | `hdmi_port` | Reconectar el HDMI, elegir la entrada correcta |
| 11 | **Cable HDMI**, la punta de cerca | `hdmi_port` (otra vista) | Junto a la foto 10 |
| 12 | **Pared con el mensaje «Sin señal»** del proyector | `projector_no_signal` | «¿Qué se ve en la pared?» |
| 13 | **Interruptor de pared del ecran** | `screen_switch` | Bajar el ecran, botón Stop, botón contrario |

### Prioridad 2 · mejoran mucho, pero pueden esperar

| # | Foto | Clave | Dónde aparece |
|---|---|---|---|
| 14 | **PC de torre** con su monitor (laboratorios), que se vea el cable entre ambos | `tower_pc` | Monitor apagado, cable de video |
| 15 | **Botón de encendido de la torre**, de cerca | `computer_power_button` (otra vista) | Junto a la foto 2 |
| 16 | **NUC** (la cajita pequeña) y su botón | `computer_power_button` (otra vista) | Junto a la foto 2 |
| 17 | **Entradas USB de la AIO** | `usb_ports` | Reconectar mouse o teclado, probar otra entrada |
| 18 | **Mouse** con su receptor USB, y por debajo si tiene interruptor | `mouse` | Mouse inalámbrico, superficie |
| 19 | **Teclado**, que se vean «Bloq Num», «Bloq Mayús» y la tecla de Windows | `keyboard` | Teclas que no funcionan, reiniciar con el teclado |
| 20 | **Parlante del aula** y su perilla de volumen | `speaker` | Prender y subir los parlantes |
| 21 | **Cable de audio** (punta verde) conectado en la computadora | `audio_cable` | Revisar el cable de audio |
| 22 | **Ecran bajado**, entero, que se vea el borde de abajo | `screen` | Tipo de ecran, obstáculos, ecran manual |

### Capturas de pantalla · desde una computadora del aula

No son fotos: se toman en la propia computadora con **Windows + Shift + S** y se guardan como imagen.
Que no se vea ningún nombre ni archivo personal.

| # | Captura | Clave | Dónde aparece |
|---|---|---|---|
| 23 | Menú de **Windows + P** con «Duplicar» | `win_p` | Mandar la imagen al proyector |
| 24 | Menú de **Ctrl + Alt + Supr** con «Administrador de tareas» | `task_manager` | Cerrar un programa trabado |
| 25 | **Ícono del volumen** en la barra de tareas, abajo a la derecha | `volume_icon` | Sonido silenciado, volumen del programa |
| 26 | Menú del volumen con la **salida de sonido** desplegada | `audio_output` | Elegir los parlantes del aula |
| 27 | **Idioma del teclado** («ESP») en la barra de tareas | `keyboard_language` | La ñ o los signos salen cambiados |

---

## Cómo subir una foto

1. Pasa las fotos del celular a la PC de soporte (cable, correo propio o la nube institucional).
2. Entra al panel: <http://localhost:8080/entrar>.
3. Menú de la izquierda: **Banco de imágenes › Subir imagen**.
4. Completa el formulario:

   | Campo | Qué poner | Ejemplo |
   |---|---|---|
   | **Fotografía** | El archivo de la foto (JPG, PNG o WebP) | `IMG_2041.jpg` |
   | **Código** | Un nombre único en MAYÚSCULAS, con guiones | `PUERTO-HDMI-AIO` |
   | **Título** | Qué es | `Entrada HDMI de la computadora` |
   | **Descripción para quien no puede ver la imagen** | Lo que se ve, en una frase | `Costado de la computadora con la entrada HDMI marcada OUT` |
   | **Pie de foto** | Opcional: una frase que se lee debajo | `El cable del proyector va en OUT.` |
   | **De dónde salió** | Quién la tomó y dónde | `Fotografía propia de soporte — aula C305` |
   | **Licencia** | | `Propia` |
   | **Ámbito** | Siempre **Componente** | |
   | **Clave** | La clave de la tabla, **exactamente igual** | `hdmi_port` |

5. **Subir imagen**.

**Atajo:** en **Banco de imágenes › Ver qué falta**, cada paso pendiente tiene un botón «Subir la
foto de…» que abre el formulario con la clave ya escrita.

> **La clave tiene que ser exacta**: en minúsculas, con guion bajo y sin espacios. `hdmi_port` sí;
> `HDMI port` o `hdmi-port` no. Una clave mal escrita no da ningún error: la foto se sube y no
> aparece en ningún paso.

## Cómo comprobar que quedó bien

1. **Banco de imágenes**: arriba se ve el **porcentaje** de pasos que ya tienen foto real. Con
   **Ver qué falta** aparece la lista de lo pendiente, ordenada por clave.
2. Recorre el problema en el celular, como docente: la foto tiene que aparecer en el paso.

La foto real **reemplaza sola** a la ilustración provisional en todos los pasos que la usan. No hay
que borrar nada ni volver a cargar los procedimientos.

---

## Ilustraciones provisionales

Para cargarlas, en PowerShell, en la carpeta del sistema:

```powershell
cd C:\incidencias
php artisan imagenes:provisionales
```

**Qué tiene que salirte:** `24 ilustraciones provisionales cargadas.`

Son dibujos simples, no fotos: muestran dónde suele estar cada pieza, con la parte importante
marcada en rojo. Sirven para comprobar que las imágenes se ven en cada paso y para orientar al
docente mientras tanto.

- **No hace falta quitarlas para subir las fotos reales:** cada foto real gana sola a la
  ilustración de su misma clave.
- Para quitarlas todas, por ejemplo si se prefiere que un paso sin foto real se vea solo con texto:

  ```powershell
  php artisan imagenes:provisionales --quitar
  ```

Los dibujos están en `database/imagenes-provisionales/` y se generan con `generar.py`, en esa misma
carpeta.

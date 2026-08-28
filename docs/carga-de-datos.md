# Qué hay que cargar en el sistema

Guía para quien va a introducir la información real. El código ya está hecho: **falta el contenido**.

> ⚠️ Mientras haya datos `DEMO-` en el sistema, la barra amarilla de arriba lo avisa. Antes del piloto hay que purgarlos y cargar los reales.

---

## Regla de oro

**phpMyAdmin para mirar. La aplicación para escribir.**

Un `INSERT` a mano salta las validaciones (unicidad de códigos, coherencia de la jerarquía, campos obligatorios) y puede dejar la base en un estado que el sistema no sabe manejar. No es una restricción burocrática: la aplicación comprueba cosas que la base de datos por sí sola no puede.

---

## 0. Por dónde entra cada cosa

Todo se carga desde la aplicación. Ya no hace falta pedirle a nadie que toque código.

| Qué tienes | Por dónde entra |
|---|---|
| **Excel/CSV de aulas** | `php artisan import:catalogo aulas archivo.csv` |
| **Excel/CSV de equipos** | `php artisan import:catalogo equipos archivo.csv` |
| **PDF, DOCX, MD o TXT** de procedimientos | Panel → **Conocimiento** |
| **Fotos** de equipos y puertos | Panel → **Banco de imágenes** |
| **Los pasos del diagnóstico** | Panel → **Procedimientos** |
| **Tipos de problema** que ve el docente | Panel → **Tipos de problema** |
| **Cuentas del equipo de soporte** | Panel → **Usuarios** |

### La importación no escribe hasta que se lo dices

```bash
php artisan import:catalogo aulas mis-aulas.csv
```

Eso **solo simula**: te dice cuántas aulas entrarían y en qué línea está cada error, sin tocar
nada. Corriges el Excel, lo repites, y cuando salga limpio:

```bash
php artisan import:catalogo aulas mis-aulas.csv --confirmar
```

**Es todo o nada.** Si una fila está mal, no se carga ninguna: un catálogo a medias parece
completo y nadie sabe por dónde iba. Las plantillas están en `docs/plantillas/`.

---

## 1. Aulas y equipos

### El orden importa

No se puede crear un aula sin su piso, ni un piso sin su pabellón. El sistema lo impide a propósito.

```
Sede  →  Pabellón  →  Piso  →  Aula  →  Equipos
```

### Qué hace falta de cada cosa

| Nivel | Datos | Ejemplo |
|---|---|---|
| **Sede** | Código, nombre | `HYO` · Huancayo |
| **Pabellón** | Código, nombre, orden de aparición | `C` · Pabellón C · 3 |
| **Piso** | Número, etiqueta visible | `3` · "Piso 3" |
| **Aula** | **Código real**, nombre, capacidad, criticidad | `C305` · Aula C305 · 40 · Normal |
| **Equipo** | Código de inventario, tipo, aula, marca, modelo, serie | `C305-PRY` · Proyector · C305 |

### Tres cosas que suelen confundir

**El código del aula se escribe tal cual está en la puerta.** No se calcula juntando pabellón + piso + número. Por eso `LAB-2` convive sin problema con `C305` en el mismo piso.

**Un pabellón puede no empezar en el piso 1.** Si el pabellón C arranca en el 2, simplemente no se crea el piso 1.

**La criticidad sube la prioridad de los tickets.** Un auditorio o un laboratorio marcado como "Crítica" hace que sus incidencias salgan por encima en la bandeja de soporte. Úsala con criterio: si todo es crítico, nada lo es.

### Si son muchas aulas

Pásalas primero a una hoja de cálculo con esas columnas y avísale a Ítalo: hay comandos de importación con vista previa, y es mucho más rápido y seguro que crearlas una por una.

---

## 2. Procedimientos de diagnóstico

Es lo que el docente ve paso a paso cuando reporta un problema.

### Cómo se estructura un paso

| Campo | Qué es |
|---|---|
| **Pregunta** | Una sola cosa, en lenguaje llano |
| **Ayuda** | Una línea extra si hace falta |
| **Opciones** | Respuestas cerradas (botones) |
| **A dónde lleva cada respuesta** | El siguiente paso, o el final |
| **Componente** | Qué pieza física ilustra la imagen |

### Reglas que conviene respetar

**Una pregunta por paso.** "¿Está encendido y conectado?" es dos preguntas: si el docente responde "no", no se sabe a cuál.

**Siempre una opción "No estoy seguro".** Sin ella, quien duda se ve forzado a mentir, y a partir de ahí el diagnóstico va por el camino equivocado.

**Habla del síntoma, no del componente.** "No se ve la imagen" antes que "fallo de señal HDMI".

**Nunca pidas abrir equipos ni tocar cableado eléctrico.** Si un paso llega ahí, ese paso es "llama a soporte".

### Al publicar

Cada vez que se publica un procedimiento se crea una **versión nueva**. Las incidencias ya registradas siguen apuntando a la versión que de verdad se ejecutó — es lo que permite que la comparación de la investigación siga siendo válida aunque el procedimiento cambie a mitad del piloto.

---

## 3. Imágenes

**Se necesitan 15 fotos para arrancar.** La lista completa está en el Anexo A del plan maestro.

### Prioridad 1 — con esto ya funciona

Proyector: vista general · botonera con el botón de encendido · luces indicadoras · botón Source · panel de conexiones
HDMI: puerto del proyector · puerto de la PC · punta del cable
PC: botón de encendido
Audio: salida verde de la PC · volumen de los parlantes
Micrófono: interruptor · compartimiento de pilas
Eléctrico: regleta o tomacorriente
Pantalla: captura de `Win+P`

### Cómo tomarlas

| | |
|---|---|
| Resolución | Mínimo 1200 px de ancho |
| Encuadre | **Un componente por foto** |
| Ángulo | Desde donde el docente lo ve de pie |
| Iluminación | Luz del aula, sin flash directo |
| Cantidad | 2 tomas de cada uno (general + primer plano) |
| Nombre del archivo | El código, por ejemplo `PUERTO-HDMI-PC.jpg` |
| **Prohibido** | Personas, rostros, pantallas con datos, etiquetas con nombres |

Al subirlas el sistema las reduce, las convierte a WebP y **les borra los metadatos** (incluida la geolocalización, que las cámaras de celular añaden sin avisar).

### Lo que el sistema NO hace

**No genera imágenes con IA, y no debe hacerlo.** Los modelos dibujan puertos con el número de pines equivocado. Una imagen que parece correcta pero no lo es hace que el docente manipule el conector equivocado, y a diferencia de un texto falso, una imagen falsa no "suena" mal. La IA solo puede elegir entre las imágenes que ustedes carguen.

Mientras no haya fotos, el sistema usa diagramas de conectores dibujados por el equipo. Cuando lleguen las fotos, conviven: el diagrama explica **qué es** la pieza, la foto muestra **dónde está** en ese aula.

---

## 4. Información para el asistente (más adelante)

Esto es de la Fase 5-6, todavía no está construido. Cuando toque hará falta:

- Manuales y guías de los equipos
- Procedimientos escritos de soporte
- Preguntas frecuentes
- Soluciones de incidencias pasadas que valga la pena reutilizar

Formatos: PDF, Word, texto o Markdown.

**Importante:** el asistente solo podrá responder con lo que esté en esos documentos, y siempre citando de dónde lo sacó. Si no encuentra respuesta, dirá que no sabe y escalará a soporte. No inventa políticas ni procedimientos institucionales — hay pruebas automáticas que lo verifican con tolerancia cero.

---

## 5. Antes del piloto

- [ ] Purgar los datos DEMO
- [ ] Cargar aulas y equipos reales
- [ ] Cargar los procedimientos validados por soporte
- [ ] Subir las 15 fotos mínimas
- [ ] **Probar el QR impreso con un celular dentro de un aula**, en la red que usan los docentes
- [ ] Endurecer XAMPP en el servidor (ver README)
- [ ] Autorización institucional para el piloto y para fotografiar

El penúltimo punto de esa lista es el más importante: si el celular del docente no alcanza el servidor desde el aula, el piloto no puede ocurrir. Conviene comprobarlo antes que nada.

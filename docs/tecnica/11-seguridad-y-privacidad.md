# 11 · Seguridad y privacidad

> **Qué encuentras aquí:** cómo se protege el sistema, qué datos personales maneja y cómo,
> y por qué cada control está donde está. Incluye la tabla completa de roles y permisos.

---

## 11.1 El modelo de amenazas

Este sistema tiene una característica poco común que condiciona toda su seguridad:

> **Una parte del sistema está abierta a cualquiera que escanee un QR, sin autenticación,
> y lo que esa persona escribe se le muestra después a un usuario autenticado.**

Eso define las amenazas principales:

| Amenaza | Escenario | Control |
|---|---|---|
| **XSS almacenado** | Alguien escribe código malicioso en la descripción; un técnico lo abre en el panel | Política de seguridad de contenido estricta + escapado en todas las vistas |
| **Abuso del canal abierto** | Alguien genera cientos de tickets falsos y colapsa a soporte | Seis controles antiabuso |
| **Escalada de privilegios** | Un técnico modifica catálogos que afectan a todos los tickets | Veintiún permisos granulares |
| **Fuga de datos del piloto** | Extracción masiva no registrada | Permiso propio para exportar + auditoría |
| **Contenido malicioso en archivos subidos** | Un PDF o una imagen con carga útil | Validación de tipo, reprocesado, nombres aleatorios, servidos por controlador |
| **Inyección desde el modelo de lenguaje** | El modelo devuelve texto con marcado | El texto del modelo se trata como entrada no confiable |

---

## 11.2 Roles y permisos

### Los veintiún permisos

| Permiso | Qué autoriza |
|---|---|
| `locations.view` | Ver el catálogo de ubicaciones |
| `locations.manage` | Crear, editar y activar/desactivar ubicaciones |
| `equipment.view` | Ver el inventario de equipos |
| `equipment.manage` | Crear, editar y mover equipos |
| `catalogs.manage` | Administrar categorías, estados y prioridades |
| `incidents.view` | Ver incidencias |
| `incidents.assign` | Asignar incidencias a técnicos |
| `incidents.update` | Cambiar estado, prioridad y registrar diagnóstico |
| `incidents.close` | Cerrar y reabrir incidencias |
| `incidents.cancel` | Cancelar incidencias |
| `knowledge.view` | Consultar la base de conocimiento |
| `knowledge.manage` | Cargar, publicar y reindexar documentos |
| `media.manage` | Administrar el banco de imágenes |
| `diagnostics.manage` | Definir y versionar árboles de diagnóstico |
| `dashboard.view` | Ver el tablero de métricas |
| `reports.view` | Consultar reportes |
| `reports.export` | Exportar el conjunto de datos de investigación |
| `risk.view` | Consultar las señales de riesgo |
| `audit.view` | Consultar la auditoría |
| `abuse.view` | Consultar solicitudes rechazadas por antiabuso |
| `users.manage` | Administrar usuarios y roles |

### Los seis roles

| Rol | Permisos |
|---|---|
| **Administrador** | Todos |
| **Coordinador** | Ubicaciones (ver), equipos (ver y gestionar), incidencias (todo), conocimiento (ver), tablero, reportes, riesgo, antiabuso |
| **Técnico** | Ubicaciones (ver), equipos (ver), incidencias (ver, asignar, actualizar, cerrar), conocimiento (ver), tablero, riesgo |
| **Gestor de conocimiento** | Ubicaciones y equipos (ver), incidencias (ver), conocimiento (ver y gestionar), imágenes, procedimientos |
| **Investigador** | Ubicaciones, equipos e incidencias (**solo ver**), tablero, reportes, **exportar**, riesgo, antiabuso |
| **Docente** | Ninguno — no tiene cuenta |

### Las tres decisiones de este diseño

**El investigador es un rol aparte, de solo lectura.** Si el investigador usara una cuenta
de administrador para extraer datos, cualquier acción suya quedaría mezclada con las de
soporte en la auditoría y **contaminaría las métricas del piloto**. La separación no es
formalismo: protege la validez de los datos.

**El técnico no administra catálogos.** No es desconfianza: es que un cambio accidental en
el catálogo de aulas, estados o categorías afecta a **todos** los tickets, incluidos los
ya cerrados que la investigación va a analizar.

**Exportar es un permiso distinto de ver.** Descargar el detalle completo del piloto no es
lo mismo que consultar un total en pantalla. El acceso masivo tiene su propio permiso y
queda auditado.

---

## 11.3 La política de seguridad de contenido

Es el control técnico más importante del sistema.

### La decisión que la hace valer algo

**No se permite JavaScript incrustado en el HTML** (`unsafe-inline` en `script-src`).

Si se permitiera, el navegador no podría distinguir el código que escribimos nosotros del
que inyecta un atacante, y la política pasaría a ser decorativa.

Por eso **ningún `onclick=""` sobrevive en las vistas**: todo el comportamiento vive en un
único archivo de JavaScript y se engancha por atributos `data-*`.

### Por qué importa más aquí que en un sistema normal

1. **El docente entra sin autenticarse y escribe texto libre** que después se muestra en el
   panel de soporte. Un técnico autenticado leyendo la descripción de una incidencia es
   exactamente el objetivo de un ataque de este tipo.

2. **Parte del texto que se muestra proviene de un modelo de lenguaje**, que se trata como
   entrada no confiable por principio.

### La excepción que se declara en vez de disimularse

En los **estilos** sí se permite contenido incrustado. El motivo: el cartel imprimible del
QR y las anotaciones sobre las imágenes llevan sus estilos dentro precisamente para no
romperse en la impresora.

El riesgo de CSS inyectado es real pero mucho menor que el de JavaScript, y quitarlo
exigiría rehacer el cartel. Se documenta como decisión consciente.

### Las otras cabeceras

| Cabecera | Valor | Qué evita |
|---|---|---|
| `X-Content-Type-Options` | `nosniff` | Que el navegador adivine el tipo de un archivo y lo ejecute |
| `X-Frame-Options` | `DENY` | Que la página se incruste en otra para engañar al usuario |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Que se filtren URLs internas a sitios externos |
| `Permissions-Policy` | Cámara, micrófono, ubicación, pagos y USB **desactivados** | Que la página pida permisos que no necesita |

---

## 11.4 Controles antiabuso

### El principio

> **Mirar es gratis. Movilizar a un técnico no.**

El diagnóstico guiado es completamente abierto: navegar el catálogo y seguir los pasos no
le cuesta nada a la institución. Lo que se protege es la **acción costosa**: crear un
ticket que hace caminar a una persona hasta un aula.

### Por qué no hay una URL firmada

El diseño anterior contemplaba una URL firmada en el QR. Se descartó: **no es un control
real**. Quien escanea el cartel una vez conserva el enlace para siempre y puede
compartirlo.

Se protege el **efecto**, no la puerta.

### Los seis controles, en orden

| # | Control | Umbral actual | Nota |
|---|---|---|---|
| 1 | Confirmación explícita | — | No toca la base de datos |
| 2 | Señal de bot | < 3 segundos o campo trampa relleno | No toca la base de datos |
| 3 | Duplicado abierto | Ventana de 15 minutos | |
| 4 | Aula saturada | 3 solicitudes activas | |
| 5 | Límite por dispositivo | 3 por hora | |
| 6 | Límite por red | **30 por hora** | Deliberadamente laxo |
| — | Similitud de texto | 0,85 | **Solo marca, nunca bloquea** |

**Los dos primeros no consultan la base de datos.** Un bot que dispara mil peticiones no
debe costar mil consultas.

**El límite por red es laxo a propósito.** En la WiFi institucional muchos docentes
comparten la misma dirección de salida. Un umbral estrecho bloquearía usuarios legítimos.
Nunca es el control principal.

### El campo trampa en lugar de un CAPTCHA

Un campo oculto que las personas no ven y los bots rellenan.

Se prefiere a un CAPTCHA porque un CAPTCHA **contradice de frente** el requisito de que el
sistema sea usable por docentes con poca familiaridad tecnológica. Un CAPTCHA delante de
alguien con una clase esperando garantiza que use WhatsApp.

### Todos los umbrales son provisionales, y se dice

Se van a calibrar en la primera semana del piloto observando la tabla de rechazos. **El
ajuste hay que documentarlo**: cambiar un umbral sin dejar constancia hace que los datos de
antes y después dejen de ser comparables.

---

## 11.5 Datos personales

### Qué NO se guarda del docente

| Dato | ¿Se guarda? |
|---|---|
| Nombre | **No** |
| Correo | **No** |
| Código de docente | **No** |
| Teléfono | **No** |
| Dirección IP en texto claro | **No** |

### Qué sí se guarda, y en qué forma

| Dato | Forma | Para qué |
|---|---|---|
| Identificador de dispositivo | Cadena aleatoria en una cookie | Detectar abuso desde un mismo navegador |
| Dirección IP | **Huella criptográfica**, nunca en claro | Detectar abuso desde una misma red |
| Descripción del problema | Texto tal cual lo escribió | Es el objeto del sistema |
| Foto | Opcional, reprocesada sin metadatos | Ayudar al técnico |
| Dato de contacto | Opcional, solo si el docente lo escribe | Coordinar la visita |

### Por qué la IP va cifrada

Una dirección IP es un dato personal. Guardarla en claro convierte una base de incidencias
técnicas en un registro de quién estaba dónde.

Con la huella criptográfica se conserva **exactamente** la capacidad que hace falta —saber
si dos solicitudes vienen de la misma red— y se pierde la que no hace falta —saber qué red
es—.

**La excepción declarada:** la tabla de sesiones de Laravel guarda la IP en claro. Es parte
del framework, afecta solo a usuarios autenticados de soporte, y se declara como excepción
conocida en lugar de disimularse.

### La foto: la superficie más expuesta del sistema

Es la única subida que el sistema acepta **sin autenticar**. Por eso lleva cuatro
controles:

| Control | Qué evita |
|---|---|
| Se reprocesa a WebP | Elimina cualquier carga útil incrustada en el archivo original |
| Se eliminan los metadatos EXIF | Incluida la ubicación GPS donde se tomó |
| Nombre de archivo aleatorio | Ataques por recorrido de directorios |
| Se sirve por controlador, no desde una carpeta pública | Verifica permisos; la foto puede mostrar una pizarra con nombres o una pantalla con datos |

---

## 11.6 Auditoría

### Dos registros, no uno

| Tabla | Responde |
|---|---|
| `audit_logs` | «¿Quién cambió la configuración del sistema?» |
| `incident_events` | «¿Qué le pasó a esta incidencia?» |

Mezclarlas obligaría a filtrar ruido administrativo cada vez que se quiere entender un
ticket. Son dos preguntas distintas y por eso son dos tablas distintas.

### Qué se registra en cada acción administrativa

Quién · qué acción · sobre qué objeto · cuándo · desde qué red (cifrada) · qué cambió.

**El registro de eventos de incidencia solo admite inserciones.** No se modifica ni se
borra: es lo que permite reconstruir la historia completa de un ticket sin depender de la
memoria de nadie.

---

## 11.7 Autenticación del personal

| Control | Valor |
|---|---|
| Longitud mínima de contraseña | 10 caracteres |
| Almacenamiento | Cifrado unidireccional (algoritmo por defecto de Laravel) |
| Sesión | 120 minutos |
| Protección contra falsificación de peticiones | En todos los formularios |
| Límite de intentos | Sí |

Los secretos (claves, contraseñas de base de datos) viven en el archivo `.env`, que **está
excluido del repositorio**.

---

## 11.8 Los datos de demostración

Todo registro falso lleva una marca, y un comando los borra en bloque.

**Por qué es una cuestión de integridad y no de comodidad:** ningún número presentado en la
tesis puede provenir de datos inventados. La única forma de garantizarlo es que el propio
sistema sepa cuáles son y pueda eliminarlos por completo antes de empezar a medir.

El comando de purga **exige una confirmación explícita por parámetro**, no una pregunta
interactiva. La diferencia importa: una pregunta interactiva se responde con Enter sin
leerla.

---

## 11.9 Lo que este sistema NO protege

Declararlo es parte del trabajo de seguridad:

| No protege | Por qué |
|---|---|
| **Contra un docente que reporta un problema falso** | No hay autenticación. Se detecta por patrón (repetición, ráfagas), no por identidad. |
| **Contra alguien dentro de la institución con credenciales robadas** | Se mitiga con auditoría, no se evita. |
| **Contra el acceso físico a la computadora servidor** | Fuera del alcance del software. |
| **Contra la denegación de servicio a nivel de red** | Fuera del alcance; los límites son por aplicación. |
| **No cifra la base de datos en reposo** | Depende de la infraestructura donde se despliegue. |

---

## 11.10 Consideraciones éticas del piloto

| Punto | Cómo se aborda |
|---|---|
| El docente no sabe que participa en una investigación | Debe informarse mediante el cartel del QR y la comunicación institucional |
| Se registran sus palabras textuales | No se le identifica; el análisis es agregado |
| Se le pide una foto | Es opcional y se advierte para qué se usa |
| Los datos alimentan una tesis | El conjunto exportado debe anonimizarse antes de compartirse |

---

## 11.11 Resumen para citar en la tesis

> El sistema opera con una superficie de exposición atípica: el flujo del reportante es
> accesible sin autenticación y su contenido se presenta posteriormente a usuarios
> autenticados, lo que convierte la inyección de secuencias de comandos almacenada en la
> amenaza principal. Se mitiga mediante una política de seguridad de contenido que prohíbe
> la ejecución de código incrustado, lo que obligó a externalizar la totalidad del
> comportamiento del cliente. El acceso abierto se protege en el efecto y no en el punto de
> entrada: los controles antiabuso resguardan la creación de tickets —la acción con coste
> operativo real— y no la consulta. No se recopilan identificadores personales del
> reportante; las direcciones de red se almacenan como resúmenes criptográficos,
> preservando la capacidad de detección de abuso y eliminando la de identificación. Los
> registros de demostración están marcados y son eliminables en bloque, condición necesaria
> para que ninguna cifra reportada provenga de datos sintéticos.

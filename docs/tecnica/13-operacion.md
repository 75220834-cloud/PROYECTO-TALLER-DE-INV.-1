# 13 · Operación del sistema

> **Qué encuentras aquí:** cómo se pone en marcha, qué procesos tiene que estar corriendo,
> qué tareas se ejecutan solas, cómo se comprueba que está sano y qué hacer cuando algo
> falla.

---

## 13.1 Los procesos que componen el sistema

```mermaid
graph TB
    subgraph "Obligatorios"
        A["Servidor web<br/>Apache :8080"]
        B["MariaDB"]
        D["Programador de tareas<br/>schedule:run cada minuto"]
    end
    subgraph "Para el acceso desde internet"
        T["Túnel<br/>cloudflared o proxy de TI"]
    end
    subgraph "Opcional"
        E["Ollama"]
    end
    A --> B
    D --> B
    T --> A
    A -.-> E
```

| Proceso | ¿Obligatorio? | Qué pasa si no está |
|---|---|---|
| Servidor web (Apache) | **Sí** | No hay sistema |
| MariaDB | **Sí** | No hay sistema |
| Programador de tareas | **Sí** | Los documentos no se indexan, no hay copias de seguridad, no se calculan riesgos ni agregados |
| Túnel o proxy | Para docentes con datos móviles | El sistema solo se alcanza desde la red interna |
| Ollama | Opcional | El sistema funciona en modo determinista |

**El servidor web es Apache, no `php artisan serve`.** En Windows, `artisan serve` atiende una
petición a la vez: si un docente espera una respuesta de la IA, que puede tardar hasta 30
segundos, todos los demás esperan detrás. `artisan serve` sirve para desarrollar, no para
atender un piloto.

**La cola ya no es un proceso aparte.** El programador la procesa cada minuto
(`queue:work --stop-when-empty`), de modo que en la PC de soporte basta **una** tarea
programada de Windows en lugar de dos procesos que alguien tiene que mantener vivos.

---

## 13.2 Puesta en marcha

Hay dos instalaciones distintas, con guías distintas:

- **Para atender a docentes reales**, en la PC de soporte:
  [`docs/despliegue/`](../despliegue/). Apache, arranque automático con Windows, tarea
  programada, copias de seguridad y publicación en internet.
- **Para trabajar en el código:** [`docs/instalar-en-otra-pc.md`](../instalar-en-otra-pc.md).

Resumen de la instalación en la PC de soporte:

| # | Qué |
|---|---|
| 1 | XAMPP con PHP 8.2, en `C:\xampp`; la base de datos cerrada a la red (`bind-address`) |
| 2 | Base `incidencias` y usuario `incidencias` con permisos solo sobre ella |
| 3 | Composer, Node.js (LTS) y Git; el sistema clonado en `C:\incidencias` |
| 4 | `.env` desde `.env.produccion.example`; `composer install --no-dev`, `npm ci`, `npm run build` |
| 5 | `php artisan migrate --seed --force`: roles, categorías y los ocho procedimientos reales |
| 6 | `import:checklist` con el checklist de soporte |
| 7 | Ollama con los dos modelos (opcional) y `conocimiento:cargar` |
| 8 | `usuarios:crear-admin`: la primera cuenta, con contraseña escrita por quien la usará |
| 9 | Apache en el puerto 8080; Apache y MySQL como servicios de Windows |
| 10 | La tarea programada, con `scripts\windows\registrar-tarea-programada.bat` |
| 11 | `php artisan optimize` |
| 12 | Publicación: dominio propio con túnel de Cloudflare, o proxy de TI |

### La trampa que más tiempo hace perder

> **La carpeta del proyecto no puede tener tildes ni eñes en la ruta.**

`C:\proyectos\incidencias` funciona. Una ruta con «Investigación» dentro produce errores
que apuntan a archivos que sí existen, y se pierden horas buscando el problema donde no
está.

### Lo que nunca hay que copiar entre computadoras

`vendor/` · `node_modules/` · `.env`

Las tres se generan en cada máquina. Copiarlas de otra es la causa número uno de errores
inexplicables.

---

## 13.3 Comandos propios del sistema

| Comando | Qué hace | Cuándo se ejecuta |
|---|---|---|
| `import:checklist <archivo>` | **Carga las aulas y sus equipos desde el checklist de soporte.** Sin `--confirmar` solo simula. Con `--sede` y `--nombre-sede` carga otra sede | A mano, cuando cambia el inventario |
| `conocimiento:cargar` | Carga las guías técnicas del proyecto y las manda a indexar | A mano, cuando cambian las guías |
| `conocimiento:reindexar` | Vuelve a indexar todos los documentos publicados | Al activar o cambiar la IA |
| `usuarios:crear-admin` | Crea una cuenta de administrador pidiendo los datos por teclado | Una vez, al instalar |
| `qr:generar` | Genera el QR en archivos y dice a qué dirección apunta | Al publicar o cambiar la dirección |
| `respaldo:crear` | Copia de seguridad de la base de datos; conserva las 14 últimas | Automático, 01:30; y a mano antes de actualizar |
| `pruebas:reiniciar --force` | **Borra la actividad de prueba y conserva aulas, equipos, guías y procedimientos** | Entre una prueba y otra |
| `piloto:sembrar` | Siembra una estructura aproximada. **Obsoleto**: se usaba antes de tener el checklist real | — |
| `demo:purge --force` | Elimina **todos** los datos de demostración | A mano, antes de cargar datos reales |
| `incidents:purge-drafts` | Borra los borradores abandonados | Automático, cada hora |
| `metrics:snapshot` | Recalcula los agregados diarios | Automático, 2:30 |
| `risk:compute` | Calcula las señales de riesgo | Automático, 3:00 |

### Por qué el importador simula antes de escribir

`import:checklist` **no escribe nada** hasta que se le pasa `--confirmar`. Quien carga el
catálogo lo hace con un archivo que preparó otra persona, y descubrir los errores después
de escribir obliga a limpiar antes de reintentar.

El ciclo correcto es: previsualizar, corregir el checklist, previsualizar otra vez, y solo
entonces confirmar.

Lo más importante que hay que revisar en la vista previa **no son los errores, sino los
equipos ausentes**: cada celda vacía es un botón que el docente no verá, y eso no produce
ningún error. Es la única oportunidad de detectar una celda vacía por descuido.

El importador es **idempotente**: volver a ejecutarlo actualiza en lugar de duplicar, así
que se puede corregir el archivo y reintentar sin limpiar antes.

### Por qué `demo:purge` exige `--force`

Antes preguntaba de forma interactiva. Se cambió por dos motivos:

1. **Una pregunta interactiva se responde con Enter sin leerla.** Es más seguro exigir que
   se escriba el parámetro.
2. La versión interactiva **fallaba** al ejecutarse en ciertos entornos de Windows,
   produciendo un error que parecía otra cosa completamente distinta.

---

## 13.4 Tareas programadas

| Hora | Tarea | Por qué a esa hora |
|---|---|---|
| Cada minuto | Procesar la cola (indexación de documentos) | Un minuto de espera no se nota; a cambio no hace falta un proceso aparte |
| Cada hora | Purgar borradores abandonados | Los borradores se acumulan durante el día |
| 01:30 | Copia de seguridad de la base de datos | La primera de la noche: si otra tarea falla a medias, la copia ya está hecha |
| 02:30 | Agregados de métricas | Antes del cálculo de riesgo, que los usa |
| 03:00 | Cálculo de riesgo | De madrugada, sin usuarios y sin competir por recursos |

### Cómo se activan

En desarrollo:

```bash
php artisan schedule:work
```

En la PC de soporte, `scripts\windows\registrar-tarea-programada.bat` crea una tarea de
Windows que ejecuta cada minuto, como `SYSTEM`, aunque nadie haya iniciado sesión:

```bash
php artisan schedule:run
```

**Si nadie ejecuta ninguna de las dos, las tareas no corren.** Es la causa más común de
«el riesgo no se actualiza».

---

## 13.5 La cola de trabajos

Se usa para la indexación de documentos, que puede tardar minutos. La procesa el
programador cada minuto; para procesarla en el momento:

```bash
php artisan queue:work --stop-when-empty
```

### Qué pasa si no está corriendo

Los documentos subidos se quedan en estado «pendiente» y **nunca se indexan**. El
asistente no los encuentra. No hay error visible, solo silencio — que es el peor tipo de
fallo.

Por eso el estado del sistema informa de los trabajos pendientes y fallidos.

### La política de reintento

Un reintento. Si falla dos veces seguidas, el problema es el documento o el entorno, no la
mala suerte, y se registra el error para que alguien lo mire en lugar de reintentar
indefinidamente.

---

## 13.6 Comprobación de salud

Hay una dirección `/salud` que devuelve el estado del sistema en formato legible por
máquina.

### Qué comprueba

| Componente | ¿Degrada el estado global? |
|---|---|
| Base de datos | **Sí** |
| Cola de trabajos | No, pero informa |
| Modelo de lenguaje | **No** |
| Almacenamiento | No, pero informa |

### La decisión que hace útil este endpoint

**Ollama no degrada el estado.** Es deliberado y coherente con toda la arquitectura: sin
modelo, el sistema sigue funcionando completo en modo determinista.

Marcarlo como caído haría sonar una alarma a las tres de la mañana por algo que **no
impide a ningún docente reportar nada**. Y una alarma que suena sin motivo se acaba
silenciando — justo antes de la vez que sí importaba.

Los trabajos fallidos tampoco tumban el estado, pero se informan: significan documentos sin
indexar o avisos sin entregar, que es una degradación real que alguien debe mirar sin
prisa.

### No revela nada de la instalación

El endpoint **no requiere autenticación**, y por eso no expone versiones, rutas, nombres de
base de datos ni mensajes de error.

Un endpoint de salud que describe la instalación es un regalo para quien la está
explorando.

---

## 13.7 Configuración

Toda la configuración del comportamiento vive en `config/incidencias.php`, y los valores
concretos en el archivo `.env`.

### Las variables que más se tocan

| Variable | Para qué |
|---|---|
| `LLM_PROVIDER` | `ollama` o `null` |
| `LLM_MODEL` | Qué modelo usar |
| `EMBEDDING_MODEL` | Modelo de vectores |
| `ASSISTANT_CONFIDENCE_HIGH` / `_LOW` | Umbrales de confianza. **Vacíos hoy, a propósito** |
| `ABUSE_*` | Los umbrales antiabuso |
| `RISK_HORIZON_DAYS` | Horizonte de predicción |
| `APP_DISPLAY_TIMEZONE` | Hora de presentación (Lima) |
| `ALLOW_DEMO_SEED` | Permite crear datos de demostración |

### Después de tocar el `.env`

```bash
php artisan config:clear
```

Sin esto, la configuración sigue en caché y el cambio no tiene efecto. Es la segunda causa
más común de «cambié algo y no pasa nada».

---

## 13.8 Zonas horarias

| Dónde | Qué se usa |
|---|---|
| Almacenamiento | UTC |
| Presentación | Hora de Lima |

**Por qué:** guardar en hora local hace que los cálculos de duración fallen en los cambios
de horario y que los datos no se puedan comparar entre sedes. La conversión se hace al
mostrar, no al guardar.

---

## 13.9 Respaldos

**La base de datos se respalda sola**, cada día a la 01:30, con `respaldo:crear`. Conserva las
14 últimas copias en `storage/app/respaldos` o en la carpeta de `RESPALDO_CARPETA`. El ciclo
completo —copia y restauración— se probó contra la base real: 212 aulas, 1 551 equipos y el
resto de las tablas volvieron idénticos.

La contraseña de la base nunca va en la línea de órdenes: se pasa a `mysqldump` en un archivo
temporal que se borra al terminar, también si falla. Escribirla como argumento la dejaría a la
vista en la lista de procesos de Windows.

Lo que hay que respaldar:

| Qué | Dónde | Por qué |
|---|---|---|
| Base de datos completa | `respaldo:crear`, automático | Es todo el dato del piloto |
| Carpeta de almacenamiento | `storage/app` | Documentos e imágenes subidos |
| Archivo `.env` | Aparte, **nunca en el repositorio** | Lleva las claves |

**Lo que no hace falta respaldar:** `vendor/`, `node_modules/`, `public/build/` — se
regeneran.

### Recomendación para el piloto

Que `RESPALDO_CARPETA` apunte a **otro disco**: una copia en el mismo disco que la base
protege de un error humano, no de una avería. Y una vez por semana, una copia fuera de la PC.
Perder los datos del piloto significa perder el semestre, y no hay forma de reconstruirlos.

Cómo restaurar: [`docs/despliegue/06-actualizar-y-mantener.md`](../despliegue/06-actualizar-y-mantener.md).

---

## 13.10 Qué hacer cuando algo falla

| Síntoma | Causa habitual | Solución |
|---|---|---|
| `could not find driver` | Falta la extensión de MySQL en PHP | En `php.ini`, quitar el `;` de `extension=pdo_mysql` |
| `Connection refused` | MariaDB apagado | Arrancarlo desde el panel de XAMPP |
| `Failed to open stream` en archivos que sí existen | La ruta tiene tildes | Mover el proyecto a una ruta sin tildes |
| La página se ve sin estilos | Falta compilar | `npm run build` |
| `Class not found` | Autoload desactualizado | `composer dump-autoload` |
| Cambié el `.env` y no pasa nada | Configuración en caché | `php artisan config:clear` |
| Los documentos no se indexan | La cola no está corriendo | `php artisan queue:work` |
| El riesgo no se actualiza | El programador no está corriendo | `php artisan schedule:work` |
| El asistente siempre dice «no lo sé» | No hay documentos publicados, o Ollama está apagado | Cargar y publicar documentos; comprobar `/salud` |

Detalles adicionales en
[`docs/endurecimiento-y-operacion.md`](../endurecimiento-y-operacion.md).

---

## 13.11 Lo que NO hay que hacer

| Nunca | Por qué |
|---|---|
| **Cargar datos con `INSERT` desde phpMyAdmin** | Se salta las comprobaciones del sistema y deja la base en un estado que la aplicación no sabe manejar |
| **Copiar `vendor` o `node_modules` de otra computadora** | Se generan por máquina |
| **Subir el `.env` al repositorio** | Lleva las claves |
| **Cambiar un umbral sin documentarlo** | Los datos de antes y después dejan de ser comparables |
| **Borrar registros directamente en la base** | Rompe las relaciones y deja incidencias huérfanas |
| **Ejecutar `demo:purge` después de empezar a recoger datos reales** | Sin riesgo para los datos reales, pero conviene no acostumbrarse |

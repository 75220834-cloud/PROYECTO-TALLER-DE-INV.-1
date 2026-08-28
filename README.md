# Sistema inteligente de asistencia, gestión y predicción de incidencias tecnológicas en aulas

Universidad Continental — sede Huancayo
Taller de Investigación 1 · Ingeniería de Sistemas e Informática

**Equipo:** Ítalo (arquitectura e investigación) · Brayan (soporte TI y conocimiento operativo) · Dickmar (desarrollo, pruebas y documentación)

> ⚠️ El sistema opera actualmente con **datos DEMO**. Nada de lo que contiene describe aulas, equipos ni procedimientos reales de la Universidad.

---

## Requisitos

Todos los integrantes deben usar **la misma versión** de cada herramienta. La divergencia de entornos es el riesgo R17 del plan.

| Herramienta | Versión verificada |
|---|---|
| XAMPP (Apache + PHP + MariaDB + phpMyAdmin) | PHP **8.2.12**, MariaDB **10.4.32** |
| Composer | 2.9+ |
| Node.js | 24.x |
| Git | 2.x |
| Ollama *(opcional en esta fase)* | reciente |

Extensiones de PHP necesarias: `pdo_mysql`, `mbstring`, `fileinfo`, `zip`, `gd`, `curl`, `openssl`, `exif`.

---

## Puesta en marcha

**1. Arrancar XAMPP.** Inicia **Apache** y **MySQL** desde el panel de control.

**2. Crear la base de datos.** En phpMyAdmin, o por consola:

```bash
mysql -u root -e "CREATE DATABASE incidencias CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE incidencias_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**3. Crear el usuario de aplicación.** La aplicación **no debe usar `root`**:

```bash
mysql -u root -e "CREATE USER 'incidencias_app'@'localhost' IDENTIFIED BY 'TU_CLAVE'; GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,DROP,INDEX,ALTER,REFERENCES ON incidencias.* TO 'incidencias_app'@'localhost'; GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,DROP,INDEX,ALTER,REFERENCES ON incidencias_test.* TO 'incidencias_app'@'localhost'; FLUSH PRIVILEGES;"
```

**4. Instalar dependencias y configurar:**

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Luego edita `.env` y completa `DB_USERNAME` y `DB_PASSWORD`.

**5. Migrar y sembrar:**

```bash
php artisan migrate --seed
```

**6. Levantar:**

```bash
php artisan serve
```

---

## Comprobar que todo quedó bien

```bash
php artisan test
```

Deben pasar todas. Si alguna falla, el entorno no está bien y no hay que seguir adelante.

```bash
./vendor/bin/phpstan analyse
```

Debe decir `No errors`.

---

## Trabajo con la base de datos

**Regla:** phpMyAdmin para **leer y verificar**; comandos e interfaz de administración para **escribir**.

Un `INSERT` manual salta la validación de la aplicación (unicidad de códigos, coherencia de la jerarquía, campos obligatorios) y puede dejar la base en un estado que el sistema no sabe manejar. Para cargar el catálogo real se usarán los comandos de importación con vista previa.

---

## Estructura

```
app/
├── Modules/          13 módulos de dominio (plan §12)
│   ├── Locations/    catálogo sede→pabellón→piso→aula y validación en cascada
│   ├── Incidents/    ciclo de vida, máquina de estados, antiabuso
│   ├── Diagnostics/  árboles de decisión versionados
│   ├── Assistant/    clasificación, confianza, escalamiento
│   ├── Media/        banco de imágenes curado
│   └── …
└── Shared/           enums, excepciones y utilidades transversales
docs/decisions/       bitácora de decisiones técnicas (ADR)
```

---

## Estado actual

| Fase | Estado |
|---|---|
| 1 · Arquitectura y base | ✅ completa |
| 2 · Ubicaciones y equipos | ✅ completa |
| 3 · Incidencias, tickets y antiabuso | ✅ completa |
| 4 · Asistente, diagnóstico guiado e imágenes | ✅ completa |
| 5 · Base de conocimiento | ✅ completa |
| 6 · RAG y recuperación híbrida | ✅ completa |
| 7 · Tablero y analítica | ✅ completa |
| 8 · Riesgo y predicción | ✅ completa |
| 9 · Endurecimiento | ✅ completa |
| 10 · Testing integral | ✅ completa · salvo pruebas con docentes reales |
| 11 · Despliegue institucional | ⬜ depende de la Universidad |
| 12 · Piloto y medición | ⬜ depende de la Universidad |

**Todo lo que Brayan necesita cargar tiene ya su pantalla o su comando**: aulas y equipos por CSV,
procedimientos en PDF/DOCX, fotos, árboles de diagnóstico, tipos de problema y usuarios.

**Todo el código del plan está escrito.** Lo que queda no es programación:
son autorizaciones, datos reales y trabajo de campo.

**Fase 2 incluye:** catálogo administrable (sedes, pabellones, pisos, aulas, equipos) con validación
en cascada en servidor, guarda de dependencias al eliminar, búsqueda por código, autenticación con
roles y permisos, QR genérico único con cartel imprimible, y el flujo de selección de ubicación del
docente con confirmación explícita.

**Fase 3 incluye:** ciclo de vida completo con máquina de estados, cálculo de prioridad por reglas
(explicable y nunca elegida por el docente), la pila antiabuso de 7 controles con su registro de
rechazos, panel de soporte con bandeja y detalle, notificación interna desacoplada, e historial
append-only que permite reconstruir por qué un ticket terminó donde terminó.

### Tarea programada

```bash
php artisan incidents:purge-drafts
```

Marca como abandonados los borradores que nadie completó. No los borra: son el indicador de
fricción del QR genérico y forman parte de los datos de la investigación.

**Fase 4 incluye:** motor de diagnóstico guiado con árboles versionados, banco de imágenes con
resolución en cascada y degradación limpia a solo texto, abstracción de IA intercambiable
(Ollama / fake / sin modelo) y clasificación de texto libre con validación estricta contra el
catálogo. **El sistema funciona completo sin modelo de lenguaje**: hay pruebas que lo verifican.

**Fases 5 y 6 incluyen:** ingesta versionada de PDF/DOCX/MD/TXT con estado de procesamiento,
fragmentación que no parte procedimientos numerados, y recuperación híbrida (léxica + vectorial
fusionadas por RRF) con cita de documento, sección y página. El asistente **escala en lugar de
inventar** cuando no hay fuente: hay una suite de sondas que lo verifica con la base vacía.

> **Limitación honesta y documentada:** sin un modelo de embeddings real, la búsqueda por paráfrasis
> no funciona. No está disimulado: hay una prueba llamada *LIMITACIÓN CONOCIDA* que lo deja escrito
> en la propia suite.

**Fases 7 y 8 incluyen:** tablero con los indicadores del plan —incluidos los incómodos: abandono
de docentes y rechazos del antiabuso—, exportación del conjunto de datos para el análisis, agregados
diarios, y señales de riesgo explicables por aula y por aula+categoría.

> **Cómo leer las señales de riesgo:** ordenan por dónde conviene empezar una revisión. **No son
> probabilidades de avería** y no están calibradas, porque el piloto no tendrá volumen para
> calibrarlas. Presentarlas como probabilidades en el informe sería inventar precisión que no se
> tiene.

**Fases 9 y 10 incluyen:** cabeceras de seguridad con CSP que **no permite JavaScript incrustado**
(hay una prueba que recorre las vistas y falla si alguien vuelve a meter un `onclick`), IP siempre
hasheada, detección de riesgo físico que salta el diagnóstico y escala con prioridad máxima, y el
flujo de extremo a extremo verificando consistencia de datos en toda la cadena.

### Tareas programadas

Requieren `php artisan schedule:work` en desarrollo, o cron / Programador de tareas en el servidor.

| Comando | Cuándo | Para qué |
|---|---|---|
| `incidents:purge-drafts` | cada hora | marca borradores abandonados |
| `metrics:snapshot` | 02:30 | agregados diarios del tablero |
| `risk:compute` | 03:00 | señales de riesgo |

### Estado del sistema

`GET /salud` responde el estado de base de datos, colas, modelo y disco, sin autenticación y sin
revelar nada de la instalación. **La falta del modelo de lenguaje no cuenta como caída**: el sistema
funciona completo sin él.

### Probar a escala del piloto

```bash
php artisan piloto:sembrar
```

Deja la base con la estructura **aproximada** de la sede Huancayo: pabellones **C, D, E, G, H, I, J**,
5 pisos cada uno y 3–4 aulas por piso (~121 aulas), más su equipamiento. Purga primero los datos demo
anteriores, para que quede **una sola sede** y el docente no vea una pantalla de selección que en el
aula real no existirá.

> **Qué es real y qué no.** Los pabellones, los pisos y la cantidad aproximada de aulas los indicó
> Ítalo. Los **códigos concretos de cada aula** (`C301`, `D105`…) los genera el seeder siguiendo una
> convención verosímil que **nadie ha confirmado** contra el catálogo oficial. Por eso todo queda con
> `is_demo = true` y el aviso de DATOS DE DEMOSTRACIÓN sigue visible. Cuando Brayan entregue el
> catálogo real, esto se purga y se reemplaza entero — no se corrige a mano.

```bash
php artisan demo:purge
```

Borra **de verdad** todos los datos de demostración: aulas, equipos, incidencias e historial asociado.
No es borrado suave — una purga que dejara filas ocultas seguiría contaminando las consultas de la
investigación. El banco de imágenes y los documentos se conservan: un diagrama de un conector HDMI
sigue siendo correcto con el catálogo real.

### 📋 Qué hay que cargar

Ver **[docs/carga-de-datos.md](docs/carga-de-datos.md)**: aulas, equipos, procedimientos e imágenes.
El código está hecho; falta el contenido.

### Instalar en otra computadora

Guía paso a paso, pensada para seguir sin saber nada del proyecto:
**[docs/instalar-en-otra-pc.md](docs/instalar-en-otra-pc.md)**

### Accesos de demostración

**La contraseña es la misma para todos** y se define en `DEMO_USER_PASSWORD` dentro de `.env`.
Por defecto: `demo_local_2026`. Si dejas esa variable vacía, el seeder genera una aleatoria y la
muestra una sola vez en consola.

| Usuario | Qué puede hacer |
|---|---|
| `admin@demo.local` | Todo |
| `coordinador@demo.local` | Atender y reasignar incidencias, tablero, riesgo |
| `tecnico@demo.local` | Atender incidencias. No toca el catálogo |
| `conocimiento@demo.local` | Cargar procedimientos, imágenes y árboles de diagnóstico |
| `investigador@demo.local` | Solo lectura y exportación de datos |

El docente **no tiene cuenta**: entra por el QR sin identificarse.

El plan maestro completo está fuera del repositorio, en el archivo de planificación del equipo.

---

## Antes del piloto

Nada de esto es opcional:

- Verificar **en un aula real** que un celular alcanza el servidor (riesgo R2 del plan — puede invalidar el piloto entero).
- Obtener autorización institucional para el piloto y para fotografiar los equipos.
- Seguir la lista completa de **[docs/endurecimiento-y-operacion.md](docs/endurecimiento-y-operacion.md)**:
  `APP_DEBUG=false`, `DocumentRoot` en `public/` (verificado con `curl /.env` → 404), phpMyAdmin
  restringido, y respaldo **y restauración** probados al menos una vez.
- Revisar los umbrales antiabuso tras la primera semana mirando `incident_abuse_rejections`: los
  valores actuales son **provisionales** y nadie los ha calibrado todavía.
- Ejecutar las pruebas con docentes reales (§17.9): son las únicas del plan que no se pueden
  automatizar, y son las que dirán si el QR genérico cuesta demasiados pasos.
- Purgar los datos DEMO y cargar el catálogo real.

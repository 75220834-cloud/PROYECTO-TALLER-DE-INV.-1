# Sistema inteligente de asistencia, gestión y predicción de incidencias tecnológicas en aulas

Universidad Continental — sede Huancayo
Taller de Investigación 1 · Ingeniería de Sistemas e Informática

**Equipo:** Ítalo (arquitectura e investigación) · Dickmar (soporte TI y conocimiento operativo) · Brayan (desarrollo, pruebas y documentación)

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
| 5-6 · Base de conocimiento y RAG | ⬜ pendiente |
| 7-8 · Tablero y predicción | ⬜ pendiente |

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

### 📋 Qué hay que cargar

Ver **[docs/carga-de-datos.md](docs/carga-de-datos.md)**: aulas, equipos, procedimientos e imágenes.
El código está hecho; falta el contenido.

### Accesos de demostración

Usuarios `admin@demo.local`, `coordinador@demo.local`, `tecnico@demo.local`, `conocimiento@demo.local`
e `investigador@demo.local`. La contraseña es la que definas en `DEMO_USER_PASSWORD`; si la dejas
vacía, el seeder genera una aleatoria y la muestra una sola vez en consola.

El plan maestro completo está fuera del repositorio, en el archivo de planificación del equipo.

---

## Antes del piloto

Nada de esto es opcional:

- Verificar **en un aula real** que un celular alcanza el servidor (riesgo R2 del plan — puede invalidar el piloto entero).
- Obtener autorización institucional para el piloto y para fotografiar los equipos.
- Endurecer XAMPP: contraseña de `root`, phpMyAdmin restringido, `DocumentRoot` apuntando a `public/`.
- Purgar los datos DEMO y cargar el catálogo real.

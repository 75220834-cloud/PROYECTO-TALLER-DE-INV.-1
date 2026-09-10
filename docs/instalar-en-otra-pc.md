# Instalar el sistema en otra computadora

Guía paso a paso. Si sigues las instrucciones en orden y no te saltas nada, funciona.

Cada paso dice **qué tiene que salirte**. Si te sale otra cosa, para ahí y avisa: seguir adelante
con un paso a medias hace que el error aparezca tres pasos después, donde no se entiende.

> **Antes de empezar:** haz esto en una carpeta **sin tildes ni eñes** en la ruta. Windows y PHP se
> llevan mal con ellas y provocan errores que parecen otra cosa. `C:\proyectos\incidencias` va bien.
> `D:\Investigación\...` da problemas.

---

## Paso 1 · Instalar XAMPP

1. Descarga XAMPP desde <https://www.apachefriends.org> — la versión con **PHP 8.2**.
2. Instálalo en `C:\xampp` (la carpeta que propone por defecto).
3. Abre el **Panel de Control de XAMPP**.
4. Pulsa **Start** en la fila de **Apache** y en la de **MySQL**.

**Qué tiene que salirte:** las palabras «Apache» y «MySQL» sobre fondo verde.

Si MySQL no arranca, casi siempre es porque tienes otro MySQL instalado ocupando el puerto 3306.
Desinstálalo o cambia el puerto.

---

## Paso 2 · Instalar Composer

1. Descarga <https://getcomposer.org/Composer-Setup.exe> y ejecútalo.
2. Cuando te pida la ruta de PHP, elige `C:\xampp\php\php.exe`.
3. Acepta el resto de opciones.
4. **Cierra todas las ventanas de PowerShell** y abre una nueva (si no, no encuentra el comando).

Comprueba:

```bash
composer --version
```

**Qué tiene que salirte:** algo como `Composer version 2.9.x`.

---

## Paso 3 · Instalar Node.js

1. Descarga la versión **LTS** desde <https://nodejs.org>.
2. Instálala con todas las opciones por defecto.
3. Cierra y abre PowerShell otra vez.

```bash
node --version
```

**Qué tiene que salirte:** `v22.x.x` o superior.

---

## Paso 4 · Instalar Git y traer el proyecto

1. Descarga Git desde <https://git-scm.com/download/win> e instálalo con las opciones por defecto.
2. Cierra y abre PowerShell.
3. Colócate donde quieras el proyecto y clónalo:

```bash
cd C:\proyectos
```

```bash
git clone URL_DEL_REPOSITORIO incidencias
```

```bash
cd incidencias
```

**Qué tiene que salirte:** una carpeta `incidencias` con dentro `app`, `public`, `resources`…

> Si no usan Git, copia la carpeta del proyecto en un USB — **pero sin las carpetas `vendor`,
> `node_modules` ni el archivo `.env`**. Esas tres se generan en los pasos siguientes, y copiarlas
> de otra máquina es la causa número uno de errores raros.

---

## Paso 5 · Crear la base de datos

Abre <http://localhost/phpmyadmin> en el navegador.

1. Pestaña **Bases de datos**.
2. Nombre: `incidencias` · Cotejamiento: **`utf8mb4_unicode_ci`** · botón **Crear**.
3. Repite con el nombre `incidencias_test` (esta la usan las pruebas).

**Qué tiene que salirte:** las dos bases en la lista de la izquierda.

> El cotejamiento importa: con otro, las tildes y las «ñ» se guardan mal y no hay forma de
> arreglarlo después sin volver a cargar todo.

---

## Paso 6 · Configurar el proyecto

Todo esto en PowerShell, dentro de la carpeta del proyecto.

```bash
copy .env.example .env
```

Abre el archivo `.env` con el Bloc de notas y revisa que tenga estas líneas:

```
DB_DATABASE=incidencias
DB_USERNAME=root
DB_PASSWORD=
```

Si a MySQL le pusiste contraseña, ponla en `DB_PASSWORD`. Si no, déjalo vacío.

Ahora, uno por uno:

```bash
composer install
```

```bash
npm install
```

```bash
php artisan key:generate
```

**Qué tiene que salirte:** `APPLICATION KEY SET`.

---

## Paso 7 · Crear las tablas y los datos de prueba

```bash
php artisan migrate --seed
```

**Qué tiene que salirte:** una lista larga de líneas terminadas en `DONE`.

```bash
php artisan piloto:sembrar
```

**Qué tiene que salirte:** `Piloto: 121 aulas en 7 pabellones`.

```bash
npm run build
```

**Qué tiene que salirte:** `built in ...` con unos archivos listados.

---

## Paso 8 · Arrancar

```bash
php artisan serve
```

**Qué tiene que salirte:** `Server running on [http://127.0.0.1:8000]`.

**Deja esa ventana abierta.** Si la cierras, el sistema se apaga.

Abre el navegador en <http://127.0.0.1:8000/reportar> — deberías ver «¿En qué pabellón estás?».

---

## Paso 9 · La inteligencia artificial (opcional pero recomendable)

Sin esto el sistema funciona igual, pero el asistente clasifica por palabras sueltas en vez de
entender la frase. **Ocupa unos 6 GB.**

1. Descarga <https://ollama.com/download/OllamaSetup.exe> e instálalo.
2. Abre una **segunda** ventana de PowerShell (la primera tiene el servidor corriendo) y ejecuta:

```bash
ollama pull qwen2.5:7b-instruct
```

```bash
ollama pull nomic-embed-text
```

Tarda un buen rato la primera vez.

Comprueba que el sistema lo ve:

```bash
php artisan tinker --execute="echo app(App\Modules\Assistant\Contracts\LlmProvider::class)->isAvailable() ? 'IA lista' : 'IA no responde';"
```

**Qué tiene que salirte:** `IA lista`.

> Ollama se queda funcionando en segundo plano. Si reinicias la computadora, arráncalo de nuevo
> buscando «Ollama» en el menú de inicio.

---

## Paso 10 · Comprobar que todo está bien

```bash
php artisan test
```

**Qué tiene que salirte:** `Tests: 442 passed` (o más). Si alguna falla, el entorno tiene algo mal
y **no hay que seguir**: avisa antes de cargar datos reales.

---

## Cada vez que quieras usarlo

No repitas la instalación. Solo:

1. Abre el Panel de XAMPP y pulsa **Start** en Apache y MySQL.
2. En PowerShell, dentro de la carpeta del proyecto:

```bash
php artisan serve
```

3. Abre <http://127.0.0.1:8000>.

Si además quieres la IA, arranca Ollama desde el menú de inicio.

---

## Si algo falla

| Lo que ves | Qué pasa | Qué hacer |
|---|---|---|
| `could not find driver` | Falta la extensión de MySQL en PHP | Abre `C:\xampp\php\php.ini`, busca `;extension=pdo_mysql` y quítale el `;` del principio |
| `Connection refused` | MySQL no está encendido | Panel de XAMPP → Start en MySQL |
| `Failed to open stream` en archivos de `vendor` | La ruta del proyecto tiene tildes | Mueve el proyecto a una carpeta sin tildes |
| La página se ve sin colores ni estilos | Faltan los archivos compilados | `npm run build` |
| `Class not found` | El autoload quedó viejo | `composer dump-autoload` |
| Cambiaste el `.env` y no pasa nada | La configuración está en caché | `php artisan config:clear` |

---

## Lo que NO hay que hacer

- **No cargues datos con `INSERT` desde phpMyAdmin.** Se salta las comprobaciones del sistema y
  deja la base en un estado que la aplicación no sabe manejar. Para eso están los comandos de
  importación y las pantallas del panel.
- **No copies las carpetas `vendor` ni `node_modules`** de otra computadora.
- **No subas el archivo `.env` a Git.** Lleva las contraseñas.

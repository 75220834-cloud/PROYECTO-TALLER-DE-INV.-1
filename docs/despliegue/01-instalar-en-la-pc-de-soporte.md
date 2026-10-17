# 1 · Instalar en la PC de soporte

Esta guía deja el sistema funcionando en una PC con Windows, desde cero, tal como tiene que quedar
para atender a docentes reales. Al terminar, el sistema funciona **dentro** de la PC; para que los
docentes entren desde su celular falta publicarlo (guía 3 o 4).

Cada paso dice **qué tiene que salirte**. Si sale otra cosa, para ahí: seguir con un paso a medias
hace que el error aparezca tres pasos después, donde ya no se entiende.

**Tiempo aproximado:** 1 a 2 horas, la mayor parte esperando descargas.

---

## Antes de empezar

### Lo que necesita la PC

| | Mínimo | Con inteligencia artificial |
|---|---|---|
| Windows | 10 u 11, 64 bits | 10 u 11, 64 bits |
| Memoria RAM | 8 GB | **16 GB o más** |
| Disco libre | 10 GB | 20 GB |
| Internet | Para instalar y para el túnel | Igual |
| Encendida | En todo el horario de clases | Igual |

Con menos de 16 GB de RAM, instala el sistema **sin IA** (se explica en el paso 10). Todo funciona
igual, salvo entender lo que el docente escribe con sus palabras y responder preguntas abiertas:
los botones, los pasos de diagnóstico y el pedido de soporte funcionan completos.

### Lo que tienes que tener a mano

- Una cuenta de **administrador** de Windows en esa PC.
- El **checklist de aulas** en formato CSV, el mismo que usa soporte. **No está en GitHub**, a
  propósito: el repositorio es público y el checklist es información de la universidad.
- Una **contraseña nueva** para la base de datos, que solo usará el sistema. Anótala en un lugar
  seguro: la vas a escribir una sola vez, en el paso 6.

### La carpeta

Todo se instala en **`C:\incidencias`**. Si eliges otra, que **no tenga espacios, tildes, eñes ni
paréntesis**: Windows, PHP y Apache se llevan mal con ellos y producen errores que parecen otra cosa.

---

## Paso 1 · Instalar XAMPP

1. Descarga XAMPP para Windows desde <https://www.apachefriends.org>, la versión con **PHP 8.2**.
2. Instálalo en `C:\xampp`, la carpeta que propone por defecto.
3. Al terminar, **no** arranques nada todavía.

**Qué tiene que salirte:** existe la carpeta `C:\xampp\php` y dentro el archivo `php.exe`.

---

## Paso 2 · Cerrar la base de datos a la red

XAMPP viene configurado para que **cualquier equipo de la red** pueda conectarse a la base de datos,
y su usuario `root` **no tiene contraseña**. En la red de una universidad eso significa que alguien
conectado al mismo WiFi podría leer todos los datos. Este paso lo cierra: la base de datos solo
aceptará conexiones desde la propia PC.

1. Abre el **Bloc de notas** como administrador: menú Inicio, escribe «Bloc de notas», clic derecho,
   **Ejecutar como administrador**.
2. En el Bloc de notas: **Archivo › Abrir**, ve a `C:\xampp\mysql\bin`, cambia el filtro de abajo a
   **Todos los archivos** y abre **`my.ini`**.
3. Pulsa **Ctrl + B** y busca: `bind-address`
4. Vas a encontrar esta línea:

   ```
   # bind-address="127.0.0.1"
   ```

   Bórrale el `# ` del principio, para que quede así:

   ```
   bind-address="127.0.0.1"
   ```

5. **Archivo › Guardar**.

**Qué tiene que salirte:** la línea guardada **sin** el `#` delante.

---

## Paso 3 · Arrancar XAMPP y crear la base de datos

1. Abre el **Panel de Control de XAMPP** (`C:\xampp\xampp-control.exe`).
2. Pulsa **Start** en la fila de **MySQL**. **No** arranques Apache todavía.

   **Qué tiene que salirte:** «MySQL» sobre fondo verde.

3. Abre **PowerShell**: menú Inicio, escribe «PowerShell», Enter.
4. Entra a la base de datos:

   ```powershell
   C:\xampp\mysql\bin\mysql.exe -u root
   ```

   **Qué tiene que salirte:** `MariaDB [(none)]>`

5. Copia estas líneas **cambiando `PON-AQUI-LA-CONTRASEÑA`** por la contraseña nueva que preparaste.
   Respeta las comillas simples.

   ```sql
   CREATE DATABASE incidencias CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'incidencias'@'localhost' IDENTIFIED BY 'PON-AQUI-LA-CONTRASEÑA';
   GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, INDEX, ALTER, LOCK TABLES ON incidencias.* TO 'incidencias'@'localhost';
   FLUSH PRIVILEGES;
   EXIT;
   ```

   **Qué tiene que salirte:** `Query OK` en cada línea y `Bye` al final.

> **Por qué no usar `root`:** `root` puede hacer cualquier cosa con cualquier base de datos de la PC.
> El usuario `incidencias` solo puede tocar la base `incidencias`. Si algún día alguien obtuviera la
> contraseña del sistema, no podría hacer nada fuera de ella.

---

## Paso 4 · Instalar Composer, Node.js y Git

Son las herramientas que bajan y preparan el sistema. Se instalan una sola vez.

1. **Composer:** descarga <https://getcomposer.org/Composer-Setup.exe> y ejecútalo. Cuando pida la
   ruta de PHP, elige `C:\xampp\php\php.exe`. Acepta el resto.
2. **Node.js:** descarga la versión **LTS** desde <https://nodejs.org> e instálala con las opciones
   por defecto.
3. **Git:** descarga Git para Windows desde <https://git-scm.com> e instálalo con las opciones por
   defecto.
4. **Cierra PowerShell y abre uno nuevo.** Si no, no encuentra los programas que acabas de instalar.
5. Comprueba:

   ```powershell
   php --version
   composer --version
   node --version
   git --version
   ```

**Qué tiene que salirte:** cuatro líneas con versiones. La de PHP tiene que empezar por **8.2**.

Si `php` dice que no se reconoce: agrega `C:\xampp\php` a la variable de entorno **Path** de Windows,
cierra PowerShell y ábrelo de nuevo.

---

## Paso 5 · Descargar el sistema

```powershell
git clone https://github.com/75220834-cloud/PROYECTO-TALLER-DE-INV.-1.git C:\incidencias
cd C:\incidencias
```

**Qué tiene que salirte:** `Resolving deltas: 100%` y, al final, el indicador de PowerShell en
`PS C:\incidencias>`.

**Desde ahora, todos los comandos se ejecutan dentro de `C:\incidencias`.** Si cierras PowerShell,
al abrirlo de nuevo escribe primero `cd C:\incidencias`.

---

## Paso 6 · Configurar

1. Crea el archivo de configuración a partir de la plantilla de producción:

   ```powershell
   copy .env.produccion.example .env
   notepad .env
   ```

2. En el Bloc de notas, busca la línea `DB_PASSWORD=` y escribe a continuación la contraseña del
   paso 3, sin espacios ni comillas:

   ```
   DB_PASSWORD=la-contraseña-del-paso-3
   ```

3. Busca la línea `LLM_PROVIDER=`:
   - Con IA (PC de 16 GB o más): déjala en `LLM_PROVIDER=ollama`.
   - Sin IA: cámbiala a `LLM_PROVIDER=null`.

4. Deja `PUBLIC_URL=` **vacía** por ahora. Se completa al publicar el sistema (guía 3 o 4).
5. **Guardar** y cerrar.

> **El `.env` es secreto.** Tiene la contraseña de la base de datos. Nunca lo subas a GitHub, nunca
> lo mandes por chat. Git ya está configurado para ignorarlo.

---

## Paso 7 · Instalar las dependencias

```powershell
composer install --no-dev --optimize-autoloader
php artisan key:generate
npm ci
npm run build
```

**Qué tiene que salirte:**

- `composer install`: termina sin la palabra `error`. Puede decir que hay paquetes buscando
  financiamiento: eso no es un problema.
- `key:generate`: `Application key set successfully`. Se ejecuta **una sola vez**. Si lo
  repites, pregunta si quieres reemplazar la clave: responde **no**. Reemplazarla cierra todas las
  sesiones abiertas.
- `npm ci`: `found 0 vulnerabilities`.
- `npm run build`: `built in` y unos archivos listados.

---

## Paso 8 · Crear las tablas

```powershell
php artisan migrate --seed --force
```

**Qué tiene que salirte:** una lista de migraciones marcadas `DONE`, y al final:

```
  Roles: 5 | Permisos: 21
  ...
  Datos DEMO omitidos: entorno de produccion.
```

Esa última línea es correcta y es importante: en el servidor **no** se cargan datos de
demostración. Lo que sí se carga siempre son los roles, las categorías de problema y los ocho
procedimientos de diagnóstico reales.

---

## Paso 9 · Cargar las aulas y sus equipos

1. Crea la carpeta para el checklist. Está excluida de Git, así que lo que pongas ahí nunca se
   sube a GitHub:

   ```powershell
   mkdir datos-reales
   ```

2. Copia el checklist en esa carpeta y llámalo `checklist.csv`.
3. **Simula** la carga. Este comando no escribe nada, solo muestra lo que haría:

   ```powershell
   php artisan import:checklist datos-reales\checklist.csv
   ```

   **Qué tiene que salirte:** un recuento de pabellones, aulas y equipos, y una lista de **equipos
   que el checklist no registra**.

   > **Revisa esa última lista con cuidado.** Cada equipo ausente es un botón que el docente de esa
   > aula **no va a ver**, y eso no produce ningún error. Es la única oportunidad de detectar una
   > celda que quedó vacía por descuido.

4. Si los números cuadran, carga de verdad:

   ```powershell
   php artisan import:checklist datos-reales\checklist.csv --confirmar
   ```

Se puede repetir cuantas veces haga falta: **actualiza en lugar de duplicar**. Cuando el checklist
cambie, se vuelve a ejecutar el mismo comando con el archivo nuevo.

---

## Paso 10 · La inteligencia artificial

**Si elegiste `LLM_PROVIDER=null` en el paso 6, salta al paso 11.**

1. Descarga Ollama para Windows desde <https://ollama.com/download> e instálalo. Queda un ícono de
   llama junto al reloj de Windows: eso significa que está corriendo.
2. Descarga los dos modelos. El primero pesa unos 4,7 GB y tarda según la conexión:

   ```powershell
   ollama pull qwen2.5:7b-instruct
   ollama pull nomic-embed-text
   ```

3. Comprueba:

   ```powershell
   ollama list
   ```

**Qué tiene que salirte:** dos líneas, una con `qwen2.5:7b-instruct` y otra con `nomic-embed-text`.

> **Qué es esto.** Ollama es un programa gratuito que ejecuta modelos de IA en la propia PC. No se
> conecta a ChatGPT ni a ningún servicio de internet, no necesita claves y no envía datos fuera de
> la PC. Los modelos se descargan una sola vez y después funcionan sin conexión.

> **Si la PC se reinicia,** Ollama arranca cuando alguien inicia sesión en Windows. Mientras nadie
> lo haga, el sistema funciona sin IA, y cuando alguien entra vuelve a tenerla solo.

---

## Paso 11 · Cargar las guías que lee la IA

```powershell
php artisan conocimiento:cargar
php artisan queue:work --stop-when-empty
```

**Qué tiene que salirte:** `8 guías cargadas, 0 sin cambios.` y, después, ocho líneas
terminadas en `DONE`.

El segundo comando procesa las guías ahora mismo. Desde el paso 15, la tarea programada lo hace sola
cada minuto, así que más adelante no hará falta ejecutarlo a mano.

> **Si más adelante activas la IA** en un sistema que empezó sin ella: cambia `LLM_PROVIDER` a
> `ollama`, ejecuta `php artisan optimize` y después `php artisan conocimiento:reindexar`. Sin eso,
> el asistente no encuentra las guías, que quedaron preparadas para el modo sin IA, y no da ningún
> error: simplemente escala todo.

### Opcional: ilustraciones provisionales

Mientras soporte no tome las fotos reales, los pasos se ven solo con texto. Para que muestren un
dibujo que orienta al docente:

```powershell
php artisan imagenes:provisionales
```

Cada foto real que se suba después las reemplaza sola. Detalles en la guía
[7 · Fotos de los equipos](07-fotos-de-los-equipos.md).

---

## Paso 12 · Crear tu cuenta de administrador

```powershell
php artisan usuarios:crear-admin
```

Te pide nombre, correo y contraseña. **La contraseña no se ve mientras la escribes:** es normal.
Tiene que tener al menos 10 caracteres.

**Qué tiene que salirte:** `Cuenta creada: <tu nombre> <tu correo> con el rol de administrador.`

Con esa cuenta, desde el panel, creas después las cuentas de los técnicos: **Panel › Usuarios ›
Nuevo usuario**. Cada persona con su propia cuenta; nunca compartir una.

---

## Paso 13 · Apache

Apache es el servidor web que atiende a los docentes. El sistema usa su propio puerto, **8080**,
separado del 80 de XAMPP.

1. En el **Panel de Control de XAMPP**, en la fila de **Apache**, pulsa **Config › Apache
   (httpd.conf)**. Se abre en el Bloc de notas.
2. Baja **hasta el final** del archivo y agrega esta línea:

   ```
   Include "C:/incidencias/scripts/apache/incidencias.conf"
   ```

   Con barras `/`, no `\`. Si instalaste en otra carpeta, cambia la ruta aquí y también la línea
   `Define` dentro de `scripts\apache\incidencias.conf`.

3. **Guardar**.
4. En el Panel de XAMPP, pulsa **Start** en la fila de **Apache**.

   **Qué tiene que salirte:** «Apache» sobre fondo verde, y en la columna de puertos `80, 443, 8080`.

   Si Windows muestra un aviso del **Firewall** sobre Apache: marca **Redes privadas** y pulsa
   **Permitir acceso**. Si no vas a abrir el panel desde otra PC de la red, puedes cancelar: el túnel
   no lo necesita.

5. Abre el navegador de la PC en <http://localhost:8080>.

**Qué tiene que salirte:** la pantalla del docente, «¿Dónde estás?», con la lista de pabellones.

---

## Paso 14 · Que todo arranque solo con Windows

Sin este paso, si la PC se reinicia, Apache y MySQL quedan apagados hasta que alguien los arranque a
mano, y mientras tanto ningún QR funciona.

1. Cierra el Panel de XAMPP.
2. Ábrelo **como administrador**: clic derecho sobre `C:\xampp\xampp-control.exe`, **Ejecutar como
   administrador**.
3. Pulsa **Stop** en Apache y en MySQL.
4. En la columna **Service**, a la izquierda, pulsa la **X roja** de **Apache** y confirma con **Sí**.
   Lo mismo con la **X roja** de **MySQL**.

   **Qué tiene que salirte:** las dos X rojas se vuelven **marcas verdes**.

5. Pulsa **Start** en Apache y en MySQL.

Desde ahora arrancan solos al encender Windows, aunque nadie inicie sesión.

---

## Paso 15 · La tarea programada

Una sola tarea de Windows hace, cada minuto, todo lo que el sistema necesita en segundo plano:
procesar las guías de la IA, marcar reportes abandonados, recalcular indicadores, calcular el riesgo
por aula y **sacar la copia de seguridad diaria** de la base de datos.

1. Abre la carpeta `C:\incidencias\scripts\windows` en el Explorador.
2. Clic derecho sobre **`registrar-tarea-programada.bat`** › **Ejecutar como administrador**.

**Qué tiene que salirte:** `Tarea registrada: "Incidencias - programador"`.

Para comprobarla: menú Inicio, «Programador de tareas», y en la lista de la izquierda **Biblioteca
del Programador de tareas**. Tiene que aparecer **Incidencias - programador** con estado **Listo**.

---

## Paso 16 · Dejar el sistema optimizado

```powershell
php artisan optimize
```

**Qué tiene que salirte:** varias líneas terminadas en `DONE`.

> **Muy importante:** este comando guarda la configuración en memoria para que el sistema sea más
> rápido. **Cada vez que cambies algo en el `.env`, vuelve a ejecutar `php artisan optimize`.** Si
> no, el sistema sigue usando la configuración anterior, sin avisar.

---

## Paso 17 · Comprobar

1. Abre <http://localhost:8080/salud> en el navegador de la PC.

   **Qué tiene que salirte:** un texto como este:

   ```
   {"status":"ok","checks":{"database":true,"queue":"ok","llm":"ok","storage":"ok"}, ...}
   ```

   Sin IA, en lugar de `"llm":"ok"` dice que está desactivada: es lo esperado.

2. Comprueba que las herramientas de XAMPP **no** se ven por el puerto del sistema. Las tres tienen
   que dar **«Not Found»**:

   - <http://localhost:8080/phpmyadmin/>
   - <http://localhost:8080/cgi-bin/printenv.pl>
   - <http://localhost:8080/php-cgi/php-cgi.exe>

   > **Por qué importa.** XAMPP publica phpMyAdmin y otras herramientas para todo el servidor, y las
   > protege con «solo desde esta PC». Pero todo lo que entra por el túnel llega **desde esta PC**:
   > sin este cierre, con el túnel prendido, cualquiera en internet podría abrir phpMyAdmin. El archivo
   > `scripts/apache/incidencias.conf` las cierra en el puerto 8080. Si alguna de las tres abre algo,
   > **no publiques el sistema**: revisa que la línea `Include` del paso 13 esté bien escrita.

3. Abre <http://localhost:8080/entrar> y entra con la cuenta del paso 12.

   **Qué tiene que salirte:** el panel de soporte.

4. En el panel, ve a **Conocimiento**. Las ocho guías tienen que aparecer con procesamiento
   **«Listo»** y estado **«Publicado»**.

Si todo eso salió bien, el sistema está instalado. Ahora:

- Para probar desde un celular con datos móviles **sin comprar nada**: guía
  [2 · Prueba rápida con túnel temporal](02-prueba-rapida-con-tunel-temporal.md).
- Para el despliegue real: guía [3 · Dominio propio](03-publicar-con-dominio-propio.md) **o**
  guía [4 · TI de la universidad](04-publicar-con-ti-de-la-universidad.md).

---

## Recomendado: contraseña para `root`

Después del paso 2, solo los programas de la propia PC pueden conectarse a la base de datos. Pero
`root` sigue sin contraseña, y cualquiera que use esta PC puede abrir phpMyAdmin
(<http://localhost/phpmyadmin>) y ver o borrar todo.

1. En PowerShell:

   ```powershell
   C:\xampp\mysql\bin\mysql.exe -u root
   ```

   ```sql
   ALTER USER 'root'@'localhost' IDENTIFIED BY 'otra-contraseña-distinta';
   EXIT;
   ```

2. Abre `C:\xampp\phpMyAdmin\config.inc.php` con el Bloc de notas como administrador, busca la línea
   `$cfg['Servers'][$i]['auth_type'] = 'config';` y cambia `'config'` por `'cookie'`. Guarda.

Desde entonces phpMyAdmin pide usuario y contraseña. El sistema no se ve afectado: usa su propio
usuario, `incidencias`.

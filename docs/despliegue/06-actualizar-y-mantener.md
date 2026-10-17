# 6 · Actualizar y mantener

Lo que hay que hacer con el sistema ya funcionando: traer cambios de GitHub, cuidar las copias de
seguridad, ampliarlo a más aulas o sedes, y qué hacer cuando algo falla.

---

## Traer una versión nueva desde GitHub

Cuando el equipo publica cambios en GitHub, en la PC de soporte:

```powershell
cd C:\incidencias
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --force
php artisan conocimiento:cargar
php artisan optimize:clear
php artisan optimize
php artisan up
```

Qué hace cada línea:

| Comando | Para qué |
|---|---|
| `down` | Muestra a los docentes «en mantenimiento» mientras dura la actualización, en lugar de errores a medias |
| `git pull` | Trae el código nuevo |
| `composer install` / `npm ci` / `npm run build` | Pone al día las dependencias y la interfaz |
| `migrate --force` | Aplica los cambios a la base de datos, **sin borrar datos** |
| `db:seed --force` | Actualiza roles, categorías y procedimientos de diagnóstico. **Si un procedimiento no cambió, no lo toca** |
| `conocimiento:cargar` | Carga las guías nuevas o corregidas. **Las que no cambiaron, las salta** |
| `optimize:clear` | Borra todo lo que el sistema tenía guardado de la versión anterior. **No lo saltes:** sin esto pueden aparecer errores de páginas que ya no existen |
| `optimize` | Vuelve a guardar la configuración nueva |
| `up` | Quita el aviso de mantenimiento |

**Hazlo fuera del horario de clases.** Tarda unos minutos.

**Antes de actualizar, saca una copia de seguridad a mano** (siguiente sección). Si la
actualización sale mal, es la forma de volver atrás.

---

## Copias de seguridad

### Automáticas

La tarea programada saca **una copia cada día a la 01:30** y conserva las **14 últimas**. Están en
`C:\incidencias\storage\app\respaldos`, con nombres como `incidencias-2026-10-20_013000.sql`.

> **Una copia en el mismo disco no sirve si el disco falla.** Configura otra carpeta en `.env`, en un
> **disco distinto** o una memoria USB que quede conectada:
>
> ```
> RESPALDO_CARPETA=E:\respaldos-incidencias
> ```
>
> Y después `php artisan optimize`.
>
> Además, **una vez por semana**, copia el archivo más reciente a otro lugar: un USB que se guarde
> fuera de la PC, o la nube institucional.

Esas copias contienen datos de la universidad y de la investigación. **No las subas a GitHub ni las
mandes por chat.** Git ya está configurado para ignorarlas.

### A mano

```powershell
cd C:\incidencias
php artisan respaldo:crear
```

**Qué tiene que salirte:** `Copia creada: …\incidencias-<fecha>.sql`.

### Restaurar una copia

Solo si se perdieron datos. **Restaurar reemplaza todo lo que hay ahora por lo que había en la copia.**

1. Elige el archivo de la fecha que quieres recuperar.
2. En PowerShell:

   ```powershell
   cd C:\incidencias
   php artisan down
   C:\xampp\mysql\bin\mysql.exe -u incidencias -p incidencias
   ```

   Te pide la contraseña de la base de datos, la del `DB_PASSWORD` del `.env`.

3. Dentro de MariaDB, con la ruta de tu archivo y **con barras `/`**:

   ```sql
   source C:/incidencias/storage/app/respaldos/incidencias-2026-10-20_013000.sql
   exit
   ```

4. De vuelta en PowerShell:

   ```powershell
   php artisan optimize:clear
   php artisan optimize
   php artisan up
   ```

---

## Cuando cambia el checklist de aulas

Se pusieron equipos nuevos, se habilitó un aula o se retiró un proyector: se exporta el checklist
actualizado a CSV y se carga igual que la primera vez.

```powershell
cd C:\incidencias
php artisan import:checklist datos-reales\checklist.csv
php artisan import:checklist datos-reales\checklist.csv --confirmar
```

El primero **simula** y el segundo **carga**. La carga actualiza lo que ya existe y agrega lo nuevo;
nunca duplica. Revisa siempre, en la simulación, la lista de **equipos que el checklist no
registra**: cada uno es un botón que el docente de esa aula deja de ver.

---

## Agregar otra sede

El sistema no está atado a Huancayo. Cada sede se carga con **su propio checklist** y su código:

```powershell
php artisan import:checklist datos-reales\checklist-lima.csv --sede=LIM --nombre-sede="Lima"
php artisan import:checklist datos-reales\checklist-lima.csv --sede=LIM --nombre-sede="Lima" --confirmar
```

- `--sede` es un código corto que identifica la sede. Si no se indica, se asume `HYO` (Huancayo).
- `--nombre-sede` es el nombre que verá el docente. Es obligatorio la primera vez; después ya no hace
  falta.
- Los pabellones, pisos y aulas de cada sede quedan separados: un «Pabellón A» de Lima no se mezcla
  con uno de Huancayo.

En cuanto exista más de una sede, la pantalla del docente empieza a preguntar en cuál está.
Mientras haya una sola, esa pregunta no aparece.

**El QR no cambia:** es el mismo para todas las aulas de todas las sedes.

---

## Problemas frecuentes

| Síntoma | Causa probable | Qué hacer |
|---|---|---|
| Cambié algo en el `.env` y no pasa nada | La configuración está guardada en memoria | `php artisan optimize` |
| Error 500, página «Server Error» | Ver el detalle en el registro | Abrir el archivo más reciente de `C:\incidencias\storage\logs` y buscar `ERROR` |
| Después de actualizar, error que menciona una clase que «no existe» | Quedó guardada la versión anterior | `php artisan optimize:clear` y después `php artisan optimize` |
| El asistente nunca responde, siempre manda a soporte | Ollama no está corriendo | Ver el ícono de la llama junto al reloj; si no está, abrir **Ollama** desde el menú Inicio |
| El asistente nunca responde, y Ollama sí está | Las guías se indexaron sin IA | `php artisan conocimiento:reindexar` |
| Las guías se quedan «pendientes» en el panel | La tarea programada no corre | Revisar **Incidencias - programador** en el Programador de tareas |
| El panel muestra el QR con un recuadro rojo | `PUBLIC_URL` vacía o con una dirección que no sirve | Leer el recuadro: dice exactamente qué falta |
| Desde la propia PC, `/entrar` da 404 | Se abrió por la dirección pública en lugar de `localhost` | Usar <http://localhost:8080/entrar> |
| MySQL no arranca en el Panel de XAMPP | Ver el motivo en el registro | Abrir `C:\xampp\mysql\data\mysql_error.log` y leer las últimas líneas con `[ERROR]` |

### Lo que NO hay que hacer

- **No apagar la PC desde el botón de encendido** ni cortar la corriente con el sistema corriendo.
  Apagar siempre desde Windows. Un corte brusco puede dañar las tablas internas de MariaDB.
- **No cerrar MySQL con el Administrador de tareas.** Siempre con **Stop** en el Panel de XAMPP.
- **No borrar archivos dentro de `C:\xampp\mysql\data`.** Ahí están los datos.
- **No ejecutar `php artisan pruebas:reiniciar`** una vez que empezaron los docentes reales: borra
  los reportes, que son los datos de la investigación.
- **No ejecutar `php artisan migrate:fresh`** nunca en esta PC: borra la base de datos entera.

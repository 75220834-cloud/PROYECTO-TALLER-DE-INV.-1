# 2 · Prueba rápida con túnel temporal

Sirve para probar el sistema **desde un celular con datos móviles**, sin comprar dominio ni pedir
nada a nadie. Se hace en diez minutos.

> **No sirve para los carteles.** La dirección que da este túnel **cambia cada vez que se prende**.
> Un QR impreso con ella deja de funcionar en cuanto se reinicia el túnel. El panel lo sabe y marca
> esos carteles con «NO PEGAR».

**Requisito:** el sistema instalado y funcionando en <http://localhost:8080> (guía 1).

---

## Cómo funciona

Hoy la PC no es visible desde internet: un celular con datos no tiene forma de llegar a ella.
**cloudflared** es un programa de Cloudflare que abre una conexión **desde** la PC **hacia**
Cloudflare. Cloudflare responde con una dirección pública del estilo:

```
https://palabra-palabra-palabra.trycloudflare.com
```

Cualquiera que abra esa dirección, desde cualquier red, llega a la PC por esa conexión. La PC no abre
ningún puerto: es ella la que sale hacia afuera.

---

## Paso 1 · Descargar cloudflared

1. Entra a <https://github.com/cloudflare/cloudflared/releases/latest>.
2. En **Assets**, descarga **`cloudflared-windows-amd64.exe`**.
3. Muévelo a una carpeta fácil, por ejemplo `C:\herramientas\cloudflared.exe`. Cámbiale el nombre
   a `cloudflared.exe`.

No se instala nada: es un solo archivo.

---

## Paso 2 · Prender el túnel

En PowerShell:

```powershell
C:\herramientas\cloudflared.exe tunnel --url http://localhost:8080
```

**Qué tiene que salirte:** después de unos segundos, un recuadro con una línea como esta:

```
|  https://palabra-palabra-palabra.trycloudflare.com  |
```

Copia esa dirección. **No cierres esta ventana:** si la cierras, el túnel se apaga.

Si no aparece ninguna dirección y salen errores de conexión: la red está bloqueando el puerto que
usa el túnel (7844). Pasa en algunas redes institucionales. Prueba desde otra red, o mira el paso 1
de la guía 3.

---

## Paso 3 · Decirle al sistema cuál es su dirección

En **otra** ventana de PowerShell:

```powershell
cd C:\incidencias
notepad .env
```

Busca `PUBLIC_URL=` y pega la dirección del paso 2:

```
PUBLIC_URL=https://palabra-palabra-palabra.trycloudflare.com
```

Guarda, cierra el Bloc de notas y ejecuta:

```powershell
php artisan optimize
php artisan qr:generar
```

**Qué tiene que salirte:** la dirección con `/reportar` al final, y un aviso de que es un túnel
temporal. El aviso es correcto: es justo lo que es.

El QR queda en `C:\incidencias\storage\app\qr\qr-acceso.png`.

---

## Paso 4 · Probar con el celular

1. **Apaga el WiFi del celular**, para probar de verdad con datos móviles.
2. Abre el archivo `qr-acceso.png` en la pantalla de la PC y escanéalo con la cámara del celular.

**Qué tiene que salirte:** la pantalla «¿Dónde estás?» del sistema, con la dirección
`https://…trycloudflare.com` arriba y el candado de conexión segura.

A partir de ahí, sigue la lista de la guía [5 · Probar todo](05-probar-todo.md), sección
**Desde el celular**.

### Comprobar que el panel NO es visible desde internet

En el celular, con datos, abre la dirección del túnel agregando `/entrar` al final:

```
https://palabra-palabra-palabra.trycloudflare.com/entrar
```

**Qué tiene que salirte:** una página de **«No encontrado» (404)**. Es correcto: desde internet, la
pantalla de inicio de sesión no existe. Desde la propia PC, <http://localhost:8080/entrar> sí abre.

---

## Paso 5 · Al terminar

1. En la ventana del túnel, pulsa **Ctrl + C**. El túnel se apaga y la dirección deja de existir.
2. Borra la dirección del `.env`, deja `PUBLIC_URL=` vacía y ejecuta `php artisan optimize`.

Si no la borras, el panel seguirá mostrando un QR que apunta a una dirección que ya no existe.

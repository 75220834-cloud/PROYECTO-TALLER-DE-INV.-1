# 3 · Publicar con dominio propio (opción D)

Resultado: el sistema queda en una dirección **fija** como `https://incidencias.tudominio.com`, con
conexión segura (HTTPS), accesible desde cualquier red, sin hosting y sin abrir puertos en la red de
la universidad. El QR impreso con esa dirección sirve para siempre, mientras se renueve el dominio.

**Costo:** solo el dominio, que se paga **una vez al año**. El precio depende de la terminación
(`.com`, `.net`, `.pe`…); se ve antes de pagar. El túnel de Cloudflare es gratuito.

**Requisito:** el sistema instalado y funcionando en <http://localhost:8080> (guía 1).

> **Los menús de Cloudflare cambian con el tiempo.** Esta guía usa los nombres vigentes en octubre
> de 2026. Si un botón no está donde dice, la documentación oficial es
> <https://developers.cloudflare.com/cloudflare-one/networks/connectors/cloudflare-tunnel/>.

---

## Paso 1 · Comprobar que la red de la universidad deja pasar el túnel

**Hazlo antes de pagar nada.** El túnel necesita que la PC pueda conectarse hacia afuera por el
puerto **7844**. En una casa casi siempre está abierto; en una red institucional puede estar
bloqueado.

En PowerShell, **en la PC de soporte, conectada a la red de la universidad**:

```powershell
Test-NetConnection region1.v2.argotunnel.com -Port 7844
```

**Qué tiene que salirte:** `TcpTestSucceeded : True`.

Si sale `False`, el firewall de la universidad bloquea el túnel. Tienes dos caminos:

- Pedir a TI que permita la **salida** hacia `region1.v2.argotunnel.com` y
  `region2.v2.argotunnel.com` por el puerto **7844, TCP y UDP**. Es solo salida: no se abre nada
  hacia adentro.
- O usar la guía [4 · TI de la universidad](04-publicar-con-ti-de-la-universidad.md).

Si quieres confirmarlo del todo, haz la prueba de la guía 2 desde esa PC: si el túnel temporal da
una dirección, el definitivo también va a funcionar.

---

## Paso 2 · Crear la cuenta de Cloudflare

1. Entra a <https://dash.cloudflare.com/sign-up>.
2. Regístrate con un **correo que vaya a seguir existiendo**: idealmente uno de soporte, no uno
   personal que alguien deje de usar al terminar el ciclo.
3. **Confirma el correo:** Cloudflare manda un enlace y sin confirmarlo no deja comprar dominios.

---

## Paso 3 · Comprar el dominio

1. En el panel de Cloudflare, en el menú de la izquierda, entra a **Domain Registration › Register
   Domains** (o busca «Register domains» en la barra de búsqueda del panel).
2. Escribe el nombre que quieres, por ejemplo `incidencias-aulas-hyo`, y pulsa **Search**.
3. Junto a una opción disponible, pulsa **Purchase**.
4. En **Payment option**, elige **1 año**.
5. Completa los datos de contacto: nombre, correo, teléfono con código de país (+51), dirección.
   **Solo letras sin tildes:** el formulario no acepta caracteres especiales.
6. Completa el pago y revisa los acuerdos.
7. Pulsa **Complete purchase**.

**Qué tiene que salirte:** en unos segundos, la página de administración del dominio y un correo de
confirmación.

> **Renovación automática:** viene activada. Déjala así. Si el dominio vence, **todos los carteles
> dejan de funcionar el mismo día.** Anota la fecha de renovación en el calendario de soporte.

> **Si quieres un `.pe`:** búscalo igual. Si Cloudflare no lo ofrece, se compra en otro registrador
> y después se agrega a Cloudflare (**Add a domain**) cambiando sus servidores de nombres a los que
> indica Cloudflare. Es un paso más y puede tardar unas horas en activarse.

---

## Paso 4 · Crear el túnel

1. En el menú de la izquierda: **Networking › Tunnels**.
2. Pulsa **Create a tunnel**.
3. Ponle un nombre: `incidencias-pc-soporte`. Pulsa **Create Tunnel**.
4. Te pregunta el sistema operativo: elige **Windows**.
5. Aparece un comando que empieza por `cloudflared.exe service install` seguido de una clave larga.
   **Esa clave es secreta:** quien la tenga puede conectar su propia PC en lugar de la de soporte.
   No la mandes por chat ni la guardes en un documento compartido.
6. Antes de ejecutarlo, instala cloudflared en la PC de soporte:
   - Descarga **`cloudflared-windows-amd64.msi`** desde
     <https://github.com/cloudflare/cloudflared/releases/latest> e instálalo.
7. Abre **PowerShell como administrador** (clic derecho › Ejecutar como administrador), **pega el
   comando** que muestra Cloudflare y pulsa Enter.
8. Vuelve al navegador y espera unos segundos.

**Qué tiene que salirte:** en la parte de abajo de la página, el conector aparece como
**Connected**. Pulsa **Continue**.

Ese comando instaló cloudflared como **servicio de Windows**: arranca solo al encender la PC, aunque
nadie inicie sesión.

---

## Paso 5 · Publicar el sistema en el dominio

1. En **Networking › Tunnels**, entra a tu túnel `incidencias-pc-soporte`.
2. Abre la pestaña **Routes**, pulsa **Add route** y elige **Published application**.
3. Completa:

   | Campo | Qué poner |
   |---|---|
   | **Subdomain** | `incidencias` |
   | **Domain** | el dominio que compraste, de la lista |
   | **Path** | vacío |
   | **Service URL** | `http://localhost:8080` |

4. Pulsa **Add route**.

**Qué tiene que salirte:** en **Networking › Tunnels**, el túnel con estado **Healthy**.

Tu dirección pública es ahora:

```
https://incidencias.<tu-dominio>
```

> **Un solo nivel de subdominio.** `incidencias.tudominio.com` funciona tal cual. Algo como
> `aulas.incidencias.tudominio.com` requiere un certificado adicional de pago. No lo necesitas.

---

## Paso 6 · Decirle al sistema cuál es su dirección

En PowerShell, en la PC de soporte:

```powershell
cd C:\incidencias
notepad .env
```

Completa la línea `PUBLIC_URL=` con tu dirección, **con `https://` y sin barra al final**:

```
PUBLIC_URL=https://incidencias.tudominio.com
```

Guarda y ejecuta:

```powershell
php artisan optimize
php artisan qr:generar
```

**Qué tiene que salirte:** `https://incidencias.tudominio.com/reportar` y **ningún** aviso de
«ATENCIÓN». Si aparece un aviso, léelo: dice exactamente qué está mal.

---

## Paso 7 · Comprobar desde fuera

Con un celular **con el WiFi apagado**, usando datos móviles:

1. Abre `https://incidencias.tudominio.com`.

   **Qué tiene que salirte:** la pantalla del docente, con el candado de conexión segura.

2. Abre `https://incidencias.tudominio.com/entrar`.

   **Qué tiene que salirte:** **«No encontrado» (404)**. Es lo correcto: desde internet, el panel
   de soporte no existe. Solo se abre en la PC, en <http://localhost:8080/entrar>.

Después, la lista completa de la guía [5 · Probar todo](05-probar-todo.md).

---

## Paso 8 · Imprimir los carteles

1. En la PC, entra al panel: <http://localhost:8080/entrar>.
2. Ve a **Código QR**.

   **Qué tiene que salirte:** el QR con tu dirección debajo y **sin recuadro rojo de advertencia**.

3. Pulsa **Ver cartel para imprimir** e imprime **uno** de prueba.
4. **Pruébalo antes de imprimir el resto:** pégalo en un aula, escanéalo con un celular con datos
   móviles y haz un reporte completo.
5. Solo entonces, imprime los demás.

El cartel es **el mismo para todas las aulas**: no hay que imprimir uno distinto por aula.

---

## Qué hacer si algo deja de funcionar

| Síntoma | Causa probable | Qué hacer |
|---|---|---|
| El celular dice que no encuentra el sitio | La PC está apagada o sin internet | Prender la PC; revisar su conexión |
| Cloudflare muestra «Error 1033» | El servicio cloudflared está detenido | En la PC: `services.msc` › **Cloudflared agent** › Iniciar |
| Cloudflare muestra «Error 502» | Apache está detenido | Panel de XAMPP › Start en Apache |
| El túnel aparece **Down** en Cloudflare | Lo mismo que 1033, o la red bloqueó el puerto 7844 | Revisar el servicio y repetir el paso 1 |
| Todo funcionaba y de un día para otro nada | El dominio venció | Cloudflare › **Domain Registration** › renovar |

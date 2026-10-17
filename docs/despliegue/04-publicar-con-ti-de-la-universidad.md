# 4 · Publicar con TI de la universidad (opción E)

Resultado: el sistema queda en una dirección **fija** dentro del dominio de la universidad, con
conexión segura (HTTPS), accesible desde cualquier red. **Costo cero** para el proyecto.

A cambio, depende de que el área de TI lo apruebe y lo configure, y de sus plazos. Esta guía dice
**qué pedir exactamente**, para que la solicitud no vuelva con preguntas, y **qué hacer cuando
respondan**.

**Requisito:** el sistema instalado y funcionando en <http://localhost:8080> (guía 1).

> **Esta guía no asume nada sobre cómo trabaja TI.** No sabemos qué equipos usa, qué dominio
> asignaría ni cuánto tarda. Lo que sigue es la solicitud técnica; el canal y el formato los define
> TI.

---

## Paso 1 · Antes de pedir, averiguar dos datos de la PC

En la PC de soporte, en PowerShell:

```powershell
hostname
ipconfig | findstr IPv4
```

Anota:

- **Nombre de la PC:** la línea que devuelve `hostname`.
- **IP actual:** la de `ipconfig`, del tipo `10.x.x.x` o `172.x.x.x`.

TI los necesita para identificar la PC dentro de su red.

---

## Paso 2 · La solicitud

Copia este texto, completa lo que está entre corchetes y envíalo por el canal que use TI.

---

> **Asunto:** Solicitud de publicación interna de un servicio web para reporte de incidencias en aulas
>
> **Área solicitante:** Soporte Técnico — [nombre de quien solicita], [correo], [anexo]
>
> **Qué es.** Un sistema web para que los docentes reporten fallas de los equipos del aula
> (proyector, computadora, audio) escaneando un código QR con su celular. El sistema los guía con
> pasos simples y, si no se resuelve, registra el pedido para soporte con la ubicación exacta.
> Forma parte de un proyecto de investigación del Taller de Investigación 1 (NRC 30162-30163).
>
> **Dónde corre.** En una PC del área de soporte:
>
> - Nombre del equipo: [nombre de la PC]
> - IP actual: [IP]
> - Servidor web: Apache (XAMPP), escuchando en el puerto **8080**, HTTP, solo dentro de la PC.
>
> **Lo que solicitamos:**
>
> 1. **IP fija** para esa PC (reserva en el DHCP), para que la dirección no cambie.
> 2. **Un nombre público**, por ejemplo `incidencias.[dominio de la universidad]`, accesible desde
>    internet, porque los docentes entran con sus datos móviles.
> 3. **Publicación mediante proxy inverso** de la universidad, con certificado HTTPS en el proxy,
>    reenviando hacia `http://[IP]:8080`.
> 4. Que el proxy agregue las cabeceras estándar `X-Forwarded-For`, `X-Forwarded-Proto` y
>    `X-Forwarded-Host`. El sistema las usa para conocer la IP real de quien reporta, que necesita
>    para limitar abusos, y para generar los enlaces con HTTPS.
> 5. Que nos indiquen la **IP interna del proxy**, para configurar el sistema y que solo confíe en
>    él.
>
> **Lo que NO pedimos:**
>
> - No pedimos abrir ningún puerto de la PC directamente hacia internet.
> - La base de datos **no** se expone: solo acepta conexiones desde la propia PC.
> - El panel de administración **no** necesita ser visible desde internet: el sistema lo bloquea
>   para cualquier petición que llegue a través del proxy. Si el proxy permite filtrar por ruta,
>   basta con publicar las rutas que empiezan por `/reportar`, `/imagen`, `/build` y `/fonts`, y la
>   raíz `/`.
>
> **Datos personales.** El docente no inicia sesión ni se le pide ningún dato personal. Las IP se
> guardan cifradas de forma irreversible. Las fotos que el docente adjunta son opcionales y se les
> borran los metadatos, incluida la ubicación.
>
> **Disponibilidad necesaria.** Horario de clases. La PC queda encendida en ese horario.
>
> Quedamos atentos a cualquier requisito adicional del área.

---

## Paso 3 · Cuando TI responda

TI te va a dar dos cosas: **la dirección pública** y **la IP del proxy**. Con ellas:

```powershell
cd C:\incidencias
notepad .env
```

1. En `PUBLIC_URL=`, la dirección pública, **con `https://` y sin barra al final**:

   ```
   PUBLIC_URL=https://incidencias.ejemplo.edu.pe
   ```

2. En `TRUSTED_PROXIES=`, agrega la IP del proxy **después** de lo que ya hay, separada por coma:

   ```
   TRUSTED_PROXIES=127.0.0.1,::1,10.20.30.40
   ```

   Usa la IP que te den, no la del ejemplo.

3. Guarda y ejecuta:

   ```powershell
   php artisan optimize
   php artisan qr:generar
   ```

**Qué tiene que salirte:** tu dirección con `/reportar` al final y **ningún** aviso de «ATENCIÓN».

> **Por qué hay que agregar la IP del proxy.** El sistema solo cree lo que dicen las cabeceras de
> reenvío cuando vienen de un equipo de confianza. Si el proxy no está en la lista, todos los
> docentes parecen venir de la misma IP, la del proxy, y el límite de reportes por red los bloquea
> a todos juntos después de treinta reportes en una hora.

---

## Paso 4 · Comprobar e imprimir

Igual que en la guía 3, a partir del **paso 7**:

1. Desde un celular **con datos móviles**, la dirección pública abre la pantalla del docente.
2. La dirección pública con `/entrar` al final da **«No encontrado» (404)**.
3. El panel, en la PC, muestra el QR **sin advertencia roja**.
4. Un cartel de prueba, pegado y probado en un aula, **antes** de imprimir el resto.

Después, la lista completa de la guía [5 · Probar todo](05-probar-todo.md).

# Endurecimiento y operación

Lista de verificación para pasar del entorno de desarrollo al servidor
institucional. Corresponde a las fases 9 y 11 del plan (§16, §20.4, §20.5).

**Nada de esto es opcional antes del piloto.** El sistema queda expuesto a la
red universitaria y recibe entrada de personas que no se autentican.

---

## 1. Lo que ya viene resuelto en el código

No hay que hacer nada para esto; se lista para saber qué está cubierto y qué
no, y para poder responderlo en la sustentación.

| Control | Dónde vive |
|---|---|
| Cabeceras de seguridad (CSP, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy) | `app/Http/Middleware/SecurityHeaders.php`, aplicado a **todas** las respuestas |
| CSP sin `'unsafe-inline'` en scripts | Ninguna vista lleva `onclick=""`; el comportamiento vive en `resources/js/interactions.js`. Hay una prueba que falla si alguien vuelve a meter uno |
| Bloqueo de intentos de login | `LoginRequest`, 5 intentos por correo **+ IP**, no solo por IP |
| Mensaje de login que no revela si el correo existe | `LoginRequest::authenticate()` |
| IP siempre hasheada (HMAC con `APP_KEY`) | `app/Shared/Support/IpHasher.php` |
| Antiabuso de la creación de tickets, 7 controles | `app/Modules/Incidents/Services/AbuseGuard.php` |
| Límite de tasa amplio en la navegación pública | `routes/web.php`, grupo `throttle:120,1` |
| CSRF, escapado de Blade, consultas parametrizadas | Framework, sin excepciones en el código |
| Imágenes reprocesadas al subir (elimina EXIF) | Módulo Media |
| Auditoría de dependencias | `composer audit` y `npm audit`, sin avisos a la fecha de este documento |

**Excepción declarada:** la tabla `sessions` de Laravel guarda `ip_address` en
claro. Es infraestructura del framework y alterarla rompería el manejo de
sesiones. El dato es efímero —la fila se borra al cerrar sesión y expira por
inactividad— y corresponde a personal interno autenticado, no a docentes. No
debe afirmarse en el informe que el sistema no guarda ninguna IP: guarda esa.

---

## 2. Variables de entorno del servidor

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://[DNS INTERNO]

SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
SESSION_SAME_SITE=lax

LLM_PROVIDER=ollama
LLM_BASE_URL=http://127.0.0.1:11434
```

`APP_DEBUG=true` en el servidor mostraría la traza completa —incluidas
credenciales de base de datos— en cualquier error. Es el fallo de
configuración más común y más grave.

`APP_KEY` debe generarse **en el servidor** y no copiarse del entorno de
desarrollo: es la clave con la que se hashean las IP y se cifran las sesiones.

---

## 3. Elección del stack en el servidor (§20.5)

XAMPP está documentado por sus propios autores como herramienta de
**desarrollo**. Su configuración por defecto trae `root` sin contraseña,
phpMyAdmin sin restricción, listado de directorios y errores detallados.
Desplegarlo tal cual en la red universitaria sería una vulnerabilidad seria.

Esto **no invalida** usar XAMPP para desarrollar. Solo significa que el
servidor exige un paso más, y hay dos caminos:

### Opción A — Stack nativo (recomendada)

Apache o Nginx + PHP + MariaDB instalados como servicios del sistema y
endurecidos por TI según sus estándares. **El código de la aplicación es
idéntico**; solo cambia el `.env`. No hay retrabajo.

### Opción B — XAMPP endurecido

Viable si TI lo prefiere. Mínimo exigible:

- [ ] Contraseña fuerte para el usuario `root` de MariaDB
- [ ] Usuario de aplicación con privilegios solo sobre la base del proyecto
- [ ] phpMyAdmin restringido a `localhost` o deshabilitado
- [ ] MariaDB escuchando solo en `127.0.0.1` (puerto 3306 nunca expuesto)
- [ ] Ollama escuchando solo en `127.0.0.1` (puerto 11434 nunca expuesto)
- [ ] `display_errors=Off` en `php.ini`
- [ ] Listado de directorios deshabilitado en Apache
- [ ] `DocumentRoot` apuntando a `public/`, **nunca** a la raíz del proyecto

### Verificación obligatoria del `DocumentRoot`

```bash
curl -i https://[DNS INTERNO]/.env
```

Debe responder **404**. Si devuelve el contenido, el `DocumentRoot` está mal
y las credenciales de la base de datos están publicadas en la red. Es el error
de configuración más frecuente en despliegues con XAMPP.

Comprobar lo mismo con `/vendor/`, `/storage/` y `/composer.json`.

---

## 4. Puesta en marcha

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate
php artisan migrate --force
php artisan optimize
```

Procesos que deben quedar corriendo (servicio del sistema, no una terminal
abierta):

| Proceso | Qué pasa si no corre |
|---|---|
| `php artisan queue:work` | Los documentos nunca se indexan y las notificaciones no llegan |
| `php artisan schedule:run` (cada minuto, vía cron o Programador de tareas) | No se purgan borradores, no se recalculan métricas ni señales de riesgo. **El tablero no avisa de que sus datos están viejos** |
| `ollama serve` | El sistema sigue funcionando completo en modo determinista |

---

## 5. Respaldo y restauración (§20.4)

```bash
mysqldump --single-transaction -u [USUARIO] -p incidencias | gzip > respaldo-$(date +%F).sql.gz
```

`--single-transaction` es **obligatorio** con InnoDB: sin él el volcado se
toma mientras la aplicación escribe y sale inconsistente. Parece correcto y no
lo es, que es la peor forma de fallar en un respaldo.

También hay que copiar, semanalmente:

- `storage/app/knowledge` — los documentos originales
- `storage/app/media` — el banco de imágenes
- `.env` — en ubicación segura, **fuera** del repositorio

No hace falta respaldar los *embeddings*: son caché derivada y se regeneran
con `php artisan knowledge:reindex`.

### Restauración — hay que probarla al menos una vez

Un respaldo que nunca se restauró no es un respaldo: es una suposición.

1. Restaurar el volcado sobre una base limpia
2. Restaurar `storage/app/knowledge` y `storage/app/media`
3. `php artisan knowledge:reindex`
4. Verificar: el flujo del docente completo y una búsqueda en el asistente

Objetivo declarado: RPO 24 h, RTO 4 h.

---

## 6. Antes de abrir el piloto

- [ ] Conectividad verificada **físicamente desde un aula**, con un celular en
      la WiFi institucional (supuesto S1, riesgo R2 — puede invalidar el piloto entero)
- [ ] `curl -i https://[DNS INTERNO]/.env` devuelve 404
- [ ] `APP_DEBUG=false` confirmado en el servidor
- [ ] Restauración de respaldo ejecutada con éxito una vez
- [ ] Datos DEMO purgados (`php artisan demo:purge`) y datos reales cargados
- [ ] Umbrales antiabuso revisados tras la primera semana mirando
      `incident_abuse_rejections` — vienen con valores **provisionales** que
      nadie ha calibrado todavía
- [ ] Autorizaciones institucionales por escrito: piloto, señalética, registro
      fotográfico, acceso al histórico

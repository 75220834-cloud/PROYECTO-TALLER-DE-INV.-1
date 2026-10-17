# Despliegue en la PC de soporte

Guías para dejar el sistema funcionando en una PC de la universidad, de forma que los docentes
entren desde su celular **con cualquier red**, incluidos sus datos móviles, y el personal de
soporte lo administre desde esa misma PC.

## En qué orden leerlas

| # | Guía | Para qué | Cuándo |
|---|---|---|---|
| 1 | [Instalar en la PC de soporte](01-instalar-en-la-pc-de-soporte.md) | Dejar el sistema corriendo en la PC, desde cero | Siempre, primero |
| 2 | [Prueba rápida con túnel temporal](02-prueba-rapida-con-tunel-temporal.md) | Probar desde un celular con datos móviles, sin comprar nada | Para probar; **no** para los carteles |
| 3 | [Publicar con dominio propio](03-publicar-con-dominio-propio.md) | Dirección fija en internet, por el costo del dominio | Despliegue real, opción D |
| 4 | [Publicar con TI de la universidad](04-publicar-con-ti-de-la-universidad.md) | Dirección fija en un subdominio de la universidad | Despliegue real, opción E |
| 5 | [Probar todo](05-probar-todo.md) | Lista de comprobación completa antes de imprimir los QR | Después de 1 y de 3 o 4 |
| 6 | [Actualizar y mantener](06-actualizar-y-mantener.md) | Traer cambios de GitHub, copias de seguridad, problemas frecuentes | Durante la operación |
| 7 | [Fotos de los equipos](07-fotos-de-los-equipos.md) | Qué fotos tomar, con qué clave subirlas, e ilustraciones provisionales | Antes del piloto |

Las guías 3 y 4 son **alternativas**: se elige una de las dos.

## Cómo funciona, en un dibujo

```
  Celular del docente                           PC de soporte (universidad)
  (datos móviles o WiFi)                       ┌───────────────────────────────┐
          │                                     │                               │
          │  https://<dirección pública>        │   Apache :8080  ──►  Sistema  │
          ▼                                     │        ▲                      │
   ┌──────────────┐    conexión de salida       │        │                      │
   │  Cloudflare  │◄────────────────────────────┤   cloudflared (túnel)         │
   │  (o proxy TI)│    (la PC no abre puertos)  │                               │
   └──────────────┘                             │   MariaDB · Ollama (IA)       │
                                                │                               │
  Personal de soporte ─── http://localhost:8080/panel ──► (solo dentro de la PC   │
                                                │          o de la red interna)  │
                                                └───────────────────────────────┘
```

- El **docente** entra por la dirección pública. Solo existe para él la parte de reportar.
- El **panel de soporte** solo se abre desde la propia PC o desde la red interna. Desde internet
  responde como si no existiera, incluida la pantalla de inicio de sesión.
- La PC **no abre ningún puerto hacia internet**: es ella la que se conecta hacia afuera.

## Lo que tiene que estar decidido antes de imprimir los QR

1. **La dirección pública definitiva** (guía 3 o 4). El QR la lleva adentro: si cambia, los
   carteles pegados dejan de funcionar.
2. **Que la PC quede prendida en horario de clases.** Si se apaga, ningún QR funciona.

El panel avisa en rojo cuando la dirección del QR no sirve para imprimir: cuando apunta a
`localhost`, a una IP interna o a un túnel temporal. Un cartel impreso con esa dirección sale
marcado con «NO PEGAR ESTE CARTEL EN UN AULA».

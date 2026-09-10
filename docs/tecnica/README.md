# Documentación técnica del sistema

Sistema inteligente para la asistencia, gestión y predicción de incidencias tecnológicas
en aulas · Universidad Continental, sede Huancayo.

Esta carpeta describe **qué hace el sistema, cómo lo hace y por qué está hecho así**. Está
escrita para servir de material de referencia al redactar informes, la tesis o cualquier
documento del curso: cada afirmación sale del código real, no de lo que estaba planeado.

---

## Los dieciséis documentos

| # | Documento | De qué trata |
|---|---|---|
| 01 | [Visión general](01-vision-general.md) | Qué problema resuelve, para quién, qué **no** hace y por qué. Glosario de todos los términos |
| 02 | [Arquitectura](02-arquitectura.md) | Stack tecnológico y por qué cada pieza, monolito modular, los 13 módulos, y las **doce decisiones de diseño** que atraviesan el sistema |
| 03 | [Modelo de datos](03-modelo-de-datos.md) | Las 44 tablas, cómo se relacionan y por qué están diseñadas así |
| 04 | [Ciclo de vida de la incidencia](04-ciclo-de-vida-de-la-incidencia.md) | Los ocho estados, las transiciones permitidas, el cálculo de prioridad y la detección de riesgo físico |
| 05 | [Flujo del docente](05-flujo-del-docente.md) | Todo lo que ve el docente desde que escanea el QR, pantalla por pantalla, y los seis controles antiabuso |
| 06 | [Flujo de soporte](06-flujo-de-soporte.md) | El panel completo: bandeja, alertas, detalle del ticket, historial del aula, reportes, administración |
| 07 | [Inteligencia artificial](07-inteligencia-artificial.md) | Qué modelo se usa y por qué es local, las tres funciones de la IA, el glosario peruano, la política de confianza y qué pasa cuando falla |
| 08 | [Conocimiento y recuperación](08-conocimiento-y-recuperacion.md) | Cómo un PDF se vuelve buscable, y la búsqueda híbrida completa (RAG) |
| 09 | [Diagnóstico guiado](09-diagnostico-guiado.md) | Los árboles de diagnóstico, por qué son deterministas, el versionado y las imágenes |
| 10 | [Modelo de riesgo](10-modelo-de-riesgo.md) | Qué significa y qué **no** significa el score, la prevención de fuga temporal y el camino hacia un modelo supervisado |
| 11 | [Seguridad y privacidad](11-seguridad-y-privacidad.md) | Modelo de amenazas, los 21 permisos y 6 roles, la política de seguridad de contenido, y qué datos personales se manejan |
| 12 | [Pruebas y calidad](12-pruebas-y-calidad.md) | Las 442 pruebas en 7 suites, el análisis estático y la integración continua |
| 13 | [Operación](13-operacion.md) | Puesta en marcha, comandos, tareas programadas, respaldos y solución de problemas |
| 14 | [Métricas e investigación](14-metricas-e-investigacion.md) | Qué mide el sistema, las diez preguntas de investigación que puede responder y cómo se exportan los datos |
| 15 | [Estado real del piloto](15-estado-real-del-piloto.md) | Qué está probado y qué no, con las cifras exactas de hoy. **Situación transitoria** |
| 16 | [Mejoras futuras](16-mejoras-futuras.md) | Todo lo que se puede añadir, marcando de dónde sale cada idea, y la recomendación de alcance para Taller 2 |

---

## Por dónde empezar

| Si quieres… | Lee |
|---|---|
| Entender de qué va todo | 01 → 02 |
| Escribir el capítulo de la IA | 07 → 08 → 09 |
| Escribir el capítulo de metodología | 14 → 10 → 15 |
| Escribir el capítulo de arquitectura | 02 → 03 |
| Explicar cómo se usa | 05 → 06 |
| Defender el trabajo ante el jurado | 15 → 11 → 12 |
| Planificar Taller 2 | 15 → 16 |

Cada documento de la parte técnica termina con un párrafo **«Resumen para citar en la
tesis»**, redactado en registro académico impersonal.

---

## Guías prácticas (fuera de esta carpeta)

Estas son operativas, no descriptivas. Explican **cómo hacer algo**, paso a paso:

| Documento | Para qué |
|---|---|
| [`docs/instalar-en-otra-pc.md`](../instalar-en-otra-pc.md) | Instalar el sistema en otra computadora, con lo que tiene que salir en pantalla en cada paso |
| [`docs/carga-de-datos.md`](../carga-de-datos.md) | Qué información hay que subir y en qué formato |
| [`docs/endurecimiento-y-operacion.md`](../endurecimiento-y-operacion.md) | Preparar el sistema para el piloto real |
| [`docs/decisions/`](../decisions/) | Registro de decisiones de arquitectura |
| [`docs/plantillas/`](../plantillas/) | Plantillas CSV para importar aulas y equipos |

---

## Estado del proyecto

| Dimensión | Estado |
|---|---|
| Código | ✅ Terminado — 139 rutas, 442 pruebas en verde, análisis estático limpio |
| Datos institucionales | ⬜ Pendientes de cargar |
| Corpus para la IA | ⬜ Vacío |
| Piloto | ⬜ No iniciado |

El detalle honesto de esa tabla está en
[15 · Estado real del piloto](15-estado-real-del-piloto.md).

---

## Cómo mantener esta documentación

Si el código cambia, estos documentos deberían cambiar con él. Dos reglas que conviene
conservar:

1. **No hay código dentro.** Los fragmentos de código se desactualizan en silencio; la
   explicación de por qué algo funciona así, no. Se nombran archivos, clases y comandos —
   eso sí sirve para encontrar las cosas— pero no se copia su contenido.

2. **Cada decisión lleva su porqué y su alternativa descartada.** Es lo que hace útil esta
   documentación dentro de seis meses, cuando nadie recuerde por qué se hizo así.

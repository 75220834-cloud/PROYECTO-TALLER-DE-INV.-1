# 14 · Métricas e investigación

> **Qué encuentras aquí:** qué mide el sistema, cómo lo mide, qué preguntas de
> investigación puede responder y cómo se extraen los datos para el análisis estadístico.

---

## 14.1 Las dos reglas que atraviesan todas las métricas

Antes de cualquier indicador, estas dos reglas condicionan todos los números:

### Regla 1 · Los borradores no cuentan como incidencias

Un docente que confirmó su aula y se fue sin reportar nada **no es una incidencia**.

Incluirlo inflaría los totales y hundiría artificialmente los porcentajes de resolución.
Se cuentan aparte, como **indicador de fricción**.

### Regla 2 · El tiempo se mide desde que el docente confirmó el aula

No desde que se creó el ticket.

**Por qué:** medir desde el ticket descontaría todo el rato que el docente pasó en el
diagnóstico, y haría que el sistema pareciera mejor de lo que es.

Es la diferencia entre medir el proceso completo y medir solo la parte que favorece.

---

## 14.2 El indicador central

> **Tasa de autonomía de resolución:** de todas las incidencias resueltas, qué proporción
> se resolvió sin que soporte se moviera.

```
autonomía = resueltas por el asistente ÷ total de resueltas × 100
```

Es lo que el proyecto pretende mover. Todo lo demás lo contextualiza.

### El indicador derivado

> **Desplazamientos evitados:** resueltas por el asistente + resueltas de forma remota.

Es la traducción del indicador central a algo que una institución entiende directamente:
cuántas veces nadie tuvo que caminar hasta un aula.

---

## 14.3 Todos los indicadores del tablero

### Volumen

| Indicador | Qué dice |
|---|---|
| Incidencias en el periodo | El total real (sin borradores) |
| Por categoría | Qué falla más |
| Por aula y por pabellón | Dónde falla más |
| Abiertas ahora mismo | Carga pendiente |

### Resolución

| Indicador | Qué dice |
|---|---|
| Total resueltas | |
| Resueltas por el asistente | **El numerador del indicador central** |
| Resueltas de forma remota | Sin desplazamiento |
| Resueltas presencialmente | El caso más costoso |
| **Tasa de autonomía** | El indicador central |
| **Desplazamientos evitados** | Su traducción operativa |

### Tiempos — siempre en mediana, nunca en media

| Indicador | Desde | Hasta |
|---|---|---|
| Hasta la primera respuesta | Confirmación del aula | Alguien vio el ticket |
| Hasta la asignación | Confirmación | Un técnico se hizo cargo |
| Hasta la llegada | Confirmación | El técnico llegó al aula |
| **Hasta la resolución** | Confirmación | Se marcó resuelta |

**Por qué mediana y no media:** una sola incidencia que se quedó abierta un fin de semana
desplaza la media varias horas y hace ilegible el indicador. La mediana describe el caso
típico, que es lo que interesa comparar.

### Fricción — los números incómodos

| Indicador | Qué revela |
|---|---|
| Borradores abandonados | Cuánta gente entra y no llega a reportar |
| Tasa de abandono | Su proporción sobre el total de entradas |

Este es el indicador que **mide el coste del QR genérico**. Si sale alto, es un hallazgo
legítimo de la investigación y se reporta. No es algo que convenga esconder: es
exactamente el tipo de dato que hace creíble un trabajo.

### Integridad del canal

| Indicador | Qué revela |
|---|---|
| Rechazos del antiabuso, por motivo | Si los umbrales están bien puestos |

**Se muestran siempre, incluso cuando son cero.** Si aparecen rechazos por límite de red,
hay que revisar los umbrales: probablemente se esté bloqueando a docentes legítimos que
comparten la WiFi institucional.

### Efecto de las imágenes

| Indicador | Qué responde |
|---|---|
| Resolución en pasos con imagen vs. sin imagen | **¿Las fotos ayudan de verdad?** |

Es una pregunta de investigación propia, y solo se puede responder porque cada respuesta
del docente registra qué imágenes se le mostraron.

### Satisfacción

| Indicador | Qué dice |
|---|---|
| Facilidad percibida (1 a 5) | Cómo lo vivió el docente |

### Calidad de la resolución

| Indicador | Qué revela |
|---|---|
| Reaperturas | Si se está cerrando sin resolver |

Un sistema que cierra rápido y reabre mucho no está resolviendo, está cerrando.

---

## 14.4 Los reportes

Responden una pregunta distinta a la del tablero:

| Herramienta | Pregunta |
|---|---|
| Tablero | ¿Cómo va el servicio? |
| Reportes | **¿Dónde se concentra el problema?** |

La segunda es la que sostiene una decisión de compra, de mantenimiento o de turno.

### Agrupaciones

Periodo · pabellón · aula · tipo de problema · tipo de equipo · técnico · mes.

### El aviso de muestra pequeña

Con menos de treinta incidencias en el periodo, el reporte muestra una advertencia:

> *«Solo hay N incidencias en este periodo. Es muy poco para sacar conclusiones: las
> diferencias entre filas pueden ser casualidad.»*

**Por qué está:** este reporte va a acabar citado en la tesis. Con seis incidencias, «el
50 % en el pabellón C» son tres tickets, y presentar eso como un hallazgo sería un error
que un jurado detecta de inmediato.

---

## 14.5 Exportación del conjunto de datos

El sistema exporta un archivo CSV con una fila por incidencia, para hacer el análisis
estadístico fuera (en R, Python o SPSS).

### Qué se exporta

| Grupo | Variables |
|---|---|
| Identificación | `uuid`, `ticket` |
| Ubicación | `aula`, `pabellon`, `piso`, `criticidad_aula` |
| Clasificación | `categoria`, `prioridad`, `estado`, `bloquea_clase` |
| Resolución | `tipo_resolucion`, `evito_desplazamiento` |
| Tiempos | `confirmado_en`, `reportado_en`, `primera_respuesta_en`, `resuelto_en`, `cerrado_en` |
| Duraciones calculadas | `min_hasta_primera_respuesta`, `min_hasta_resolucion` |
| Diagnóstico | `pasos_diagnostico`, `pasos_con_imagen` |
| Calidad | `reaperturas`, `reportes_adicionales` |
| Percepción | `facilidad_1a5` |
| Cualitativo | `descripcion` |

### Qué NO se exporta, y por qué

| Variable | Motivo |
|---|---|
| `device_key` | Sirve al antiabuso dentro del sistema y no aporta nada al análisis. Sacarlo sería mover identificadores de origen a una hoja de cálculo que después circula por correo. |
| `ip_hash` | Igual. |
| `reporter_hint` | Es un dato de contacto que el docente dio **para que soporte pudiera ubicarlo**, no para acabar en un conjunto de datos. |

### La advertencia sobre la descripción libre

La descripción **sí** se exporta, porque es material de análisis cualitativo. Pero conviene
revisarla antes de compartirla fuera: un docente puede haber escrito su nombre o su número
sin que nadie se lo pidiera.

### La exportación queda auditada

Es un acceso masivo a los datos del piloto: tiene permiso propio (`reports.export`) y cada
descarga deja constancia de quién la hizo y cuándo.

---

## 14.6 Preguntas de investigación que el sistema puede responder

| # | Pregunta | Con qué dato |
|---|---|---|
| 1 | ¿Qué proporción de incidencias se resuelve sin desplazar personal? | Tasa de autonomía |
| 2 | ¿Cuánto se reduce el tiempo de interrupción de clase? | Mediana hasta resolución, comparada con la línea base |
| 3 | ¿Las imágenes mejoran la resolución autónoma? | Comparación de pasos con y sin imagen |
| 4 | ¿Qué coste de fricción tiene el QR genérico? | Tasa de abandono |
| 5 | ¿Qué tipos de problema son más resolubles por el docente? | Autonomía por categoría |
| 6 | ¿Se concentran las incidencias en aulas concretas? | Distribución por aula y pabellón |
| 7 | ¿La señal de riesgo anticipa incidencias? | Score histórico vs. incidencias posteriores |
| 8 | ¿El asistente escala cuando debe? | Confianza registrada vs. desenlace real |
| 9 | ¿Cómo perciben los docentes el sistema? | Encuesta de facilidad |
| 10 | ¿Se cierran los tickets sin resolver de verdad? | Tasa de reapertura |

### Lo que hace falta para responderlas

Casi todas necesitan **una línea base**: cómo era el proceso antes del sistema. Sin ella,
la pregunta 2 no se puede contestar, porque no hay con qué comparar.

Ver [15 · Estado real del piloto](15-estado-real-del-piloto.md).

---

## 14.7 Agregados precalculados

Una tarea nocturna calcula los agregados del día y los guarda.

**Por qué:** para que el tablero no recorra toda la tabla de incidencias en cada carga.
Con el volumen del piloto no haría falta, pero el coste de implementarlo era bajo y el
beneficio crece con los datos.

---

## 14.8 Advertencias metodológicas para la tesis

Estas son las objeciones previsibles y cómo el sistema las aborda:

| Objeción | Respuesta |
|---|---|
| «Los datos podrían ser inventados» | Los datos de demostración están marcados y se purgan antes de medir |
| «El modelo podría haber visto el futuro» | Prevención de fuga temporal verificada por pruebas automáticas |
| «El sistema podría estar mostrando solo lo favorable» | El tablero incluye abandono, rechazos y reaperturas |
| «El score de riesgo se presenta como probabilidad» | Se declara explícitamente que es un ordenamiento, en el código y en la interfaz |
| «Los umbrales se eligieron después de ver los resultados» | Los umbrales de activación de modelos están declarados por adelantado en la configuración |
| «La muestra es pequeña» | El reporte avisa automáticamente cuando lo es |
| «El tiempo se mide desde donde conviene» | Se mide desde la confirmación del aula, que es lo más desfavorable |

---

## 14.9 Resumen para citar en la tesis

> La instrumentación del sistema se diseñó con anterioridad a la recolección. El indicador
> principal es la tasa de resolución autónoma, definida como la proporción de incidencias
> resueltas sin desplazamiento de personal técnico sobre el total de incidencias resueltas.
> Los tiempos se reportan como medianas, dado que la distribución presenta valores extremos
> asociados a incidencias reportadas fuera del horario de atención. El instante de origen
> para toda medición temporal es la confirmación de ubicación por parte del reportante, y
> no la creación del ticket, criterio que incorpora deliberadamente el tiempo invertido en
> el diagnóstico asistido. Los intentos abandonados se excluyen del denominador de las
> métricas de resolución y se reportan de forma independiente como indicador de fricción de
> acceso.

# 16 · Mejoras futuras

> **Qué encuentras aquí:** todo lo que se puede añadir al sistema, de dónde salió cada
> idea, cuánto cuesta y qué problema resuelve. Es el material para decidir el alcance de
> Taller de Investigación 2.

---

## 16.1 Cómo leer esta lista

Cada propuesta lleva **de dónde sale**:

| Marca | Origen |
|---|---|
| 📋 **Plan** | Estaba previsto en el plan original y quedó fuera del alcance de Taller 1 |
| 💡 **Propuesta** | Idea nueva surgida durante el desarrollo |
| 🔍 **Hallazgo** | Detectado revisando el código o probando el sistema |

Y tres valoraciones:

| Campo | Escala |
|---|---|
| **Esfuerzo** | Bajo (días) · Medio (1–2 semanas) · Alto (más de un mes) |
| **Valor para la tesis** | Bajo · Medio · Alto |
| **Depende de** | Qué tiene que existir antes |

> **Ninguna de estas mejoras es necesaria para el piloto.** El sistema está completo para
> lo que se planteó. Esto es material para la siguiente etapa.

---

## 16.2 Prioridad alta — lo que más aporta a la investigación

### 16.2.1 📋 Conjunto de preguntas etiquetado y calibración de umbrales

**Qué es:** un conjunto de 100–200 preguntas reales de docentes, etiquetadas por soporte
con la respuesta correcta y con si el sistema *debía* haber escalado o no.

**Qué resuelve:** hoy los umbrales de confianza del asistente están sin fijar y el sistema
opera en modo conservador. Con este conjunto se pueden calibrar maximizando el acierto de
la decisión de escalar.

**Por qué es lo primero:** es lo único que convierte «el asistente parece funcionar» en una
métrica defendible. Y es un capítulo entero de la tesis.

| Esfuerzo | Valor para la tesis | Depende de |
|---|---|---|
| Medio | **Muy alto** | Que el piloto haya generado preguntas reales |

---

### 16.2.2 📋 Línea base pre-sistema

**Qué es:** medir cómo funcionaba el proceso **antes** del sistema: cuánto tardaba, por qué
canal, qué proporción se resolvía.

**Qué resuelve:** sin esto, la afirmación «el sistema redujo el tiempo de interrupción» no
tiene respaldo. No hay con qué comparar.

**Aviso de oportunidad:** hay que hacerlo **antes** de encender el sistema. Después ya no se
puede observar el proceso anterior.

| Esfuerzo | Valor para la tesis | Depende de |
|---|---|---|
| Medio | **Crítico** | Acceso a docentes y a registros históricos |

---

### 16.2.3 📋 Evaluación del modelo de riesgo contra datos reales

**Qué es:** con historial suficiente, comparar los scores calculados contra lo que
efectivamente ocurrió después, y evaluar si activar los modelos supervisados previstos.

**Qué resuelve:** hoy el módulo predictivo es una línea base declarada como ordenamiento.
Con datos, se puede afirmar algo sobre su capacidad predictiva.

**Ya está preparado:** los umbrales de activación están declarados por adelantado, la
etiqueta supervisada está implementada y probada, y la exportación del vector de
características existe.

| Esfuerzo | Valor para la tesis | Depende de |
|---|---|---|
| Medio | **Alto** | 200+ observaciones con 40+ positivos |

---

### 16.2.4 💡 Pruebas de navegador automatizadas

**Qué es:** pruebas que abren el sistema en un navegador real y comprueban que las
pantallas funcionan.

**Qué resuelve:** hoy nada detecta un fallo visual. La carpeta `tests/Browser` existe y
está vacía. Durante el desarrollo se encontraron tres errores que las pruebas no veían
—política de seguridad bloqueando recursos, un efecto visual que no se aplicaba, un
redirección incorrecta— y todos aparecieron solo al abrir el navegador.

| Esfuerzo | Valor para la tesis | Depende de |
|---|---|---|
| Medio | Bajo | — |

---

## 16.3 Prioridad media — mejoras funcionales con impacto real

### 16.3.1 💡 Modo sin conexión para el docente

**Qué es:** que la aplicación funcione aunque el aula no tenga señal, guardando el reporte
y enviándolo al recuperar conexión.

**Qué resuelve:** un aula sin cobertura es exactamente un aula donde el docente no puede
reportar, y probablemente una de las que más problemas tiene.

| Esfuerzo | Valor | Depende de |
|---|---|---|
| Alto | Alto en la práctica, medio para la tesis | — |

---

### 16.3.2 💡 Aviso por correo a soporte

**Qué es:** además del aviso en el panel, un correo cuando entra una incidencia crítica.

**Qué resuelve:** hoy el aviso solo existe dentro del panel. Si nadie lo tiene abierto, una
incidencia crítica espera.

**Por qué no se hizo:** el aviso en el panel cubre el caso normal y no exige configurar un
servidor de correo. Pero para incidencias críticas el retraso sí importa.

| Esfuerzo | Valor | Depende de |
|---|---|---|
| Bajo | Medio | Servidor de correo institucional |

---

### 16.3.3 🔍 Panel específico para el técnico en movimiento

**Qué es:** una vista pensada para el celular, con solo lo que necesita quien está caminando
hacia un aula: qué lleva, qué falló antes ahí, botón de «llegué».

**Qué resuelve:** hoy el panel está pensado para una pantalla de escritorio. El técnico lo
va a abrir en el celular.

**Por qué es un hallazgo:** el sistema registra `arrived_at` (cuándo llegó el técnico) pero
**no hay ninguna pantalla que lo permita registrar cómodamente**. Ese dato alimenta una de
las métricas de tiempo y hoy es difícil de capturar.

| Esfuerzo | Valor | Depende de |
|---|---|---|
| Medio | Medio | — |

---

### 16.3.4 💡 Sugerencia automática de solución al técnico

**Qué es:** cuando el técnico abre un ticket, que el sistema le muestre las soluciones
anteriores más parecidas.

**Qué resuelve:** hoy el historial del aula muestra qué pasó en **esa** aula. Esto mostraría
qué pasó en **casos parecidos**, en cualquier aula.

**Está casi hecho:** la búsqueda híbrida ya existe. Sería aplicarla sobre las notas de
resolución en lugar de sobre los documentos.

| Esfuerzo | Valor | Depende de |
|---|---|---|
| Bajo | Alto | Historial de resoluciones |

---

### 16.3.5 📋 Mantenimiento preventivo a partir del riesgo

**Qué es:** que las aulas en banda alta generen automáticamente una tarea de revisión
preventiva.

**Qué resuelve:** hoy el score se calcula y se muestra, pero **no dispara ninguna acción**.
Cierra el ciclo entre predecir y actuar.

| Esfuerzo | Valor | Depende de |
|---|---|---|
| Medio | Alto | Que el modelo demuestre capacidad predictiva |

---

### 16.3.6 💡 Reconocimiento del equipo por foto

**Qué es:** que el docente fotografíe el equipo y el sistema identifique el modelo y cargue
el procedimiento correcto.

**Qué resuelve:** elimina pasos de la cascada y evita errores de identificación.

**Por qué es ambicioso:** exige un modelo de visión y un conjunto de fotos etiquetadas de
los equipos reales. Sería un trabajo de investigación por sí solo.

| Esfuerzo | Valor | Depende de |
|---|---|---|
| Alto | Alto como contribución original | Inventario fotografiado |

---

## 16.4 Prioridad baja — mejoras de calidad

### 16.4.1 🔍 Pruebas contra el modelo real

**Qué es:** una suite que corra contra Ollama de verdad, no contra el proveedor de mentira.

**Qué resuelve:** hoy un cambio de modelo podría degradar la clasificación sin que nada
avise. La suite de vocabulario peruano protege el prompt, pero no el modelo.

| Esfuerzo | Valor | Depende de |
|---|---|---|
| Bajo | Medio | — |

---

### 16.4.2 💡 Verificación semántica de la respuesta

**Qué es:** sustituir el verificador léxico actual por uno que compruebe el significado.

**Qué resuelve:** el verificador actual no detecta una respuesta que use las palabras
correctas y las combine mal («desconecta el cable HDMI para que aparezca la imagen»).

**Está declarado como límite conocido** en la documentación del sistema.

| Esfuerzo | Valor | Depende de |
|---|---|---|
| Medio | Medio | — |

---

### 16.4.3 💡 Accesibilidad verificada

**Qué es:** auditoría con lector de pantalla y contraste, y pruebas automatizadas.

**Qué resuelve:** el sistema se diseñó con botones grandes y buen contraste, pero **no se
ha verificado** con herramientas de accesibilidad.

| Esfuerzo | Valor | Depende de |
|---|---|---|
| Bajo | Medio | — |

---

### 16.4.4 💡 Multisede

**Qué es:** ampliar el piloto a otras sedes.

**Qué resuelve:** nada urgente. La estructura de datos **ya lo soporta** —hay una tabla de
sedes y toda la cascada la respeta—, así que técnicamente es abrir el alcance, no
programar.

| Esfuerzo | Valor | Depende de |
|---|---|---|
| Bajo | Bajo | Éxito del piloto en Huancayo |

---

### 16.4.5 💡 Integración con la mesa de ayuda institucional

**Qué es:** que los tickets se sincronicen con el sistema corporativo.

**Qué resuelve:** evita que soporte trabaje en dos sistemas.

**Por qué no se hizo:** exige acceso a una API institucional que no está disponible para un
trabajo de investigación.

| Esfuerzo | Valor | Depende de |
|---|---|---|
| Alto | Bajo | Autorización y API institucional |

---

## 16.5 Mejoras de interfaz y experiencia

| # | Mejora | Origen | Esfuerzo |
|---|---|---|---|
| 1 | **Recordar el aula en el mismo dispositivo** — quien reportó ayer en C305 probablemente esté hoy en C305 | 💡 | Bajo |
| 2 | **Búsqueda por voz** — el docente dicta el problema en vez de escribirlo | 💡 | Medio |
| 3 | **Indicador de progreso en el diagnóstico** — «paso 2 de 5», para que no se sienta infinito | 💡 | Bajo |
| 4 | **Vista de impresión del ticket** — para dejar constancia física en el aula | 💡 | Bajo |
| 5 | **Filtros guardados en el panel** — que cada técnico conserve su vista | 💡 | Bajo |
| 6 | **Gráficos en el tablero** — hoy son cifras y tablas | 💡 | Medio |
| 7 | **Modo oscuro automático por hora** | 💡 | Bajo |
| 8 | **Atajos de teclado en el panel** — para quien lo usa todo el día | 💡 | Bajo |

De estas, la **1** y la **3** son las que más impacto tienen sobre el indicador de
abandono, que es una métrica de la investigación.

---

## 16.6 Deuda técnica identificada

Cosas que funcionan pero que convendría revisar:

| # | Qué | Por qué importa | Esfuerzo |
|---|---|---|---|
| 1 | 🔍 `tests/Browser` está vacía | Ninguna prueba cubre la interfaz | Medio |
| 2 | 🔍 El coseno se calcula en PHP | Deja de escalar con un corpus grande | Alto |
| 3 | 🔍 Los estilos incrustados están permitidos en la política de seguridad | Es una excepción declarada por el cartel imprimible | Medio |
| 4 | 🔍 La tabla de sesiones guarda la IP en claro | Es del framework; está declarado como excepción conocida | Bajo |
| 5 | 🔍 El estimador de tokens es aproximado | Solo decide dónde cortar; no afecta a nada crítico | Bajo |
| 6 | 🔍 No hay respaldo automático configurado | Perder los datos del piloto sería perder el semestre | Bajo |

**El 6 es el más urgente de todos**, y el más barato.

---

## 16.7 Recomendación de alcance para Taller de Investigación 2

Si hubiera que elegir, este es el conjunto que produce una tesis más sólida:

| Orden | Qué | Por qué |
|---|---|---|
| 1 | **Línea base pre-sistema** | Sin ella no se puede afirmar ninguna mejora. Y hay que hacerla antes de encender el sistema |
| 2 | **Ejecutar el piloto y recoger datos** | Es el objeto del trabajo |
| 3 | **Conjunto etiquetado y calibración de umbrales** | Convierte el asistente en algo medible |
| 4 | **Evaluación del modelo de riesgo** | Cierra el componente predictivo |
| 5 | **Respaldo automático** | Barato y evita un desastre |
| 6 | **Sugerencia de solución al técnico** | Barato, ya está casi hecho, y aporta valor visible |

Lo demás es mejora de producto, no de investigación. Un trabajo con esos seis puntos
resueltos tiene resultados, método verificable y una contribución defendible.

---

## 16.8 Lo que deliberadamente NO se recomienda

| Idea | Por qué no |
|---|---|
| Migrar a microservicios | Resuelve problemas de escala que este proyecto no tiene, a cambio de complejidad operativa que sí tendría |
| Cambiar a un modelo de IA en la nube | Reintroduce coste y dependencia externa; contradice una restricción del proyecto |
| Añadir autenticación para el docente | Es exactamente la fricción que el sistema existe para eliminar |
| Dejar que la IA genere los pasos del diagnóstico | Compromete la seguridad física y destruye la validez metodológica del piloto |
| Presentar el score de riesgo como probabilidad | No está calibrado; sería inventar precisión |
| Ampliar a incidencias no tecnológicas | Diluye el indicador central y mezcla flujos de resolución distintos |

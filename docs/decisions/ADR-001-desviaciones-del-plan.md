# ADR-001 — Desviaciones del plan detectadas al implementar

**Fecha:** 2026-08-26
**Estado:** aceptadas
**Contexto:** primeras fases de implementación (Fase 1 y parte de la Fase 2).

Al llevar el plan maestro a código aparecieron cinco diferencias respecto de lo escrito. Ninguna cambia el diseño; todas se documentan aquí para que el equipo no las redescubra y para poder sustentarlas si se preguntan en la revisión.

---

## D-001-1 · PHP 8.2.12, no 8.3+ → Laravel 12, no 11 ni 13

**Qué decía el plan:** PHP 8.3+, Laravel 11.x.

**Qué se encontró:** el XAMPP instalado trae **PHP 8.2.12**. El esqueleto actual de Laravel (13.x) exige PHP ^8.3.

**Qué se hizo:** fijar **Laravel 12.68.0**, la última rama que soporta PHP 8.2.

**Por qué no se actualizó PHP:** actualizar el PHP del XAMPP habría cambiado el entorno de todos los proyectos del equipo y roto la paridad con la máquina de Brayan — que es justamente lo que la decisión D-5 buscaba proteger. Fijar la versión del framework es reversible y local; cambiar el runtime del sistema no lo es.

---

## D-001-2 · Livewire 4, no Livewire 3

**Qué decía el plan:** Livewire 3.x.

**Qué se encontró:** Composer resolvió **Livewire 4.4.2**, compatible con Laravel 12 y PHP 8.2.

**Impacto:** ninguno sobre la arquitectura. Afecta a la sintaxis de los componentes del panel de soporte, que aún no están escritos. Se documenta para que el equipo consulte la documentación de la versión correcta y no tutoriales de la 3.

---

## D-001-3 · MariaDB 10.4 no tiene tipo `VECTOR` — supuesto S8 confirmado

**Qué decía el plan:** verificar si la versión de MariaDB del XAMPP incluye tipo `VECTOR` nativo (disponible desde 11.7).

**Qué se encontró:** **MariaDB 10.4.32**. No lo tiene.

**Consecuencia:** se confirma la ruta ya prevista en el plan §10.2 — embeddings almacenados como JSON, prefiltrado en SQL y similitud coseno calculada en PHP sobre el conjunto candidato, con `embedding_norm` precalculada.

**Esto no es un contratiempo:** el plan ya había elegido esa ruta y diseñado el adaptador `VectorStore` para poder cambiarla. La verificación solo cierra el supuesto S8.

---

## D-001-4 · MySQL/MariaDB no soporta índices parciales

**Qué decía el plan (§11.4):** un índice parcial `WHERE status_id IN (activos)` sobre `incidents`.

**Qué se encontró:** los índices parciales son sintaxis de PostgreSQL. Quedó en el plan como residuo del diseño anterior a la decisión D-1.

**Qué se hizo:** se sustituyó por índices compuestos que sirven las mismas consultas: `(status_id, created_at)` para la bandeja y `(room_id, category_id, status_id)` para el control antiduplicados.

**Impacto en rendimiento:** despreciable al volumen del piloto.

---

## D-001-5 · `env()` fuera del directorio `config` — bug real, no advertencia de estilo

**Qué se encontró:** la guarda que impide sembrar datos DEMO en producción usaba `env('ALLOW_DEMO_SEED')` dentro de un seeder.

**Por qué era un bug:** con la configuración cacheada (`php artisan config:cache`, que es lo normal en producción), `env()` devuelve `null` fuera de `config/`. La guarda se habría evaluado siempre como falsa y **el seeder demo habría corrido igual en el servidor del piloto**, contaminando los datos de la investigación con aulas ficticias.

**Qué se hizo:** el valor se movió a `config/incidencias.php` y el seeder lee `config('incidencias.allow_demo_seed')`.

**Lección:** lo detectó el análisis estático, no una prueba. Vale la pena mantener PHPStan en el pipeline desde el principio.

---

## Hallazgo adicional (no es desviación)

La tabla de sistema `mysql.db` del XAMPP tenía el índice **corrupto** de antes de este proyecto (`Table is marked as crashed`). Impedía otorgar permisos a cualquier usuario nuevo. Se reparó con `REPAIR TABLE`. Habría bloqueado a cualquiera del equipo al configurar su entorno.

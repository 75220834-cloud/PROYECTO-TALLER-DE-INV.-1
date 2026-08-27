<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Suites de prueba (plan 17)
|--------------------------------------------------------------------------
| Unit        - reglas y calculos puros
| Feature     - endpoints HTTP y permisos
| Integration - base de datos, IA, recuperacion, notificaciones
| Ai          - clasificacion, confianza y ALUCINACIONES
| Retrieval   - busqueda hibrida (ver nota abajo)
| Risk        - modelo predictivo, con verificacion de fuga temporal
| Browser     - flujos E2E
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Integration', 'Ai', 'Risk');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Recuperacion: TRUNCADO en lugar de transaccion
|--------------------------------------------------------------------------
| InnoDB NO actualiza el indice FULLTEXT hasta que la transaccion hace
| commit. Con RefreshDatabase —que envuelve cada prueba en una transaccion
| que nunca se confirma— las filas insertadas durante la prueba son
| invisibles para MATCH ... AGAINST, y la mitad lexica de la busqueda
| hibrida quedaria SIN PROBAR sin que ninguna prueba fallara.
|
| Ese es justo el tipo de agujero que las pruebas existen para evitar: todo
| pasaba en verde apoyandose solo en la via semantica.
|
| Truncar es mas lento, y aqui esa lentitud esta justificada.
*/
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Retrieval');

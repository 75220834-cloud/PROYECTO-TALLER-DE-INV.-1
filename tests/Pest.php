<?php

declare(strict_types=1);

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
| Risk        - modelo predictivo, con verificacion de fuga temporal
| Browser     - flujos E2E
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Integration', 'Ai', 'Risk');

pest()->extend(TestCase::class)->in('Unit');

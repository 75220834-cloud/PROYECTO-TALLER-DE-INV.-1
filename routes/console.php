<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
| Todas de madrugada: recorren el historial completo y no hay razon para
| hacerlo mientras un docente esta reportando una incidencia.
|
| Requiere que alguien ejecute `php artisan schedule:work` (desarrollo) o una
| entrada de cron / Programador de tareas (servidor). Sin eso estas tareas
| simplemente no corren, y conviene saberlo: el tablero no avisa de que sus
| agregados estan viejos.
*/

// Los borradores que nadie promovio no son incidencias: son abandonos. Se
// purgan para que no ensucien el historial, pero antes ya fueron contados
// como fricción de acceso (plan 26.bis).
Schedule::command('incidents:purge-drafts')->hourly();

Schedule::command('metrics:snapshot')->dailyAt('02:30');

Schedule::command('risk:compute')->dailyAt('03:00');

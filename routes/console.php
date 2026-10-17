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

// Antes que las demas: si alguna falla a medias, la copia ya esta hecha.
Schedule::command('respaldo:crear')->dailyAt('01:30');

// La cola, procesada por el mismo programador. Solo la usa la indexacion
// de documentos, que no tiene prisa: un minuto de espera no se nota. A
// cambio, en la PC de soporte basta UNA tarea programada de Windows en
// lugar de dos procesos que alguien tiene que mantener vivos.
//
// --stop-when-empty: termina al vaciar la cola, y el minuto siguiente
// vuelve a empezar. withoutOverlapping: si indexar un documento largo
// dura mas de un minuto, no se lanza un segundo trabajador encima.
Schedule::command('queue:work --stop-when-empty')
    ->everyMinute()
    ->withoutOverlapping(15);

<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Estado de un equipo, en lenguaje del panel
|--------------------------------------------------------------------------
| Las CLAVES son los valores del enum de la columna y no cambian: son lo
| que consultan las metricas. Estos textos solo traducen.
*/

return [
    'operational' => 'Operativo',
    'degraded' => 'Con fallas',
    'out_of_service' => 'Fuera de servicio',
    'in_maintenance' => 'En mantenimiento',
];

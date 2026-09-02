<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Como se cerro una incidencia
|--------------------------------------------------------------------------
| La distincion entre "assistant" y "remote" importa para la investigacion:
| las dos evitaron un desplazamiento, pero solo la primera la resolvio el
| docente sin intervencion de nadie. Es el indicador central del proyecto.
*/

return [
    'assistant' => 'La resolvió el docente',
    'remote' => 'Resuelta en remoto',
    'onsite' => 'Requirió ir al aula',
    'none' => 'Sin resolver',
];

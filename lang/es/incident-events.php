<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Nombres legibles de los eventos del historial
|--------------------------------------------------------------------------
| Los CODIGOS (las claves) son los que guarda incident_events y no cambian
| nunca: son lo que consultan las metricas de la investigacion. Estos
| textos son solo la traduccion para el panel, y se pueden reescribir sin
| tocar un solo dato.
*/

return [
    'location_confirmed' => 'El docente confirmó la ubicación',
    'problem_reported' => 'El docente indicó el problema',
    'status_changed' => 'Cambio de estado',
    'resolved_by_assistant' => 'Resuelto por el propio docente',
    'escalated_to_support' => 'Se solicitó apoyo presencial',
    'merged_into_existing' => 'Se sumó a una solicitud existente',
    'additional_report_received' => 'Otra persona reportó lo mismo',
    'assigned' => 'Asignado a un técnico',
    'reassigned' => 'Reasignado',
    'resolved' => 'Resuelto por soporte',
    'closed' => 'Cerrado',
    'reopened' => 'Reabierto',
    'cancelled' => 'Cancelado',
];

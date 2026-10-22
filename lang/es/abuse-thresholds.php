<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Umbrales antiabuso, en palabras
|--------------------------------------------------------------------------
| Cada umbral con su frase y la variable del .env que lo cambia.
*/

return [
    'duplicate_window_minutes' => ['Minutos en que una solicitud igual cuenta como repetida', 'ABUSE_DUPLICATE_WINDOW_MINUTES'],
    'max_active_per_room' => ['Solicitudes abiertas como máximo por aula', 'ABUSE_MAX_ACTIVE_PER_ROOM'],
    'max_tickets_per_device_hour' => ['Solicitudes por hora como máximo desde un mismo celular', 'ABUSE_MAX_TICKETS_PER_DEVICE_HOUR'],
    'max_tickets_per_ip_hour' => ['Solicitudes por hora como máximo desde una misma red', 'ABUSE_MAX_TICKETS_PER_IP_HOUR'],
    'min_form_seconds' => ['Segundos mínimos para llenar el formulario', 'ABUSE_MIN_FORM_SECONDS'],
    'similarity_threshold' => ['Parecido desde el que dos descripciones cuentan como iguales (0 a 1)', 'ABUSE_SIMILARITY_THRESHOLD'],
    'draft_purge_hours' => ['Horas tras las que un reporte sin terminar se da por abandonado', 'DRAFT_PURGE_HOURS'],
];

<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Motivos de rechazo del antiabuso, en lenguaje del panel
|--------------------------------------------------------------------------
| Las CLAVES son los codigos que guarda incident_abuse_rejections y no
| cambian: son lo que consultan las metricas. Estos textos solo traducen.
*/

return [
    'duplicate' => 'Ya había una solicitud igual abierta',
    'room_active_limit' => 'El aula llegó a su tope de solicitudes abiertas',
    'device_rate' => 'Demasiadas solicitudes desde el mismo dispositivo',
    'ip_rate' => 'Demasiadas solicitudes desde la misma red',
    'similarity' => 'Descripción muy parecida a otra reciente',
    'bot_signal' => 'Señales de envío automatizado',
    'unconfirmed' => 'Se envió sin confirmar',
];

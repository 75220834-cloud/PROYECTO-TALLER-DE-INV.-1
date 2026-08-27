<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Events;

use App\Modules\Incidents\Models\Incident;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Una incidencia se convirtio en ticket y soporte debe enterarse.
 *
 * Es un EVENTO y no una llamada directa a "enviar notificacion" porque el
 * plan exige que la notificacion sea desacoplada (plan 14): hoy solo
 * escribe en el panel interno, pero manana podra sumarse correo o tiempo
 * real sin tocar una sola linea del modulo de incidencias.
 */
final class IncidentEscalated
{
    use Dispatchable;

    public function __construct(public readonly Incident $incident) {}
}

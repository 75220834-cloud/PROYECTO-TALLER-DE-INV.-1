<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Listeners;

use App\Modules\Incidents\Events\IncidentEscalated;
use App\Modules\Notifications\Services\NotificationDispatcher;

/**
 * Avisa a soporte cuando una incidencia se convierte en ticket.
 *
 * El aviso lleva YA todo lo que el tecnico necesita para decidir si va y
 * con que prioridad: aula, categoria y si la clase esta detenida. El
 * objetivo del plan es que soporte no tenga que volver a preguntar nada de
 * lo que el sistema ya sabe (plan 45).
 */
final class NotifySupportOfEscalation
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function handle(IncidentEscalated $event): void
    {
        $incident = $event->incident->loadMissing(['room.floor.building', 'category', 'priority']);

        $this->dispatcher->dispatch(
            $this->dispatcher->supportStaff(),
            'incident.escalated',
            [
                'incident_uuid' => $incident->uuid,
                'ticket_number' => $incident->ticket_number,
                'room_code' => $incident->room?->code,
                'location' => $incident->room?->fullPath(),
                'category' => $incident->category?->name,
                'priority' => $incident->priority?->name,
                'blocks_class' => $incident->blocks_class,
                'reported_at' => $incident->reported_at?->toIso8601String(),
            ],
        );
    }
}

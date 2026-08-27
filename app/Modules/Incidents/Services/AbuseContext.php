<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Services;

use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Locations\Models\Room;

/**
 * Datos que la guarda antiabuso necesita para decidir.
 *
 * Se pasa un objeto en lugar de siete argumentos sueltos porque el orden
 * de siete parametros del mismo tipo es una fuente segura de errores
 * silenciosos: intercambiar deviceKey e ipHash compilaria sin problema y
 * rompferia dos controles a la vez sin que nada fallara visiblemente.
 */
final readonly class AbuseContext
{
    public function __construct(
        public Room $room,
        public ?IncidentCategory $category,
        public ?string $deviceKey,
        public ?string $ipHash,
        public ?string $description = null,
        public bool $confirmed = false,

        /** Segundos entre que se cargo el formulario y se envio. */
        public ?int $formElapsedSeconds = null,

        /** Campo trampa: solo lo rellenan los bots. */
        public ?string $honeypot = null,
    ) {}
}

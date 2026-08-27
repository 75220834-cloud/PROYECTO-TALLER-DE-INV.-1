<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Services;

use App\Shared\Enums\IncidentPriority;

/**
 * Prioridad calculada junto con los factores que la produjeron.
 *
 * Los factores no son decorativos: se guardan en el historial del ticket
 * para que un tecnico pueda ver por que este esta por encima de aquel. Una
 * prioridad sin explicacion se percibe como arbitraria y acaba ignorandose.
 */
final readonly class PriorityResult
{
    /**
     * @param  list<string>  $factors
     */
    public function __construct(
        public IncidentPriority $priority,
        public array $factors,
    ) {}

    public function explanation(): string
    {
        return implode(' · ', $this->factors);
    }
}

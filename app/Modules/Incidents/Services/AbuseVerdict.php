<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Services;

use App\Modules\Incidents\Models\Incident;
use App\Shared\Enums\AbuseReason;

/**
 * Resultado de la guarda antiabuso.
 *
 * Un rechazo SIEMPRE lleva un mensaje comprensible y, cuando existe, el
 * ticket al que el docente puede sumarse. El plan lo exige de forma
 * explicita: un docente con un proyector averiado y un mensaje de error
 * sin salida es un fracaso del sistema, no una defensa (plan 16.4).
 */
final readonly class AbuseVerdict
{
    private function __construct(
        public bool $allowed,
        public ?AbuseReason $reason = null,
        public ?Incident $relatedIncident = null,

        /** Marcado para revision manual, pero NO bloqueado. */
        public bool $flagged = false,
    ) {}

    public static function allow(bool $flagged = false): self
    {
        return new self(allowed: true, flagged: $flagged);
    }

    public static function reject(AbuseReason $reason, ?Incident $related = null): self
    {
        return new self(allowed: false, reason: $reason, relatedIncident: $related);
    }

    public function teacherMessage(): ?string
    {
        return $this->reason?->teacherMessage();
    }

    /** Si se le puede ofrecer sumarse a un ticket ya abierto. */
    public function canJoinExisting(): bool
    {
        return $this->relatedIncident !== null && $this->reason?->offersJoin() === true;
    }
}

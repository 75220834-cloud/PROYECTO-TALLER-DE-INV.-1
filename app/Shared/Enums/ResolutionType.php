<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Como se resolvio la incidencia.
 *
 * Es el indicador central de la investigacion: la proporcion de
 * incidencias resueltas por Assistant frente a las que exigieron Onsite es
 * exactamente lo que el proyecto pretende mover (plan 26.bis).
 */
enum ResolutionType: string
{
    /** El docente lo resolvio siguiendo el diagnostico guiado. */
    case Assistant = 'assistant';

    /** Requirio que un tecnico se desplazara al aula. */
    case Onsite = 'onsite';

    /** Soporte lo resolvio sin desplazarse. */
    case Remote = 'remote';

    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Assistant => 'Resuelta por asistencia',
            self::Onsite => 'Atención presencial',
            self::Remote => 'Resuelta en remoto',
            self::None => 'Sin resolución',
        };
    }

    /** Evito un desplazamiento de soporte. */
    public function avoidedTrip(): bool
    {
        return in_array($this, [self::Assistant, self::Remote], true);
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Estados del ciclo de vida de una incidencia (plan 7.2).
 *
 * Estos son los CODIGOS, no los nombres. El catalogo `incident_statuses`
 * guarda el nombre que ve el usuario y el administrador puede editarlo;
 * este enum guarda lo que compara la logica y no cambia nunca. Esa
 * separacion es lo que permite que los estados sean "configurables" sin
 * que renombrar uno rompa la maquina de estados (plan 11.1).
 */
enum IncidentStatus: string
{
    /** Ubicacion confirmada. No es un ticket: soporte no lo ve. */
    case Draft = 'DRAFT';

    /** El docente esta ejecutando el diagnostico guiado. */
    case Diagnosing = 'DIAGNOSING';

    /** Ticket creado y a la espera de que soporte lo tome. */
    case New = 'NEW';

    case InProgress = 'IN_PROGRESS';

    /** Requiere un tercero (proveedor, otra area) para avanzar. */
    case Escalated = 'ESCALATED';

    case Resolved = 'RESOLVED';

    case Closed = 'CLOSED';

    case Cancelled = 'CANCELLED';

    /**
     * Cuenta como abierta. Es lo que consultan el tope antiabuso por aula
     * y la bandeja de soporte.
     *
     * DRAFT queda FUERA a proposito: existe para medir abandonos, no para
     * ocupar un cupo. Si contara, un docente que entra y no completa
     * bloquearia a quien si tiene un problema real.
     */
    public function isOpen(): bool
    {
        return in_array($this, [
            self::Diagnosing, self::New, self::InProgress, self::Escalated,
        ], true);
    }

    /** Visible en la bandeja de soporte. */
    public function isVisibleToSupport(): bool
    {
        return ! in_array($this, [self::Draft, self::Diagnosing], true);
    }

    public function isResolved(): bool
    {
        return in_array($this, [self::Resolved, self::Closed], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Closed, self::Cancelled], true);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Incidents\StateMachine;

use App\Modules\Incidents\Exceptions\InvalidTransitionException;
use App\Shared\Enums\IncidentStatus as S;

/**
 * Maquina de estados de la incidencia (plan 7.2).
 *
 * Las transiciones se declaran aqui una sola vez y NADIE cambia un estado
 * sin pasar por esta clase. Es lo que impide que una incidencia salte de
 * "cerrada" a "en diagnostico", o que un ticket resuelto vuelva a la
 * bandeja sin dejar rastro.
 *
 * Importa para la investigacion tanto como para la correccion: si los
 * estados pudieran cambiar en cualquier orden, las marcas de tiempo
 * dejarian de ser interpretables y los indicadores de tiempo de atencion
 * medirian ruido.
 */
final class IncidentStateMachine
{
    /**
     * Transiciones permitidas: estado actual => estados alcanzables.
     *
     * @var array<string, list<S>>
     */
    private const TRANSITIONS = [
        // El borrador solo avanza al diagnostico o se abandona (lo purga
        // una tarea programada, no una transicion).
        S::Draft->value => [S::Diagnosing, S::Cancelled],

        // Desde el diagnostico: el docente lo resolvio solo, o escala.
        S::Diagnosing->value => [S::Resolved, S::New, S::Cancelled],

        S::New->value => [S::InProgress, S::Cancelled],

        S::InProgress->value => [S::Escalated, S::Resolved, S::Cancelled],

        // Escalado a un tercero: vuelve a atencion o se resuelve.
        S::Escalated->value => [S::InProgress, S::Resolved, S::Cancelled],

        // Resuelta -> cerrada, o reapertura si el problema persiste.
        S::Resolved->value => [S::Closed, S::InProgress],

        // Cerrada admite reapertura dentro de la ventana permitida.
        S::Closed->value => [S::InProgress],

        // Cancelada es definitiva.
        S::Cancelled->value => [],
    ];

    /** @return list<S> */
    public function allowedFrom(S $current): array
    {
        // Sin ?? []: el array cubre todos los casos del enum. Dejar un
        // valor por defecto ocultaria un fallo real si manana se anade un
        // estado y se olvida declarar sus transiciones.
        return self::TRANSITIONS[$current->value];
    }

    public function canTransition(S $from, S $to): bool
    {
        return in_array($to, $this->allowedFrom($from), true);
    }

    /**
     * @throws InvalidTransitionException
     */
    public function assertCanTransition(S $from, S $to): void
    {
        if ($from === $to) {
            throw InvalidTransitionException::sameState($from);
        }

        if (! $this->canTransition($from, $to)) {
            throw InvalidTransitionException::notAllowed($from, $to, $this->allowedFrom($from));
        }
    }

    /**
     * Reabrir tiene su propia comprobacion porque no es solo una
     * transicion: pasada la ventana, un problema "igual" casi siempre es
     * uno nuevo. Reabrir el viejo falsearia el tiempo de resolucion (se
     * contaria desde el reporte original) y ocultaria una recurrencia que
     * el modulo de riesgo necesita ver como dos eventos, no como uno.
     */
    public function canReopen(S $current, ?\DateTimeInterface $closedAt, int $windowDays): bool
    {
        if (! in_array($current, [S::Resolved, S::Closed], true)) {
            return false;
        }

        if ($current === S::Resolved || $closedAt === null) {
            return true;
        }

        return $closedAt >= now()->subDays($windowDays);
    }
}

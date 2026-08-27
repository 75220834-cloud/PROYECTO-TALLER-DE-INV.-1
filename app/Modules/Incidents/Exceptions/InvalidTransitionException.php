<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Exceptions;

use App\Shared\Enums\IncidentStatus as S;
use RuntimeException;

/**
 * Transicion de estado no permitida.
 *
 * El mensaje enumera los estados que SI eran alcanzables. Un
 * "transicion invalida" a secas obliga a abrir la maquina de estados para
 * entender que se podia hacer; incluir las opciones convierte el error en
 * la respuesta.
 */
final class InvalidTransitionException extends RuntimeException
{
    /**
     * @param  list<S>  $allowed
     */
    public static function notAllowed(S $from, S $to, array $allowed): self
    {
        $options = $allowed === []
            ? 'ninguno (es un estado final)'
            : implode(', ', array_map(fn (S $s) => $s->value, $allowed));

        return new self(
            "No se puede pasar de {$from->value} a {$to->value}. Estados posibles: {$options}."
        );
    }

    public static function sameState(S $state): self
    {
        return new self("La incidencia ya está en el estado {$state->value}.");
    }

    public static function reopenWindowExpired(int $windowDays): self
    {
        return new self(
            "La ventana de reapertura ({$windowDays} días) ya venció. Registra una incidencia nueva."
        );
    }
}

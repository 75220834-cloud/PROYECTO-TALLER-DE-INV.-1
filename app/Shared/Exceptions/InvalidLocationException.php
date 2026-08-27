<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use RuntimeException;

/**
 * Ubicacion invalida en la cascada sede -> pabellon -> piso -> aula.
 *
 * Lleva DOS mensajes distintos a proposito:
 *
 *  - getMessage(): tecnico, va al log y a las pruebas.
 *  - teacherMessage(): en lenguaje llano, es lo unico que ve el docente.
 *
 * La separacion no es cosmetica. Mostrar "El aula no pertenece al piso
 * indicado" a un docente con una clase esperando no le dice que hacer, y
 * ademas revela como esta estructurado el catalogo por dentro. El docente
 * necesita una salida ("vuelve a elegir el aula"), no un diagnostico.
 */
final class InvalidLocationException extends RuntimeException
{
    private function __construct(
        string $message,
        private readonly string $teacherMessage,
        private readonly string $reason,
    ) {
        parent::__construct($message);
    }

    public static function roomNotFound(int $roomId): self
    {
        return new self(
            "El aula {$roomId} no existe en el catalogo.",
            'No encontramos esa aula. Vuelve a elegirla, por favor.',
            'room_not_found',
        );
    }

    public static function mismatch(string $detail): self
    {
        return new self(
            $detail,
            'Esa combinación de pabellón, piso y aula no existe. Vuelve a elegir, por favor.',
            'cascade_mismatch',
        );
    }

    public static function inactive(string $detail): self
    {
        return new self(
            $detail,
            'Esta aula no está disponible para reportes ahora mismo. Avisa a soporte por el canal habitual.',
            'inactive',
        );
    }

    public static function brokenChain(int $roomId): self
    {
        return new self(
            "La cadena de ubicacion del aula {$roomId} esta incompleta.",
            'Hubo un problema con los datos de esa aula. Vuelve a elegirla, por favor.',
            'broken_chain',
        );
    }

    public function teacherMessage(): string
    {
        return $this->teacherMessage;
    }

    /** Motivo estable para logs y metricas; no depende del texto mostrado. */
    public function reason(): string
    {
        return $this->reason;
    }
}

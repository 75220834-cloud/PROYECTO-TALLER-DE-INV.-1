<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Motivos por los que se rechaza la creacion de un ticket (plan 16.4).
 *
 * Se registran TODOS. No es burocracia: si los umbrales estan rechazando
 * solicitudes legitimas, tiene que verse en una tabla, no descubrirse por
 * una queja de un docente.
 */
enum AbuseReason: string
{
    case Duplicate = 'duplicate';
    case RoomActiveLimit = 'room_active_limit';
    case DeviceRate = 'device_rate';
    case IpRate = 'ip_rate';
    case Similarity = 'similarity';
    case BotSignal = 'bot_signal';
    case Unconfirmed = 'unconfirmed';

    /**
     * Mensaje para el docente. Nunca es un callejon sin salida: siempre
     * explica que pasa y que puede hacer.
     */
    public function teacherMessage(): string
    {
        return match ($this) {
            self::Duplicate => 'Ya hay una solicitud abierta para este mismo problema en esta aula. Puedes sumarte a ella.',
            self::RoomActiveLimit => 'Esta aula ya tiene varias solicitudes abiertas y soporte está al tanto. Puedes sumarte a una de ellas.',
            self::DeviceRate => 'Enviaste varias solicitudes en la última hora. Espera unos minutos antes de enviar otra. Si es urgente, llama a soporte.',
            self::IpRate => 'Se recibieron muchas solicitudes desde esta red en poco tiempo. Espera unos minutos y vuelve a intentarlo.',
            self::Similarity => 'Esta solicitud se parece mucho a otra que acabas de enviar. Revisa si ya la mandaste.',

            /*
             * El mensaje de «no humano» decía solo «no pudimos procesar la
             * solicitud», que es un callejón sin salida: el docente no sabe
             * qué hizo mal ni qué hacer. El plan (16.4) exige justo lo
             * contrario — explicar y ofrecer salida.
             *
             * NO se dice «detectamos un bot»: quien dispara esto casi siempre
             * es una persona con prisa que envió el formulario en dos
             * segundos, y acusarla de robot la deja peor que antes.
             */
            self::BotSignal => 'El formulario se envió demasiado rápido y no pudimos verificarlo. Espera un momento y vuelve a enviarlo.',

            self::Unconfirmed => 'Falta marcar la casilla de confirmación antes de enviar.',
        };
    }

    /** Si el docente puede sumarse a un ticket existente en vez de crear otro. */
    public function offersJoin(): bool
    {
        return in_array($this, [self::Duplicate, self::RoomActiveLimit], true);
    }
}

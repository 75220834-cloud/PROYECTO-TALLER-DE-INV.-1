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
            self::Duplicate => 'Ya hay una solicitud abierta para este mismo problema en esta aula.',
            self::RoomActiveLimit => 'Esta aula ya tiene varias solicitudes abiertas. Soporte está al tanto.',
            self::DeviceRate => 'Has enviado varias solicitudes en poco tiempo. Espera unos minutos antes de enviar otra.',
            self::IpRate => 'Se recibieron muchas solicitudes desde esta red. Espera unos minutos, por favor.',
            self::Similarity => 'Esta solicitud se parece mucho a otra reciente.',
            self::BotSignal => 'No pudimos procesar la solicitud. Vuelve a intentarlo.',
            self::Unconfirmed => 'Falta confirmar la solicitud antes de enviarla.',
        };
    }

    /** Si el docente puede sumarse a un ticket existente en vez de crear otro. */
    public function offersJoin(): bool
    {
        return in_array($this, [self::Duplicate, self::RoomActiveLimit], true);
    }
}

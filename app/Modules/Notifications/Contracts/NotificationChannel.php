<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Contracts;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Canal de entrega de avisos.
 *
 * La implementacion inicial escribe en la base y se ve en el panel. La
 * interfaz existe para que anadir correo institucional, notificacion del
 * navegador o tiempo real sea agregar una clase, no reescribir el flujo.
 *
 * NO se depende de WhatsApp, Twilio, Telegram ni Firebase: el sistema debe
 * poder funcionar entero dentro de la red institucional (plan 14 y 20).
 */
interface NotificationChannel
{
    /**
     * @param  Collection<int, User>  $recipients
     * @param  array<string, mixed>  $payload
     */
    public function send(Collection $recipients, string $type, array $payload): void;

    public function name(): string;
}

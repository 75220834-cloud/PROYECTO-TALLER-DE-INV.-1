<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Models\User;
use App\Modules\Notifications\Contracts\NotificationChannel;
use Illuminate\Support\Collection;

/**
 * Entrega un aviso por todos los canales configurados.
 *
 * Hoy solo hay uno (base de datos). Anadir correo institucional sera
 * registrar otro canal aqui, sin tocar el modulo de incidencias.
 */
final class NotificationDispatcher
{
    /** @var list<NotificationChannel> */
    private array $channels;

    public function __construct(NotificationChannel ...$channels)
    {
        $this->channels = $channels;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(Collection $recipients, string $type, array $payload): void
    {
        foreach ($this->channels as $channel) {
            $channel->send($recipients, $type, $payload);
        }
    }

    /**
     * Destinatarios de un aviso operativo: quien puede atender tickets.
     *
     * Se resuelve por PERMISO y no por rol a proposito. Si manana se crea
     * un rol nuevo que atienda incidencias, empezara a recibir avisos sin
     * que nadie recuerde venir a editar esta lista.
     *
     * @return Collection<int, User>
     */
    public function supportStaff(): Collection
    {
        return User::query()
            ->whereHas('roles.permissions', fn ($q) => $q->where('name', 'incidents.update'))
            ->get();
    }
}

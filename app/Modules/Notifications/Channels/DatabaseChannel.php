<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Channels;

use App\Modules\Notifications\Contracts\NotificationChannel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Canal interno: la notificacion se guarda y se muestra en el panel.
 *
 * Es el minimo que el plan exige y el unico que no depende de nada
 * externo. Funciona con la salida a Internet bloqueada.
 */
final class DatabaseChannel implements NotificationChannel
{
    public function send(Collection $recipients, string $type, array $payload): void
    {
        if ($recipients->isEmpty()) {
            return;
        }

        $rows = $recipients->map(fn ($user) => [
            'id' => (string) Str::uuid(),
            'type' => $type,
            'notifiable_type' => $user::class,
            'notifiable_id' => $user->id,
            'data' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        DB::table('notifications')->insert($rows);
    }

    public function name(): string
    {
        return 'database';
    }
}

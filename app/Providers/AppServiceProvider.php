<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Assistant\Contracts\LlmProvider;
use App\Modules\Assistant\Providers\FakeLlmProvider;
use App\Modules\Assistant\Providers\NullLlmProvider;
use App\Modules\Assistant\Providers\OllamaProvider;
use App\Modules\Incidents\Events\IncidentEscalated;
use App\Modules\Incidents\Listeners\NotifySupportOfEscalation;
use App\Modules\Notifications\Channels\DatabaseChannel;
use App\Modules\Notifications\Services\NotificationDispatcher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * Canales de notificacion (plan 14).
         *
         * Hoy solo el interno, que no depende de nada externo y funciona
         * con la salida a Internet bloqueada. Anadir correo institucional
         * o tiempo real sera sumar un canal a esta lista, sin tocar el
         * modulo de incidencias.
         */
        $this->app->singleton(NotificationDispatcher::class, fn ($app) => new NotificationDispatcher(
            $app->make(DatabaseChannel::class),
        ));

        /*
         * Proveedor de modelo de lenguaje (plan 13.3).
         *
         * Se resuelve por configuracion para que cambiar de modelo, mover la
         * inferencia a otro servidor o apagar la IA sea tocar el .env y nada
         * mas. Un valor desconocido cae a NullLlmProvider: es preferible que
         * el sistema opere en modo determinista a que reviente por una
         * variable mal escrita.
         */
        $this->app->singleton(LlmProvider::class, fn () => match (config('incidencias.llm.provider')) {
            'ollama' => new OllamaProvider,
            'fake' => new FakeLlmProvider,
            default => new NullLlmProvider,
        });
    }

    public function boot(): void
    {
        /*
         * Los listeners viven en app/Modules y no en app/Listeners, asi que
         * el descubrimiento automatico de Laravel no los encuentra: hay que
         * registrarlos a mano. Es el precio de la estructura modular, y es
         * barato mientras esten todos declarados en un unico sitio visible.
         */
        Event::listen(IncidentEscalated::class, NotifySupportOfEscalation::class);
    }
}

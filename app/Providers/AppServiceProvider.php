<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Assistant\Contracts\LlmProvider;
use App\Modules\Assistant\Providers\FakeLlmProvider;
use App\Modules\Assistant\Providers\NullLlmProvider;
use App\Modules\Assistant\Providers\OllamaProvider;
use App\Modules\Incidents\Events\IncidentEscalated;
use App\Modules\Incidents\Listeners\NotifySupportOfEscalation;
use App\Modules\Knowledge\Extractors\PdfExtractor;
use App\Modules\Knowledge\Extractors\PlainTextExtractor;
use App\Modules\Knowledge\Extractors\WordExtractor;
use App\Modules\Knowledge\Pipeline\Chunker;
use App\Modules\Knowledge\Pipeline\DocumentIngestionPipeline;
use App\Modules\Notifications\Channels\DatabaseChannel;
use App\Modules\Notifications\Services\NotificationDispatcher;
use App\Modules\Retrieval\Contracts\EmbeddingProvider;
use App\Modules\Retrieval\Providers\HashEmbeddingProvider;
use App\Modules\Retrieval\Providers\OllamaEmbeddingProvider;
use App\Modules\Risk\Contracts\RiskModel;
use App\Modules\Risk\Services\BaselineRecencyModel;
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

        /*
         * Proveedor de vectores.
         *
         * Solo con 'ollama' se usa un modelo real. En cualquier otro caso se
         * cae al proveedor por hashing: es determinista, no necesita nada
         * instalado y permite que la base de conocimiento se indexe y se
         * busque igual. Su calidad semantica es muy inferior —no reconoce
         * parafrasis— y por eso el sistema apoya la busqueda en el filtro
         * lexico cuando no hay modelo (plan 14.3).
         */
        $this->app->singleton(EmbeddingProvider::class, fn () => match (config('incidencias.llm.provider')) {
            'ollama' => new OllamaEmbeddingProvider,
            default => new HashEmbeddingProvider,
        });

        /*
         * Tuberia de ingesta con sus extractores. El orden importa: se
         * consulta uno a uno hasta encontrar el que acepta el formato, y el
         * de texto plano acepta 'application/octet-stream', asi que va al
         * final para no capturar archivos que otro extractor manejaria mejor.
         */
        /*
         * Metodo de estimacion de riesgo (plan 15.2).
         *
         * Hoy solo existe el baseline N0, y muy probablemente sea el unico
         * que llegue a usarse: activar un modelo supervisado exige un volumen
         * de datos que el piloto quiza no alcance. La interfaz existe igual
         * para que ese cambio, si llega, sea sustituir esta linea.
         */
        $this->app->singleton(RiskModel::class, fn () => new BaselineRecencyModel);
        $this->app->singleton(DocumentIngestionPipeline::class, fn ($app) => new DocumentIngestionPipeline(
            $app->make(Chunker::class),
            $app->make(EmbeddingProvider::class),
            new PdfExtractor,
            new WordExtractor,
            new PlainTextExtractor,
        ));
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

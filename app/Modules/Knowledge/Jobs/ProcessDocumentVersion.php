<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Jobs;

use App\Modules\Knowledge\Models\KnowledgeDocumentVersion;
use App\Modules\Knowledge\Pipeline\DocumentIngestionPipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Procesa una versión de documento en segundo plano.
 *
 * Va en cola porque vectorizar un manual largo lleva minutos: hacerlo en la
 * petición web dejaría al administrador mirando un formulario colgado y
 * acabaría en un tiempo de espera agotado.
 *
 * Se pasa el ID y no el modelo: entre que el trabajo se encola y se ejecuta
 * puede pasar un rato, y conviene leer el estado actual y no una copia
 * congelada del momento en que se encoló.
 */
class ProcessDocumentVersion implements ShouldQueue
{
    use Queueable;

    /**
     * Un reintento. Si falla dos veces seguidas, el problema es el
     * documento (formato raro, PDF escaneado) y no un tropiezo pasajero:
     * insistir solo alargaría el rato hasta que alguien lo mire.
     */
    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public readonly int $versionId) {}

    public function handle(DocumentIngestionPipeline $pipeline): void
    {
        $version = KnowledgeDocumentVersion::with('document')->find($this->versionId);

        if ($version === null) {
            return;
        }

        $pipeline->process($version);
    }

    /**
     * Si el trabajo muere del todo (tiempo agotado, memoria), la tubería no
     * llegó a marcar el fallo. Sin esto, el documento se quedaría para
     * siempre en "En cola" y nadie sabría que no va a avanzar.
     */
    public function failed(\Throwable $e): void
    {
        KnowledgeDocumentVersion::where('id', $this->versionId)->update([
            'processing_status' => 'failed',
            'processing_error' => mb_substr('El procesamiento se interrumpió: '.$e->getMessage(), 0, 500),
        ]);
    }
}

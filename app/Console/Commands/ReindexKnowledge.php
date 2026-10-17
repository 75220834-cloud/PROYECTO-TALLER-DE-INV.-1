<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Knowledge\Models\KnowledgeDocument;
use App\Modules\Knowledge\Models\KnowledgeDocumentVersion;
use App\Modules\Knowledge\Services\KnowledgeService;
use Illuminate\Console\Command;

/**
 * Vuelve a indexar la version vigente de cada documento publicado.
 *
 * Hace falta cuando cambia el modelo de vectores, sobre todo al activar la
 * IA en un sistema que empezo sin ella. Cada fragmento guarda el modelo con
 * el que se vectorizo, y la busqueda descarta los de un modelo distinto
 * para no comparar vectores incompatibles. Sin reindexar, el asistente se
 * queda sin fuentes y escala todo, sin dar ningun error.
 */
final class ReindexKnowledge extends Command
{
    protected $signature = 'conocimiento:reindexar';

    protected $description = 'Vuelve a indexar todos los documentos publicados (necesario al activar o cambiar la IA)';

    public function handle(KnowledgeService $knowledge): int
    {
        $total = 0;

        KnowledgeDocument::query()
            ->where('status', 'published')
            ->orderBy('id')
            ->each(function (KnowledgeDocument $document) use ($knowledge, &$total): void {
                $latest = KnowledgeDocumentVersion::query()
                    ->where('document_id', $document->id)
                    ->orderByDesc('version')
                    ->first();

                if ($latest === null) {
                    return;
                }

                $knowledge->reindex($latest);
                $total++;
                $this->line(sprintf('  v%-3d %s', $latest->version, $document->title));
            });

        $this->newLine();
        $this->info("{$total} documentos enviados a indexar.");

        if ($total > 0) {
            $this->comment('La tarea programada los procesa en el próximo minuto. Para hacerlo ya: php artisan queue:work --stop-when-empty');
        }

        return self::SUCCESS;
    }
}

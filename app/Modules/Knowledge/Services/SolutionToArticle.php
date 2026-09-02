<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Knowledge\Jobs\ProcessDocumentVersion;
use App\Modules\Knowledge\Models\KnowledgeDocument;
use App\Modules\Knowledge\Models\KnowledgeDocumentVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Convierte una solución ya registrada en un artículo de la base de
 * conocimiento (plan CU-S-16).
 *
 * POR QUÉ EXISTE: la mayor parte del conocimiento útil de soporte no está en
 * ningún manual. Está en la cabeza del técnico que ya arregló esto tres
 * veces. Cada vez que resuelve un ticket y escribe qué hizo, ese saber queda
 * enterrado en una fila de la base de datos que nadie vuelve a leer. Esto lo
 * saca de ahí y lo mete en el mismo índice que consulta el asistente, para
 * que la cuarta vez el docente ya no tenga que llamar a nadie.
 *
 * DOS DECISIONES QUE PARECEN INCÓMODAS Y SON DELIBERADAS:
 *
 * 1. El artículo nace en BORRADOR, nunca publicado. Una nota de resolución
 *    se escribe para un compañero que conoce el contexto: puede decir
 *    «lo de siempre», nombrar a una persona o describir un apaño que no
 *    debe generalizarse. Publicar eso automáticamente equivale a que el
 *    asistente empiece a recomendárselo a todos los docentes de la sede.
 *
 * 2. El técnico REDACTA el artículo; el sistema solo le adelanta un
 *    borrador con lo que ya sabe. Copiar la nota tal cual produciría
 *    artículos como «cambié el cable», inútiles para quien no estuvo ahí.
 */
final class SolutionToArticle
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Texto que se le propone al técnico en el formulario, ya rellenado con
     * lo que el sistema sabe del ticket. Él lo corrige antes de guardar.
     */
    public function borrador(Incident $incident): string
    {
        $lineas = [];

        $lineas[] = '## Problema';
        $lineas[] = $incident->reported_description !== null && $incident->reported_description !== ''
            ? $incident->reported_description
            : ($incident->category->name ?? 'Describe aquí qué estaba pasando.');
        $lineas[] = '';

        if ($incident->technical_diagnosis !== null && $incident->technical_diagnosis !== '') {
            $lineas[] = '## Causa';
            $lineas[] = $incident->technical_diagnosis;
            $lineas[] = '';
        }

        $lineas[] = '## Solución';
        $lineas[] = $incident->resolution_notes ?? '';

        return implode("\n", $lineas);
    }

    /**
     * Crea el documento, guarda el texto como versión 1 y lo manda a
     * indexar. Devuelve el documento en estado borrador.
     */
    public function crear(Incident $incident, User $autor, string $titulo, string $cuerpo, ?string $resumen = null): KnowledgeDocument
    {
        return DB::transaction(function () use ($incident, $autor, $titulo, $cuerpo, $resumen): KnowledgeDocument {
            $document = KnowledgeDocument::create([
                'title' => mb_substr($titulo, 0, 200),
                'type' => 'procedure',
                'category_id' => $incident->category_id,
                'source_incident_id' => $incident->id,
                'summary' => $resumen !== null ? mb_substr($resumen, 0, 500) : null,
                'status' => 'draft',
                'created_by' => $autor->id,
                // Un artículo nacido de una incidencia de demostración sigue
                // siendo de demostración: si no se marca, la purga de datos
                // falsos lo deja dentro y el asistente lo cita como real.
                'is_demo' => (bool) $incident->room?->is_demo,
            ]);

            $texto = $this->componer($document, $incident, $cuerpo);

            $disk = Storage::disk(config('incidencias.media.disk'));
            $path = 'knowledge/'.Str::random(40).'.md';
            $disk->put($path, $texto);

            $version = KnowledgeDocumentVersion::create([
                'document_id' => $document->id,
                'version' => 1,
                'file_path' => $path,
                'original_name' => Str::slug(mb_substr($titulo, 0, 80)).'.md',
                'file_hash' => hash('sha256', $texto),
                'mime_type' => 'text/markdown',
                'file_size' => strlen($texto),
                'processing_status' => 'pending',
            ]);

            $this->audit->record('knowledge.created_from_incident', $document, [
                'incident_id' => $incident->id,
                'ticket' => $incident->ticket_number,
            ]);

            ProcessDocumentVersion::dispatch($version->id);

            return $document;
        });
    }

    /**
     * Añade al texto la procedencia del artículo.
     *
     * Va DENTRO del archivo, no solo en la base de datos, porque el archivo
     * se puede descargar y circular por correo. Un procedimiento suelto sin
     * fecha ni origen se sigue aplicando años después de haber dejado de
     * ser cierto.
     */
    private function componer(KnowledgeDocument $document, Incident $incident, string $cuerpo): string
    {
        $cabecera = [
            '# '.$document->title,
            '',
            sprintf(
                '> Redactado a partir del ticket %s, atendido el %s en el aula %s.',
                $incident->ticket_number ?? '(sin número)',
                $incident->resolved_at?->timezone(config('incidencias.display_timezone'))->format('d/m/Y') ?? 'fecha no registrada',
                $incident->room->code ?? 'no registrada'
            ),
            '',
        ];

        return implode("\n", $cabecera).trim($cuerpo)."\n";
    }
}

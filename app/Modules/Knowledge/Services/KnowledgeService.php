<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Knowledge\Jobs\ProcessDocumentVersion;
use App\Modules\Knowledge\Models\KnowledgeDocument;
use App\Modules\Knowledge\Models\KnowledgeDocumentVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Alta y versionado de documentos de la base de conocimiento (plan 41).
 */
final class KnowledgeService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Sube una versión nueva de un documento y la manda a procesar.
     *
     * Nunca se sobrescribe la versión anterior: se añade otra. Un
     * procedimiento cambia con el tiempo, y las incidencias atendidas bajo
     * la versión antigua tienen que seguir siendo interpretables contra
     * ella (misma lógica que los árboles de diagnóstico, plan 11.3).
     *
     * @throws RuntimeException si el archivo ya está cargado
     */
    public function addVersion(KnowledgeDocument $document, UploadedFile $file): KnowledgeDocumentVersion
    {
        $hash = hash_file('sha256', $file->getRealPath());

        // Mismo archivo, byte a byte, ya cargado en este documento. Volver a
        // procesarlo gastaría minutos de vectorización para acabar con un
        // índice idéntico.
        $duplicate = KnowledgeDocumentVersion::query()
            ->where('document_id', $document->id)
            ->where('file_hash', $hash)
            ->exists();

        if ($duplicate) {
            throw new RuntimeException('Ese archivo ya está cargado en este documento.');
        }

        $disk = Storage::disk(config('incidencias.media.disk'));

        // Nombre aleatorio: el original puede revelar rutas o nombres de
        // personas, y es una vía clásica de traversal.
        $path = 'knowledge/'.Str::random(40).'.'.$file->getClientOriginalExtension();
        $disk->put($path, file_get_contents($file->getRealPath()));

        $version = KnowledgeDocumentVersion::create([
            'document_id' => $document->id,
            'version' => ((int) $document->versions()->max('version')) + 1,
            'file_path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'file_hash' => $hash,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'file_size' => $file->getSize() ?: 0,
            'processing_status' => 'pending',
        ]);

        $this->audit->record('knowledge.version_uploaded', $document, [
            'version' => $version->version,
            'file' => $version->original_name,
        ]);

        // A la cola: un manual de 80 páginas tarda varios minutos en
        // vectorizarse y nadie debe esperar mirando un formulario.
        ProcessDocumentVersion::dispatch($version->id);

        return $version;
    }

    /** Vuelve a procesar una versión ya cargada. */
    public function reindex(KnowledgeDocumentVersion $version): void
    {
        $version->update([
            'processing_status' => 'pending',
            'processing_error' => null,
        ]);

        $this->audit->record('knowledge.reindex', $version->document, [
            'version' => $version->version,
        ]);

        ProcessDocumentVersion::dispatch($version->id);
    }

    public function publish(KnowledgeDocument $document): void
    {
        $document->update(['status' => 'published']);
        $this->audit->record('knowledge.published', $document);
    }

    public function unpublish(KnowledgeDocument $document): void
    {
        $document->update(['status' => 'draft']);
        $this->audit->record('knowledge.unpublished', $document);
    }

    /**
     * Archiva el documento.
     *
     * No se borra: un documento archivado deja de alimentar al asistente,
     * pero sigue explicando POR QUÉ una incidencia pasada se resolvió como
     * se resolvió. Borrarlo dejaría esas respuestas sin fuente verificable.
     */
    public function archive(KnowledgeDocument $document): void
    {
        $document->update(['status' => 'archived']);
        $this->audit->record('knowledge.archived', $document);
    }
}

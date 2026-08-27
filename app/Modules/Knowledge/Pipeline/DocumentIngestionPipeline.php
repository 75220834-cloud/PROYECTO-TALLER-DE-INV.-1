<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Pipeline;

use App\Modules\Knowledge\Contracts\DocumentExtractor;
use App\Modules\Knowledge\Models\KnowledgeChunk;
use App\Modules\Knowledge\Models\KnowledgeDocument;
use App\Modules\Knowledge\Models\KnowledgeDocumentVersion;
use App\Modules\Retrieval\Contracts\EmbeddingProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Convierte un documento subido en fragmentos indexados (plan 14.1).
 *
 *   validar -> extraer -> limpiar -> fragmentar -> vectorizar -> indexar
 *
 * Cada etapa actualiza `processing_status`, que es lo que el panel muestra:
 * quien sube un documento tiene que poder ver en qué punto está y, si algo
 * falla, POR QUÉ falló. "Error" a secas obliga a abrir los logs.
 *
 * Todo el reemplazo del índice ocurre dentro de una transacción: si la
 * ingesta se cae a mitad, no queda un documento a medio indexar
 * respondiendo con la mitad de su contenido.
 */
final class DocumentIngestionPipeline
{
    /** @var list<DocumentExtractor> */
    private array $extractors;

    public function __construct(
        private readonly Chunker $chunker,
        private readonly EmbeddingProvider $embeddings,
        DocumentExtractor ...$extractors,
    ) {
        $this->extractors = $extractors;
    }

    public function process(KnowledgeDocumentVersion $version): void
    {
        try {
            $blocks = $this->extractText($version);
            $chunks = $this->buildChunks($version, $blocks);
            $this->index($version, $chunks);
        } catch (Throwable $e) {
            $version->update([
                'processing_status' => 'failed',
                // Mensaje acotado: suficiente para entender qué pasó, sin
                // volcar una traza entera en la interfaz.
                'processing_error' => mb_substr($e->getMessage(), 0, 500),
            ]);

            Log::error('Falló la ingesta de un documento', [
                'version_id' => $version->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return list<array{page: int|null, section: string|null, text: string}>
     */
    private function extractText(KnowledgeDocumentVersion $version): array
    {
        $version->update(['processing_status' => 'extracting', 'processing_error' => null]);

        $disk = Storage::disk(config('incidencias.media.disk'));

        if (! $disk->exists($version->file_path)) {
            throw new RuntimeException('El archivo del documento no se encuentra en el almacenamiento.');
        }

        $extractor = $this->extractorFor($version->mime_type);

        if ($extractor === null) {
            throw new RuntimeException("No hay soporte para el formato «{$version->mime_type}».");
        }

        $blocks = $extractor->extract($disk->path($version->file_path));

        if ($blocks === []) {
            // Causa habitual: un PDF que es un escaneo, sin capa de texto.
            // Decirlo así evita que alguien pierda una tarde preguntándose
            // por qué el asistente "no sabe" algo que sí subió.
            throw new RuntimeException(
                'No se pudo extraer texto. Si es un PDF escaneado, necesita OCR o una versión con texto seleccionable.'
            );
        }

        return $blocks;
    }

    /**
     * @param  list<array{page: int|null, section: string|null, text: string}>  $blocks
     * @return list<array{content: string, section: string|null, page: int|null, tokens: int}>
     */
    private function buildChunks(KnowledgeDocumentVersion $version, array $blocks): array
    {
        $version->update(['processing_status' => 'chunking']);

        $document = $version->document;
        $chunks = $this->chunker->chunk(
            $blocks,
            $document instanceof KnowledgeDocument ? $document->title : 'Documento'
        );

        if ($chunks === []) {
            throw new RuntimeException('El documento no tiene contenido suficiente para indexar.');
        }

        return $chunks;
    }

    /**
     * @param  list<array{content: string, section: string|null, page: int|null, tokens: int}>  $chunks
     */
    private function index(KnowledgeDocumentVersion $version, array $chunks): void
    {
        $version->update(['processing_status' => 'embedding']);

        $vectors = $this->embeddings->embedBatch(array_column($chunks, 'content'));
        $model = $this->embeddings->modelIdentifier();
        $categoryId = $version->document instanceof KnowledgeDocument ? $version->document->category_id : null;

        DB::transaction(function () use ($version, $chunks, $vectors, $model, $categoryId) {
            // Reindexar sustituye: si quedaran los fragmentos anteriores, el
            // asistente respondería con contenido de dos versiones a la vez.
            $version->chunks()->delete();

            foreach ($chunks as $i => $chunk) {
                $vector = $vectors[$i] ?? null;

                KnowledgeChunk::create([
                    'document_version_id' => $version->id,
                    'chunk_index' => $i,
                    'content' => $chunk['content'],
                    'token_count' => $chunk['tokens'],
                    'section_title' => $chunk['section'],
                    'page_number' => $chunk['page'],
                    'embedding' => $vector,
                    'embedding_model' => $vector !== null ? $model : null,
                    'embedding_norm' => $vector !== null ? $this->norm($vector) : null,
                    'category_id' => $categoryId,
                ]);
            }

            $version->update([
                'processing_status' => 'indexed',
                'indexed_at' => now(),
                'chunk_count' => count($chunks),
                'processing_error' => null,
            ]);
        });

        // Sin vectores el documento sigue siendo BUSCABLE por texto: se
        // indexa igual y se avisa. Es preferible a rechazarlo, pero hay que
        // saberlo porque la búsqueda semántica no funcionará sobre él.
        if (count(array_filter($vectors)) === 0) {
            Log::warning('Documento indexado SIN vectores; solo será buscable por texto.', [
                'version_id' => $version->id,
            ]);
        }
    }

    private function extractorFor(string $mimeType): ?DocumentExtractor
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($mimeType)) {
                return $extractor;
            }
        }

        return null;
    }

    /** @param  list<float>  $vector */
    private function norm(array $vector): float
    {
        $sum = 0.0;

        foreach ($vector as $value) {
            $sum += $value * $value;
        }

        return sqrt($sum);
    }
}

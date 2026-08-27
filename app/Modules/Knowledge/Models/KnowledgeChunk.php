<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Models;

use App\Modules\Incidents\Models\IncidentCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fragmento indexado de un documento.
 *
 * Conserva de dónde salió (documento, sección, página) porque el asistente
 * tiene que poder CITAR la fuente de cada respuesta (plan 42). Una
 * respuesta sin procedencia no se puede verificar, y una que no se puede
 * verificar no se puede distinguir de una inventada.
 *
 * @property int $id
 * @property int $document_version_id
 * @property int $chunk_index
 * @property string $content
 * @property string|null $section_title
 * @property int|null $page_number
 * @property array|null $embedding
 * @property string|null $embedding_model
 * @property float|null $embedding_norm
 * @property int|null $category_id
 * @property-read KnowledgeDocumentVersion|null $version
 */
class KnowledgeChunk extends Model
{
    protected $fillable = [
        'document_version_id', 'chunk_index', 'content', 'token_count',
        'section_title', 'page_number', 'embedding', 'embedding_model',
        'embedding_norm', 'category_id',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'embedding_norm' => 'float',
            'chunk_index' => 'integer',
            'token_count' => 'integer',
            'page_number' => 'integer',
        ];
    }

    /** @return BelongsTo<KnowledgeDocumentVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocumentVersion::class, 'document_version_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(IncidentCategory::class, 'category_id');
    }

    /**
     * Referencia legible de la cita: "Manual del proyector, p. 12".
     */
    public function citation(): string
    {
        $document = $this->version?->document;
        $parts = [$document instanceof KnowledgeDocument ? $document->title : 'Documento'];

        if ($this->section_title !== null && $this->section_title !== '') {
            $parts[] = $this->section_title;
        }

        if ($this->page_number !== null) {
            $parts[] = "p. {$this->page_number}";
        }

        return implode(', ', $parts);
    }

    /**
     * Similitud coseno contra otro vector.
     *
     * Usa la norma PRECALCULADA de este fragmento. Es la optimización que
     * hace viable calcular esto en PHP: la norma no cambia nunca, así que
     * recalcularla en cada consulta sería trabajo tirado (plan 10.2).
     *
     * @param  list<float>  $query
     */
    public function cosineAgainst(array $query, float $queryNorm): float
    {
        $embedding = $this->embedding;

        if ($embedding === null || $this->embedding_norm === null) {
            return 0.0;
        }

        if ($this->embedding_norm <= 0.0 || $queryNorm <= 0.0) {
            return 0.0;
        }

        // Dimensiones distintas = vectores de modelos distintos. Compararlos
        // daría un número sin significado, así que se descarta.
        if (count($embedding) !== count($query)) {
            return 0.0;
        }

        $dot = 0.0;

        foreach ($query as $i => $value) {
            $dot += $value * $embedding[$i];
        }

        return $dot / ($this->embedding_norm * $queryNorm);
    }
}

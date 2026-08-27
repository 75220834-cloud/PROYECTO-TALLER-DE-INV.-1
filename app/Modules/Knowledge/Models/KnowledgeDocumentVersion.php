<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Version concreta de un documento: el archivo tal como se subio.
 *
 * @property int $id
 * @property int $document_id
 * @property int $version
 * @property string $file_path
 * @property string $file_hash
 * @property string $processing_status
 * @property string|null $processing_error
 * @property int $chunk_count
 * @property Carbon|null $indexed_at
 * @property-read KnowledgeDocument|null $document
 */
class KnowledgeDocumentVersion extends Model
{
    protected $fillable = [
        'document_id', 'version', 'file_path', 'original_name', 'file_hash',
        'mime_type', 'file_size', 'page_count', 'processing_status',
        'processing_error', 'indexed_at', 'chunk_count',
    ];

    protected function casts(): array
    {
        return [
            'indexed_at' => 'datetime',
            'version' => 'integer',
            'file_size' => 'integer',
            'chunk_count' => 'integer',
        ];
    }

    /** @return BelongsTo<KnowledgeDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'document_id');
    }

    /** @return HasMany<KnowledgeChunk, $this> */
    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class, 'document_version_id')->orderBy('chunk_index');
    }

    public function isIndexed(): bool
    {
        return $this->processing_status === 'indexed';
    }

    public function hasFailed(): bool
    {
        return $this->processing_status === 'failed';
    }

    /** Etiquetas en espanol de cada etapa, para el panel. */
    public static function statusLabels(): array
    {
        return [
            'pending' => 'En cola',
            'extracting' => 'Extrayendo texto',
            'chunking' => 'Fragmentando',
            'embedding' => 'Generando vectores',
            'indexed' => 'Listo',
            'failed' => 'Falló',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->processing_status] ?? $this->processing_status;
    }
}

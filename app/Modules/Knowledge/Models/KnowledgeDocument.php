<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Models;

use App\Models\User;
use App\Modules\Incidents\Models\IncidentCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Documento de la base de conocimiento.
 *
 * El documento es la FUENTE DE VERDAD; los fragmentos indexados son cache
 * derivado. Se puede borrar el indice entero y reconstruirlo desde aqui.
 *
 * @property int $id
 * @property string $title
 * @property string $type
 * @property string $status
 * @property int|null $category_id
 * @property bool $is_demo
 */
class KnowledgeDocument extends Model
{
    use SoftDeletes;

    protected $fillable = ['title', 'type', 'category_id', 'source_incident_id', 'status', 'summary', 'created_by', 'is_demo'];

    protected function casts(): array
    {
        return ['is_demo' => 'boolean'];
    }

    /** @return HasMany<KnowledgeDocumentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(KnowledgeDocumentVersion::class, 'document_id')->orderByDesc('version');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(IncidentCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function currentVersion(): ?KnowledgeDocumentVersion
    {
        $version = $this->versions()->first();

        return $version instanceof KnowledgeDocumentVersion ? $version : null;
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Disponible para el asistente.
     *
     * Exige DOS cosas a la vez: que el documento este publicado y que su
     * version este indexada. Un documento publicado cuya indexacion fallo no
     * sirve para responder, y darlo por bueno haria que el asistente
     * pareciera no saber algo que si esta cargado.
     */
    public function isUsable(): bool
    {
        return $this->isPublished() && $this->currentVersion()?->processing_status === 'indexed';
    }

    public static function typeLabels(): array
    {
        return [
            'manual' => 'Manual',
            'procedure' => 'Procedimiento',
            'faq' => 'Preguntas frecuentes',
            'guide' => 'Guía',
            'protocol' => 'Protocolo',
            'other' => 'Otro',
        ];
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}

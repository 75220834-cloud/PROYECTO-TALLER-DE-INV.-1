<?php

declare(strict_types=1);

namespace App\Modules\Media\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Imagen del banco visual (plan 10.8, 13.6 y Anexo A).
 *
 * La IA SELECCIONA de este catalogo; nunca crea ni describe una imagen que
 * no este aqui. Una imagen generada de un puerto HDMI sale con los pines
 * mal y lleva al docente a manipular el conector equivocado: es peor que
 * no mostrar nada y mas dificil de detectar que una alucinacion de texto.
 *
 * @property int $id
 * @property string $code
 * @property string $title
 * @property string $alt_text
 * @property string|null $caption
 * @property string $type
 * @property string $file_path
 * @property string|null $thumbnail_path
 * @property int|null $width
 * @property int|null $height
 * @property int|null $file_size
 * @property string|null $mime_type
 * @property string $source
 * @property string $license
 * @property string|null $attribution
 * @property array|null $annotations
 * @property string $scope_type
 * @property string|null $scope_key
 * @property bool $is_active
 * @property bool $is_demo
 */
class MediaAsset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'title', 'alt_text', 'caption', 'type',
        'file_path', 'thumbnail_path', 'width', 'height', 'file_size', 'mime_type',
        'source', 'license', 'attribution', 'annotations',
        'scope_type', 'scope_key', 'is_active', 'is_demo', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'annotations' => 'array',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
            'width' => 'integer',
            'height' => 'integer',
            'file_size' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Los diagramas SVG se guardan como texto y se incrustan directamente;
     * las fotos se sirven por controlador. Distinguirlo aqui evita que las
     * vistas tengan que saber de donde viene cada cosa.
     */
    public function isInlineSvg(): bool
    {
        return $this->mime_type === 'image/svg+xml';
    }

    public function url(): string
    {
        return route('media.show', ['asset' => $this->id]);
    }

    public function thumbnailUrl(): string
    {
        return $this->thumbnail_path !== null
            ? route('media.show', ['asset' => $this->id, 'thumb' => 1])
            : $this->url();
    }

    /** Peso en KB, para vigilar el presupuesto del plan (150 KB). */
    public function sizeKb(): ?int
    {
        return $this->file_size !== null ? (int) round($this->file_size / 1024) : null;
    }

    public function exceedsBudget(): bool
    {
        return $this->file_size !== null
            && $this->file_size > (int) config('incidencias.media.max_bytes');
    }
}

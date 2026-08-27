<?php

declare(strict_types=1);

namespace App\Modules\Diagnostics\Models;

use App\Modules\Incidents\Models\IncidentCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Arbol de diagnostico de una categoria de incidencia.
 *
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property bool $is_active
 */
class DiagnosticFlow extends Model
{
    protected $fillable = ['category_id', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(IncidentCategory::class, 'category_id');
    }

    /** @return HasMany<DiagnosticFlowVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(DiagnosticFlowVersion::class, 'flow_id');
    }

    /** Version publicada vigente, o null si el arbol aun es borrador. */
    public function publishedVersion(): ?DiagnosticFlowVersion
    {
        $version = $this->versions()
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->first();

        return $version instanceof DiagnosticFlowVersion ? $version : null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

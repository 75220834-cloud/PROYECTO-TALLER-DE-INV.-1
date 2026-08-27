<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $teacher_label
 * @property int|null $default_priority_id
 * @property int|null $media_asset_id
 * @property int $sort_order
 * @property bool $is_active
 */
class IncidentCategory extends Model
{
    protected $fillable = [
        'code', 'name', 'teacher_label', 'icon',
        'media_asset_id', 'default_priority_id', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function defaultPriority(): BelongsTo
    {
        return $this->belongsTo(IncidentPriority::class, 'default_priority_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Texto que ve el docente. Describe el SINTOMA, no el componente:
     * quien no sabe que es un HDMI si sabe que "no se ve la imagen".
     */
    public function teacherText(): string
    {
        return $this->teacher_label ?: $this->name;
    }
}

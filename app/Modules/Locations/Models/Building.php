<?php

declare(strict_types=1);

namespace App\Modules\Locations\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $site_id
 * @property string $code
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_demo
 * @property-read Site|null $site
 * @property-read Collection<int, Floor> $floors
 */
class Building extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['site_id', 'code', 'name', 'sort_order', 'is_active', 'is_demo'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_demo' => 'boolean', 'sort_order' => 'integer'];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function floors(): HasMany
    {
        return $this->hasMany(Floor::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

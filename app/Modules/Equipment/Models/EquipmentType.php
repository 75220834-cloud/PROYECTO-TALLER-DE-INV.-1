<?php

declare(strict_types=1);

namespace App\Modules\Equipment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int|null $default_category_id
 * @property int|null $media_asset_id
 * @property int $sort_order
 * @property bool $is_active
 */
class EquipmentType extends Model
{
    protected $fillable = ['code', 'name', 'default_category_id', 'media_asset_id', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

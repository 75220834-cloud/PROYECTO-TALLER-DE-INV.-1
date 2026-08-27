<?php

declare(strict_types=1);

namespace App\Modules\Locations\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property bool $is_demo
 * @property-read Collection<int, Building> $buildings
 */
class Site extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['code', 'name', 'is_active', 'is_demo'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_demo' => 'boolean'];
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

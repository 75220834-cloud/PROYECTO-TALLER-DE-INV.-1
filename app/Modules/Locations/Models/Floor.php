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
 * @property int $building_id
 * @property int $number
 * @property string $label
 * @property bool $is_active
 * @property bool $is_demo
 * @property-read Building|null $building
 * @property-read Collection<int, Room> $rooms
 */
class Floor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['building_id', 'number', 'label', 'is_active', 'is_demo'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_demo' => 'boolean', 'number' => 'integer'];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

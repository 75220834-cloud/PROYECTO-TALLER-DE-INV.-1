<?php

declare(strict_types=1);

namespace App\Modules\Equipment\Models;

use App\Modules\Locations\Models\Room;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $room_id
 * @property int $equipment_type_id
 * @property string $asset_code
 * @property string|null $brand
 * @property string|null $model
 * @property string|null $serial_number
 * @property string $status
 * @property bool $is_active
 * @property bool $is_demo
 * @property-read Room|null $room
 * @property-read EquipmentType|null $type
 */
class Equipment extends Model
{
    use SoftDeletes;

    // Sin esto Eloquent buscaria la tabla "equipments": "equipment" no
    // tiene plural en ingles.
    protected $table = 'equipment';

    protected $fillable = [
        'room_id', 'equipment_type_id', 'asset_code', 'brand', 'model',
        'serial_number', 'status', 'commissioned_at', 'is_active', 'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
            'commissioned_at' => 'date',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(EquipmentType::class, 'equipment_type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Etiquetas en espanol de los estados, para no repetirlas en cada vista. */
    public static function statusLabels(): array
    {
        return [
            'operational' => 'Operativo',
            'degraded' => 'Con fallas',
            'in_maintenance' => 'En mantenimiento',
            'out_of_service' => 'Fuera de servicio',
        ];
    }
}

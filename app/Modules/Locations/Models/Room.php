<?php

declare(strict_types=1);

namespace App\Modules\Locations\Models;

use App\Modules\Equipment\Models\Equipment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $floor_id
 * @property string $code
 * @property string|null $name
 * @property int|null $capacity
 * @property int $criticality
 * @property bool $is_active
 * @property bool $is_demo
 * @property-read Floor|null $floor
 */
class Room extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['floor_id', 'code', 'name', 'room_type', 'capacity', 'criticality', 'self_service', 'support_only_reason', 'is_active', 'is_demo'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
            'self_service' => 'boolean',
            'capacity' => 'integer',
            'criticality' => 'integer',
        ];
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Ruta legible para la pantalla de confirmacion que exige el plan 7.1:
     * "Aula C305 - Pabellon C - Piso 3".
     *
     * Se construye leyendo el catalogo, nunca concatenando codigos para
     * inventar el nombre del aula.
     */
    public function fullPath(): string
    {
        $floor = $this->floor;
        $building = $floor?->building;

        // El nombre del pabellon es lo que el docente reconoce; el codigo
        // es el respaldo si no se le puso nombre. El "?" final solo aparece
        // si la cadena esta rota, y en ese caso CascadeValidator ya habria
        // impedido llegar hasta aqui: es una red de seguridad, no un caso
        // esperado.
        $buildingLabel = $building === null
            ? '?'
            : ($building->name !== '' ? $building->name : $building->code);

        return sprintf(
            'Aula %s - Pabellon %s - %s',
            $this->code,
            $buildingLabel,
            $floor === null ? '?' : $floor->label
        );
    }
}

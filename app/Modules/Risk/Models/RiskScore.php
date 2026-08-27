<?php

declare(strict_types=1);

namespace App\Modules\Risk\Models;

use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Locations\Models\Room;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una señal de riesgo calculada, con su explicacion (plan 15.5).
 *
 * @property int $id
 * @property string $scope_type
 * @property int $scope_id
 * @property int|null $category_id
 * @property int $horizon_days
 * @property float $score
 * @property string $risk_band
 * @property list<array{label: string, detail: string, contribution: float}> $factors
 * @property string $model_code
 * @property string $model_version
 * @property Carbon $computed_at
 */
class RiskScore extends Model
{
    protected $fillable = [
        'scope_type', 'scope_id', 'category_id', 'horizon_days',
        'score', 'risk_band', 'factors', 'model_code', 'model_version', 'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'factors' => 'array',
            'score' => 'float',
            'horizon_days' => 'integer',
            'computed_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(IncidentCategory::class);
    }

    /**
     * El aula del score. Es una relacion sobre `scope_id` y solo tiene
     * sentido cuando el ambito es un aula, por eso no se declara como
     * `belongsTo` normal: para un score de equipo apuntaria a un aula
     * inexistente y nadie lo notaria hasta ver un dato absurdo en pantalla.
     */
    public function room(): ?Room
    {
        if (! in_array($this->scope_type, ['room', 'room_category'], true)) {
            return null;
        }

        return Room::query()->find($this->scope_id);
    }
}

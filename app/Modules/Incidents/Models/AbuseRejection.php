<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Models;

use App\Modules\Locations\Models\Room;
use App\Shared\Enums\AbuseReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de solicitudes rechazadas por los controles antiabuso.
 *
 * Existe por tres razones distintas y conviene tenerlas presentes:
 *   1. Seguridad: sin registro no se sabe si el endpoint esta siendo atacado.
 *   2. Calibracion: los umbrales son estimaciones sin datos. Si rechazan
 *      gente legitima tiene que verse aqui, no descubrirse por una queja.
 *   3. Investigacion: la tasa de rechazos es un indicador del piloto.
 *
 * @property int $id
 * @property string $reason
 * @property int|null $room_id
 */
class AbuseRejection extends Model
{
    public $timestamps = false;

    protected $table = 'incident_abuse_rejections';

    protected $fillable = [
        'room_id', 'category_id', 'reason', 'ip_hash', 'device_key',
        'payload_excerpt', 'related_incident_id', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function reasonEnum(): AbuseReason
    {
        return AbuseReason::from($this->reason);
    }

    /*
     * Ambas relaciones son opcionales en la base de datos y lo son tambien
     * aqui: un rechazo puede ocurrir antes de que el docente haya elegido
     * categoria, y el aula puede haberse dado de baja despues. La pantalla
     * de revision tiene que seguir mostrando la fila igual, porque el motivo
     * del rechazo sigue siendo informacion util para calibrar los umbrales.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(IncidentCategory::class);
    }
}

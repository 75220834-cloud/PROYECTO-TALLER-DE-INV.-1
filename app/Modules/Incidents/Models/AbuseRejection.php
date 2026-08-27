<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Models;

use App\Shared\Enums\AbuseReason;
use Illuminate\Database\Eloquent\Model;

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
}

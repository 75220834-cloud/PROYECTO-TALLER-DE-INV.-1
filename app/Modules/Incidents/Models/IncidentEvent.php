<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historial APPEND-ONLY de una incidencia.
 *
 * No tiene timestamps ni se actualiza nunca: solo se agrega. Es la fuente
 * de verdad que permite reconstruir por que un ticket termino donde
 * termino, y sin esa garantia la auditoria no vale nada (plan 11.5).
 *
 * @property int $id
 * @property int $incident_id
 * @property string $event_type
 * @property string $actor_type
 * @property array|null $from_value
 * @property array|null $to_value
 * @property array|null $metadata
 */
class IncidentEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'incident_id', 'event_type', 'actor_type', 'actor_id',
        'from_value', 'to_value', 'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'from_value' => 'array',
            'to_value' => 'array',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Encuesta de facilidad de uso. OPCIONAL y omitible: el plan advierte
 * contra perjudicar la experiencia del docente por medir (plan 30).
 *
 * @property int $id
 * @property int $incident_id
 * @property int $ease_score
 */
class SatisfactionResponse extends Model
{
    protected $fillable = ['incident_id', 'ease_score', 'comment', 'answered_at'];

    protected function casts(): array
    {
        return ['ease_score' => 'integer', 'answered_at' => 'datetime'];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }
}

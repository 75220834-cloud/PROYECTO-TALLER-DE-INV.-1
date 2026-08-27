<?php

declare(strict_types=1);

namespace App\Modules\Diagnostics\Models;

use App\Modules\Incidents\Models\Incident;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Respuesta del docente a un paso concreto.
 *
 * Es la materia prima de la investigacion: permite saber que pasos se
 * ejecutaron, cual resolvio el problema, cuanto tardo cada uno y que
 * imagenes se mostraron.
 *
 * @property int $id
 * @property int $incident_id
 * @property int $step_id
 * @property string $answer_value
 * @property int|null $duration_ms
 * @property array|null $media_shown
 * @property-read DiagnosticStep|null $step
 */
class DiagnosticAnswer extends Model
{
    protected $fillable = [
        'incident_id', 'step_id', 'answer_value', 'answered_at', 'duration_ms', 'media_shown',
    ];

    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
            'media_shown' => 'array',
            'duration_ms' => 'integer',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(DiagnosticStep::class, 'step_id');
    }

    /** Etiqueta legible de la respuesta, para mostrarsela a soporte. */
    public function answerLabel(): string
    {
        foreach ($this->step?->options() ?? [] as $option) {
            if ($option['value'] === $this->answer_value) {
                return $option['label'];
            }
        }

        return $this->answer_value;
    }

    public function sawImage(): bool
    {
        return ! empty($this->media_shown);
    }
}

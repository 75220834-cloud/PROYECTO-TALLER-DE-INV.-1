<?php

declare(strict_types=1);

namespace App\Modules\Diagnostics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Version concreta de un arbol de diagnostico.
 *
 * El versionado NO es un lujo (plan 11.3): cada incidencia guarda la
 * version que realmente ejecuto. Si manana soporte mejora el procedimiento
 * del proyector, las incidencias de ayer siguen siendo interpretables
 * contra el procedimiento que de verdad se siguio.
 *
 * Sin esto, cualquier comparacion antes/despues de la investigacion
 * quedaria contaminada: no se sabria si el cambio en los resultados vino
 * del sistema o de que alguien edito un paso a mitad del piloto.
 *
 * @property int $id
 * @property int $flow_id
 * @property int $version
 * @property Carbon|null $published_at
 */
class DiagnosticFlowVersion extends Model
{
    protected $fillable = ['flow_id', 'version', 'published_at', 'published_by'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'version' => 'integer'];
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(DiagnosticFlow::class, 'flow_id');
    }

    /** @return HasMany<DiagnosticStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(DiagnosticStep::class, 'flow_version_id')->orderBy('sort_order');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /** Primer paso del recorrido. */
    public function firstStep(): ?DiagnosticStep
    {
        $step = $this->steps()->orderBy('sort_order')->first();

        return $step instanceof DiagnosticStep ? $step : null;
    }

    public function stepByKey(string $key): ?DiagnosticStep
    {
        $step = $this->steps()->where('step_key', $key)->first();

        return $step instanceof DiagnosticStep ? $step : null;
    }
}

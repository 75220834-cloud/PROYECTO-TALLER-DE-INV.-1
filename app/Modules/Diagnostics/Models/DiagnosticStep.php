<?php

declare(strict_types=1);

namespace App\Modules\Diagnostics\Models;

use App\Modules\Media\Models\MediaAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Un paso del arbol de diagnostico.
 *
 * `prompt_text` es SIEMPRE valido por si mismo: es lo que se muestra si el
 * modelo de lenguaje no esta disponible o si su reformulacion se descarta.
 * El sistema nunca depende del LLM para poder guiar al docente.
 *
 * @property int $id
 * @property int $flow_version_id
 * @property string $step_key
 * @property int $sort_order
 * @property string $prompt_text
 * @property string|null $help_text
 * @property array $answer_options
 * @property array|null $next_step_map
 * @property bool $is_terminal
 * @property string|null $terminal_outcome
 * @property string|null $component_key
 */
class DiagnosticStep extends Model
{
    protected $fillable = [
        'flow_version_id', 'step_key', 'sort_order', 'prompt_text', 'help_text',
        'answer_options', 'next_step_map', 'is_terminal', 'terminal_outcome',
        'component_key', 'knowledge_ref',
    ];

    protected function casts(): array
    {
        return [
            'answer_options' => 'array',
            'next_step_map' => 'array',
            'is_terminal' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<DiagnosticFlowVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DiagnosticFlowVersion::class, 'flow_version_id');
    }

    /** @return BelongsToMany<MediaAsset, $this> */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'diagnostic_step_media', 'step_id', 'media_asset_id')
            ->withPivot(['role', 'sort_order'])
            ->orderByPivot('sort_order');
    }

    /**
     * Siguiente paso segun la respuesta.
     *
     * Devuelve null cuando la respuesta no lleva a ningun sitio: eso se
     * interpreta como agotamiento del arbol y termina en escalamiento, que
     * es el desenlace conservador correcto. Un mapa incompleto nunca deja
     * al docente atrapado en una pantalla.
     */
    public function nextKeyFor(string $answer): ?string
    {
        $map = $this->next_step_map ?? [];

        return isset($map[$answer]) && is_string($map[$answer]) ? $map[$answer] : null;
    }

    /**
     * Opciones de respuesta normalizadas: [['value' => ..., 'label' => ...]].
     *
     * Siempre cerradas y en botones. El docente nunca escribe aqui: una
     * respuesta libre no seria analizable y haria el recorrido irreproducible,
     * que es justo lo que la investigacion necesita evitar.
     *
     * @return list<array{value: string, label: string}>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->answer_options ?? [] as $key => $option) {
            if (is_array($option)) {
                $options[] = [
                    'value' => (string) ($option['value'] ?? $key),
                    'label' => (string) ($option['label'] ?? $option['value'] ?? $key),
                ];

                continue;
            }

            $options[] = ['value' => (string) $key, 'label' => (string) $option];
        }

        return $options;
    }

    public function isValidAnswer(string $answer): bool
    {
        return in_array($answer, array_column($this->options(), 'value'), true);
    }
}

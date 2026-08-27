<?php

declare(strict_types=1);

namespace App\Modules\Diagnostics\Engine;

use App\Modules\Diagnostics\Models\DiagnosticAnswer;
use App\Modules\Diagnostics\Models\DiagnosticFlow;
use App\Modules\Diagnostics\Models\DiagnosticFlowVersion;
use App\Modules\Diagnostics\Models\DiagnosticStep;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Services\MediaResolver;
use Illuminate\Support\Collection;

/**
 * Motor del diagnostico guiado.
 *
 * DETERMINISTA por completo. El modelo de lenguaje NO decide el camino:
 * solo clasifica el texto libre de entrada, reformula el texto de un paso
 * y sugiere una imagen del catalogo. Si el modelo no esta disponible, el
 * diagnostico funciona exactamente igual (plan 13.1).
 *
 * Esa decision es lo que hace la investigacion defendible: dos docentes con
 * el mismo problema reciben el mismo procedimiento, y el resultado se puede
 * atribuir a la intervencion en lugar de al azar de una generacion.
 */
final class DiagnosticEngine
{
    /** Tope duro de pasos, por si un arbol mal editado tuviera un ciclo. */
    private const HARD_STEP_LIMIT = 30;

    public function __construct(private readonly MediaResolver $media) {}

    /**
     * Version publicada del arbol de una categoria, o null si no hay.
     *
     * Null es un caso NORMAL, no un error: mientras soporte no haya cargado
     * los procedimientos, el sistema ofrece reportar y escalar sin
     * diagnostico. Es preferible a inventar pasos.
     */
    public function flowFor(int $categoryId): ?DiagnosticFlowVersion
    {
        $flow = DiagnosticFlow::query()
            ->active()
            ->where('category_id', $categoryId)
            ->first();

        return $flow?->publishedVersion();
    }

    /**
     * Paso en el que esta la incidencia ahora mismo.
     *
     * Se calcula recorriendo las respuestas ya dadas en vez de guardar un
     * puntero. Asi el estado del recorrido vive en un solo sitio (las
     * respuestas), y no puede desincronizarse de el.
     */
    public function currentStep(Incident $incident, DiagnosticFlowVersion $version): ?DiagnosticStep
    {
        $answers = $this->answersOf($incident);

        if ($answers->isEmpty()) {
            return $version->firstStep();
        }

        $last = $answers->last();
        $lastStep = $version->steps()->find($last->step_id);

        if ($lastStep === null || $lastStep->is_terminal) {
            return null;
        }

        $nextKey = $lastStep->nextKeyFor($last->answer_value);

        // Sin siguiente paso declarado: el arbol se agoto. No es un fallo,
        // es el final del procedimiento conocido.
        if ($nextKey === null) {
            return null;
        }

        $next = $version->stepByKey($nextKey);

        // Los pasos terminales son marcadores de desenlace, no pantallas.
        // Devolverlos aqui haria que el docente los viera al recargar la
        // pagina despues de haber terminado, que es justo lo que answer()
        // ya evita. Las dos rutas tienen que coincidir.
        return $next !== null && ! $next->is_terminal ? $next : null;
    }

    /**
     * Registra la respuesta del docente y devuelve el resultado del paso.
     *
     * @param  list<int>  $mediaShown  ids de las imagenes que se le mostraron
     */
    public function answer(
        Incident $incident,
        DiagnosticStep $step,
        string $answer,
        ?int $durationMs = null,
        array $mediaShown = [],
    ): StepOutcome {
        if (! $step->isValidAnswer($answer)) {
            return StepOutcome::invalid();
        }

        DiagnosticAnswer::create([
            'incident_id' => $incident->id,
            'step_id' => $step->id,
            'answer_value' => $answer,
            'answered_at' => now(),
            'duration_ms' => $durationMs,

            // Que imagenes se mostraron realmente. Permite contrastar la
            // resolucion autonoma en pasos CON y SIN apoyo visual, y
            // convierte una decision de diseno en observacion medible.
            'media_shown' => $mediaShown === [] ? null : $mediaShown,
        ]);

        $nextKey = $step->nextKeyFor($answer);

        if ($nextKey === null) {
            // Arbol agotado: se acabaron los pasos conocidos y el problema
            // sigue. Escalar es el desenlace correcto, no un fallo.
            return StepOutcome::escalate();
        }

        $next = $step->version->stepByKey($nextKey);

        if ($next === null) {
            // Referencia rota en el arbol. Se escala igualmente: el docente
            // no puede quedarse atrapado por un error de edicion.
            return StepOutcome::escalate();
        }

        // Los pasos terminales son MARCADORES de desenlace, no pantallas: si
        // se mostraran, el docente tendria que dar un toque mas solo para
        // enterarse de algo que el sistema ya sabe. Se resuelven al llegar.
        if ($next->is_terminal) {
            return $next->terminal_outcome === 'resolved'
                ? StepOutcome::resolved()
                : StepOutcome::escalate();
        }

        if ($this->answersOf($incident)->count() >= $this->stepLimit()) {
            return StepOutcome::escalate();
        }

        return StepOutcome::next($next);
    }

    /**
     * Imagenes a mostrar en un paso, ya resueltas por cascada.
     *
     * @return array{primary: ?MediaAsset, alternates: Collection<int, MediaAsset>, reference: ?MediaAsset}
     */
    public function visualsFor(DiagnosticStep $step): array
    {
        $assets = $step->relationLoaded('media') ? $step->media : $step->media()->get();

        $primary = $this->media->primaryFor($assets, $step->component_key);

        return [
            'primary' => $primary,
            'alternates' => $this->media->alternatesFor($assets, $primary),
            'reference' => $this->media->componentReference($step->component_key),
        ];
    }

    /** Pasos ya ejecutados, para entregarselos a soporte al escalar. */
    public function progressOf(Incident $incident): Collection
    {
        return DiagnosticAnswer::query()
            ->with('step')
            ->where('incident_id', $incident->id)
            ->orderBy('answered_at')
            ->get();
    }

    /** @return Collection<int, DiagnosticAnswer> */
    private function answersOf(Incident $incident): Collection
    {
        return DiagnosticAnswer::query()
            ->where('incident_id', $incident->id)
            ->orderBy('id')
            ->get();
    }

    private function stepLimit(): int
    {
        $configured = (int) config('incidencias.assistant.max_diagnostic_steps');

        return min($configured > 0 ? $configured : self::HARD_STEP_LIMIT, self::HARD_STEP_LIMIT);
    }
}

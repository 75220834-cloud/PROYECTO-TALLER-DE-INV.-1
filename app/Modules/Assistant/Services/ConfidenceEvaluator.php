<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Services;

use App\Modules\Assistant\Contracts\Classification;
use App\Modules\Retrieval\Services\RetrievedChunk;
use Illuminate\Support\Collection;

/**
 * Decide si el asistente puede responder o debe escalar (plan 13.5).
 *
 * NO es una fórmula inventada. Combina tres señales objetivas y medibles, y
 * los umbrales se calibran contra un conjunto etiquetado por soporte. Hasta
 * que eso exista, el sistema opera en modo CONSERVADOR y escala de más.
 *
 * Esa asimetría es deliberada: escalar de más cuesta un desplazamiento;
 * responder mal con seguridad puede hacer que un docente manipule el
 * equipo equivocado. El error barato y el caro no son simétricos, así que
 * la política por defecto tampoco lo es.
 *
 * LAS TRES SEÑALES
 *
 *  s_ret  ¿Existe conocimiento relevante?
 *         No basta con el mejor resultado: importa el MARGEN entre el
 *         primero y el tercero. Un margen pequeño significa que hay varios
 *         fragmentos igual de "buenos", y eso es ambigüedad disfrazada de
 *         confianza.
 *
 *  s_cls  ¿La intención está clara?
 *         Viene del clasificador. Si no se sabe de qué habla el docente,
 *         nada de lo que se recupere sirve.
 *
 *  s_cov  ¿La respuesta está sustentada?
 *         Proporción de fragmentos recuperados que aparecen realmente
 *         citados. Una respuesta que no cita lo que recuperó se la está
 *         inventando.
 */
final class ConfidenceEvaluator
{
    /** Pesos iniciales iguales: sin datos, no hay motivo para inclinar la balanza. */
    private const W_RETRIEVAL = 1 / 3;

    private const W_CLASSIFICATION = 1 / 3;

    private const W_COVERAGE = 1 / 3;

    /**
     * Umbrales por defecto mientras no haya calibración.
     *
     * Deliberadamente exigentes. Se ajustarán con el conjunto etiquetado
     * maximizando el F1 de la decisión de escalar, priorizando el recall
     * del escalamiento (plan 13.5).
     */
    private const DEFAULT_HIGH = 0.75;

    private const DEFAULT_LOW = 0.45;

    /**
     * @param  Collection<int, RetrievedChunk>  $retrieved
     */
    public function evaluate(
        Classification $classification,
        Collection $retrieved,
        int $citedCount = 0,
    ): ConfidenceVerdict {
        $retrievalScore = $this->retrievalStrength($retrieved);
        $classificationScore = $classification->label !== null ? $classification->confidence : 0.0;
        $coverageScore = $this->coverage($retrieved, $citedCount);

        $confidence =
            self::W_RETRIEVAL * $retrievalScore +
            self::W_CLASSIFICATION * $classificationScore +
            self::W_COVERAGE * $coverageScore;

        return new ConfidenceVerdict(
            confidence: round($confidence, 3),
            band: $this->band($confidence, $retrieved),
            retrievalScore: round($retrievalScore, 3),
            classificationScore: round($classificationScore, 3),
            coverageScore: round($coverageScore, 3),
        );
    }

    /**
     * Fuerza de la recuperación: calidad del mejor resultado y margen
     * respecto de los siguientes.
     *
     * @param  Collection<int, RetrievedChunk>  $retrieved
     */
    private function retrievalStrength(Collection $retrieved): float
    {
        if ($retrieved->isEmpty()) {
            return 0.0;
        }

        $scores = $retrieved->pluck('score')->values();
        $best = (float) $scores->first();

        if ($best <= 0.0) {
            return 0.0;
        }

        // Un fragmento encontrado por AMBAS vías (léxica y semántica) es la
        // señal más fuerte que existe: coincide en palabras y en significado.
        $bonus = $retrieved->first()->foundByBoth() ? 0.2 : 0.0;

        // Margen entre el primero y el tercero. Si son casi iguales, hay
        // ambigüedad aunque la puntuación absoluta parezca buena.
        $third = $scores->count() >= 3 ? (float) $scores[2] : 0.0;
        $margin = $best > 0.0 ? ($best - $third) / $best : 0.0;

        return min(1.0, ($margin * 0.6) + $bonus + ($retrieved->count() >= 2 ? 0.3 : 0.15));
    }

    /**
     * @param  Collection<int, RetrievedChunk>  $retrieved
     */
    private function coverage(Collection $retrieved, int $citedCount): float
    {
        if ($retrieved->isEmpty()) {
            return 0.0;
        }

        return min(1.0, $citedCount / $retrieved->count());
    }

    /**
     * @param  Collection<int, RetrievedChunk>  $retrieved
     */
    private function band(float $confidence, Collection $retrieved): string
    {
        // REGLA DURA que manda sobre el número: sin fragmentos recuperados
        // no hay nada en qué apoyarse, y da igual lo alto que salga el
        // cálculo. Responder aquí sería inventar (plan 13.4).
        if ($retrieved->isEmpty()) {
            return 'low';
        }

        $high = config('incidencias.assistant.confidence_high');
        $low = config('incidencias.assistant.confidence_low');

        $high = $high !== null ? (float) $high : self::DEFAULT_HIGH;
        $low = $low !== null ? (float) $low : self::DEFAULT_LOW;

        return match (true) {
            $confidence >= $high => 'high',
            $confidence >= $low => 'medium',
            default => 'low',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Retrieval\Services;

use App\Modules\Knowledge\Models\KnowledgeChunk;

/**
 * Un fragmento recuperado, con la traza de cómo llegó al resultado.
 *
 * Se conservan las dos posiciones de origen (léxica y semántica) porque
 * sirven para dos cosas distintas:
 *
 *  - El evaluador de confianza las usa para saber si el resultado es sólido
 *    o si solo apareció por una de las dos vías.
 *  - Al depurar, dicen POR QUÉ el sistema recuperó algo aparentemente
 *    irrelevante, que de otro modo es imposible de reconstruir.
 */
final readonly class RetrievedChunk
{
    public function __construct(
        public KnowledgeChunk $chunk,
        public float $score,
        public ?int $lexicalRank = null,
        public ?int $semanticRank = null,
    ) {}

    public function citation(): string
    {
        return $this->chunk->citation();
    }

    public function content(): string
    {
        return $this->chunk->content;
    }

    /**
     * Apareció por AMBAS vías.
     *
     * Es la señal de recuperación más fuerte que hay: coincide en palabras
     * y también en significado. Un fragmento que solo sale por una vía
     * puede ser una coincidencia de vocabulario o un parecido vago.
     */
    public function foundByBoth(): bool
    {
        return $this->lexicalRank !== null && $this->semanticRank !== null;
    }
}

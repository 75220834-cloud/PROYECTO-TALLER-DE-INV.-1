<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Services;

use App\Modules\Retrieval\Services\RetrievedChunk;
use Illuminate\Support\Collection;

/**
 * Respuesta del asistente, con su origen y sus fuentes.
 *
 * `source` distingue cómo se produjo, y esa distinción importa para la
 * investigación tanto como para el usuario:
 *
 *   generated  el modelo redactó a partir de las fuentes, y la respuesta
 *              pasó la verificación de anclaje
 *   passages   se muestran los pasajes literales con su cita, porque no
 *              había modelo o su respuesta se descartó
 *   escalate   no hay base suficiente: se avisa a soporte
 *
 * Poder contar cuántas respuestas salieron por cada vía es lo que permite
 * decir después si el modelo aportó algo o si el sistema habría funcionado
 * igual sin él.
 */
final readonly class AssistantAnswer
{
    /**
     * @param  Collection<int, RetrievedChunk>  $sources
     */
    private function __construct(
        public string $text,
        public string $source,
        public Collection $sources,
        public ConfidenceVerdict $verdict,
        public bool $escalated,
    ) {}

    /**
     * @param  Collection<int, RetrievedChunk>  $sources
     */
    public static function generated(string $text, Collection $sources, ConfidenceVerdict $verdict): self
    {
        return new self($text, 'generated', $sources, $verdict, false);
    }

    /**
     * @param  Collection<int, RetrievedChunk>  $sources
     */
    public static function fromPassages(Collection $sources, ConfidenceVerdict $verdict): self
    {
        $text = $sources
            ->map(fn ($s) => trim($s->content()))
            ->implode("\n\n");

        return new self($text, 'passages', $sources, $verdict, false);
    }

    public static function escalate(ConfidenceVerdict $verdict): self
    {
        // Texto literal del plan (§44). No se parafrasea.
        return new self($verdict->escalationMessage(), 'escalate', collect(), $verdict, true);
    }

    /**
     * Citas legibles, sin repetir el mismo documento y página.
     *
     * @return list<string>
     */
    public function citations(): array
    {
        return $this->sources
            ->map(fn ($s) => $s->citation())
            ->unique()
            ->values()
            ->all();
    }

    public function hasSources(): bool
    {
        return $this->sources->isNotEmpty();
    }

    /** Se responde, pero advirtiendo de que puede no ser exacto. */
    public function needsWarning(): bool
    {
        return $this->verdict->needsWarning();
    }
}

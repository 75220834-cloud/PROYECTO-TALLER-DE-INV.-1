<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Services;

use App\Modules\Incidents\Models\Incident;
use App\Modules\Retrieval\Contracts\EmbeddingProvider;
use App\Modules\Retrieval\Providers\HashEmbeddingProvider;
use App\Modules\Retrieval\Services\RetrievalService;
use App\Shared\Support\TextNormalizer;
use Illuminate\Support\Collection;

/**
 * Lo que la IA le sugiere al técnico en la ficha de una incidencia.
 *
 * Dos cosas, las que un técnico con experiencia ya tiene en la cabeza:
 *
 *  1. CASOS PARECIDOS que ya se resolvieron, y cómo. Solo del mismo tipo de
 *     problema y con lo que se hizo escrito: un caso sin solución anotada no
 *     enseña nada. Manda el parecido del problema; los de la misma aula
 *     llevan ventaja, porque un aula que ya falló por el cable suele volver
 *     a fallar por el cable.
 *  2. LA GUÍA que mejor habla del problema, con la misma búsqueda híbrida
 *     que usa el asistente del docente, filtro de relevancia incluido.
 *
 * El parecido entre casos se mide por SIGNIFICADO cuando Ollama está
 * disponible, y por palabras cuando no: sin IA las sugerencias empeoran,
 * pero la ficha no se cae. Son sugerencias: no deciden nada.
 */
final class IncidentSuggestions
{
    /** Casos que se comparan como máximo: el coseno se calcula en PHP. */
    private const CANDIDATES = 25;

    private const SHOWN = 3;

    /** Ventaja de los casos de la misma aula sobre el parecido del texto. */
    private const SAME_ROOM_BONUS = 0.15;

    public function __construct(
        private readonly RetrievalService $retrieval,
        private readonly EmbeddingProvider $embeddings,
    ) {}

    public function for(Incident $incident): SuggestionSet
    {
        $query = $this->queryFor($incident);
        [$cases, $semantic] = $this->similarCases($incident, $query);

        $guides = $query === '' ? collect() : $this->retrieval->search($query, $incident->category_id)->take(2);

        return new SuggestionSet($cases, $guides, $semantic);
    }

    /** Lo que se sabe del problema, en una sola frase para buscar. */
    private function queryFor(Incident $incident): string
    {
        return trim(implode('. ', array_filter([
            $incident->category?->name,
            $incident->reported_description,
            $incident->technical_diagnosis,
        ])));
    }

    /**
     * @return array{0: Collection<int, array{incident: Incident, score: float, sameRoom: bool}>, 1: bool}
     */
    private function similarCases(Incident $incident, string $query): array
    {
        if ($incident->category_id === null) {
            return [collect(), false];
        }

        $candidates = Incident::query()
            ->with('room')
            ->whereKeyNot($incident->getKey())
            ->where('category_id', $incident->category_id)
            ->whereNotNull('resolved_at')
            ->whereNotNull('resolution_notes')
            ->where('resolution_notes', '!=', '')
            ->latest('resolved_at')
            ->limit(self::CANDIDATES)
            ->get();

        if ($candidates->isEmpty()) {
            return [collect(), false];
        }

        $texts = $candidates->map(fn (Incident $c): string => trim(($c->reported_description ?? '').'. '.($c->technical_diagnosis ?? '')))->all();
        [$scores, $semantic] = $this->similarities($query, $texts);

        $cases = $candidates->values()->map(fn (Incident $c, int $i): array => [
            'incident' => $c,
            'score' => $scores[$i] + ($c->room_id === $incident->room_id ? self::SAME_ROOM_BONUS : 0.0),
            'sameRoom' => $c->room_id === $incident->room_id,
        ]);

        // A igual puntaje gana el más reciente: ya vienen ordenados así y el
        // orden de PHP es estable.
        return [$cases->sortByDesc('score')->take(self::SHOWN)->values(), $semantic];
    }

    /**
     * Parecido de cada texto con la consulta, de 0 a 1.
     *
     * @param  list<string>  $texts
     * @return array{0: list<float>, 1: bool} puntajes y si se usó la IA
     */
    private function similarities(string $query, array $texts): array
    {
        // El proveedor de respaldo no entiende significados: con él, contar
        // palabras en común es igual de bueno y no aparenta lo que no es.
        if (! $this->embeddings instanceof HashEmbeddingProvider && $query !== '') {
            $vectors = $this->embeddings->embedBatch([$query, ...$texts]);
            $consulta = array_shift($vectors);

            if ($consulta !== null && ! in_array(null, $vectors, true)) {
                return [array_map(fn (array $v): float => $this->cosine($consulta, $v), $vectors), true];
            }
        }

        $palabras = $this->words($query);

        return [array_map(function (string $text) use ($palabras): float {
            $otras = $this->words($text);
            $union = count(array_unique([...$palabras, ...$otras]));

            return $union === 0 ? 0.0 : count(array_intersect($palabras, $otras)) / $union;
        }, $texts), false];
    }

    /** @return list<string> palabras de 3 letras o más, sin tildes */
    private function words(string $text): array
    {
        $tokens = preg_split('/[^a-z0-9ñ]+/u', TextNormalizer::fold($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($tokens, fn (string $t): bool => mb_strlen($t) >= 3)));
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private function cosine(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return 0.0;
        }

        $dot = $na = $nb = 0.0;

        foreach ($a as $i => $x) {
            $dot += $x * $b[$i];
            $na += $x * $x;
            $nb += $b[$i] * $b[$i];
        }

        return $na > 0.0 && $nb > 0.0 ? $dot / (sqrt($na) * sqrt($nb)) : 0.0;
    }
}

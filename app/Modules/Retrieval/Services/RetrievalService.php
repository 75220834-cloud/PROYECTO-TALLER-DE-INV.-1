<?php

declare(strict_types=1);

namespace App\Modules\Retrieval\Services;

use App\Modules\Knowledge\Models\KnowledgeChunk;
use App\Modules\Knowledge\Models\KnowledgeDocumentVersion;
use App\Modules\Retrieval\Contracts\EmbeddingProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Búsqueda híbrida sobre la base de conocimiento (plan 14.3).
 *
 * POR QUÉ HÍBRIDA
 * La búsqueda solo vectorial falla con términos exactos ("HDMI", "C305",
 * "Epson EB-X41"); la solo léxica falla con paráfrasis ("no se ve nada" vs
 * "ausencia de señal de vídeo"). En este dominio ocurren las dos cosas, así
 * que combinar no es un lujo.
 *
 * ORDEN DE LAS ETAPAS
 * MariaDB 10.4 no tiene índice vectorial, así que el coseno se calcula en
 * PHP. Para que eso sea viable hay que reducir ANTES el conjunto: primero
 * se prefiltra en SQL y solo después se calcula la similitud sobre los
 * candidatos. Invertir el orden obligaría a recorrer toda la base en cada
 * consulta.
 *
 * LA SALVAGUARDA
 * El prefiltrado léxico tiene un problema evidente: elimina justo los
 * fragmentos que no comparten vocabulario con la pregunta, que son
 * exactamente los que la búsqueda vectorial debería rescatar. Por eso, si
 * el filtro devuelve muy pocos candidatos, se AMPLÍA a toda la categoría
 * antes de calcular la similitud. Sin esto, el prefiltrado anularía el
 * beneficio del vectorial precisamente en el caso que lo justifica.
 */
final class RetrievalService
{
    public function __construct(private readonly EmbeddingProvider $embeddings) {}

    /**
     * Prepara la consulta para MATCH ... AGAINST en MODO BOOLEANO.
     *
     * POR QUÉ MODO BOOLEANO Y NO NATURAL
     * El modo natural de MySQL descarta los términos que aparecen en más del
     * 50 % de las filas. Con una base pequeña —exactamente la situación al
     * arrancar el piloto, cuando soporte ha subido uno o dos documentos—
     * eso significa que TODOS los términos se descartan y la búsqueda léxica
     * no devuelve nada. Falla en silencio: no da error, solo deja de
     * encontrar cosas, y encima empieza a funcionar sola cuando crece la
     * base, lo que hace el problema muy difícil de diagnosticar.
     *
     * El modo booleano no aplica ese umbral.
     *
     * Los operadores booleanos (+ - * " ~ < > ( ) @) se ELIMINAN de la
     * entrada del usuario: si un docente escribe "no funciona -nada", ese
     * guion se interpretaría como "excluir" y cambiaría el sentido de su
     * propia búsqueda.
     */
    private function booleanQuery(string $query): string
    {
        $cleaned = preg_replace('/[+\-*"~<>()@]+/u', ' ', $query) ?? $query;

        $words = preg_split('/\s+/u', trim($cleaned), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // Términos de menos de 3 caracteres los ignora el índice de todos
        // modos (innodb_ft_min_token_size).
        $words = array_filter($words, static fn (string $w): bool => mb_strlen($w) >= 3);

        return implode(' ', $words);
    }

    /**
     * Recupera los fragmentos más relevantes para una consulta.
     *
     * @return Collection<int, RetrievedChunk>
     */
    public function search(string $query, ?int $categoryId = null): Collection
    {
        $query = trim($query);

        if ($query === '') {
            return collect();
        }

        $candidates = $this->prefilter($query, $categoryId);

        if ($candidates->isEmpty()) {
            return collect();
        }

        $lexical = $this->rankLexically($query, $candidates);
        [$semantic, $similarities] = $this->rankSemantically($query, $candidates);

        return $this->fuse($lexical, $semantic, $similarities, $candidates);
    }

    /**
     * Etapa 1: reducir el conjunto en SQL.
     *
     * @return Collection<int, KnowledgeChunk>
     */
    private function prefilter(string $query, ?int $categoryId): Collection
    {
        $limit = (int) config('incidencias.retrieval.prefilter_limit');
        $min = (int) config('incidencias.retrieval.prefilter_min');
        $boolean = $this->booleanQuery($query);

        if ($boolean === '') {
            // Nada indexable en la consulta (solo palabras muy cortas). Se
            // amplia directamente: el vectorial todavia puede aportar.
            $boolean = $query;
        }

        /*
         * Solo la ULTIMA version indexada de cada documento publicado.
         *
         * Sin la restriccion de version, un procedimiento corregido compite
         * con su propia version obsoleta: las dos estan indexadas, las dos
         * hablan del mismo tema, y el asistente puede citar la que ya no es
         * cierta. Es un fallo silencioso —no da error, solo responde con
         * informacion vieja— y se detecto al recargar las guias y ver que
         * los fragmentos se habian duplicado.
         *
         * Las versiones anteriores NO se borran: siguen explicando por que
         * una incidencia pasada se resolvio como se resolvio. Simplemente
         * dejan de alimentar al asistente.
         */
        $latest = KnowledgeDocumentVersion::query()
            ->where('processing_status', 'indexed')
            ->whereHas('document', fn ($d) => $d->where('status', 'published'))
            ->selectRaw('MAX(id) as id')
            ->groupBy('document_id');

        $base = fn () => KnowledgeChunk::query()
            ->whereIn('document_version_id', $latest->clone()->pluck('id'));

        $byText = $base()
            ->when($categoryId !== null, fn ($q) => $q->where(fn ($sub) => $sub
                ->where('category_id', $categoryId)
                ->orWhereNull('category_id')))
            ->whereRaw('MATCH(content) AGAINST (? IN BOOLEAN MODE)', [$boolean])
            ->limit($limit)
            ->get();

        // SALVAGUARDA: pocos resultados léxicos suele significar que la
        // pregunta está formulada con otras palabras, que es justo cuando la
        // búsqueda semántica aporta. Se amplía el conjunto.
        if ($byText->count() >= $min) {
            return $byText;
        }

        $widened = $base()
            ->when($categoryId !== null, fn ($q) => $q->where(fn ($sub) => $sub
                ->where('category_id', $categoryId)
                ->orWhereNull('category_id')))
            ->limit($limit)
            ->get();

        // Se unen ambos: los que ya coincidían por texto no se pierden.
        return $byText->concat($widened)->unique('id')->values();
    }

    /**
     * Etapa 2: ranking léxico sobre los candidatos.
     *
     * @param  Collection<int, KnowledgeChunk>  $candidates
     * @return array<int, int> id del fragmento => posición (1 = mejor)
     */
    private function rankLexically(string $query, Collection $candidates): array
    {
        $ids = $candidates->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $rows = DB::table('knowledge_chunks')
            ->selectRaw('id, MATCH(content) AGAINST (? IN BOOLEAN MODE) AS score', [$this->booleanQuery($query)])
            ->whereIn('id', $ids)
            ->orderByDesc('score')
            ->get();

        $ranks = [];
        $position = 1;

        foreach ($rows as $row) {
            if ((float) $row->score > 0.0) {
                $ranks[(int) $row->id] = $position++;
            }
        }

        return $ranks;
    }

    /**
     * Etapa 3: similitud coseno en PHP sobre los candidatos.
     *
     * @param  Collection<int, KnowledgeChunk>  $candidates
     * @return array{0: array<int, int>, 1: array<int, float>} posiciones y similitudes
     */
    private function rankSemantically(string $query, Collection $candidates): array
    {
        $vector = $this->embeddings->embed($query);

        if ($vector === null) {
            // Sin vectores el sistema no se cae: se apoya solo en el léxico.
            return [[], []];
        }

        $model = $this->embeddings->modelIdentifier();
        $queryNorm = $this->norm($vector);

        if ($queryNorm <= 0.0) {
            return [[], []];
        }

        $scored = [];

        foreach ($candidates as $chunk) {
            // Fragmentos vectorizados con OTRO modelo se descartan: compararlos
            // daría un número sin significado y contaminaría el ranking sin
            // que nada fallara visiblemente (plan 14.5).
            if ($chunk->embedding_model !== $model) {
                continue;
            }

            $score = $chunk->cosineAgainst($vector, $queryNorm);

            if ($score > 0.0) {
                $scored[$chunk->id] = $score;
            }
        }

        arsort($scored);

        $ranks = [];
        $position = 1;

        foreach (array_keys($scored) as $id) {
            $ranks[(int) $id] = $position++;
        }

        return [$ranks, $scored];
    }

    /**
     * Etapa 4: fusión por Reciprocal Rank Fusion.
     *
     * RRF combina rankings sin necesidad de normalizar puntuaciones de
     * escalas distintas — el score de FULLTEXT y el coseno no son
     * comparables entre sí, y cualquier intento de ponderarlos a mano sería
     * un número inventado.
     *
     * @param  array<int, int>  $lexical
     * @param  array<int, int>  $semantic
     * @param  array<int, float>  $similarities
     * @param  Collection<int, KnowledgeChunk>  $candidates
     * @return Collection<int, RetrievedChunk>
     */
    private function fuse(array $lexical, array $semantic, array $similarities, Collection $candidates): Collection
    {
        $k = (int) config('incidencias.retrieval.rrf_k');
        $limit = (int) config('incidencias.retrieval.context_limit');

        $scores = [];

        foreach ([$lexical, $semantic] as $ranking) {
            foreach ($ranking as $id => $position) {
                $scores[$id] = ($scores[$id] ?? 0.0) + 1 / ($k + $position);
            }
        }

        if ($scores === []) {
            return collect();
        }

        arsort($scores);

        $byId = $candidates->keyBy('id');

        $floor = (float) config('incidencias.retrieval.relevance_floor');
        $agreement = (int) config('incidencias.retrieval.agreement_rank');

        return collect(array_slice($scores, 0, $limit, true))
            ->map(function (float $score, int $id) use ($byId, $lexical, $semantic, $similarities): ?RetrievedChunk {
                $chunk = $byId->get($id);

                return $chunk === null ? null : new RetrievedChunk(
                    chunk: $chunk,
                    score: $score,
                    lexicalRank: $lexical[$id] ?? null,
                    semanticRank: $semantic[$id] ?? null,
                    similarity: $similarities[$id] ?? null,
                );
            })
            ->filter()

            /*
             * PISO DE RELEVANCIA. Se descarta lo que no tiene nada que ver
             * con la pregunta aunque sea lo mejor que haya.
             *
             * Sin esto, una pregunta ajena al dominio —«cuánto cuesta la
             * matrícula»— recuperaba cuatro pasajes sobre el proyector y el
             * asistente los presentaba como respuesta. Devolver vacío hace
             * que el sistema escale, que es el desenlace correcto.
             */
            ->filter(fn (RetrievedChunk $c): bool => $c->isRelevant($floor, $agreement))
            ->values();
    }

    /** @param  list<float>  $vector */
    private function norm(array $vector): float
    {
        $sum = 0.0;

        foreach ($vector as $value) {
            $sum += $value * $value;
        }

        return sqrt($sum);
    }
}
